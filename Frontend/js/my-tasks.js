// My Tasks — two containers:
//   Pending -> requests I sent that are still waiting for admin review (edit / withdraw)
//   History -> tasks I marked as done, tasks whose deadline has passed, and my reviewed requests

const STATUS_CHIP = {
  pending: ['Waiting in queue', 'bg-sun-tint'],
  approved: ['Approved', 'bg-mint-tint text-success'],
  rejected: ['Not approved', 'bg-danger-tint'],
};

let tab = location.hash.slice(1) === 'history' ? 'history' : 'pending';
let mine = null;     // { requests, pending, limit }
let history = null;  // { completed, ended }

(async () => {
  await Shell.init({ active: 'home', eyebrow: sectionEyebrow, title: 'My tasks' });
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function load() {
  [mine, history] = await Promise.all([API.get('tasks.php', { mine: 1 }), API.get('tasks.php', { history: 1 })]);
  render();
}

function render() {
  const pending = mine.requests.filter((r) => r.status === 'pending');
  const reviewed = mine.requests.filter((r) => r.status !== 'pending');
  const historyCount = history.completed.length + history.ended.length + reviewed.length;
  $('#main').innerHTML = `
    <div class="grid grid-cols-2 rounded-full bg-white p-1 text-sm shadow-card lg:max-w-md" role="tablist" aria-label="My tasks">
      ${[['pending', 'Pending', pending.length], ['history', 'History', historyCount]].map(([k, l, n]) => `
        <button type="button" role="tab" data-tab="${k}" aria-selected="${tab === k}" class="rounded-full py-2.5 font-medium ${tab === k ? 'bg-charcoal text-white' : 'text-muted'}">${l} <span class="text-xs opacity-70">${n}</span></button>`).join('')}
    </div>
    <div class="mt-5">${tab === 'pending' ? pendingView(pending) : historyView(reviewed)}</div>`;

  $$('[data-tab]').forEach((b) => b.onclick = () => { tab = b.dataset.tab; history_replace(); render(); });
  $$('[data-withdraw]').forEach((b) => b.onclick = async () => {
    try {
      await API.post('request_task.php', { action: 'withdraw', request_id: Number(b.dataset.withdraw) });
      UI.toast('Request withdrawn', { sub: 'It left the approval queue' });
      await load();
    } catch (err) { UI.toast(err.message, { iconName: 'alert' }); }
  });
  UI.bindDone($('#main'), () => load());
}

const history_replace = () => window.history.replaceState(null, '', `#${tab}`);

/* ------------------------------------------------------------------ Pending */
function pendingView(pending) {
  const { limit } = mine;
  return `
    <section class="rounded-[1.75rem] bg-white p-4 shadow-card lg:max-w-2xl lg:p-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-xl font-bold">Pending requests</h2>
          <p class="mt-0.5 text-xs text-muted">Tasks you asked your admin to post. They show up on the schedule once approved.</p>
        </div>
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-sun-tint">${icon('inbox', 18)}</span>
      </div>
      <div class="mt-3 flex items-center gap-3 rounded-2xl bg-canvas px-3 py-2.5">
        <div class="flex gap-1">${Array.from({ length: limit }, (_, i) => `<span class="h-2 w-5 rounded-full ${i < pending.length ? 'bg-charcoal' : 'bg-line'}"></span>`).join('')}</div>
        <span class="text-xs text-muted">${pending.length} of ${limit} waiting${pending.length >= limit ? ' — edit or withdraw one to send more' : ''}</span>
      </div>
      <div class="mt-3 space-y-2">
        ${pending.length ? pending.map(pendingRow).join('') : `
          <div class="rounded-2xl bg-canvas p-6 text-center">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-mint-tint text-success">${icon('check', 22)}</div>
            <div class="mt-3 font-semibold">No pending requests</div>
            <p class="mt-1 text-sm text-muted">Tap + to ask your admin to add a task.</p>
          </div>`}
      </div>
      ${pending.length < limit ? `<a href="task-form.html" class="btn btn-dark mt-4 w-full">${icon('plus', 18)}Request a task</a>` : ''}
    </section>`;
}

function pendingRow(r) {
  return `
    <div class="rounded-2xl border border-line p-3">
      <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1">
          <div class="truncate text-[15px] font-semibold">${esc(r.title)}${r.kind === 'change' ? ' <span class="text-xs font-normal text-muted">(edit request)</span>' : ''}</div>
          <div class="mt-0.5 text-xs text-muted">${esc(r.subject_code)} · ${esc(r.category)}${r.work_mode === 'group' ? ` · group of ${UI.groupSize(r)}` : ''}</div>
          <div class="mt-0.5 text-xs text-muted">${esc(Fmt.due(r))} · sent ${esc(Fmt.ago(r.created_ts))}</div>
        </div>
        <span class="chip shrink-0 bg-sun-tint">${r.queue_position ? `#${r.queue_position} in queue` : 'Waiting'}</span>
      </div>
      <div class="mt-2.5 flex gap-2">
        <a href="task-form.html?request=${r.id}" class="chip bg-canvas px-3 py-1.5 text-xs font-semibold">${icon('edit', 12)}Edit</a>
        <button type="button" data-withdraw="${r.id}" class="chip bg-danger-tint px-3 py-1.5 text-xs font-semibold">Withdraw</button>
      </div>
    </div>`;
}

/* ------------------------------------------------------------------ History */
function historyView(reviewed) {
  const { completed, ended } = history;
  const box = (title, sub, iconName, tint, inner) => `
    <section class="rounded-[1.75rem] bg-white p-4 shadow-card lg:p-5">
      <div class="mb-3 flex items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full ${tint}">${icon(iconName, 18)}</span>
        <div class="min-w-0 flex-1"><h2 class="text-lg font-bold leading-tight">${title}</h2><div class="text-xs text-muted">${sub}</div></div>
      </div>
      ${inner}
    </section>`;
  const none = (text) => `<p class="rounded-2xl bg-canvas p-4 text-sm text-muted">${text}</p>`;

  return `
    <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
      ${box('Completed', `${completed.length} task${completed.length === 1 ? '' : 's'} you checked off`, 'check', 'bg-mint-tint text-success',
        completed.length ? `<div class="space-y-2">${completed.map((t) => historyRow(t, `Done ${t.done_ts ? Fmt.ago(t.done_ts) : ''}`, true)).join('')}</div>`
          : none('Nothing checked off yet. Open a task and tap the check when you finish it.'))}
      <div class="space-y-5">
        ${box('Past deadlines', `${ended.length} task${ended.length === 1 ? '' : 's'} not marked as done`, 'clock', 'bg-peach-tint',
          ended.length ? `<div class="space-y-2">${ended.map((t) => historyRow(t, `Ended ${Fmt.relative(t.deadline_ts)}`, true)).join('')}</div>`
            : none('No missed deadlines.'))}
        ${box('Request history', `${reviewed.length} reviewed by your admin`, 'inbox', 'bg-lavender-tint',
          reviewed.length ? `<div class="space-y-2">${reviewed.map(requestRow).join('')}</div>` : none('None of your requests have been reviewed yet.'))}
      </div>
    </div>`;
}

function historyRow(t, when, toggle) {
  const d = t.deadline_ts ? new Date(t.deadline_ts * 1000) : null;
  return `
    <div class="flex items-center gap-3 rounded-2xl bg-canvas p-2.5 pr-3">
      <a href="task.html?id=${t.id}" class="flex min-w-0 flex-1 items-center gap-3">
        <span class="grid h-11 w-10 shrink-0 place-items-center rounded-xl bg-white text-center leading-none">
          ${d ? `<span><span class="block text-base font-bold">${String(d.getDate()).padStart(2, '0')}</span><span class="mt-0.5 block text-[9px] font-semibold uppercase text-muted">${MONTHS[d.getMonth()]}</span></span>` : '<span class="text-[10px] font-bold text-muted">TBA</span>'}
        </span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold ${t.done ? 'text-muted line-through decoration-1' : ''}">${esc(t.title)}</span>
          <span class="block truncate text-xs text-muted">${esc(t.subject_code)} · ${esc(t.category)} · ${esc(when)}</span>
        </span>
      </a>
      ${toggle ? UI.doneButton(t, 'h-9 w-9') : ''}
    </div>`;
}

function requestRow(r) {
  const [label, cls] = STATUS_CHIP[r.status] || [r.status, 'bg-canvas'];
  const link = r.status === 'approved' ? `task.html?id=${r.kind === 'change' ? r.target_task_id : r.id}` : null;
  const tag = link ? `a href="${link}"` : 'div';
  return `
    <${tag} class="flex items-center gap-3 rounded-2xl bg-canvas p-3 ${link ? 'hover:bg-line/60' : ''}">
      <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold">${esc(r.title)}${r.kind === 'change' ? ' <span class="text-xs font-normal text-muted">(edit request)</span>' : ''}</span>
        <span class="block truncate text-xs text-muted">${esc(r.subject_code)} · ${esc(r.category)} · sent ${esc(Fmt.ago(r.created_ts))}</span>
      </span>
      ${UI.chip(label, cls)}
    </${link ? 'a' : 'div'}>`;
}
