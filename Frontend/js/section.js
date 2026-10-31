// Section onboarding: choose → join (6-character code) or create (creator becomes admin).

$$('[data-icon]').forEach((el) => { el.innerHTML = icon(el.dataset.icon, el.dataset.icon === 'chevronDown' ? 18 : 22); });
$('#back').innerHTML = icon('arrowLeft', 20);
$('#logout').onclick = Shell.logout;

const TITLES = {
  choose: ['Get started', 'Find your section'],
  join: ['Get started', 'Join Section'],
  create: ['Admin setup', 'Create Section'],
};

function show() {
  const view = location.hash.slice(1) || 'choose';
  $$('[data-view]').forEach((s) => { s.hidden = s.dataset.view !== view; });
  [$('#eyebrow').textContent, $('#title').textContent] = TITLES[view] || TITLES.choose;
  if (view === 'join') $('.code-box').focus();
}
window.addEventListener('hashchange', show);
$('#back').onclick = () => {
  if (location.hash && location.hash !== '#choose') location.hash = '';
  else history.length > 1 ? history.back() : (location.href = 'index.html');
};

// Must be logged in to be here.
API.get('auth.php').then((me) => {
  if (me.section && !location.hash) {
    $('#eyebrow').textContent = `You’re in ${me.section.name}`;
  }
});
show();

/* ---------------------------------------------------------------- join */
const boxes = $$('.code-box');
const joinBtn = $('#join-form button[type=submit]');
let lookupTimer;

const code = () => boxes.map((b) => b.value).join('').toUpperCase();

boxes.forEach((box, i) => {
  box.addEventListener('input', () => {
    box.value = box.value.replace(/[^a-z0-9]/gi, '').toUpperCase();
    if (box.value && i < 5) boxes[i + 1].focus();
    lookup();
  });
  box.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
  });
  box.addEventListener('paste', (e) => {
    e.preventDefault();
    const text = (e.clipboardData.getData('text') || '').replace(/[^a-z0-9]/gi, '').toUpperCase().slice(0, 6);
    text.split('').forEach((c, j) => { boxes[j].value = c; });
    boxes[Math.min(text.length, 5)].focus();
    lookup();
  });
});

function lookup() {
  clearTimeout(lookupTimer);
  joinBtn.disabled = true;
  $('#found').innerHTML = '';
  if (code().length !== 6) return;
  lookupTimer = setTimeout(async () => {
    try {
      const { section } = await API.get('section.php', { code: code() });
      $('#found').innerHTML = `
        <div class="card-pastel mint-soft rounded-[1.75rem] p-5">
          <span class="sparkle">✦</span><span class="ring-deco"></span>
          <div class="flex items-center gap-2 text-sm font-medium"><span class="grid h-6 w-6 place-items-center rounded-full bg-success text-white">${icon('check', 14)}</span>Section found</div>
          <div class="mt-2 text-3xl font-extrabold tracking-tight">${esc(section.name)}</div>
          <div class="mt-1 text-sm text-muted">${esc(section.program)} · ${section.members} members</div>
          <span class="chip mt-3 bg-white">Joining as Client</span>
        </div>`;
      joinBtn.disabled = false;
    } catch (e) {
      $('#found').innerHTML = `<div class="rounded-2xl bg-danger-tint px-4 py-3 text-sm">${esc(e.message)}</div>`;
    }
  }, 250);
}

UI.bindForm($('#join-form'), async () => {
  await API.post('section.php', { action: 'join', code: code() });
  location.href = 'home.html';
});

/* ---------------------------------------------------------------- create */
UI.bindForm($('#create-form'), async (data) => {
  const { section } = await API.post('section.php', { action: 'create', ...data });
  $('#created').hidden = false;
  $('#created').innerHTML = `
    <div class="card-pastel dark mt-5 rounded-[1.75rem] p-5">
      <span class="sparkle">✦</span><span class="ring-deco"></span>
      <div class="text-[11px] font-semibold uppercase tracking-wider text-white/60">Your class code</div>
      <div class="mt-1 flex items-center justify-between">
        <div class="text-4xl font-extrabold tracking-wider">${esc(section.class_code)}</div>
        <button type="button" id="copy" class="grid h-12 w-12 place-items-center rounded-full bg-sun text-charcoal" aria-label="Copy class code">${icon('copy', 20)}</button>
      </div>
      <div class="mt-2 text-xs text-white/70">Share this code with classmates so they can join.</div>
    </div>
    <div class="mt-4 flex gap-3 rounded-2xl bg-sun-tint p-4">
      ${icon('info', 18, 'mt-0.5')}
      <div><div class="text-sm font-semibold">You’ll be the admin</div><div class="text-xs text-muted">Next: add your subjects and their weekly schedule.</div></div>
    </div>
    <a href="add-subject.html" class="btn btn-dark mt-5 w-full">Open my section</a>`;
  $('#create-form button[type=submit]').hidden = true;
  $$('#create-form input, #create-form select').forEach((i) => { i.disabled = true; });
  $('#copy').onclick = () => navigator.clipboard?.writeText(section.class_code).then(() => UI.toast('Class code copied'));
});
