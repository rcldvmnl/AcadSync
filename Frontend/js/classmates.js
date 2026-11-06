// Classmates directory (students) / Members (admin).
// Server-side: Selection Sort A–Z, Linear Search by name, Binary Search by ID.
// The People tab shows every classmate with their group for each group assignment,
// so "Group 3" always reads as "Group 3 of <that assignment>".

/* Pale identity tints for people (initials), stable per person. */
const PERSON_TINTS = ['#E6E1FD', '#FCE3CF', '#E5F6EE', '#FDF3C7', '#E3EEF9', '#F6E3EE'];
const personTint = (id) => PERSON_TINTS[Number(id) % PERSON_TINTS.length];
const initialsOf = (name) => esc(Fmt.initials(name));
const avatar = (p, cls = '') => `<span class="cm-avatar ${cls}" style="background:${personTint(p.id)}" aria-hidden="true">${initialsOf(p.name)}</span>`;
const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;
const STUDENT_ID = /^\d{4}-\d{4}$/;
/** "Nov 18" or "Nov 23–25" for a column header. */
const shortDue = (t) => {
  if (t.deadline_type === 'unset' || !t.deadline_ts) return 'Date not set';
  if (t.deadline_type !== 'range' || !t.deadline_end) return Fmt.monthDay(t.deadline_end || t.deadline);
  const a = Fmt.date(t.deadline); const b = Fmt.date(t.deadline_end);
  return a.getMonth() === b.getMonth() ? `${Fmt.monthDay(a)}–${b.getDate()}` : `${Fmt.monthDay(a)} – ${Fmt.monthDay(b)}`;
};

/**
 * Shared data for both tabs: everyone in the section, every group, the group assignments.
 * Loaded once, reloaded after any change (join, leave, accept…).
 */
const Data = {
  people: [],      // full list, A–Z (Selection Sort on the server)
  groups: null,    // groups.php: { mine, others, upcoming, members }
  section: null,
  me: 0,
  admin: false,

  async load() {
    const [people, groups] = await Promise.all([API.get('classmates.php'), API.get('groups.php')]);
    Data.people = people.members;
    Data.section = people.section;
    Data.me = people.me;
    Data.groups = groups;
    Data.index();
    return Data;
  },

  /** Lookups used by both tabs. */
  index() {
    const all = [...Data.groups.mine, ...Data.groups.others];
    Data.allGroups = all;
    Data.byId = new Map(Data.people.map((p) => [p.id, p]));
    // Group assignments still coming up (timeline order), the people table shows the nearest three
    Data.assignments = Data.groups.upcoming;
    // Two groups of one assignment can share a name ("Group 3"): tell them apart by a member's first name
    const byKey = new Map();
    all.forEach((g) => {
      g.label = g.name;
      const key = `${g.task ? g.task.id : 's'}|${g.name.trim().toLowerCase()}`;
      byKey.set(key, [...(byKey.get(key) || []), g]);
    });
    byKey.forEach((list) => list.length > 1 && list.forEach((g) => {
      const who = g.members.find((m) => m.id === g.created_by) || g.members[0];
      g.label = who ? `${g.name} (${who.name.split(' ')[0]})` : g.name;
    }));
    // personId -> taskId -> group
    Data.seat = new Map();
    all.forEach((g) => g.members.forEach((m) => {
      if (!Data.seat.has(m.id)) Data.seat.set(m.id, new Map());
      Data.seat.get(m.id).set(g.task ? g.task.id : `s${g.id}`, g);
    }));
  },

  groupOf(personId, taskId) { return Data.seat.get(personId)?.get(taskId) || null; },

  /** Assignments this person still has no group for. */
  missing(personId) { return Data.assignments.filter((t) => !Data.groupOf(personId, t.id)); },

  /** Everyone who shares any group with me. */
  groupmates() {
    const ids = new Set();
    Data.allGroups.filter((g) => g.is_member).forEach((g) => g.members.forEach((m) => m.id !== Data.me && ids.add(m.id)));
    return ids;
  },
};

