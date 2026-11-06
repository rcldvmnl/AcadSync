// Task Details.

const taskId = new URLSearchParams(location.search).get('id');

(async () => {
  const { task: t, role } = await API.get('tasks.php', { id: taskId });
  await Shell.init({ active: 'schedule', eyebrow: `${t.subject_code} · ${t.subject_title}`, title: 'Task Details' });
  document.title = `AcadSync · ${t.title}`;
  $('#main').innerHTML = render(t, role === 'admin');
  $('#delete-task')?.addEventListener('click', () => confirmDelete(t));
  if ($('#done-card')) bindDoneCard(t);
  if (t.work_mode === 'group' && t.status === 'approved') loadGroups(t, role === 'admin');
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

/* ------------------------------------------------------------------ groups for a group task */
async function loadGroups(t, admin) {
  const box = $('#groups');
  let d;
  try {
    d = await API.get('groups.php', { task_id: t.id });
  } catch (e) {
    box.innerHTML = UI.errorBox(e);
    return;
  }
  const me = Shell.me.user.id;
  const total = d.groups.reduce((n, g) => n + g.members.length, 0) + d.ungrouped.length;
  const pct = total ? Math.round(((total - d.ungrouped.length) / total) * 100) : 0;
  box.innerHTML = `
    <div class="rounded-[1.75rem] bg-white p-5 shadow-card">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Groups</h3>
        <span class="chip bg-canvas">${UI.groupSize(t)} per group</span>
      </div>
      <div class="mt-3 h-2 overflow-hidden rounded-full bg-canvas"><div class="h-full rounded-full bg-success" style="width:${pct}%"></div></div>
      <p class="mt-1.5 text-xs text-muted">${total - d.ungrouped.length} of ${total} classmates are in a group · ${d.groups.length} group${d.groups.length === 1 ? '' : 's'}</p>

      <div class="mt-4 space-y-3">
        ${d.groups.map((g) => {
          const full = g.max_members !== null && g.members.length >= g.max_members;
          const under = g.min_members !== null && g.members.length < g.min_members;
          const canManage = admin || g.created_by === me;
          return `
          <div class="rounded-2xl ${g.is_member ? 'bg-lavender-tint' : 'bg-canvas'} p-3">
            <div class="flex items-center justify-between gap-2">
              <div class="font-semibold">${esc(g.name)}${g.is_member ? ' <span class="text-xs font-medium text-lavender-dark">· your group</span>' : ''}</div>
              <span class="chip ${under ? 'bg-sun-tint' : 'bg-white'}">${g.members.length}/${g.max_members ?? '∞'}${under ? ' · needs more' : full ? ' · full' : ''}</span>
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5">${g.members.map((m) => `<span class="chip bg-white">${esc(m.name)}</span>`).join('')}</div>
            <div class="mt-2 flex flex-wrap gap-2">
              ${g.is_member ? `<button data-leave="${g.id}" class="chip bg-white px-3 py-1.5 text-xs font-semibold">Leave</button>` : ''}
              ${!g.is_member && !d.in_group && !full ? `<button data-join="${g.id}" class="chip bg-charcoal px-3 py-1.5 text-xs font-semibold text-white">Join</button>` : ''}
              ${canManage && !full && d.ungrouped.length ? `<button data-add="${g.id}" class="chip bg-white px-3 py-1.5 text-xs font-semibold">+ Add classmates</button>` : ''}
            </div>
          </div>`;
        }).join('') || '<p class="rounded-2xl bg-canvas p-4 text-sm text-muted">No groups yet. Be the first to make one.</p>'}
      </div>

      ${!d.in_group ? `<button id="new-group" class="btn btn-dark mt-4 w-full">${icon('plus', 18)}Create a group</button>` : ''}

      ${d.ungrouped.length ? `
        <div class="mt-5">
          <div class="text-sm font-semibold">Still without a group (${d.ungrouped.length})</div>
          <div class="mt-2 flex flex-wrap gap-1.5">${d.ungrouped.map((m) => `<span class="chip ${m.id === me ? 'bg-sun-tint' : 'bg-canvas'}">${esc(m.name)}${m.id === me ? ' (you)' : ''}</span>`).join('')}</div>
          ${admin ? `<button id="auto-group" class="btn btn-light mt-3 w-full border border-line">Auto-group the remaining ${d.ungrouped.length}
            <span class="text-xs font-normal text-muted">→ ${d.plan.length} group${d.plan.length === 1 ? '' : 's'} of ${d.plan.join(', ')}</span></button>` : ''}
        </div>` : '<p class="mt-4 rounded-2xl bg-mint-tint p-3 text-sm">Everyone has a group. 🎉</p>'}
    </div>`;

  const act = async (body, msg) => {
    try {
      await API.post('groups.php', body);
      if (msg) UI.toast(msg);
      loadGroups(t, admin);
    } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
  };
  $$('[data-join]', box).forEach((b) => b.onclick = () => act({ action: 'join', group_id: Number(b.dataset.join) }, 'You joined the group'));
  $$('[data-leave]', box).forEach((b) => b.onclick = () => act({ action: 'leave', group_id: Number(b.dataset.leave) }, 'You left the group'));
  $$('[data-add]', box).forEach((b) => b.onclick = () => pickMembers(d, Number(b.dataset.add), t, admin));
  $('#new-group', box)?.addEventListener('click', () => pickMembers(d, null, t, admin));
  $('#auto-group', box)?.addEventListener('click', () => act({ action: 'auto_assign', task_id: t.id }, 'Remaining classmates were grouped'));
}

/** Sheet to create a group for this task (groupId = null) or add classmates to one. */
function pickMembers(d, groupId, t, admin) {
  const me = Shell.me.user.id;
  const group = d.groups.find((g) => g.id === groupId);
  const room = (group ? group.max_members - group.members.length : t.group_max - 1);
  const people = d.ungrouped.filter((m) => m.id !== me);
  UI.sheet(`
    <form>
      <h2 class="text-2xl font-bold">${group ? `Add to ${esc(group.name)}` : 'Create a group'}</h2>
      <p class="mt-1 text-sm text-muted">${group ? '' : 'You’ll be in it. '}Pick up to ${room} more classmate${room === 1 ? '' : 's'} who don’t have a group yet.</p>
      ${group ? '' : `<div class="mt-4">${UI.field({ name: 'name', label: 'Group name (optional)', placeholder: `Group ${d.groups.length + 1}` })}</div>`}
      <div class="mt-4 max-h-64 space-y-1 overflow-y-auto">
        ${people.map((m) => `<label class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-canvas">
          <input type="checkbox" name="m" value="${m.id}" class="h-5 w-5 accent-charcoal">
          <span class="flex-1 text-sm font-medium">${esc(m.name)}</span><span class="text-xs text-muted">${esc(m.student_id)}</span></label>`).join('') || '<p class="text-sm text-muted">Everyone else already has a group.</p>'}
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">${group ? 'Add to group' : 'Create group'}</button>
    </form>`, {
    label: group ? 'Add classmates' : 'Create a group',
    onMount(el, close) {
      const form = $('form', el);
      UI.bindForm(form, async (f) => {
        const ids = $$('input[name=m]:checked', form).map((c) => Number(c.value));
        if (ids.length > room) throw new Error(`You can add at most ${room}.`);
        await API.post('groups.php', group
          ? { action: 'add_members', group_id: group.id, member_ids: ids }
          : { action: 'create', task_id: t.id, name: f.name, member_ids: ids });
        close();
        UI.toast(group ? 'Classmates added' : 'Group created');
        loadGroups(t, admin);
      });
    },
  });
}

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

/** "Mark as done" — personal check-off; the task stays on the schedule either way. */
function doneCard(t) {
  return `
    <div id="done-card" class="flex items-center gap-3 rounded-[1.75rem] p-4 shadow-card ${t.done ? 'bg-mint-tint' : 'bg-white'}">
      ${UI.doneButton(t, 'h-12 w-12')}
      <div class="min-w-0 flex-1">
        <div class="font-semibold">${t.done ? 'You finished this' : 'Finished this task?'}</div>
        <div class="text-xs text-muted">${t.done ? `Checked off${t.done_at ? ` ${esc(Fmt.monthDay(t.done_at.slice(0, 10)))}` : ''} · it stays on your schedule. Tap to undo.` : 'Tap the check to mark it as done. It stays on your schedule.'}</div>
      </div>
    </div>`;
}

function bindDoneCard(t) {
  UI.bindDone($('#done-card'), (_, done) => {
    t.done = done;
    t.done_at = done ? Fmt.iso(new Date()) : null;
    $('#done-card').outerHTML = doneCard(t);
    bindDoneCard(t);
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
    ['Work', t.work_mode === 'group' ? `Group · ${UI.groupSize(t)} per group` : 'Individual'],
  ];

  return `
    <div class="grid gap-4 lg:grid-cols-2 lg:items-start lg:gap-6">
      <div class="space-y-4">
        <div class="card-pastel ${strong} rounded-[1.75rem] p-5 lg:p-7">
          <span class="sparkle">✦</span><span class="ring-deco"></span>
          <div class="flex flex-wrap gap-2">${UI.chip(t.category, 'bg-white')}${UI.workBadge(t)}${status}</div>
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
          ${info.map(([k, v]) => `<div class="rounded-2xl bg-white p-3.5 shadow-card ${k === 'Work' ? 'col-span-2' : ''}"><div class="text-xs text-muted">${k}</div><div class="mt-0.5 text-[15px] font-medium leading-snug">${esc(v)}</div></div>`).join('')}
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
        ${t.status === 'approved' ? doneCard(t) : ''}
        ${t.work_mode === 'group' && t.status === 'approved' ? `<div id="groups">${UI.skeleton(1, 'h-40')}</div>` : ''}
        ${t.status === 'approved' ? (admin
          ? `<div class="grid grid-cols-2 gap-3">
              <button type="button" id="delete-task" class="btn btn-danger">Remove</button>
              <a href="task-form.html?id=${t.id}" class="btn btn-dark">${icon('edit', 18)}Edit task</a>
            </div>`
          : `<a href="task-form.html?mode=change&id=${t.id}" class="btn btn-light w-full shadow-card">${icon('edit', 18)}Request changes</a>`) : ''}
      </div>
    </div>`;
}
