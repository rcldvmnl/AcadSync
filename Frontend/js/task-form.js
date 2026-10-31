// One form, four modes:
//   student            -> "Request a Task"   (ENQUEUED to the admin approval queue)
//   student ?mode=change&id= -> "Request Changes" (edit request, also queued)
//   admin              -> "Add Task"         (published right away)
//   admin ?id=         -> "Edit Task"        (applied right away)

const params = new URLSearchParams(location.search);
const editId = params.get('id');
const CATEGORIES = ['Reporting', 'Quiz', 'Exam', 'Activity', 'Project'];
const CAT_ON = { Reporting: 'bg-lavender-tint', Quiz: 'bg-lavender-tint', Exam: 'bg-peach-tint', Activity: 'bg-mint-tint', Project: 'bg-sun-tint' };

let picker, times, category = 'Reporting', mode, task = null;

(async () => {
  const me = await API.get('auth.php');
  const admin = me.user.role === 'admin';
  if (editId) task = (await API.get('tasks.php', { id: editId })).task;
  mode = admin ? (task ? 'edit' : 'add') : (task ? 'change' : 'request');

  const heads = {
    request: ['Goes to the admin approval queue', 'Request a Task'],
    change: [task && `${task.title} · ${task.subject_code}`, 'Request Changes'],
    add: ['Admin · publishes right away', 'Add Task'],
    edit: [task && `${task.title} · ${task.subject_code}`, 'Edit Task'],
  };
  await Shell.init({ active: mode === 'request' || mode === 'add' ? 'add' : 'schedule', eyebrow: heads[mode][0], title: heads[mode][1] });
  document.title = `AcadSync · ${heads[mode][1]}`;

  const [{ subjects }, sched] = await Promise.all([API.get('subjects.php'), API.get('schedule.php')]);
  const markers = new Set(sched.timeline.filter((t) => t.deadline).map((t) => t.deadline_end || t.deadline));
  if (!subjects.length) {
    $('#main').innerHTML = `${UI.empty('No subjects yet', admin ? 'Add a subject first, then you can add its tasks.' : 'Ask your class officer to add the section’s subjects.', 'bookOpen')}
      ${admin ? '<a href="add-subject.html" class="btn btn-dark mt-4 w-full">Add a subject</a>' : ''}`;
    return;
  }
  render(subjects, markers);
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

function banner() {
  if (mode === 'change') {
    return `<div class="flex gap-3 rounded-[1.5rem] bg-lavender-tint p-4">${icon('info', 18, 'mt-0.5 shrink-0')}
      <div><div class="text-sm font-semibold">Changes go to the admin queue</div><div class="text-xs text-muted">The current task stays as-is until the admin approves.</div></div></div>`;
  }
  const [cls, title, sub] = {
    request: ['lavender', 'Request a new task', 'Your admin reviews requests in the order they arrive.'],
    add: ['yellow', 'Add a task', 'Admin tasks skip the queue and appear on everyone’s schedule.'],
    edit: ['yellow', 'Edit this task', 'Everyone sees the change right away.'],
  }[mode];
  return `
    <div class="card-pastel ${cls} flex items-center gap-4 rounded-[1.75rem] p-4">
      <span class="sparkle">✦</span><span class="ring-deco" style="width:90px;height:90px;right:-20px;bottom:-50px;border-width:12px"></span>
      <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-white">${icon(mode === 'edit' ? 'edit' : 'plus', 22)}</span>
      <div><div class="font-semibold">${title}</div><div class="text-xs">${sub}</div></div>
    </div>`;
}

function render(subjects, markers) {
  const presetSubject = task?.subject_id || params.get('subject');
  category = task?.category || 'Reporting';
  const change = mode === 'change';
  const nowLabel = task ? (task.deadline_ts ? `Now: ${Fmt.monthDay(task.deadline_end || task.deadline)}${task.deadline_time ? ` · ${Fmt.time(task.deadline_time)}` : ''}` : 'Now: not set') : '';

  $('#main').innerHTML = `
    <form id="task-form" class="grid gap-5 lg:grid-cols-2 lg:items-start" novalidate>
      <div class="space-y-5">
        ${banner()}
        <div class="space-y-4 rounded-[1.75rem] bg-white p-5 shadow-card">
          ${change ? `<div class="flex items-center justify-between"><h2 class="text-xl font-bold">New deadline</h2><span class="chip bg-sun-tint">${esc(nowLabel)}</span></div>` : `
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Subject</span>
            <span class="field"><select name="subject_id" required>
              <option value="">Pick a subject</option>
              ${subjects.map((s) => `<option value="${s.id}" ${String(s.id) === String(presetSubject) ? 'selected' : ''}>${esc(s.code)} · ${esc(s.title)}</option>`).join('')}
            </select>${icon('chevronDown', 18)}</span></label>
          <div><div class="mb-1.5 text-xs text-muted">Category</div><div class="flex flex-wrap gap-2" id="cats"></div></div>
          <div class="text-xs text-muted">Deadline</div>`}
          <div id="picker"></div>
          <div id="time-wrap"><div class="mb-1.5 text-xs text-muted">Due time</div><div id="times"></div></div>
        </div>
      </div>

      <div class="space-y-5">
        ${change ? '' : `
        <div class="space-y-4 rounded-[1.75rem] bg-white p-5 shadow-card">
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Title</span>
            <span class="field"><input name="title" placeholder="e.g. Reporting — Chapter 3" value="${esc(task?.title || '')}" required></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Description</span>
            <span class="field area"><textarea name="description" rows="5" placeholder="Add instructions or notes… Start a line with “- ” to add a checklist item.">${esc(task?.description || '')}</textarea></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Where</span>
            <span class="field">${icon('mapPin', 18)}<input name="location" placeholder="In class · Room 204" value="${esc(task?.location || '')}"></span></label>
          <div id="dupes" aria-live="polite"></div>
        </div>`}
        <p data-error class="rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <div class="grid grid-cols-2 gap-3">
          <button type="button" id="cancel" class="btn btn-light shadow-card">Cancel</button>
          <button type="submit" class="btn btn-dark">${{ request: 'Send to queue', change: 'Send request', add: 'Publish task', edit: 'Save changes' }[mode]}</button>
        </div>
      </div>
    </form>`;

  if (!change) renderCats();
  picker = createDatePicker($('#picker'), {
    type: task?.deadline_type || 'exact',
    start: task?.deadline || null,
    end: task?.deadline_end || null,
    markers,
    onChange: (v) => { $('#time-wrap').hidden = v.type === 'unset'; },
  });
  $('#time-wrap').hidden = (task?.deadline_type || 'exact') === 'unset';
  times = createTimeChips($('#times'), { value: task?.deadline_time || (mode === 'change' ? null : '23:59') });
  $('#cancel').onclick = () => history.back();

  const form = $('#task-form');
  if (!change) {
    let timer;
    const check = () => { clearTimeout(timer); timer = setTimeout(checkDuplicates, 450); };
    form.elements.title.addEventListener('input', check);
    form.elements.description.addEventListener('input', check);
    form.elements.subject_id.addEventListener('change', check);
  }

  UI.bindForm(form, async (d) => {
    const when = picker.value();
    if (when.type !== 'unset' && !when.start) throw new Error('Pick a deadline date on the calendar.');
    if (when.type === 'range' && !when.end) throw new Error('Pick the last day of the range.');
    const body = {
      action: 'submit',
      subject_id: Number(d.subject_id || task?.subject_id),
      category: change ? task.category : category,
      title: d.title ?? task?.title,
      description: d.description ?? task?.description,
      location: d.location ?? task?.location,
      deadline_type: when.type,
      deadline: when.start,
      deadline_end: when.type === 'range' ? when.end : null,
      deadline_time: when.type === 'unset' ? null : times.value(),
      target_task_id: task ? task.id : null,
    };
    const res = await API.post('request_task.php', body);
    if (res.status === 'approved') {
      location.href = `task.html?id=${res.id}`;
      return;
    }
    UI.sheet(`
      <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-mint-tint text-success">${icon('check', 24)}</span>
      <h2 class="text-2xl font-bold">Request sent</h2>
      <p class="mt-2 text-sm text-muted">It’s <b class="text-charcoal">#${res.queue_position}</b> in the approval queue. Your admin reviews requests in the order they arrive.</p>
      ${res.similar?.length ? `<p class="mt-3 rounded-2xl bg-sun-tint px-4 py-3 text-xs">Heads-up: it looks similar to “${esc(res.similar[0].task.title)}”.</p>` : ''}
      <a href="schedule.html" class="btn btn-dark mt-6 w-full">Back to schedule</a>
      <a href="task-form.html" class="mt-3 block py-2 text-center text-sm font-semibold">Request another</a>`, { label: 'Request sent' });
  });
}

function renderCats() {
  $('#cats').innerHTML = CATEGORIES.map((c) => `<button type="button" data-cat="${c}" aria-pressed="${c === category}" class="chip px-3.5 py-2 text-sm ${c === category ? 'bg-charcoal text-white' : CAT_ON[c]}">${c}</button>`).join('');
  $$('[data-cat]').forEach((b) => b.onclick = () => { category = b.dataset.cat; renderCats(); });
}

/** Linear Search on the server for tasks with overlapping keywords. */
async function checkDuplicates() {
  const form = $('#task-form');
  const box = $('#dupes');
  const text = `${form.elements.title.value} ${form.elements.description.value}`.trim();
  if (!form.elements.subject_id.value || text.length < 4) { box.innerHTML = ''; return; }
  try {
    const { similar } = await API.post('request_task.php', {
      action: 'check', subject_id: Number(form.elements.subject_id.value), title: form.elements.title.value, description: form.elements.description.value, ignore_id: task?.id,
    });
    box.innerHTML = similar.length
      ? `<div class="rounded-2xl bg-sun-tint p-4 text-sm"><div class="flex items-center gap-2 font-semibold">${icon('alert', 16)}Possible duplicate</div>
          <div class="mt-1 text-xs text-muted">Matched “${esc(similar[0].keywords.join('”, “'))}” in:</div>
          ${similar.slice(0, 3).map((s) => `<a href="task.html?id=${s.task.id}" class="mt-1 block text-xs font-semibold underline">${esc(s.task.title)} · ${esc(Fmt.due(s.task))}</a>`).join('')}</div>`
      : `<div class="flex items-center gap-2 rounded-2xl bg-mint-tint p-4 text-sm">${icon('check', 16)}<span><b>No similar task found</b><span class="block text-xs text-muted">Checked against every task in this subject.</span></span></div>`;
  } catch { box.innerHTML = ''; }
}