const People = {
  q: new URLSearchParams(location.search).get('q') || '',
  filter: 'all',
  result: null,      // members returned by the current search (null = everyone)
  algorithm: '',

  async search() {
    const q = People.q;
    if (!q) {
      People.result = null;
      People.algorithm = 'Selection Sort (A–Z by last name)';
    } else if (STUDENT_ID.test(q)) {
      // A full student ID: [Binary Search] on the server
      const r = await API.get('classmates.php', { q, mode: 'id' });
      People.result = r.members; People.algorithm = r.algorithm;
    } else if (/^[\d-]+$/.test(q)) {
      // Part of an ID: match the start of the ID in the A–Z list
      People.result = Data.people.filter((p) => p.student_id.startsWith(q));
      People.algorithm = 'Selection Sort (A–Z), matching the start of the student ID';
    } else {
      const r = await API.get('classmates.php', { q, mode: 'name' });
      People.result = r.members; People.algorithm = r.algorithm;
    }
    People.render();
  },

  filters() {
    const base = People.result || Data.people;
    const mates = Data.groupmates();
    const list = [
      ['all', 'Everyone', base],
      ...(Data.assignments.length ? [['nogroup', 'No group yet', base.filter((p) => Data.missing(p.id).length)]] : []),
      ...(!Data.admin ? [['mine', 'My groupmates', base.filter((p) => mates.has(p.id))]] : []),
    ];
    if (!list.some(([k]) => k === People.filter)) People.filter = 'all';
    return list;
  },

  render() {
    const filters = People.filters();
    const look = { all: ['peach', 'users'], nogroup: ['lavender', 'user'], mine: ['mint', 'groups'] };
    $('#filters').innerHTML = filters.map(([k, label, rows]) => `
      <button type="button" role="radio" aria-checked="${People.filter === k}" data-filter="${k}" class="cm-stat is-${look[k][0]}">
        <span class="cm-stat-icon">${icon(look[k][1], 18)}</span>
        <span class="cm-stat-label">${label}</span>
        <span class="cm-stat-num cm-num">${rows.length}</span>
      </button>`).join('');
    $('#filters').style.setProperty('--n', filters.length);
    $$('[data-filter]').forEach((b) => b.onclick = () => { People.filter = b.dataset.filter; People.render(); });

    const rows = filters.find(([k]) => k === People.filter)[2];
    const cols = Data.assignments.slice(0, 3);
    const more = Data.assignments.length - cols.length;
    $('#algo').textContent = `Using ${People.algorithm}`;

    if (!rows.length) {
      const why = People.q
        ? (STUDENT_ID.test(People.q) ? `No classmate has student ID ${People.q}.` : `No classmate matches “${People.q}”.`)
        : People.filter === 'nogroup' ? 'Everyone has a group for every group assignment.' : 'You’re not in a group with anyone yet.';
      $('#list').innerHTML = `<div class="cm-empty">${esc(why)}${People.q ? ' <button type="button" data-clear-inline class="cm-link">Clear search</button>' : ''}</div>`;
      $('[data-clear-inline]')?.addEventListener('click', clearSearch);
      return;
    }

    const head = cols.map((t, i) => `<th scope="col"><span class="cm-th-title"><span class="cm-th-dot tone-${i}" aria-hidden="true"></span>${esc(t.title)}</span><span class="cm-th-sub">${esc(t.subject_code)} · ${esc(shortDue(t))}</span></th>`).join('');
    $('#list').innerHTML = `
      <table class="cm-table">
        <caption class="sr-only">Classmates and their group for each group assignment</caption>
        <thead><tr><th scope="col">Name</th><th scope="col" class="cm-col-id">Student ID</th>${head}</tr></thead>
        <tbody>${rows.map((p) => People.row(p, cols)).join('')}</tbody>
      </table>
      ${more > 0 ? `<p class="cm-table-note">${plural(more, 'more group assignment')} after these. See them all in <button type="button" data-goto-groups class="cm-link">Groups</button>.</p>` : ''}`;
    $$('[data-member]').forEach((tr) => {
      tr.onclick = (e) => { if (!e.target.closest('[data-group-link]')) People.open(Number(tr.dataset.member)); };
      tr.onkeydown = (e) => { if ((e.key === 'Enter' || e.key === ' ') && e.target === tr) { e.preventDefault(); People.open(Number(tr.dataset.member)); } };
    });
    $$('[data-group-link]').forEach((a) => a.onclick = (e) => { e.preventDefault(); GroupsTab.focusGroup(Number(a.dataset.groupLink)); });
    $('[data-goto-groups]')?.addEventListener('click', () => GroupsTab.show('groups'));
  },

  row(p, cols) {
    const me = p.id === Data.me;
    const cells = cols.map((t, ci) => {
      const g = Data.groupOf(p.id, t.id);
      return `<td class="cm-col-group" data-label="${esc(t.title)}">${g
        ? `<a href="#groups&group=${g.id}" data-group-link="${g.id}" class="cm-grouplink tone-${ci} ${g.is_member ? 'is-mine' : ''}">${esc(g.label)}</a>`
        : '<span class="cm-tag cm-tag-warn">No group</span>'}</td>`;
    }).join('');
    return `
      <tr data-member="${p.id}" tabindex="0" aria-label="${esc(p.first_name)} ${esc(p.last_name)}, ${esc(p.student_id)}. Open details" class="${me ? 'is-me' : ''}">
        <th scope="row">
          <span class="cm-person">${avatar(p)}
            <span class="cm-person-text">
              <span class="cm-name">${esc(p.last_name)}, ${esc(p.first_name)}${me ? ' <span class="cm-you">you</span>' : ''}${p.role === 'admin' ? ' <span class="cm-tag">Class officer</span>' : ''}</span>
              <span class="cm-sub cm-id-inline">${esc(p.student_id)}</span>
            </span>
          </span>
        </th>
        <td class="cm-col-id cm-num">${esc(p.student_id)}</td>
        ${cells}
      </tr>`;
  },

  /** One classmate: contact, and their group for every assignment. */
  open(id) {
    const p = Data.byId.get(id) || (People.result || []).find((x) => x.id === id);
    if (!p) return;
    const study = Data.allGroups.filter((g) => !g.task && g.members.some((m) => m.id === id));
    const line = (label, sub, g) => `
      <div class="cm-sheet-row">
        <div class="min-w-0 flex-1"><div class="cm-name">${esc(label)}</div><div class="cm-sub">${esc(sub)}</div></div>
        ${g ? `<button type="button" data-sheet-group="${g.id}" class="cm-grouplink ${g.is_member ? 'is-mine' : ''}">${esc(g.label)} ·${g.members.length}/${UI.groupCap(g) ?? g.members.length}</button>`
            : '<span class="cm-tag cm-tag-warn">No group yet</span>'}
      </div>`;
    UI.sheet(`
      <div class="flex items-center gap-4">
        ${avatar(p, 'cm-avatar-lg')}
        <div class="min-w-0"><h2 class="truncate text-xl font-bold">${esc(p.first_name)} ${esc(p.last_name)}</h2>
          <div class="truncate text-sm text-[#5C5C66]">${p.role === 'admin' ? 'Class officer' : 'Student'} · <span class="cm-num">${esc(p.student_id)}</span></div></div>
      </div>
      <a href="mailto:${esc(p.email)}" class="cm-mail">${icon('mail', 16)}<span class="truncate">${esc(p.email)}</span></a>
      <h3 class="mt-6 text-sm font-semibold">Group assignments</h3>
      <div class="mt-1">${Data.assignments.length
        ? Data.assignments.map((t) => line(t.title, `${t.subject_code} · ${Fmt.due(t)}`, Data.groupOf(id, t.id))).join('')
        : '<p class="cm-sub py-3">No group assignments right now.</p>'}</div>
      ${study.length ? `<h3 class="mt-5 text-sm font-semibold">Study groups</h3><div class="mt-1">${study.map((g) => line(g.name, g.subject ? g.subject.code : 'Any subject', g)).join('')}</div>` : ''}
      <button data-close class="btn btn-dark mt-6 w-full">Close</button>`, {
      label: `${p.first_name} ${p.last_name}`,
      onMount: (el, close) => $$('[data-sheet-group]', el).forEach((b) => b.onclick = () => { close(); GroupsTab.focusGroup(Number(b.dataset.sheetGroup)); }),
    });
  },
};

