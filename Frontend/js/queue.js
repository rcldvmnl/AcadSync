// Approval Queue (officers: President, Vice President, Secretary, Treasurer).
// FIFO: only the request at the front (#1) can be approved or rejected.
// Reject pushes onto the Undo Stack; Undo pops the latest rejection back into the queue.

// People who entered the class code wait in a second FIFO queue ("Waiting to join") above the task requests.

let state = { queue: [], undo: { size: 0, top: null }, joins: [], selected: null, busy: false };

(async () => {
  await Shell.init({ active: 'queue', eyebrow: (m) => `${m.user.role_label} · officers review in order`, title: 'Approval Queue' });
  // Every officer (President, Vice President, Secretary, Treasurer) reviews the queue
  if (!Shell.can('review_requests')) {
    $('#main').innerHTML = UI.errorBox('Only officers (President, Vice President, Secretary, Treasurer) can review requests.');
    return;
  }
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function load() {
  const [d, j] = await Promise.all([
    API.get('admin_queue.php'),
    Shell.can('review_members') ? API.get('join_requests.php') : Promise.resolve({ requests: [] }),
  ]);
  state.queue = d.queue;
  state.undo = d.undo;
  state.joins = j.requests;
  if (!state.queue.some((q) => q.id === state.selected)) state.selected = state.queue[0]?.id ?? null;
  render();
  updateBadges(d.size + state.joins.length);
  if (location.hash === '#members') $('#members')?.scrollIntoView({ block: 'start' });
}

/** "Waiting to join": people who entered the class code. Only #1 can be decided, like task requests. */
function joinsCard() {
  if (!state.joins.length) return '';
  const row = (r) => {
    const front = r.position === 1;
    return `
      <li class="flex flex-wrap items-center gap-3 rounded-2xl ${front ? 'bg-white' : 'bg-white/60'} p-3">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-semibold ${front ? 'bg-charcoal text-white' : 'bg-canvas text-muted'}">#${r.position}</span>
        <span class="min-w-0 flex-1">
          <span class="block truncate font-semibold">${esc(r.name)}</span>
          <span class="block truncate text-xs text-muted">${esc(r.student_id)} · asked ${esc(Fmt.monthDay(r.created_at.slice(0, 10)))}${r.other_sections ? ` · in ${plural(r.other_sections, 'other section')}` : ''}</span>
        </span>
        ${front ? `<span class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
          <button type="button" data-join-decline="${r.id}" class="btn btn-canvas h-10 px-4 text-sm">Decline</button>
          <button type="button" data-join-approve="${r.id}" class="btn btn-dark h-10 px-4 text-sm">Let in</button></span>` : ''}
      </li>`;
  };
  return `
    <section id="members" class="scroll-mt-6 rounded-[1.75rem] bg-mint-tint p-4" aria-labelledby="members-h">
      <div class="flex items-baseline justify-between gap-2 px-1">
        <h2 id="members-h" class="text-lg font-bold">Waiting to join <span class="font-normal text-muted">(${state.joins.length})</span></h2>
        <span class="text-xs text-muted">Oldest first</span>
      </div>
      <p class="mt-0.5 px-1 text-xs text-muted">They entered the class code. Check the name and student ID before letting someone in.</p>
      <ol class="mt-3 space-y-2">${state.joins.map(row).join('')}</ol>
    </section>`;
}

async function decideJoin(id, action) {
  if (state.busy) return;
  state.busy = true;
  try {
    const res = await API.post('join_requests.php', { request_id: id, action });
    UI.toast(action === 'approve' ? `${res.approved} is now a member` : `Declined ${res.declined}`);
    await load();
  } catch (e) {
    UI.toast(e.message, { iconName: 'alert' });
  } finally {
    state.busy = false;
  }
}

function updateBadges(n) {
  $$('a[href="queue.html"] .nav-badge').forEach((b) => b.remove());
  if (n) $$('a[href="queue.html"]').forEach((a) => a.insertAdjacentHTML('beforeend', `<span class="nav-badge">${n}</span>`));
}

const dueText = (q) => (q.deadline_type === 'unset' ? 'Date not set yet' : q.deadline_type === 'range'
  ? `Any day ${Fmt.monthDay(q.deadline)} – ${Fmt.monthDay(q.deadline_end)}`
  : `Due ${Fmt.short(q.deadline)}${q.deadline_time ? ` · ${Fmt.time(q.deadline_time)}` : ''}`);
const posLabel = (q) => (q.position === 1 ? '#1 · oldest' : `#${q.position} in queue`);

function render() {
  const n = state.queue.length;
  const selected = state.queue.find((q) => q.id === state.selected);
  $('#main').innerHTML = `
    ${state.joins.length ? `<div class="mb-5">${joinsCard()}</div>` : ''}
    <div class="grid gap-5 lg:grid-cols-[23rem_1fr] lg:items-start lg:gap-6">
      <div class="space-y-4">
        <div class="card-pastel dark flex items-center justify-between rounded-[1.75rem] p-5">
          <span class="sparkle">✦</span><span class="ring-deco"></span>
          <div>
            <div class="text-[10px] font-semibold uppercase tracking-wider text-white/60">Pending requests</div>
            <div class="text-4xl font-extrabold">${n}</div>
            <div class="text-xs text-white/70">waiting · oldest reviewed first</div>
          </div>
          <span class="grid h-14 w-14 place-items-center rounded-full bg-sun text-charcoal">${icon('inbox', 24)}</span>
        </div>
        <div class="flex items-center justify-between lg:hidden">
          <h2 class="text-xl font-bold">Awaiting permission</h2>
          <span class="chip bg-sun-tint">FIFO queue</span>
        </div>

        <!-- Mobile: stacked cards -->
        <div class="space-y-4 lg:hidden">${n ? state.queue.map(mobileCard).join('') : UI.empty('Queue is empty', 'New student requests will show up here.')}</div>

        <!-- Desktop: selectable list -->
        <div class="hidden space-y-3 lg:block">
          ${state.queue.map((q) => `
            <button data-select="${q.id}" class="flex w-full items-center gap-3 rounded-2xl bg-white p-3.5 text-left shadow-card ${q.id === state.selected ? 'ring-2 ring-charcoal' : ''}">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-semibold ${q.position === 1 ? 'bg-charcoal text-white' : 'bg-canvas text-muted'}">#${q.position}</span>
              <span class="min-w-0 flex-1"><span class="block truncate font-semibold">${esc(q.title)}${q.kind === 'change' ? ' <span class="text-xs font-medium text-muted">· edit</span>' : ''}</span><span class="block truncate text-xs text-muted">${esc(q.subject_code)} · ${esc(q.requested_by_name)}</span></span>
              ${icon('chevronRight', 18, 'text-muted')}
            </button>`).join('') || UI.empty('Queue is empty', 'New student requests will show up here.')}
        </div>
      </div>
      <div class="hidden lg:block">${selected ? detail(selected) : ''}</div>
    </div>`;

  $$('[data-select]').forEach((b) => b.onclick = () => { state.selected = Number(b.dataset.select); render(); });
  $$('[data-approve]').forEach((b) => b.onclick = () => decide(Number(b.dataset.approve), 'approve'));
  $$('[data-reject]').forEach((b) => b.onclick = () => decide(Number(b.dataset.reject), 'reject'));
  $$('[data-join-approve]').forEach((b) => b.onclick = () => decideJoin(Number(b.dataset.joinApprove), 'approve'));
  $$('[data-join-decline]').forEach((b) => b.onclick = () => decideJoin(Number(b.dataset.joinDecline), 'decline'));
  renderUndo();
}

function actions(q) {
  const front = q.position === 1;
  return `
    <div class="mt-4 grid grid-cols-2 gap-3">
      <button data-reject="${q.id}" class="btn btn-danger h-12" ${front ? '' : 'disabled'}>Reject</button>
      <button data-approve="${q.id}" class="btn btn-dark h-12" ${front ? '' : 'disabled'}>Approve</button>
    </div>
    ${front ? '' : '<p class="mt-2 text-center text-xs text-muted">Decide #1 first — requests are reviewed in the order they arrived.</p>'}`;
}

function changeSummary(q) {
  if (q.kind !== 'change' || !q.original) return '';
  return `<div class="mt-3 rounded-2xl bg-peach-tint p-3 text-xs">
    <div class="font-semibold">Edit request for “${esc(q.original.title)}”</div>
    ${q.changed?.length ? `<div class="mt-1 flex flex-wrap gap-1">${q.changed.map((c) => `<span class="chip bg-white px-2 py-0.5 text-[11px]">${esc(c)}</span>`).join('')}</div>` : '<div class="mt-0.5 text-muted">No fields changed</div>'}
    ${q.changed?.includes('Deadline') || q.changed?.includes('Due time') ? `<div class="mt-1 text-muted">${esc(dueText(q.original))} → <b class="text-charcoal">${esc(dueText(q))}</b></div>` : ''}
  </div>`;
}

function mobileCard(q) {
  return `
    <article class="rounded-[1.75rem] bg-white p-5 shadow-card">
      <div class="flex items-center justify-between">
        ${UI.chip(q.subject_code, tone(q.color).chip)}
        <span class="chip ${q.position === 1 ? 'bg-charcoal text-white' : 'bg-canvas text-muted'}">${posLabel(q)}</span>
      </div>
      <h3 class="mt-3 text-lg font-semibold">${esc(q.title)}</h3>
      <div class="mt-1 flex flex-wrap gap-1.5">${UI.chip(q.category, 'bg-canvas')}${UI.workBadge(q)}</div>
      <div class="mt-1 flex items-center gap-1.5 text-xs text-muted">${icon('clock', 14)}${esc(dueText(q))} · by ${esc(q.requested_by_name)}</div>
      ${changeSummary(q)}
      ${q.kind === 'new' ? similarBox(q, true) : ''}
      ${actions(q)}
    </article>`;
}

function similarBox(q, compact = false) {
  if (!q.similar.length) {
    return compact ? '' : `<div class="mt-4 flex gap-3 rounded-2xl bg-mint-tint p-4 text-sm">${icon('check', 18, 'mt-0.5 shrink-0')}
      <div><div class="font-semibold">No similar task found</div><div class="text-xs text-muted">Checked against all ${esc(q.subject_code)} task descriptions.</div></div></div>`;
  }
  const s = q.similar[0];
  return `<div class="mt-3 flex gap-3 rounded-2xl bg-sun-tint p-4 text-sm">${icon('alert', 18, 'mt-0.5 shrink-0')}
    <div><div class="font-semibold">Possible duplicate of “${esc(s.task.title)}”</div><div class="text-xs text-muted">Matched: ${esc(s.keywords.join(', '))} · ${esc(Fmt.due(s.task))}</div></div></div>`;
}

function detail(q) {
  const cells = [
    ['Type', `${q.category} · ${q.work_mode === 'group' ? `group work, ${UI.groupRule(q)}` : 'individual'}`],
    ['Due', dueText(q).replace(/^Due /, '')],
    ['Requested by', `${q.requested_by_name} · ${Fmt.monthDay(q.created_at.slice(0, 10))}`],
  ];
  return `
    <article class="rounded-[2rem] bg-white p-6 shadow-card">
      <div class="flex gap-2">${UI.chip(q.subject_code, tone(q.color).chip)}<span class="chip ${q.position === 1 ? 'bg-charcoal text-white' : 'bg-canvas text-muted'}">${posLabel(q)}</span></div>
      <h2 class="mt-3 text-4xl font-extrabold tracking-tight">${esc(q.title)}</h2>
      <div class="mt-4 grid grid-cols-3 gap-3">
        ${cells.map(([k, v]) => `<div class="rounded-2xl bg-canvas p-3"><div class="text-xs text-muted">${k}</div><div class="mt-0.5 text-sm font-semibold">${esc(v)}</div></div>`).join('')}
      </div>
      ${q.description ? `<p class="mt-4 whitespace-pre-line text-sm leading-relaxed">${esc(q.description)}</p>` : ''}
      ${changeSummary(q)}
      ${q.kind === 'new' ? similarBox(q) : ''}
      ${actions(q)}
    </article>`;
}

function renderUndo() {
  const bar = $('#undo-bar');
  if (!state.undo.size) { bar.innerHTML = ''; return; }
  bar.innerHTML = `
    <div class="fixed inset-x-4 bottom-24 z-30 mx-auto flex max-w-sm items-center gap-3 rounded-full bg-charcoal py-2 pl-2 pr-2 text-white shadow-float lg:bottom-8 lg:left-24">
      <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-charcoal">${icon('undo', 18)}</span>
      <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold">Rejected “${esc(state.undo.top.title)}”</div>
        <div class="text-xs text-white/60">${state.undo.size} item${state.undo.size === 1 ? '' : 's'} in undo stack</div></div>
      <button id="undo" class="btn btn-yellow h-10 px-5 text-sm">Undo</button>
    </div>`;
  $('#undo').onclick = undo;
}

async function decide(id, action) {
  if (state.busy) return;
  state.busy = true;
  try {
    const res = await API.post('admin_action.php', { task_id: id, action });
    if (action === 'approve') {
      UI.toast('Approved', { sub: res.timeline_position ? `Inserted at #${res.timeline_position} on the timeline` : 'Added to the schedule' });
    }
    await load();
  } catch (e) {
    UI.toast(e.message, { iconName: 'alert' });
  } finally {
    state.busy = false;
  }
}

async function undo() {
  if (state.busy) return;
  state.busy = true;
  try {
    const res = await API.post('admin_undo.php');
    UI.toast(`Restored “${res.restored.title}”`, { sub: `Back in the queue at #${res.queue_size}`, iconName: 'undo' });
    await load();
  } catch (e) {
    UI.toast(e.message, { iconName: 'alert' });
  } finally {
    state.busy = false;
  }
}
