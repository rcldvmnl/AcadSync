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
const CAT_ON = { Reporting: 'bg-lavender-tint', Quiz: 'bg-lavender-tint', Exam: 'bg-peach-tint', Activity: 'bg-[#EDEDF2]', Project: 'bg-sun-tint' };
const OTHER_SUGGESTIONS = ['Seatwork', 'Recitation', 'Laboratory', 'Film viewing', 'Field trip', 'Presentation', 'Research'];

let picker, times, lengths, mode, task = null, sectionSize = 0;
let category = 'Reporting';
let otherLabel = '';
// Group work: how the prof set it up (UI.GROUP_SETUPS) — number of groups, members per group, both,
// or the prof's own list of groups (plan = [{ name, member_ids }]).
let work = { mode: 'individual', setup: 'count', size: 4, count: 5, plan: [], planLocked: false };
let roster = null;   // classmates, loaded when the prof's own list is being entered
let submit = { modes: [], detail: '' };   // how the task is passed (see SUBMIT_MODES in ui.js)

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
      <span class="sparkle" aria-hidden="true"></span>
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
      ${full ? '<div class="text-xs">Edit or withdraw one in <a href="my-tasks.html#pending" class="font-semibold underline">My tasks</a>, or wait for the admin.</div>' : ''}</div>
  </div>`;
}

function render(subjects, markers, mine) {
  const presetSubject = task?.subject_id || params.get('subject');
  if (task) {
    if (CATEGORIES.includes(task.category)) category = task.category;
    else { category = 'Other'; otherLabel = task.category; }
    if (task.work_mode === 'group') {
      work = {
        mode: 'group', setup: task.group_mode || (task.group_count ? 'count' : 'size'),
        size: task.group_size || task.group_max || 4, count: task.group_count || 5,
        plan: (task.group_plan || []).map((g) => ({ name: g.name, member_ids: [...(g.member_ids || [])] })),
        // A published task's assigned groups already exist: officers change them in Groups, not here
        planLocked: task.status === 'approved' && task.group_mode === 'assigned',
      };
    }
    submit = Submission.parse(task.location);
  }
  const full = mine && mine.pending >= mine.limit && mode !== 'fix';
  const nowLabel = task ? (task.deadline_ts ? `Now: ${Fmt.monthDay(task.deadline_end || task.deadline)}${Fmt.dueTime(task)}` : 'Now: not set') : '';

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
          <div class="flex items-center justify-between"><span class="text-xs text-muted">Deadline</span>${task && mode !== 'fix' ? `<span class="chip bg-sun-tint">${esc(nowLabel)}</span>` : ''}</div>
          <div id="picker"></div>
          <div id="time-wrap"><div class="mb-1.5 text-xs text-muted">Due time</div><div id="times"></div>
            <div class="mb-1.5 mt-3 text-xs text-muted">How long</div><div id="lengths"></div></div>
        </div>
      </div>

      <div class="space-y-5">
        <div class="space-y-4 rounded-[1.75rem] bg-white p-5 shadow-card">
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Title</span>
            <span class="field"><input name="title" maxlength="120" placeholder="e.g. Reporting — Chapter 3" value="${esc(task?.title || '')}" required></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Description ${mode === 'change' ? '· add details or what should change' : ''}</span>
            <span class="field area"><textarea name="description" rows="5" maxlength="2000" placeholder="Add instructions or notes… Start a line with “- ” to add a checklist item.">${esc(task?.description || '')}</textarea></span></label>
          <div>
            <div class="mb-1.5 text-xs text-muted">How to submit <span class="text-muted">· pick one or more</span></div>
            <div class="grid grid-cols-3 gap-2" id="submit-modes" role="group" aria-label="How to submit"></div>
            <label class="mt-2 block"><span class="sr-only">Submission details</span>
              <span class="field"><span id="submit-icon">${icon('mapPin', 18)}</span><input name="location" list="submit-suggestions" maxlength="120" value="${esc(submit.detail)}"></span>
              <datalist id="submit-suggestions"></datalist></label>
          </div>
          <div id="dupes" aria-live="polite"></div>
        </div>
        <div class="rounded-[1.75rem] bg-white p-5 shadow-card">
          <h2 class="mb-3 text-base font-semibold">Individual or group work?</h2>
          <div class="grid grid-cols-2 rounded-full bg-canvas p-1 text-sm" role="radiogroup" aria-label="Individual or group work" id="work-mode"></div>
          <div id="group-size" class="mt-4"></div>
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
  renderSubmit();
  picker = createDatePicker($('#picker'), {
    type: task?.deadline_type || 'exact',
    start: task?.deadline || null,
    end: task?.deadline_end || null,
    markers,
    onChange: (v) => { $('#time-wrap').hidden = v.type === 'unset'; },
  });
  $('#time-wrap').hidden = (task?.deadline_type || 'exact') === 'unset';
  times = createTimeChips($('#times'), { value: task?.deadline_time || '23:59' });
  lengths = createLengthChips($('#lengths'), { start: () => times.value(), until: task?.deadline_until || null });
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
    if (when.type !== 'unset' && lengths.error()) throw new Error(lengths.error());
    const cat = category === 'Other' ? (d.other_label || '').trim() : category;
    if (category === 'Other' && cat.length < 2) throw new Error('Type what kind of task it is.');
    const body = {
      action: mode === 'fix' ? 'update_request' : 'submit',
      request_id: mode === 'fix' ? task.id : undefined,
      subject_id: Number(d.subject_id),
      category: cat,
      title: d.title,
      description: d.description,
      location: Submission.format(submit.modes, d.location),
      work_mode: work.mode,
      ...groupFields(),
      deadline_type: when.type,
      deadline: when.start,
      deadline_end: when.type === 'range' ? when.end : null,
      deadline_time: when.type === 'unset' ? null : times.value(),
      deadline_until: when.type === 'unset' ? null : lengths.value(),
      target_task_id: mode === 'change' || mode === 'edit' ? task.id : null,
    };
    const res = await API.post('request_task.php', body);
    if (res.status === 'approved') {
      location.href = `task.html?id=${res.id}`;
      return;
    }
    if (mode === 'fix') {
      location.href = 'my-tasks.html#pending';
      return;
    }
    UI.sheet(`
      <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-mint-tint text-success">${icon('check', 24)}</span>
      <h2 class="text-2xl font-bold">Request sent</h2>
      <p class="mt-2 text-sm text-muted">It’s <b class="text-charcoal">#${res.queue_position}</b> in the approval queue. Your admin reviews requests in the order they arrive.</p>
      <p class="mt-2 text-xs text-muted">You now have ${res.pending} of ${res.limit} requests waiting.</p>
      ${res.similar?.length ? `<p class="mt-3 rounded-2xl bg-sun-tint px-4 py-3 text-xs">Heads-up: it looks similar to “${esc(res.similar[0].task.title)}”.</p>` : ''}
      <a href="my-tasks.html#pending" class="btn btn-dark mt-6 w-full">See my requests</a>
      <a href="schedule.html" class="mt-3 block py-2 text-center text-sm font-semibold">Back to schedule</a>`, { label: 'Request sent' });
  });
}

/** In class / Hardcopy / Softcopy / Online — toggle chips; the details box hint follows the last pick. */
function renderSubmit() {
  $('#submit-modes').innerHTML = Object.entries(SUBMIT_MODES).map(([k, m]) => {
    const on = submit.modes.includes(k);
    return `<button type="button" data-submit="${k}" aria-pressed="${on}" class="flex items-center justify-center gap-1.5 rounded-full px-3 py-2.5 text-sm font-medium transition ${on ? 'bg-charcoal text-white' : 'bg-canvas'}">${icon(on ? 'check' : m.icon, 16)}${k}</button>`;
  }).join('');
  const last = submit.modes[submit.modes.length - 1];
  const input = $('#task-form').elements.location;
  input.placeholder = last ? SUBMIT_MODES[last].hint : 'Details, e.g. Room 204 or Google Classroom';
  $('#submit-icon').innerHTML = icon(last ? SUBMIT_MODES[last].icon : 'mapPin', 18);
  $('#submit-suggestions').innerHTML = submit.modes.includes('Softcopy / Online')
    ? ONLINE_SUGGESTIONS.map((o) => `<option>${o}</option>`).join('') : '';
  $$('[data-submit]').forEach((b) => b.onclick = () => {
    const k = b.dataset.submit;
    submit.modes = submit.modes.includes(k) ? submit.modes.filter((x) => x !== k) : [...submit.modes, k];
    submit.modes.sort((a, z) => Object.keys(SUBMIT_MODES).indexOf(a) - Object.keys(SUBMIT_MODES).indexOf(z));
    renderSubmit();
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

/** The group fields sent to the server for the chosen setup (see GroupRule on the server). */
function groupFields() {
  if (work.mode !== 'group') return {};
  const f = { group_mode: work.setup };
  if (work.setup === 'count' || work.setup === 'size_count') f.group_count = work.count;
  if (work.setup === 'size' || work.setup === 'size_count') f.group_size = work.size;
  if (work.setup === 'assigned' && !work.planLocked) {
    f.group_plan = work.plan.map((g, i) => ({ name: g.name.trim() || `Group ${i + 1}`, member_ids: g.member_ids }));
  }
  return f;
}

/**
 * The same arithmetic as the server's GroupRule subclasses, as a preview for this section's size.
 * Returns { text, warn } in plain words.
 */
function groupPreview(n) {
  const p = (k, one, many = `${one}s`) => `${k} ${k === 1 ? one : many}`;
  if (work.setup === 'count') {
    const N = work.count;
    if (n < N) return { text: `Only ${p(n, 'classmate')} for ${N} groups, so some groups will stay empty.`, warn: true };
    const base = Math.floor(n / N);
    const extra = n % N;
    const parts = [extra ? `${p(extra, 'group')} of ${base + 1}` : '', N - extra ? `${p(N - extra, 'group')} of ${base}` : ''].filter(Boolean);
    return {
      text: `${n} classmates in ${N} groups → <b>${parts.join(' and ')}</b>. All ${N} groups appear as soon as it’s posted, and classmates join one.${base < 2 ? ' Some groups would have just 1 person: check the number of groups.' : ''}`,
      warn: base < 2,
    };
  }
  if (work.setup === 'size') {
    const S = work.size;
    const full = Math.floor(n / S);
    const left = n % S;
    return {
      text: `${n} classmates, ${S} per group → <b>${p(full, 'full group')}</b>${left ? `, and ${p(left, 'classmate')} left over: they can join a group as its ${S + 1}${S + 1 === 2 ? 'nd' : S + 1 === 3 ? 'rd' : 'th'} member, or make a smaller group` : ''}. Classmates make the groups themselves.`,
    };
  }
  if (work.setup === 'size_count') {
    const S = work.size;
    const N = work.count;
    const seats = S * N;
    const over = n - seats;
    let text = `${N} groups of ${S} = <b>${seats} seats</b> for ${n} classmates.`;
    let warn = false;
    if (over > N) { text += ` Even with 1 extra in every group, ${p(over - N, 'classmate')} won’t fit. Add groups or members per group.`; warn = true; }
    else if (over > 0) text += ` So <b>${p(over, 'group')}</b> can take 1 extra member (${S + 1}).`;
    else if (over < 0) text += ` Some groups will have fewer than ${S}.`;
    return { text: `${text} All ${N} groups appear as soon as it’s posted.`, warn };
  }
  return { text: '' };
}

function renderWork() {
  $('#work-mode').innerHTML = [['individual', 'Individual', 'user'], ['group', 'Group work', 'users']].map(([k, l, ic]) => `
    <button type="button" role="radio" data-work="${k}" aria-checked="${work.mode === k}" class="flex items-center justify-center gap-2 rounded-full py-2.5 ${work.mode === k ? 'bg-charcoal text-white' : 'text-muted'}">${icon(ic, 16)}${l}</button>`).join('');
  $$('[data-work]').forEach((b) => b.onclick = () => { work.mode = b.dataset.work; renderWork(); });

  const box = $('#group-size');
  if (work.mode !== 'group') { box.innerHTML = ''; return; }

  const stepper = (key, label, min, max) => `
    <div class="flex-1 rounded-2xl bg-canvas p-3">
      <div class="text-[11px] text-muted">${label}</div>
      <div class="mt-1 flex items-center justify-between">
        <button type="button" data-step="${key}" data-d="-1" data-min="${min}" data-max="${max}" class="grid h-9 w-9 place-items-center rounded-full bg-white text-lg" aria-label="Fewer: ${label}">−</button>
        <span class="text-xl font-bold" aria-live="polite">${work[key]}</span>
        <button type="button" data-step="${key}" data-d="1" data-min="${min}" data-max="${max}" class="grid h-9 w-9 place-items-center rounded-full bg-white text-lg" aria-label="More: ${label}">+</button>
      </div>
    </div>`;
  const options = Object.entries(UI.GROUP_SETUPS).map(([k, s]) => `
    <button type="button" role="radio" data-setup="${k}" aria-checked="${work.setup === k}"
      class="flex items-start gap-2.5 rounded-2xl p-3 text-left transition ${work.setup === k ? 'bg-charcoal text-white' : 'bg-canvas hover:bg-line'}">
      <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full ${work.setup === k ? 'bg-white/15' : 'bg-white'}">${icon(s.icon, 16)}</span>
      <span class="min-w-0"><span class="block text-sm font-semibold leading-tight">${s.title}</span>
        <span class="mt-0.5 block text-[11px] leading-snug ${work.setup === k ? 'text-white/70' : 'text-muted'}">${s.hint}</span></span>
    </button>`).join('');

  let inputs = '';
  if (work.setup === 'count') inputs = stepper('count', 'Number of groups', 1, 50);
  if (work.setup === 'size') inputs = stepper('size', 'Members per group', 2, 20);
  if (work.setup === 'size_count') inputs = `<div class="flex gap-2">${stepper('count', 'Number of groups', 1, 50)}${stepper('size', 'Members per group', 2, 20)}</div>`;
  const preview = work.setup === 'assigned' ? null : groupPreview(sectionSize);

  box.innerHTML = `
    <div class="mb-1.5 text-xs text-muted">How did the prof set up the groups?</div>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" role="radiogroup" aria-label="How the groups are formed">${options}</div>
    ${inputs ? `<div class="mt-3">${inputs}</div>` : ''}
    ${preview ? `<p class="mt-3 rounded-2xl ${preview.warn ? 'bg-danger-tint' : 'bg-lavender-tint'} px-3 py-2.5 text-xs leading-relaxed">${preview.text}</p>` : ''}
    ${work.setup === 'assigned' ? '<div id="plan-builder" class="mt-3"></div>' : ''}`;

  $$('[data-setup]', box).forEach((b) => b.onclick = () => { work.setup = b.dataset.setup; renderWork(); });
  $$('[data-step]', box).forEach((b) => b.onclick = () => {
    const key = b.dataset.step;
    work[key] = Math.min(Number(b.dataset.max), Math.max(Number(b.dataset.min), work[key] + Number(b.dataset.d)));
    renderWork();
  });
  if (work.setup === 'assigned') renderPlan();
}

/** The prof's own list: each group with its members. Every classmate goes in one group. */
async function renderPlan() {
  const box = $('#plan-builder');
  if (!box) return;
  if (work.planLocked) {
    box.innerHTML = `<p class="rounded-2xl bg-sun-tint px-3 py-2.5 text-xs">These groups are already made. Officers move people from the task’s <b>Groups</b> section.</p>`;
    return;
  }
  if (!roster) {
    box.innerHTML = UI.skeleton(1, 'h-24');
    try {
      roster = (await API.get('classmates.php')).members.map((m) => ({ id: m.id, name: m.name, student_id: m.student_id }));
    } catch (e) { box.innerHTML = UI.errorBox(e); return; }
  }
  if (!work.plan.length) work.plan = [{ name: 'Group 1', member_ids: [] }, { name: 'Group 2', member_ids: [] }];
  const nameOf = (id) => roster.find((m) => m.id === id)?.name || 'Former member';
  const placed = new Set(work.plan.flatMap((g) => g.member_ids));
  const left = roster.filter((m) => !placed.has(m.id));

  box.innerHTML = `
    <div class="space-y-2">
      ${work.plan.map((g, i) => `
        <div class="rounded-2xl bg-canvas p-3">
          <div class="flex items-center gap-2">
            <span class="field h-10 flex-1 bg-white"><input data-plan-name="${i}" value="${esc(g.name)}" maxlength="40" aria-label="Name of group ${i + 1}" placeholder="Group ${i + 1}"></span>
            <button type="button" data-plan-remove="${i}" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white" aria-label="Remove ${esc(g.name || `group ${i + 1}`)}">${icon('x', 16)}</button>
          </div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            ${g.member_ids.map((id) => `<span class="chip bg-white">${esc(nameOf(id))}<button type="button" data-plan-out="${i}:${id}" class="-mr-1 ml-0.5 text-muted" aria-label="Take ${esc(nameOf(id))} out">${icon('x', 12)}</button></span>`).join('')}
            <button type="button" data-plan-add="${i}" class="chip bg-charcoal text-white" ${left.length ? '' : 'disabled'}>${icon('plus', 12)}Add members</button>
          </div>
        </div>`).join('')}
      <button type="button" data-plan-new class="btn btn-light h-11 w-full border border-line text-sm">${icon('plus', 16)}Add a group</button>
      <p class="rounded-2xl ${left.length ? 'bg-sun-tint' : 'bg-mint-tint'} px-3 py-2 text-xs">${left.length
        ? `<b>${left.length} of ${roster.length}</b> classmates aren’t in a group yet. You can post anyway; officers can add them later.`
        : `Everyone is in one of the ${work.plan.length} groups.`}</p>
    </div>`;

  $$('[data-plan-name]', box).forEach((inp) => inp.oninput = () => { work.plan[Number(inp.dataset.planName)].name = inp.value; });
  $$('[data-plan-remove]', box).forEach((b) => b.onclick = () => { work.plan.splice(Number(b.dataset.planRemove), 1); renderPlan(); });
  $$('[data-plan-out]', box).forEach((b) => b.onclick = () => {
    const [i, id] = b.dataset.planOut.split(':').map(Number);
    work.plan[i].member_ids = work.plan[i].member_ids.filter((x) => x !== id);
    renderPlan();
  });
  $('[data-plan-new]', box).onclick = () => { work.plan.push({ name: `Group ${work.plan.length + 1}`, member_ids: [] }); renderPlan(); };
  $$('[data-plan-add]', box).forEach((b) => b.onclick = () => pickForPlan(Number(b.dataset.planAdd), left));
}

/** Pick classmates (not yet in any group) for group i of the prof's list. */
function pickForPlan(i, left) {
  UI.sheet(`
    <form>
      <h2 class="text-2xl font-bold">Add to ${esc(work.plan[i].name || `Group ${i + 1}`)}</h2>
      <p class="mt-1 text-sm text-muted">Only classmates who aren’t in another group are listed.</p>
      <label class="mt-3 block"><span class="field">${icon('search', 16)}<input data-filter placeholder="Search name or student ID" aria-label="Search classmates"></span></label>
      <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
        ${left.map((m) => `<label data-row="${esc(`${m.name} ${m.student_id}`.toLowerCase())}" class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-canvas">
          <input type="checkbox" name="m" value="${m.id}" class="h-5 w-5 shrink-0 accent-charcoal">
          <span class="min-w-0 flex-1"><span class="block text-sm font-medium">${esc(m.name)}</span><span class="block text-xs text-muted">${esc(m.student_id)}</span></span></label>`).join('')}
      </div>
      <button type="submit" class="btn btn-dark mt-5 w-full">Add to group</button>
    </form>`, {
    label: 'Add members',
    onMount(el, close) {
      const form = $('form', el);
      $('[data-filter]', el).oninput = (e) => {
        const q = e.target.value.trim().toLowerCase();
        $$('[data-row]', el).forEach((r) => { r.hidden = q && !r.dataset.row.includes(q); });
      };
      form.onsubmit = (e) => {
        e.preventDefault();
        const ids = $$('input[name=m]:checked', form).map((c) => Number(c.value));
        work.plan[i].member_ids.push(...ids);
        close();
        renderPlan();
      };
    },
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