/* ------------------------------------------------------------------ boot */
$$('[data-icon]').forEach((el) => { el.outerHTML = icon(el.dataset.icon, 18, 'cm-search-icon'); });
const searchInput = $('#search').elements.q;
searchInput.value = People.q;

function syncClear() { $('[data-clear]').hidden = !searchInput.value; }
function clearSearch() { searchInput.value = ''; People.q = ''; syncClear(); People.search(); searchInput.focus(); }
$('[data-clear]').innerHTML = icon('x', 16);
$('[data-clear]').onclick = clearSearch;
syncClear();

let searchTimer;
searchInput.addEventListener('input', () => {
  syncClear();
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { People.q = searchInput.value.trim(); People.search().catch(showError); }, 250);
});
$('#search').addEventListener('submit', (e) => { e.preventDefault(); People.q = searchInput.value.trim(); People.search().catch(showError); });

function showError(e) {
  $('#list').innerHTML = `<div class="cm-error">${icon('alert', 16)}<span>Couldn’t load classmates. ${esc(e.message || e)}</span><button type="button" class="cm-link" onclick="location.reload()">Retry</button></div>`;
}

function renderInvite(section) {
  const el = $('#invite');
  el.hidden = false;
  el.innerHTML = `<span class="cm-sub">Class code</span><span class="cm-code">${esc(section.class_code)}</span>
    <button id="copy-code" type="button" class="cm-btn cm-btn-ghost">${icon('copy', 14)}Copy</button>`;
  $('#copy-code').onclick = () => navigator.clipboard?.writeText(section.class_code).then(() => UI.toast('Class code copied', { sub: 'Classmates join with it on “Find your section”' }));
}

/** Counts on the tabs: people in the section, groups in it. */
function renderCounts() {
  $('[data-count="people"]').textContent = Data.people.length;
  $('[data-count="groups"]').textContent = Data.allGroups.length;
}

(async () => {
  const me = await Shell.init({
    active: 'classmates',
    eyebrow: (m) => [m.section.name, m.section.semester].filter(Boolean).join(' · '),
    title: (m) => (m.user.role === 'admin' ? 'Members' : 'Classmates'),
  });
  Data.admin = me.user.role === 'admin';
  if (Data.admin) renderInvite(me.section);
  await Data.load();
  renderCounts();
  await People.search();
  GroupsTab.boot();
})().catch((e) => { if (e.message !== 'no section') showError(e); });
