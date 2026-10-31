// Task Details.

const taskId = new URLSearchParams(location.search).get('id');

(async () => {
  const { task: t, role } = await API.get('tasks.php', { id: taskId });
  await Shell.init({ active: 'schedule', eyebrow: `${t.subject_code} · ${t.subject_title}`, title: 'Task Details' });
  document.title = `AcadSync · ${t.title}`;
  $('#main').innerHTML = render(t, role === 'admin');
  $('#delete-task')?.addEventListener('click', () => confirmDelete(t));
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

/** Admin: remove a published task (it is unlinked from the timeline's Linked List). */
function confirmDelete(t) {
  UI.sheet(`
    <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-danger-tint">${icon('alert', 24)}</span>
    <h2 class="text-2xl font-bold">Remove this task?</h2>
    <p class="mt-2 text-sm text-muted">“${esc(t.title)}” will disappear from everyone’s schedule in your section.</p>
    <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
    <div class="mt-6 grid grid-cols-2 gap-3">
      <button type="button" data-close class="btn btn-canvas">Cancel</button>
      <button type="button" data-confirm class="btn btn-dark">Remove</button>
    </div>`, {
    label: 'Remove task',
    onMount(el) {
      $('[data-confirm]', el).onclick = async () => {
        try {
          await API.post('request_task.php', { action: 'delete', task_id: t.id });
          location.replace('schedule.html');
        } catch (e) {
          const err = $('[data-error]', el);
          err.textContent = e.message;
          err.hidden = false;
        }
      };
    },
  });
}

function render(t, admin) {
  const unset = t.deadline_type === 'unset' || !t.deadline_ts;
  const endOfToday = new Date(); endOfToday.setHours(23, 59, 59, 999);
  const dueToday = !unset && t.deadline_ts * 1000 <= endOfToday;
  const strong = t.color === 'mint' ? 'mint-soft' : tone(t.color).strong;

  const status = t.status !== 'approved'
    ? UI.chip(t.status === 'pending' ? 'Pending review' : 'Not approved', 'bg-charcoal text-white')
    : unset ? UI.chip('Date not set yet', 'bg-white')
    : dueToday ? UI.chip('Urgent · due today', 'bg-charcoal text-white') : '';

  const end = t.deadline_end || t.deadline;
  const deadlineMain = unset ? 'Not set yet'
    : t.deadline_type === 'range' ? `${Fmt.monthDay(t.deadline)} – ${Fmt.monthDay(end)}${t.deadline_time ? ` · ${Fmt.time(t.deadline_time)}` : ''}`
    : `${Fmt.short(end)}${t.deadline_time ? ` · ${Fmt.time(t.deadline_time)}` : ''}`;
  const deadlineSub = unset ? 'The professor hasn’t announced the exact date yet.'
    : t.deadline_ts * 1000 < Date.now() ? `Was due ${Fmt.relative(t.deadline_ts)}`
    : t.deadline_type === 'range' ? 'Submit on any day in the range'
    : `Due ${Fmt.relative(t.deadline_ts)}`;

  // Description: plain lines are instructions, "- " lines become a checklist.
  const lines = String(t.description || '').split('\n').map((l) => l.trim()).filter(Boolean);
  const prose = lines.filter((l) => !l.startsWith('- '));
  const checklist = lines.filter((l) => l.startsWith('- ')).map((l) => l.slice(2));

  const posted = t.requested_by_role === 'admin' ? 'Admin' : t.requested_by_name;
  const info = [
    ['Subject', t.subject_code],
    ['Type', t.category],
    ['Where', t.location || '—'],
    ['Posted by', `${posted} · ${Fmt.monthDay(t.created_at.slice(0, 10))}`],
  ];

  return `
    <div class="grid gap-4 lg:grid-cols-2 lg:items-start lg:gap-6">
      <div class="space-y-4">
        <div class="card-pastel ${strong} rounded-[1.75rem] p-5 lg:p-7">
          <span class="sparkle">✦</span><span class="ring-deco"></span>
          <div class="flex flex-wrap gap-2">${UI.chip(t.category, 'bg-white')}${status}</div>
          <h2 class="mt-3 text-[28px] font-extrabold leading-tight tracking-tight lg:text-4xl">${esc(t.title)}</h2>
        </div>
        <div class="card-pastel dark flex items-center gap-4 rounded-[1.75rem] p-5">
          <span class="sparkle">✦</span><span class="ring-deco"></span>
          <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-sun text-charcoal">${icon(unset ? 'calendar' : 'clock', 22)}</span>
          <div>
            <div class="text-[10px] font-semibold uppercase tracking-wider text-white/60">Deadline</div>
            <div class="text-xl font-bold">${esc(deadlineMain)}</div>
            <div class="text-xs text-white/70">${esc(deadlineSub)}</div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          ${info.map(([k, v]) => `<div class="rounded-2xl bg-white p-3.5 shadow-card"><div class="text-xs text-muted">${k}</div><div class="mt-0.5 text-[15px] font-medium leading-snug">${esc(v)}</div></div>`).join('')}
        </div>
      </div>

      <div class="space-y-4">
        ${unset ? `<div class="flex gap-3 rounded-[1.5rem] bg-sun-tint p-4">${icon('bell', 18, 'mt-0.5 shrink-0')}
          <div><div class="text-sm font-semibold">We’ll notify you when the date is set</div><div class="text-xs text-muted">The professor hasn’t announced the exact date yet.</div></div></div>` : ''}
        <div class="rounded-[1.75rem] bg-white p-5 shadow-card">
          <h3 class="text-lg font-semibold">Instructions</h3>
          ${prose.length ? prose.map((p) => `<p class="mt-2 text-[15px] leading-relaxed">${esc(p)}</p>`).join('') : '<p class="mt-2 text-sm text-muted">No instructions yet.</p>'}
          ${checklist.length ? `<h3 class="mt-4 text-lg font-semibold">What to prepare</h3>
            <ul class="mt-2 space-y-2">${checklist.map((c) => `<li class="flex items-center gap-3 text-[15px]"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-mint-tint text-success">${icon('check', 14)}</span>${esc(c)}</li>`).join('')}</ul>` : ''}
        </div>
        ${t.status === 'approved' ? (admin
          ? `<div class="grid grid-cols-2 gap-3">
              <button type="button" id="delete-task" class="btn btn-danger">Remove</button>
              <a href="task-form.html?id=${t.id}" class="btn btn-dark">${icon('edit', 18)}Edit task</a>
            </div>`
          : `<a href="task-form.html?mode=change&id=${t.id}" class="btn btn-light w-full shadow-card">${icon('edit', 18)}Request changes</a>`) : ''}
      </div>
    </div>`;
}
