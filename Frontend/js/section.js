// Section onboarding: choose → join (6-character code) or create (creator becomes admin).
// Views switch with history.replaceState, so the browser Back button leaves the
// page instead of bouncing between #join / #choose.

$$('[data-icon]').forEach((el) => { el.innerHTML = icon(el.dataset.icon, el.dataset.icon === 'chevronDown' ? 18 : 22); });
$('#back').innerHTML = icon('arrowLeft', 20);
$('#logout').onclick = Shell.logout;

const TITLES = {
  choose: ['Get started', 'Find your section'],
  join: ['Get started', 'Join Section'],
  create: ['Admin setup', 'Create Section'],
};
let me = null;
let enteredFromChoose = false;

function view() {
  const v = location.hash.slice(1);
  return TITLES[v] ? v : 'choose';
}

function show() {
  const v = view();
  $$('[data-view]').forEach((s) => { s.hidden = s.dataset.view !== v; });
  [$('#eyebrow').textContent, $('#title').textContent] = TITLES[v];
  // Back is useful when there's somewhere to go: a sub-view, or the app if you already have a section.
  $('#back').hidden = v === 'choose' && !me?.section;
  if (v === 'join') $('.code-box').focus();
}

function go(v) {
  enteredFromChoose = view() === 'choose';
  history.replaceState(null, '', v === 'choose' ? location.pathname : `#${v}`);
  show();
}

$$('[data-go]').forEach((b) => b.onclick = () => go(b.dataset.go));

$('#back').onclick = () => {
  if (view() !== 'choose' && (enteredFromChoose || !me?.section)) {
    go('choose');
    return;
  }
  // Leave the page: back to where you came from inside the app, otherwise home.
  const ref = document.referrer && new URL(document.referrer);
  if (ref && ref.origin === location.origin && !ref.pathname.endsWith('section.html') && !ref.pathname.endsWith('index.html') && !ref.pathname.endsWith('signup.html')) {
    location.replace(ref.href);
  } else {
    location.replace('home.html');
  }
};

/* ---------------------------------------------------------------- your sections */
async function openSection(id) {
  await API.post('section.php', { action: 'switch', section_id: id });
  location.href = 'home.html';
}

function renderMySections() {
  const list = me.sections || [];
  $('#my-sections').hidden = !list.length;
  $('#my-sections-list').innerHTML = list.map((s) => `
    <button type="button" data-open="${s.id}" class="flex w-full items-center gap-3 rounded-[1.5rem] bg-white p-4 text-left shadow-card">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-charcoal text-sm font-semibold text-white">${esc(s.name.slice(-2))}</span>
      <span class="min-w-0 flex-1"><span class="block font-semibold">${esc(s.name)}</span>
        <span class="block truncate text-xs text-muted">${esc([s.program, s.year_level, s.semester].filter(Boolean).join(' · '))} · ${s.members} members</span></span>
      ${s.id === me.section?.id ? UI.chip('Open now', 'bg-mint-tint text-success') : UI.chip(s.role === 'admin' ? 'Admin' : 'Student', 'bg-canvas')}
      ${icon('chevronRight', 18, 'text-muted')}
    </button>`).join('');
  $$('[data-open]').forEach((b) => b.onclick = () => openSection(Number(b.dataset.open)));
}

// Must be logged in to be here.
API.get('auth.php').then((data) => {
  me = data;
  renderMySections();
  show();
});
show();

/* ---------------------------------------------------------------- join */
const boxes = $$('.code-box');
const joinBtn = $('#join-form button[type=submit]');
let lookupTimer;

const code = () => boxes.map((b) => b.value).join('').toUpperCase();

boxes.forEach((box, i) => {
  box.addEventListener('input', () => {
    box.value = box.value.replace(/[^a-z0-9]/gi, '').toUpperCase().slice(-1);
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
  joinBtn.textContent = 'Join section';
  $('#found').innerHTML = '';
  if (code().length !== 6) return;
  lookupTimer = setTimeout(async () => {
    try {
      const { section, already_member: already, role } = await API.get('section.php', { code: code() });
      const meta = [section.program, section.year_level, section.semester].filter(Boolean).join(' · ');
      $('#found').innerHTML = `
        <div class="card-pastel mint-soft rounded-[1.75rem] p-5">
          <span class="sparkle">✦</span><span class="ring-deco"></span>
          <div class="flex items-center gap-2 text-sm font-medium"><span class="grid h-6 w-6 place-items-center rounded-full bg-success text-white">${icon('check', 14)}</span>Section found</div>
          <div class="mt-2 text-3xl font-extrabold tracking-tight">${esc(section.name)}</div>
          <div class="mt-1 text-sm text-muted">${esc(meta)} · ${section.members} members</div>
          <span class="chip mt-3 bg-white">${already ? `You’re already in it · ${role === 'admin' ? 'Admin' : 'Student'}` : 'Joining as Client'}</span>
        </div>`;
      joinBtn.disabled = false;
      joinBtn.textContent = already ? 'Open section' : 'Join section';
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
const yearNow = new Date().getFullYear();
$('#create-form').elements.school_year.value = `${yearNow}-${yearNow + 1}`;
$('#create-form').elements.name.addEventListener('input', (e) => { e.target.value = e.target.value.toUpperCase(); });

UI.bindForm($('#create-form'), async (data) => {
  if (!data.name.trim()) throw new Error('Enter your section name, e.g. BSINFO-2A.');
  const { section } = await API.post('section.php', { action: 'create', ...data, preset: data.preset === '1' });
  $('#created').hidden = false;
  $('#created').innerHTML = `
    <div class="card-pastel dark mt-5 rounded-[1.75rem] p-5">
      <span class="sparkle">✦</span><span class="ring-deco"></span>
      <div class="text-[11px] font-semibold uppercase tracking-wider text-white/60">${esc(section.name)} · class code</div>
      <div class="mt-1 flex items-center justify-between">
        <div class="text-4xl font-extrabold tracking-wider">${esc(section.class_code)}</div>
        <button type="button" id="copy" class="grid h-12 w-12 place-items-center rounded-full bg-sun text-charcoal" aria-label="Copy class code">${icon('copy', 20)}</button>
      </div>
      <div class="mt-2 text-xs text-white/70">Share this code with classmates so they can join.</div>
    </div>
    <div class="mt-4 flex gap-3 rounded-2xl bg-sun-tint p-4">
      ${icon('info', 18, 'mt-0.5 shrink-0')}
      <div><div class="text-sm font-semibold">You’re the admin of ${esc(section.name)}</div><div class="text-xs text-muted">Next: add your subjects and the days and times each one meets.</div></div>
    </div>
    <a href="${data.preset === '1' ? 'week.html' : 'add-subject.html'}" class="btn btn-dark mt-5 w-full">${data.preset === '1' ? 'Set class times' : 'Add subjects'}</a>
    <a href="home.html" class="mt-3 block py-2 text-center text-sm font-semibold">Go to my section</a>`;
  $('#create-form').hidden = true;
  $('#copy').onclick = () => navigator.clipboard?.writeText(section.class_code).then(() => UI.toast('Class code copied'));
});
