// Approval Queue (admin).
// FIFO: only the request at the front (#1) can be approved or rejected.
// Reject pushes onto the Undo Stack; Undo pops the latest rejection back into the queue.

let state = { queue: [], undo: { size: 0, top: null }, selected: null, busy: false };

(async () => {
  const me = await Shell.init({ active: 'queue', eyebrow: 'Admin dashboard', title: 'Approval Queue' });
  if (me.user.role !== 'admin') {
    $('#main').innerHTML = UI.errorBox('Only the class admin can review requests.');
    return;
  }
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function load() {
  const d = await API.get('admin_queue.php');
  state.queue = d.queue;
  state.undo = d.undo;
  if (!state.queue.some((q) => q.id === state.selected)) state.selected = state.queue[0]?.id ?? null;
  render();
  updateBadges(d.size);
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
    ['Type', `${q.category} · ${q.work_mode === 'group' ? `group of ${UI.groupSize(q)}` : 'individual'}`],
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
