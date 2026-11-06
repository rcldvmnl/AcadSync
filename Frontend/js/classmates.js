// Classmates directory (students) / Members (admin).
// Server-side: Selection Sort A–Z, Linear Search by name, Binary Search by ID,
// and Graph traversal for study-group filters.

const AVATAR_TONES = ['bg-lavender', 'bg-peach', 'bg-mint', 'bg-sun', 'bg-lavender-light', 'bg-peach-tint'];
const GROUP_TONES = ['bg-lavender-tint', 'bg-peach-tint', 'bg-mint-tint', 'bg-sun-tint'];
const url = new URLSearchParams(location.search);
let state = { q: url.get('q') || '', mode: 'name', group: '', data: null, admin: false };

$$('[data-icon]').forEach((el) => { el.outerHTML = icon(el.dataset.icon, 18, 'text-muted'); });
$('#search').elements.q.value = state.q;

(async () => {
  const me = await Shell.init({
    active: 'classmates',
    eyebrow: (m) => `${m.section.name} · ${m.section.members} members`,
    title: (m) => (m.user.role === 'admin' ? 'Members' : 'Classmates'),
  });
  state.admin = me.user.role === 'admin';
  if (state.admin) renderInvite(me.section);
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#list').innerHTML = UI.errorBox(e); });

function renderInvite(section) {
  const el = $('#invite');
  el.hidden = false;
  el.innerHTML = `
    <div class="card-pastel dark flex items-center justify-between rounded-[1.75rem] p-5">
      <span class="sparkle">✦</span><span class="ring-deco"></span>
      <div><div class="text-[10px] font-semibold uppercase tracking-wider text-white/60">Invite with class code</div>
        <div class="text-3xl font-extrabold tracking-wider">${esc(section.class_code)}</div></div>
      <button id="copy-code" class="grid h-12 w-12 place-items-center rounded-full bg-sun text-charcoal" aria-label="Copy class code">${icon('copy', 20)}</button>
    </div>`;
  $('#copy-code').onclick = () => navigator.clipboard?.writeText(section.class_code).then(() => UI.toast('Class code copied'));
  $('#search').elements.q.placeholder = 'Search name or student ID';
}

async function load() {
  $$('[data-mode]').forEach((b) => {
    const on = b.dataset.mode === state.mode;
    b.className = `rounded-full px-3 py-1.5 ${on ? 'bg-charcoal text-white' : 'text-muted'}`;
    b.setAttribute('aria-checked', String(on));
    b.setAttribute('role', 'radio');
  });
  try {
    state.data = await API.get('classmates.php', { q: state.q, mode: state.mode, group: state.group });
  } catch (e) {
    $('#list').innerHTML = UI.errorBox(e);
    return;
  }
  renderGroups();
  renderList();
  $('#algo').textContent = `Using ${state.data.algorithm}${state.group ? ' + Graph (study group)' : ''}`;
}

function groupTone(name) {
  const i = state.data.groups.indexOf(name);
  return GROUP_TONES[(i < 0 ? 0 : i) % GROUP_TONES.length];
}

function renderGroups() {
  const chips = [['', 'All'], ...state.data.groups.map((g) => [g, g])];
  $('#groups').innerHTML = `
    ${chips.map(([v, l]) => `<button data-group="${esc(v)}" aria-pressed="${state.group === v}" class="chip shrink-0 px-3.5 py-2 text-sm ${state.group === v ? 'bg-charcoal text-white' : v ? groupTone(v) : 'bg-white'}">${esc(l)}</button>`).join('')}`;
  $$('[data-group]').forEach((b) => b.onclick = () => { state.group = b.dataset.group; load(); });
}

function renderList() {
  const members = state.data.members;
  if (!members.length) {
    $('#list').innerHTML = UI.empty(state.mode === 'id' ? 'No student with that ID' : 'No one found', state.mode === 'id' ? 'Binary Search returned nothing — check the ID (e.g. 2023-1045).' : 'Try another name.', 'search');
    return;
  }
  // Already sorted A–Z by the server; just split into letter sections.
  const sections = [];
  members.forEach((m, i) => {
    const letter = (m.last_name[0] || '#').toUpperCase();
    if (!sections.length || sections[sections.length - 1].letter !== letter) sections.push({ letter, rows: [] });
    sections[sections.length - 1].rows.push([m, i]);
  });
  $('#list').innerHTML = sections.map((s) => `
    <section>
      <div class="mb-2 px-1 text-xs font-semibold text-muted">${esc(s.letter)}</div>
      <div class="divide-y divide-line rounded-[1.75rem] bg-white px-4 shadow-card">
        ${s.rows.map(([m, i]) => `
          <button data-member="${m.id}" class="flex w-full items-center gap-3 py-4 text-left">
            ${UI.avatar(m.name, AVATAR_TONES[m.id % AVATAR_TONES.length], 'h-12 w-12 text-sm')}
            <span class="min-w-0 flex-1">
              <span class="block font-semibold">${esc(m.last_name)}, ${esc(m.first_name)}${m.id === state.data.me ? ' <span class="text-xs font-medium text-muted">(you)</span>' : ''}</span>
              <span class="block text-xs text-muted">${esc(m.student_id)}</span>
              <span class="mt-1.5 flex flex-wrap gap-1.5">${m.role === 'admin' ? UI.chip('Admin', 'bg-charcoal text-white') : ''}${m.groups.map((g) => UI.chip(g, groupTone(g))).join('')}</span>
            </span>
            ${icon('chevronRight', 18, 'text-muted')}
          </button>`).join('')}
      </div>
    </section>`).join('');
  $$('[data-member]').forEach((b) => b.onclick = () => openMember(Number(b.dataset.member)));
}

function openMember(id) {
  const m = state.data.members.find((x) => x.id === id);
  // Teammates: everyone sharing a group (edges in the study-group graph).
  const teams = m.groups.map((g) => ({ g, people: state.data.members.filter((x) => x.id !== m.id && x.groups.includes(g)) }));
  UI.sheet(`
    <div class="flex items-center gap-4">
      ${UI.avatar(m.name, AVATAR_TONES[m.id % AVATAR_TONES.length], 'h-16 w-16 text-lg')}
      <div class="min-w-0"><h2 class="truncate text-xl font-bold">${esc(m.first_name)} ${esc(m.last_name)}</h2>
        <div class="truncate text-sm text-muted">${esc(m.email)}</div></div>
    </div>
    <div class="mt-4 grid grid-cols-2 gap-3">
      <div class="rounded-2xl bg-canvas p-3"><div class="text-xs text-muted">Student ID</div><div class="font-semibold">${esc(m.student_id)}</div></div>
      <div class="rounded-2xl bg-canvas p-3"><div class="text-xs text-muted">Role</div><div class="font-semibold">${m.role === 'admin' ? 'Admin · class officer' : 'Student'}</div></div>
    </div>
    <h3 class="mt-5 font-semibold">Study groups</h3>
    ${teams.length ? teams.map(({ g, people }) => `
      <div class="mt-2 rounded-2xl ${groupTone(g)} p-3">
        <div class="text-sm font-semibold">${esc(g)}</div>
        <div class="mt-1 text-xs text-muted">With ${people.length ? people.map((p) => esc(`${p.first_name} ${p.last_name}`)).join(', ') : 'no one else in this list'}</div>
      </div>`).join('') : '<p class="mt-1 text-sm text-muted">Not in a study group yet.</p>'}
    <button data-close class="btn btn-dark mt-6 w-full">Close</button>`, { label: `${m.first_name} ${m.last_name}` });
}

/* search */
let timer;
$('#search').elements.q.addEventListener('input', (e) => {
  clearTimeout(timer);
  timer = setTimeout(() => { state.q = e.target.value.trim(); load(); }, 250);
});
$('#search').addEventListener('submit', (e) => { e.preventDefault(); state.q = e.target.elements.q.value.trim(); load(); });
$$('[data-mode]').forEach((b) => b.onclick = () => {
  state.mode = b.dataset.mode;
  $('#search').elements.q.placeholder = state.mode === 'id' ? 'Student ID, e.g. 2023-1045' : 'Search by name…';
  $('#search').elements.q.inputMode = state.mode === 'id' ? 'numeric' : 'text';
  load();
});
