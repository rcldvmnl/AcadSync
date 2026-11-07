// Member records: the officers, every member's standing and attendance (officers),
// or one member's full record (officers, or the member themself).
//   The President chooses the Vice President and Secretary here.
//   The Secretary and the President change a member's status (Active / Inactive / Alumni).
//   The list is ranked by attendance with Merge Sort on the server.

const recordId = new URLSearchParams(location.search).get('id');
const STATUS_TINT = { active: 'bg-mint-tint', inactive: 'bg-sun-tint', alumni: 'bg-canvas' };
const OFFICE = {
  president: { label: 'President', tint: 'bg-sun-tint', duty: 'Created the section. Chooses the officers and runs everything.' },
  vice_president: { label: 'Vice President', tint: 'bg-lavender-tint', duty: 'Reviews the approval queue and runs activities.' },
  secretary: { label: 'Secretary', tint: 'bg-peach-tint', duty: 'Reviews the queue, takes attendance and keeps these records.' },
};
let state = { filter: 'all', data: null };

(async () => {
  const me = await Shell.init({ active: 'classmates', eyebrow: sectionEyebrow, title: recordId ? 'Member Record' : 'Member Records' });
  if (!recordId && !Shell.can('view_member_records')) {
    location.replace(`records.html?id=${me.user.id}`);   // members only see their own record
    return;
  }
  if (recordId) await loadOne(); else await loadAll();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

const rateText = (r) => (r.rate !== null ? `${r.attended} of ${r.expected} · ${r.rate}%`
  : r.status.key !== 'active' ? `Not counted (${r.status.label})` : 'No activities yet');
const rateBar = (r) => `<div class="act-bar"><span style="width:${r.rate ?? 0}%"></span></div>`;
const statusChip = (s) => UI.chip(s.label, STATUS_TINT[s.key]);

/* ================================================================== all records */
async function loadAll() {
  state.data = await API.get('records.php');
  renderAll();
}

function renderAll() {
  const d = state.data;
  const counts = { all: d.records.length };
  d.statuses.forEach((s) => { counts[s.key] = d.records.filter((r) => r.status.key === s.key).length; });
  const rows = d.records.filter((r) => state.filter === 'all' || r.status.key === state.filter);
  $('#main').innerHTML = `
    ${officersCard(d)}
    <section class="mt-6" aria-labelledby="rank-h">
      <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <div><h2 id="rank-h" class="text-xl font-bold">Attendance ranking</h2>
          <p class="text-xs text-muted">Best attendance first, ranked with Merge Sort. Only activities a member was expected at count.</p></div>
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Show">
          ${[['all', 'All'], ...d.statuses.map((s) => [s.key, s.label])].map(([k, l]) => `
            <button type="button" role="radio" data-filter="${k}" aria-checked="${state.filter === k}" aria-pressed="${state.filter === k}" class="chip chip-filter">${l} <span class="opacity-70">${counts[k]}</span></button>`).join('')}
        </div>
      </div>
      ${rows.length ? `<ol class="rec-list">${rows.map((r) => rankRow(r, d.records.indexOf(r) + 1)).join('')}</ol>`
        : `<p class="rounded-[1.5rem] bg-white p-5 text-sm text-muted shadow-card">No ${state.filter} members.</p>`}
    </section>`;
  $$('[data-filter]').forEach((b) => b.onclick = () => { state.filter = b.dataset.filter; renderAll(); });
  $$('[data-office]').forEach((s) => s.onchange = () => assign(s));
}

function officersCard(d) {
  const o = d.officers;
  const holderIds = [o.president?.id, o.vice_president?.id, o.secretary?.id].filter(Boolean);
  const tile = (key) => {
    const info = OFFICE[key];
    const who = o[key];
    const pickable = d.can_assign && key !== 'president';
    // Choices: active members who aren't the President and don't already hold the other office
    const options = d.records.filter((r) => r.status.key === 'active' && r.position !== 'president' && (!holderIds.includes(r.id) || r.id === who?.id));
    return `
      <div class="rounded-[1.5rem] ${info.tint} p-4">
        <div class="text-sm font-semibold">${info.label}</div>
        ${pickable ? `
          <label class="mt-2 block"><span class="sr-only">Choose the ${info.label}</span>
            <span class="rec-select"><select data-office="${key}">
              <option value="">Vacant</option>
              ${options.map((r) => `<option value="${r.id}" ${r.id === who?.id ? 'selected' : ''}>${esc(r.name)}</option>`).join('')}
            </select>${icon('chevronDown', 16)}</span></label>`
          : `<div class="mt-1 text-xl font-bold leading-tight">${who ? esc(who.name) : '<span class="text-muted">Vacant</span>'}</div>`}
        <p class="mt-2 text-xs text-muted">${info.duty}</p>
      </div>`;
  };
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="off-h">
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="off-h" class="text-xl font-bold">Officers</h2>
        <p class="text-xs text-muted">${d.can_assign ? 'Choose who serves. They’re notified, and can review the approval queue right away.' : 'Chosen by the President.'}</p>
      </div>
      <div class="mt-4 grid gap-3 sm:grid-cols-3">${tile('president')}${tile('vice_president')}${tile('secretary')}</div>
    </section>`;
}

function rankRow(r, rank) {
  return `
    <li>
      <a href="records.html?id=${r.id}" class="rec-row">
        <span class="rec-rank" aria-label="Rank ${rank}">${rank}</span>
        ${UI.avatar(r.name, 'bg-lavender-tint', 'h-10 w-10 text-xs')}
        <span class="min-w-0 flex-1">
          <span class="flex flex-wrap items-center gap-1.5"><span class="truncate font-semibold">${esc(r.name)}</span>
            ${r.is_officer ? UI.chip(r.role_label, OFFICE[r.position].tint) : ''}${r.status.key !== 'active' ? statusChip(r.status) : ''}</span>
          <span class="mt-1.5 block max-w-xs">${rateBar(r)}</span>
        </span>
        <span class="hidden w-40 shrink-0 text-right text-sm sm:block">${esc(rateText(r))}</span>
        <span class="hidden w-20 shrink-0 text-right text-sm text-muted md:block">${plural(r.groups, 'group')}</span>
        ${icon('chevronRight', 18, 'shrink-0 text-muted')}
      </a>
      <span class="block px-4 pb-2 text-xs text-muted sm:hidden">${esc(rateText(r))}</span>
    </li>`;
}

async function assign(select) {
  const position = select.dataset.office;
  select.disabled = true;
  try {
    await API.post('records.php', { action: 'assign_officer', position, user_id: select.value ? Number(select.value) : null });
    UI.toast(select.value ? `${OFFICE[position].label} updated` : `${OFFICE[position].label} is now vacant`,
      { sub: select.value ? 'They were notified and can now review the approval queue' : '' });
    await loadAll();
  } catch (e) {
    UI.toast(e.message, { iconName: 'alert' });
    await loadAll();
  }
}

/* ================================================================== one record */
async function loadOne() {
  const d = await API.get('records.php', { id: recordId });
  const r = d.record;
  document.title = `AcadSync · ${r.name}`;
  const joined = Shell.monthYear(r.joined_at);
  const terms = r.terms.map((t) => `
    <li class="flex items-baseline justify-between gap-3 py-2"><span class="font-medium">${esc(OFFICE[t.position]?.label || t.position)}</span>
      <span class="text-xs text-muted">${esc(Shell.monthYear(t.started_at))} – ${t.ended_at ? esc(Shell.monthYear(t.ended_at)) : 'now'}</span></li>`).join('');
  $('#main').innerHTML = `
    ${Shell.can('view_member_records') ? `<a href="records.html" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold">${icon('chevronLeft', 16)}All member records</a>` : ''}
    <div class="grid gap-5 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start">
      <div class="space-y-4">
        <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="who-h">
          <div class="flex items-center gap-4">
            ${UI.avatar(r.name, 'bg-peach', 'h-16 w-16 text-lg')}
            <div class="min-w-0">
              <h2 id="who-h" class="truncate text-xl font-bold">${esc(r.name)}${d.is_me ? ' <span class="text-sm font-medium text-muted">(you)</span>' : ''}</h2>
              <div class="mt-1 flex flex-wrap gap-1.5">${UI.chip(r.role_label, r.is_officer ? OFFICE[r.position].tint : 'bg-canvas')}${statusChip(r.status)}</div>
            </div>
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-2xl bg-canvas p-3"><dt class="text-xs text-muted">Student ID</dt><dd class="font-semibold">${esc(r.student_id)}</dd></div>
            <div class="rounded-2xl bg-canvas p-3"><dt class="text-xs text-muted">Member since</dt><dd class="font-semibold">${esc(joined)}</dd></div>
          </dl>
          <a href="mailto:${esc(r.email)}" class="mt-3 flex items-center gap-2 rounded-full bg-canvas px-4 py-2.5 text-sm font-medium">${icon('mail', 16)}<span class="truncate">${esc(r.email)}</span></a>
        </section>
        <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="status-h">
          <h3 id="status-h" class="text-lg font-semibold">Membership status</h3>
          ${d.can_update ? `
            <div class="mt-3 grid grid-cols-3 gap-1 rounded-full bg-canvas p-1" role="radiogroup" aria-label="Membership status">
              ${d.statuses.map((s) => `<button type="button" role="radio" data-status="${s.key}" aria-checked="${s.key === r.status.key}" class="rounded-full py-2 text-sm font-semibold ${s.key === r.status.key ? 'bg-charcoal text-white' : 'text-muted'}">${esc(s.label)}</button>`).join('')}
            </div>` : `<div class="mt-2">${statusChip(r.status)}</div>`}
          <p class="mt-2 text-sm text-muted">${esc(r.status.meaning)}</p>
        </section>
      </div>
      <div class="space-y-4">
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Summary">
          ${[[r.rate === null ? '—' : `${r.rate}%`, 'Attendance'], [`${r.attended}/${r.expected}`, 'Attended'], [r.groups, 'Groups'], [r.requests, 'Task requests']]
            .map(([v, l]) => `<div class="rounded-[1.5rem] bg-white p-4 shadow-card"><div class="text-2xl font-extrabold">${v}</div><div class="text-xs text-muted">${l}</div></div>`).join('')}
        </section>
        <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="hist-h">
          <h3 id="hist-h" class="text-lg font-semibold">Attendance history</h3>
          <p class="text-xs text-muted">Activities they were expected at, or came to. Newest first.</p>
          ${r.history.length ? `<ul class="mt-2">${r.history.slice().reverse().map((h) => `
            <li class="flex items-center gap-3 border-t border-line py-2.5 first:border-t-0">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full ${h.came ? 'bg-mint-tint text-success' : 'bg-danger-tint'}">${icon(h.came ? 'check' : 'x', 16)}</span>
              <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">${esc(h.title)}</span>
                <span class="block text-xs text-muted">${esc(h.kind_label)} · ${esc(Fmt.short(new Date(h.start_ts * 1000)))}${h.had_seat ? ' · had a seat' : ''}</span></span>
              <span class="text-sm font-semibold ${h.came ? 'text-success' : ''}">${h.came ? 'Attended' : 'Absent'}</span>
            </li>`).join('')}</ul>` : '<p class="mt-3 rounded-2xl bg-canvas p-4 text-sm text-muted">No finished activities count for them yet.</p>'}
        </section>
        <div class="grid gap-4 md:grid-cols-2">
          <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="office-h">
            <h3 id="office-h" class="text-lg font-semibold">Offices held</h3>
            ${r.position === 'president' ? '<p class="mt-2 text-sm">President since creating the section.</p>' : ''}
            ${terms ? `<ul class="mt-1 divide-y divide-line text-sm">${terms}</ul>` : r.position === 'president' ? '' : '<p class="mt-2 text-sm text-muted">None yet.</p>'}
          </section>
          <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="groups-h">
            <h3 id="groups-h" class="text-lg font-semibold">Groups</h3>
            ${r.group_list.length ? `<ul class="mt-1 divide-y divide-line text-sm">${r.group_list.map((g) => `
              <li class="py-2"><a href="classmates.html#groups&group=${g.id}" class="font-medium underline-offset-2 hover:underline">${esc(g.name)}</a>
                <span class="block text-xs text-muted">${g.task ? esc(g.task) : 'Study group'} · ${plural(g.size, 'member')}</span></li>`).join('')}</ul>`
              : '<p class="mt-2 text-sm text-muted">Not in any group.</p>'}
          </section>
        </div>
      </div>
    </div>`;
  $$('[data-status]').forEach((b) => b.onclick = async () => {
    if (b.dataset.status === r.status.key) return;
    try {
      const res = await API.post('records.php', { action: 'set_status', user_id: r.id, status: b.dataset.status });
      UI.toast(`Status is now ${res.status.label}`, { sub: res.stepped_down ? 'They stepped down from their office' : '' });
      await loadOne();
    } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
  });
}
