// Class Schedule — day picker + classes, and the task timeline in four views.
//   Today     -> Linked List order (Insertion Sort): due today, rest of this week, later, date not set
//   Urgent    -> Bubble Sort by time left, only tasks due by the end of tomorrow
//   This week -> tasks due from now until Sunday night
//   Due soon  -> every dated task due after tomorrow (next 7 days, then later)
// Tasks marked as done never disappear or move — they stay in place, checked off.

let data;
let filter = ['today', 'urgent', 'week', 'soon'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'today';
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
      <a href="week.html" class="btn btn-light mt-3 w-full shadow-card">${admin ? 'Edit whole week' : 'See whole week'}</a></section>` : ''}
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
  $('#classes').innerHTML = day && day.classes.length
    ? `<div class="flex items-center justify-between px-1 text-xs text-muted"><span>${day.classes.length} class${day.classes.length === 1 ? '' : 'es'}</span>
        <span>${Fmt.time(day.classes[0].start_time)} – ${Fmt.time(day.classes[day.classes.length - 1].end_time)}</span></div>
       ${day.classes.map((c) => UI.classRowCompact({ ...c, today: isToday })).join('')}`
    : `<div class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm text-muted shadow-card">${icon('calendar', 18)}No classes on ${DAYS_LONG[selectedDow % 7]}</div>`;
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
  const count = (items) => {
    const left = items.filter((t) => !t.done).length;
    return `${left} to do${items.length > left ? ` · ${items.length - left} done` : ''}`;
  };

  if (filter === 'urgent') {
    const items = byWork(data.by_urgency).filter((t) => t.deadline_ts && t.deadline_ts <= b.endTomorrow);
    return `${heading('Urgent · today & tomorrow', count(items))}
      <p class="-mt-1 text-xs text-muted">Most urgent first (Bubble Sort by time left).</p>
      ${list(items, 'Nothing urgent', 'Nothing is due today or tomorrow.')}`;
  }
  if (filter === 'week') {
    const items = dated.filter((t) => t.deadline_ts <= b.endWeek);
    const sunday = new Date(b.endWeek * 1000);
    return `${heading(`Due this week`, count(items))}
      <p class="-mt-1 text-xs text-muted">From today until Sunday, ${Fmt.monthDay(sunday)}.</p>
      ${list(items, 'Nothing due this week')}`;
  }
  if (filter === 'soon') {
    const items = dated.filter((t) => t.deadline_ts > b.endTomorrow);
    const next7 = items.filter((t) => t.deadline_ts <= b.in7);
    const later = items.filter((t) => t.deadline_ts > b.in7);
    const sub = (title, group) => `<div class="flex items-baseline justify-between px-1 pt-1"><h3 class="text-sm font-semibold">${title}</h3><span class="text-xs text-muted">${count(group)}</span></div>${list(group)}`;
    return `${heading('Due soon', count(items))}
      <p class="-mt-1 text-xs text-muted">Every upcoming deadline after tomorrow, nearest first. Today & tomorrow are under Urgent.</p>
      ${items.length ? `${next7.length ? sub('Next 7 days', next7) : ''}${later.length ? sub('Later', later) : ''}` : UI.empty('Nothing due soon', 'No upcoming deadlines after tomorrow.')}`;
  }

  // Today: everything, bucketed so you can see what's next at a glance
  const today = dated.filter((t) => t.deadline_ts <= b.endToday);
  const week = dated.filter((t) => t.deadline_ts > b.endToday && t.deadline_ts <= b.endWeek);
  const later = dated.filter((t) => t.deadline_ts > b.endWeek);
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
