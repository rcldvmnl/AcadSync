// Class Schedule — day picker + classes, and the task timeline in four views.
//   Today     -> Linked List order (Insertion Sort): due today, rest of this week, later, date not set
//   Urgent    -> Bubble Sort by time left, only tasks due by the end of tomorrow
//   This week -> tasks due from now until Sunday night
//   Due soon  -> every dated task due after tomorrow (next 7 days, then later)
// Tasks marked as done never disappear or move — they stay in place, checked off.
// Placement uses Fmt.placeTs(): a date range that is already open ("any day Nov 20 – 23") counts as
// today, not as its last day; lists stay in deadline order.

let data;
let filter = ['today', 'urgent', 'week', 'friday', 'soon'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'today';
let selectedDow;
let workFilter = 'all';   // all | individual | group

(async () => {
  await Shell.init({ active: 'schedule', eyebrow: sectionEyebrow, title: 'Class Schedule' });
  data = await API.get('schedule.php');
  selectedDow = new Date().getDay() || 7;   // 1 = Monday … 7 = Sunday
  render();
})().catch((e) => { if (e.message !== 'no section') $('#content').innerHTML = UI.errorBox(e); });

$$('[data-filter]').forEach((b) => b.addEventListener('click', () => {
  filter = b.dataset.filter;
  history.replaceState(null, '', `#${filter}`);
  render();
}));

function render() {
  $$('[data-filter]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.filter === filter)));
  const admin = data.role === 'admin';
  const showDays = filter !== 'urgent';
  $('#content').className = 'grid gap-6 lg:grid-cols-2 lg:items-start';
  $('#content').innerHTML = `
    ${showDays ? `<section>${dayPicker()}<div id="classes" class="mt-3 space-y-2"></div>
      <a href="week.html" class="btn btn-light mt-3 w-full shadow-card">${admin ? 'Edit whole week' : 'See whole week'}</a>
      ${sidePanel(admin)}</section>` : ''}
    <section class="space-y-3 ${showDays ? '' : 'lg:col-span-2 lg:max-w-2xl'}">${workSwitch()}${groupWorkLink()}${tasksSection()}</section>`;
  if (showDays) renderClasses();
  $$('[data-work]').forEach((b) => b.onclick = () => { workFilter = b.dataset.work; render(); });
  UI.bindDone($('#content'), (id, done) => {
    [data.timeline, data.by_urgency].forEach((list) => list.forEach((t) => { if (t.id === id) t.done = done; }));
    render();
  });
}

/** All / Individual / Group — separates group work from individual tasks. */
function workSwitch() {
  const count = (m) => data.timeline.filter((t) => (m === 'all' || t.work_mode === m) && !t.done).length;
  return `<div class="grid grid-cols-3 rounded-full bg-white p-1 text-sm shadow-card" role="radiogroup" aria-label="Show tasks">
    ${[['all', 'All'], ['individual', 'Individual'], ['group', 'Group']].map(([k, l]) => `
      <button type="button" role="radio" data-work="${k}" aria-checked="${workFilter === k}" class="rounded-full py-2 ${workFilter === k ? 'bg-charcoal text-white' : 'text-muted'}">${l} <span class="text-xs opacity-70">${count(k)}</span></button>`).join('')}
  </div>`;
}

/** Group filter: shortcut to Classmates › Groups (create a group, request to join, answer requests). */
function groupWorkLink() {
  if (workFilter !== 'group') return '';
  const groupTasks = data.timeline.filter((t) => t.work_mode === 'group' && !t.done);
  const needs = groupTasks.filter((t) => !t.my_group).length;
  return `
    <a href="classmates.html#groups" class="flex items-center gap-3 rounded-[1.5rem] bg-charcoal p-3 pl-4 text-white shadow-card transition hover:-translate-y-0.5">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-sun text-charcoal">${icon('users', 18)}</span>
      <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">Open group work</span>
        <span class="block truncate text-xs text-white/70">${needs ? `${needs} task${needs === 1 ? '' : 's'} still need${needs === 1 ? 's' : ''} your group` : 'Create groups, request to join, see members'}</span></span>
      ${icon('arrowRight', 18, 'mr-1 shrink-0')}
    </a>`;
}

const byWork = (items) => items.filter((t) => workFilter === 'all' || t.work_mode === workFilter);

/** The whole week, Monday to Sunday, always in the same place — only the highlight moves. */
function dayPicker() {
  const days = data.week.days;
  const today = Fmt.iso(new Date());
  const selected = days.find((d) => d.dow === selectedDow);
  return `
    <div class="mb-3 flex items-center justify-between">
      <h2 class="text-xl font-bold">${selected && selected.date === today ? 'Today' : DAYS_LONG[selectedDow % 7]}</h2>
      <span class="chip bg-white text-xs">${selected ? esc(Fmt.short(selected.date)) : ''}</span>
    </div>
    <div class="grid grid-cols-7 gap-1.5" role="tablist" aria-label="Day of the week">
      ${days.map((d) => {
        const on = d.dow === selectedDow;
        const isToday = d.date === today;
        return `
        <button class="day-pill ${isToday && !on ? 'ring-2 ring-charcoal ring-inset' : ''}" role="tab" data-dow="${d.dow}" aria-pressed="${on}" aria-selected="${on}" aria-label="${DAYS_LONG[d.dow % 7]} ${Fmt.monthDay(d.date)}${isToday ? ' (today)' : ''}">
          <span class="text-[10px] font-semibold uppercase ${on ? 'text-white/70' : 'text-muted'}">${DAYS[d.dow % 7]}</span>
          <span class="text-lg font-bold">${Fmt.date(d.date).getDate()}</span>
          <span class="h-1 w-1 rounded-full ${d.classes.length ? (on ? 'bg-white/70' : 'bg-lavender') : 'bg-transparent'}"></span>
        </button>`;
      }).join('')}
    </div>`;
}

function renderClasses() {
  $$('[data-dow]').forEach((b) => b.onclick = () => { selectedDow = Number(b.dataset.dow); render(); });
  const day = data.week.days.find((d) => d.dow === selectedDow);
  const isToday = day && day.date === Fmt.iso(new Date());
  const off = day ? day.classes.filter((c) => c.cancelled).length : 0;   // cancelled by a No class announcement
  const on = day ? day.classes.length - off : 0;
  $('#classes').innerHTML = day && day.classes.length
    ? `<div class="flex items-center justify-between px-1 text-xs text-muted"><span>${on ? `${on} class${on === 1 ? '' : 'es'}` : 'No classes'}${off ? ` · ${off} cancelled` : ''}</span>
        <span>${Fmt.time(day.classes[0].start_time)} – ${Fmt.time(day.classes[day.classes.length - 1].end_time)}</span></div>
       ${day.classes.map((c) => UI.classRowCompact({ ...c, today: isToday })).join('')}`
    : `<div class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm text-muted shadow-card">${icon('calendar', 18)}No classes on ${DAYS_LONG[selectedDow % 7]}</div>`;
}

/*
 * Desktop only: fills the space under the day view with Next class, Progress and Subjects.
 * On phones the task list comes right after the day view, so this stays hidden there.
 */
function sidePanel(admin) {
  return `<div class="mt-6 hidden space-y-6 lg:block">${nextClassCard()}${progressCard()}${subjectsCard(admin)}</div>`;
}

/** The next class that hasn't started yet, looking up to a week ahead (classes repeat weekly). */
function nextClass() {
  const now = new Date();
  const mins = now.getHours() * 60 + now.getMinutes();
  const toM = (s) => { const [h, m] = s.split(':').map(Number); return h * 60 + m; };
  for (let k = 0; k <= 7; k++) {
    const date = new Date(now); date.setDate(now.getDate() + k);
    const day = data.week.days.find((d) => d.dow === (date.getDay() || 7));
    // Skip a class a "No class" announcement cancelled on that exact date (this week or next)
    const iso = Fmt.iso(date);
    const off = new Set(data.week.cancelled || []);
    const c = (day ? day.classes : []).find((x) => (k > 0 || toM(x.start_time) > mins) && !off.has(`${iso}|*`) && !off.has(`${iso}|${x.subject_id}`));
    if (c) {
      const [h, m] = c.start_time.split(':').map(Number);
      date.setHours(h, m, 0, 0);
      return { c, date };
    }
  }
  return null;
}

function nextClassCard() {
  const next = nextClass();
  if (!next) return '';
  const { c, date } = next;
  const when = Fmt.isToday(date) ? `Today · ${Fmt.relative(date.getTime() / 1000)}`
    : Fmt.isTomorrow(date) ? 'Tomorrow' : DAYS_LONG[date.getDay()];
  return `
    <div>
      ${heading('Next class', esc(when))}
      <a href="subject.html?id=${c.subject_id}" class="mt-3 flex items-center gap-4 rounded-3xl bg-charcoal p-4 text-white shadow-card transition hover:-translate-y-0.5">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" style="background:${tone(c.color).bar}">${icon('bookOpen', 20, 'text-charcoal')}</span>
        <span class="min-w-0 flex-1 leading-tight">
          <span class="block text-[11px] font-semibold tracking-wide text-white/60">${esc(c.code)}</span>
          <span class="block truncate font-semibold">${esc(c.title)}</span>
          <span class="mt-1 block truncate text-xs text-white/70">${Fmt.time(c.start_time)} – ${Fmt.time(c.end_time)}${c.room ? ` · ${esc(c.room)}` : ''}</span>
        </span>
        ${icon('chevronRight', 18, 'shrink-0 text-white/60')}
      </a>
    </div>`;
}

/** Done vs. to do for the tasks on the schedule (follows the All / Individual / Group switch). */
function progressCard() {
  const items = byWork(data.timeline);
  if (!items.length) return '';
  const done = items.filter((t) => t.done).length;
  const pct = Math.round((done / items.length) * 100);
  const b = bounds();
  const open = items.filter((t) => !t.done);
  const dueWeek = open.filter((t) => t.deadline_ts && Fmt.placeTs(t, b.now) <= b.endWeek).length;
  const next = open.find((t) => t.deadline_ts);
  const stat = (value, label) => `<div class="rounded-2xl bg-canvas px-3 py-2.5"><div class="text-lg font-bold leading-none">${value}</div><div class="mt-1 text-[11px] text-muted">${label}</div></div>`;
  return `
    <div>
      ${heading('Your progress', `${done} of ${items.length} done`)}
      <div class="mt-3 rounded-3xl bg-white p-4 shadow-card">
        <div class="flex items-center justify-between text-sm"><span class="font-semibold">${pct}% complete</span><span class="text-xs text-muted">${open.length} to do</span></div>
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-canvas" role="progressbar" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100" aria-label="Tasks done">
          <div class="h-full rounded-full bg-success transition-all" style="width:${pct}%"></div>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-2">
          ${stat(open.length, 'To do')}${stat(dueWeek, 'Due this week')}${stat(done, 'Done')}
        </div>
        ${next ? `
        <a href="task.html?id=${next.id}" class="mt-3 flex items-center gap-3 rounded-2xl border border-line px-3 py-2.5 transition hover:bg-canvas">
          ${icon('clock', 18, 'shrink-0 text-muted')}
          <span class="min-w-0 flex-1 leading-tight">
            <span class="block text-[11px] text-muted">Next deadline · ${esc(Fmt.relative(next.deadline_ts))}</span>
            <span class="block truncate text-sm font-semibold">${esc(next.title)}</span>
          </span>
          ${icon('chevronRight', 16, 'shrink-0 text-muted')}
        </a>` : ''}
      </div>
    </div>`;
}

/** Every subject with how many unfinished tasks are due in the next 7 days. */
function subjectsCard(admin) {
  const subjects = data.subjects || [];
  if (!subjects.length) {
    return admin ? `<div>${heading('Subjects', '')}<a href="add-subject.html" class="btn btn-yellow mt-3 h-12 w-full">+ Add your first subject</a></div>` : '';
  }
  return `
    <div>
      ${heading('Subjects', plural(subjects.length, 'subject'))}
      <div class="mt-3 overflow-hidden rounded-3xl bg-white shadow-card">
        ${subjects.map((s, i) => `
          <a href="subject.html?id=${s.id}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-canvas ${i ? 'border-t border-line' : ''}">
            <span class="h-8 w-1 shrink-0 rounded-full" style="background:${tone(s.color_tag).bar}"></span>
            <span class="min-w-0 flex-1 leading-tight">
              <span class="block text-[11px] font-semibold tracking-wide text-muted">${esc(s.code)}</span>
              <span class="block truncate text-sm font-semibold">${esc(s.title)}</span>
            </span>
            ${s.due
              ? `<span class="chip shrink-0 ${tone(s.color_tag).chip} text-xs">${s.due} due</span>`
              : `<span class="flex shrink-0 items-center gap-1 text-xs text-success">${icon('check', 14)}Clear</span>`}
          </a>`).join('')}
      </div>
      <p class="mt-2 px-1 text-[11px] text-muted">"Due" counts unfinished tasks in the next 7 days.</p>
    </div>`;
}

/** Time boundaries used by every filter (all in the user's local time). */
function bounds() {
  const now = Date.now() / 1000;
  const endToday = new Date(); endToday.setHours(23, 59, 59, 999);
  const endTomorrow = new Date(endToday); endTomorrow.setDate(endTomorrow.getDate() + 1);
  const endWeek = new Date(endToday); endWeek.setDate(endWeek.getDate() + ((7 - endWeek.getDay()) % 7));   // this Sunday
  const in7 = new Date(endToday); in7.setDate(in7.getDate() + 7);
  const s = (d) => d.getTime() / 1000;
  return { now, endToday: s(endToday), endTomorrow: s(endTomorrow), endWeek: s(endWeek), in7: s(in7) };
}

function tasksSection() {
  const b = bounds();
  const list = (items, emptyTitle, emptyText = '') => (items.length
    ? `<div class="space-y-3">${items.map((t) => UI.taskCard(t, { toggle: true })).join('')}</div>`
    : UI.empty(emptyTitle, emptyText));
  const timeline = byWork(data.timeline);
  const dated = timeline.filter((t) => t.deadline_ts);
  const at = (t) => Fmt.placeTs(t, b.now);   // open ranges count from now, not from their last day
  const count = (items) => {
    const left = items.filter((t) => !t.done).length;
    return `${left} to do${items.length > left ? ` · ${items.length - left} done` : ''}`;
  };

  if (filter === 'urgent') {
    const items = byWork(data.by_urgency).filter((t) => t.deadline_ts && at(t) <= b.endTomorrow);
    return `${heading('Urgent · today & tomorrow', count(items))}
      <p class="-mt-1 text-xs text-muted">Most urgent first (Bubble Sort by time left). Date ranges open today or tomorrow are included.</p>
      ${list(items, 'Nothing urgent', 'Nothing is due or open today or tomorrow.')}`;
  }
  if (filter === 'week') {
    const items = dated.filter((t) => at(t) <= b.endWeek);
    const sunday = new Date(b.endWeek * 1000);
    return `${heading(`Due this week`, count(items))}
      <p class="-mt-1 text-xs text-muted">From today until Sunday, ${Fmt.monthDay(sunday)}.</p>
      ${list(items, 'Nothing due this week')}`;
  }
  if (filter === 'friday') {
    // The server binary-searched the date-sorted timeline for the cut-off (Friday 11:59 PM)
    const d = data.due_by;
    const ids = new Set(d.ids);
    const items = timeline.filter((t) => ids.has(t.id));
    const fri = Fmt.date(d.date);
    return `${heading(`Due by ${DAYS_LONG[fri.getDay()]}`, count(items))}
      <p class="-mt-1 text-xs text-muted">Everything due by ${esc(Fmt.short(fri))}, 11:59 PM. Found with Binary Search: ${plural(d.steps, 'check')} through ${plural(d.searched, 'task')} sorted by deadline.</p>
      ${list(items, `Nothing due by ${DAYS_LONG[fri.getDay()]}`)}`;
  }
  if (filter === 'soon') {
    const items = dated.filter((t) => at(t) > b.endTomorrow);
    const next7 = items.filter((t) => at(t) <= b.in7);
    const later = items.filter((t) => at(t) > b.in7);
    const sub = (title, group) => `<div class="flex items-baseline justify-between px-1 pt-1"><h3 class="text-sm font-semibold">${title}</h3><span class="text-xs text-muted">${count(group)}</span></div>${list(group)}`;
    return `${heading('Due soon', count(items))}
      <p class="-mt-1 text-xs text-muted">Every upcoming deadline after tomorrow, nearest first. Today & tomorrow are under Urgent.</p>
      ${items.length ? `${next7.length ? sub('Next 7 days', next7) : ''}${later.length ? sub('Later', later) : ''}` : UI.empty('Nothing due soon', 'No upcoming deadlines after tomorrow.')}`;
  }

  // Today: everything, bucketed so you can see what's next at a glance
  const today = dated.filter((t) => at(t) <= b.endToday);
  const week = dated.filter((t) => at(t) > b.endToday && at(t) <= b.endWeek);
  const later = dated.filter((t) => at(t) > b.endWeek);
  const unset = timeline.filter((t) => !t.deadline_ts);
  const group = (title, items, empty) => `${heading(title, count(items))}${list(items, empty)}`;

  return `
    ${today.length ? `
      <div class="rounded-[1.75rem] border-2 border-[#F5C2BC] bg-danger-tint p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="flex items-center gap-2 text-xl font-bold"><span class="h-2.5 w-2.5 rounded-full bg-danger"></span>Due today</h2>
          <span class="chip bg-white">${count(today)}</span>
        </div>
        <div class="space-y-3">${today.map((t) => UI.taskCard(t, { toggle: true })).join('')}</div>
      </div>` : `<div class="flex items-center gap-3 rounded-2xl bg-mint-tint px-4 py-3 text-sm">${icon('check', 18, 'text-success')}Nothing due today</div>`}
    ${group('Later this week', week, 'Nothing else due this week')}
    ${later.length ? group('Coming up', later) : ''}
    ${unset.length ? group('Date not set yet', unset) : ''}`;
}

function heading(title, sub) {
  return `<div class="flex items-baseline justify-between pt-1"><h2 class="text-xl font-bold">${title}</h2><span class="text-xs text-muted">${sub}</span></div>`;
}
