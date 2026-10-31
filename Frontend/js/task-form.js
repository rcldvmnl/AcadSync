// One form, five modes:
//   student                    -> "Request a Task"      (ENQUEUED to the admin approval queue)
//   student ?mode=change&id=   -> "Request Changes"     (edit request for a published task, also queued)
//   student ?request=          -> "Edit My Request"     (fix your own request while it is still waiting)
//   admin                      -> "Add Task"            (published right away)
//   admin ?id=                 -> "Edit Task"           (applied right away)

const params = new URLSearchParams(location.search);
const editId = params.get('id');
const requestId = params.get('request');
const CATEGORIES = ['Reporting', 'Quiz', 'Exam', 'Activity', 'Project'];
const CAT_ON = { Reporting: 'bg-lavender-tint', Quiz: 'bg-lavender-tint', Exam: 'bg-peach-tint', Activity: 'bg-mint-tint', Project: 'bg-sun-tint' };
const OTHER_SUGGESTIONS = ['Seatwork', 'Recitation', 'Laboratory', 'Film viewing', 'Field trip', 'Presentation', 'Research'];

let picker, times, mode, task = null, sectionSize = 0;
let category = 'Reporting';
let otherLabel = '';
let work = { mode: 'individual', min: 3, max: 4 };

(async () => {
  const me = await API.get('auth.php');
  const admin = me.user.role === 'admin';
  if (requestId) task = (await API.get('tasks.php', { id: requestId })).task;
  else if (editId) task = (await API.get('tasks.php', { id: editId })).task;
  mode = requestId ? 'fix' : admin ? (task ? 'edit' : 'add') : (task ? 'change' : 'request');
  if (mode === 'fix' && task.status !== 'pending') {
    throw new Error('The admin already reviewed this request, so it can’t be edited anymore.');
  }

  const heads = {
    request: ['Goes to the admin approval queue', 'Request a Task'],
    change: [task && `${task.title} · ${task.subject_code}`, 'Request Changes'],
    fix: ['Still waiting in the queue', 'Edit My Request'],
    add: ['Admin · publishes right away', 'Add Task'],
    edit: [task && `${task.title} · ${task.subject_code}`, 'Edit Task'],
  };
  await Shell.init({ active: mode === 'request' || mode === 'add' ? 'add' : 'schedule', eyebrow: heads[mode][0], title: heads[mode][1] });
  document.title = `AcadSync · ${heads[mode][1]}`;
  sectionSize = Shell.me.section.members;

  const [{ subjects }, sched, mine] = await Promise.all([
    API.get('subjects.php'),
    API.get('schedule.php'),
    admin ? Promise.resolve(null) : API.get('tasks.php', { mine: 1 }),
  ]);
  const markers = new Set(sched.timeline.filter((t) => t.deadline).map((t) => t.deadline_end || t.deadline));
  if (!subjects.length) {
    $('#main').innerHTML = `${UI.empty('No subjects yet', admin ? 'Add a subject first, then you can add its tasks.' : 'Ask your class officer to add the section’s subjects.', 'bookOpen')}
      ${admin ? '<a href="add-subject.html" class="btn btn-dark mt-4 w-full">Add a subject</a>' : ''}`;
    return;
  }
  render(subjects, markers, mine);
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

function banner() {
  if (mode === 'change' || mode === 'fix') {
    const [title, sub] = mode === 'change'
      ? ['Changes go to the admin queue', 'Edit anything below — title, details, deadline. The task stays as-is until the admin approves.']
      : ['Editing keeps your place in line', 'Fix anything while the admin hasn’t reviewed it yet.'];
    return `<div class="flex gap-3 rounded-[1.5rem] bg-lavender-tint p-4">${icon('info', 18, 'mt-0.5 shrink-0')}
      <div><div class="text-sm font-semibold">${title}</div><div class="text-xs text-muted">${sub}</div></div></div>`;
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

/** "2 of 3 requests waiting" — students can have at most 3 requests in the queue. */
function limitNotice(mine) {
  if (!mine || mode === 'fix') return '';
  const full = mine.pending >= mine.limit;
  return `<div class="flex items-center gap-3 rounded-[1.5rem] ${full ? 'bg-danger-tint' : 'bg-white shadow-card'} p-4">
    <div class="flex gap-1">${Array.from({ length: mine.limit }, (_, i) => `<span class="h-2.5 w-6 rounded-full ${i < mine.pending ? 'bg-charcoal' : 'bg-line'}"></span>`).join('')}</div>
    <div class="flex-1 text-sm"><b>${mine.pending} of ${mine.limit}</b> requests waiting
      ${full ? '<div class="text-xs">Edit or withdraw one in <a href="account.html#requests" class="font-semibold underline">My requests</a>, or wait for the admin.</div>' : ''}</div>
  </div>`;
}

function render(subjects, markers, mine) {
  const presetSubject = task?.subject_id || params.get('subject');
  if (task) {
    if (CATEGORIES.includes(task.category)) category = task.category;
    else { category = 'Other'; otherLabel = task.category; }
    if (task.work_mode === 'group') work = { mode: 'group', min: task.group_min, max: task.group_max };
  }
  const full = mine && mine.pending >= mine.limit && mode !== 'fix';
  const nowLabel = task ? (task.deadline_ts ? `Now: ${Fmt.monthDay(task.deadline_end || task.deadline)}${task.deadline_time ? ` · ${Fmt.time(task.deadline_time)}` : ''}` : 'Now: not set') : '';

  $('#main').innerHTML = `
    <form id="task-form" class="grid gap-5 lg:grid-cols-2 lg:items-start" novalidate>
      <div class="space-y-5">
        ${banner()}
        ${limitNotice(mine)}
        <div class="space-y-4 rounded-[1.75rem] bg-white p-5 shadow-card">
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Subject</span>
            <span class="field"><select name="subject_id" required>
              <option value="">Pick a subject</option>
              ${subjects.map((s) => `<option value="${s.id}" ${String(s.id) === String(presetSubject) ? 'selected' : ''}>${esc(s.code)} · ${esc(s.title)}</option>`).join('')}
            </select>${icon('chevronDown', 18)}</span></label>
          <div>
            <div class="mb-1.5 text-xs text-muted">Category</div>
            <div class="flex flex-wrap gap-2" id="cats"></div>
            <label id="other-wrap" class="mt-2 block" hidden><span class="field">${icon('edit', 16)}
              <input name="other_label" list="other-suggestions" maxlength="30" placeholder="What kind of task? e.g. Seatwork" value="${esc(otherLabel)}"></span>
              <datalist id="other-suggestions">${OTHER_SUGGESTIONS.map((o) => `<option>${o}</option>`).join('')}</datalist></label>
          </div>
          <div>
            <div class="mb-1.5 text-xs text-muted">Individual or group work?</div>
            <div class="grid grid-cols-2 rounded-full bg-canvas p-1 text-sm" role="radiogroup" id="work-mode"></div>
            <div id="group-size" class="mt-3"></div>
          </div>
          <div class="flex items-center justify-between"><span class="text-xs text-muted">Deadline</span>${task && mode !== 'fix' ? `<span class="chip bg-sun-tint">${esc(nowLabel)}</span>` : ''}</div>
          <div id="picker"></div>
          <div id="time-wrap"><div class="mb-1.5 text-xs text-muted">Due time</div><div id="times"></div></div>
        </div>
      </div>

      <div class="space-y-5">
        <div class="space-y-4 rounded-[1.75rem] bg-white p-5 shadow-card">
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Title</span>
            <span class="field"><input name="title" maxlength="120" placeholder="e.g. Reporting — Chapter 3" value="${esc(task?.title || '')}" required></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Description ${mode === 'change' ? '· add details or what should change' : ''}</span>
            <span class="field area"><textarea name="description" rows="5" maxlength="2000" placeholder="Add instructions or notes… Start a line with “- ” to add a checklist item.">${esc(task?.description || '')}</textarea></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Where</span>
            <span class="field">${icon('mapPin', 18)}<input name="location" placeholder="In class · Room 204" value="${esc(task?.location || '')}"></span></label>
          <div id="dupes" aria-live="polite"></div>
        </div>
        <p data-error class="rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <div class="grid grid-cols-2 gap-3">
          <button type="button" id="cancel" class="btn btn-light shadow-card">Cancel</button>
          <button type="submit" class="btn btn-dark" ${full ? 'disabled' : ''}>${{ request: 'Send to queue', change: 'Send request', fix: 'Save request', add: 'Publish task', edit: 'Save changes' }[mode]}</button>
        </div>
      </div>
    </form>`;

  renderCats();
  renderWork();
  picker = createDatePicker($('#picker'), {
    type: task?.deadline_type || 'exact',
    start: task?.deadline || null,
    end: task?.deadline_end || null,
    markers,
    onChange: (v) => { $('#time-wrap').hidden = v.type === 'unset'; },
  });
  $('#time-wrap').hidden = (task?.deadline_type || 'exact') === 'unset';
  times = createTimeChips($('#times'), { value: task?.deadline_time || '23:59' });
  $('#cancel').onclick = () => history.back();

  const form = $('#task-form');
  if (mode !== 'change' && mode !== 'edit') {
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
    const cat = category === 'Other' ? (d.other_label || '').trim() : category;
    if (category === 'Other' && cat.length < 2) throw new Error('Type what kind of task it is.');
    const body = {
      action: mode === 'fix' ? 'update_request' : 'submit',
      request_id: mode === 'fix' ? task.id : undefined,
      subject_id: Number(d.subject_id),
      category: cat,
      title: d.title,
      description: d.description,
      location: d.location,
      work_mode: work.mode,
      group_min: work.mode === 'group' ? work.min : null,
      group_max: work.mode === 'group' ? work.max : null,
      deadline_type: when.type,
      deadline: when.start,
      deadline_end: when.type === 'range' ? when.end : null,
      deadline_time: when.type === 'unset' ? null : times.value(),
      target_task_id: mode === 'change' || mode === 'edit' ? task.id : null,
    };
    const res = await API.post('request_task.php', body);
    if (res.status === 'approved') {
      location.href = `task.html?id=${res.id}`;
      return;
    }
    if (mode === 'fix') {
      location.href = 'account.html#requests';
      return;
    }
    UI.sheet(`
      <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-mint-tint text-success">${icon('check', 24)}</span>
      <h2 class="text-2xl font-bold">Request sent</h2>
      <p class="mt-2 text-sm text-muted">It’s <b class="text-charcoal">#${res.queue_position}</b> in the approval queue. Your admin reviews requests in the order they arrive.</p>
      <p class="mt-2 text-xs text-muted">You now have ${res.pending} of ${res.limit} requests waiting.</p>
      ${res.similar?.length ? `<p class="mt-3 rounded-2xl bg-sun-tint px-4 py-3 text-xs">Heads-up: it looks similar to “${esc(res.similar[0].task.title)}”.</p>` : ''}
      <a href="account.html#requests" class="btn btn-dark mt-6 w-full">See my requests</a>
      <a href="schedule.html" class="mt-3 block py-2 text-center text-sm font-semibold">Back to schedule</a>`, { label: 'Request sent' });
  });
}

function renderCats() {
  $('#cats').innerHTML = [...CATEGORIES, 'Other'].map((c) => `<button type="button" data-cat="${c}" aria-pressed="${c === category}" class="chip px-3.5 py-2 text-sm ${c === category ? 'bg-charcoal text-white' : (CAT_ON[c] || 'bg-canvas')}">${c}</button>`).join('');
  $('#other-wrap').hidden = category !== 'Other';
  $$('[data-cat]').forEach((b) => b.onclick = () => {
    category = b.dataset.cat;
    renderCats();
    if (category === 'Other') $('#task-form').elements.other_label.focus();
  });
}

/** Mirrors Algorithms::planGroupSizes on the server: fewest groups within max, spread evenly. */
function planGroups(n, min, max) {
  if (n <= 0) return [];
  const g = Math.ceil(n / max);
  const base = Math.floor(n / g);
  return Array.from({ length: g }, (_, i) => base + (i < n % g ? 1 : 0));
}

function renderWork() {
  $('#work-mode').innerHTML = [['individual', 'Individual', 'user'], ['group', 'Group work', 'users']].map(([k, l, ic]) => `
    <button type="button" role="radio" data-work="${k}" aria-checked="${work.mode === k}" class="flex items-center justify-center gap-2 rounded-full py-2.5 ${work.mode === k ? 'bg-charcoal text-white' : 'text-muted'}">${icon(ic, 16)}${l}</button>`).join('');
  $$('[data-work]').forEach((b) => b.onclick = () => { work.mode = b.dataset.work; renderWork(); });

  const box = $('#group-size');
  if (work.mode !== 'group') { box.innerHTML = ''; return; }
  const stepper = (key, label) => `
    <div class="flex-1 rounded-2xl bg-canvas p-3">
      <div class="text-[11px] text-muted">${label}</div>
      <div class="mt-1 flex items-center justify-between">
        <button type="button" data-step="${key}" data-d="-1" class="grid h-8 w-8 place-items-center rounded-full bg-white text-lg" aria-label="Fewer">−</button>
        <span class="text-xl font-bold" aria-live="polite">${work[key]}</span>
        <button type="button" data-step="${key}" data-d="1" class="grid h-8 w-8 place-items-center rounded-full bg-white text-lg" aria-label="More">+</button>
      </div>
    </div>`;
  const sizes = planGroups(sectionSize, work.min, work.max);
  const small = sizes.filter((s) => s < work.min).length;
  const summary = {};
  sizes.forEach((s) => { summary[s] = (summary[s] || 0) + 1; });
  box.innerHTML = `
    <div class="mb-1.5 text-xs text-muted">Members per group (a range is fine when the class doesn’t divide evenly)</div>
    <div class="flex items-center gap-2">${stepper('min', 'At least')}<span class="text-muted">to</span>${stepper('max', 'At most')}</div>
    <p class="mt-2 rounded-2xl bg-lavender-tint px-3 py-2 text-xs">
      ${sectionSize} members → about <b>${sizes.length} groups</b>
      (${Object.entries(summary).sort((a, b) => b[0] - a[0]).map(([s, n]) => `${n} of ${s}`).join(', ')})${small ? ` · ${small} group${small === 1 ? '' : 's'} would be under ${work.min}` : ''}
    </p>`;
  $$('[data-step]', box).forEach((b) => b.onclick = () => {
    const key = b.dataset.step;
    work[key] = Math.min(20, Math.max(2, work[key] + Number(b.dataset.d)));
    if (work.min > work.max) { if (key === 'min') work.max = work.min; else work.min = work.max; }
    renderWork();
  });
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
