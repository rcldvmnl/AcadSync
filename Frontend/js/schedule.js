// Class Schedule — day picker + classes, and the task timeline in four views.
//   Today     -> Linked List order (Insertion Sort), split into "due today" + upcoming
//   Urgent    -> Bubble Sort by time left
//   This week -> tasks due before Sunday
//   Due soon  -> tasks due in the next 3 days

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
    ${showDays ? `<section>${dayPicker()}<div id="classes" class="mt-4 space-y-3"></div>
      <a href="week.html" class="btn btn-light mt-3 w-full shadow-card">${admin ? 'Edit whole week' : 'See whole week'}</a></section>` : ''}
    <section class="space-y-3 ${showDays ? '' : 'lg:col-span-2 lg:max-w-2xl'}">${workSwitch()}${tasksSection()}</section>`;
  if (showDays) renderClasses();
  $$('[data-work]').forEach((b) => b.onclick = () => { workFilter = b.dataset.work; render(); });
}

/** All / Individual / Group — separates group work from individual tasks. */
function workSwitch() {
  const count = (m) => data.timeline.filter((t) => m === 'all' || t.work_mode === m).length;
  return `<div class="grid grid-cols-3 rounded-full bg-white p-1 text-sm shadow-card" role="radiogroup" aria-label="Show tasks">
    ${[['all', 'All'], ['individual', 'Individual'], ['group', 'Group']].map(([k, l]) => `
      <button type="button" role="radio" data-work="${k}" aria-checked="${workFilter === k}" class="rounded-full py-2 ${workFilter === k ? 'bg-charcoal text-white' : 'text-muted'}">${l} <span class="text-xs opacity-70">${count(k)}</span></button>`).join('')}
  </div>`;
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
  $('#classes').innerHTML = day && day.classes.length
    ? day.classes.map((c) => UI.classRow(c)).join('')
    : UI.empty('No classes', `Nothing scheduled on ${DAYS_LONG[selectedDow % 7]}.`, 'calendar');
}

function tasksSection() {
  const now = Date.now() / 1000;
  const endToday = new Date(); endToday.setHours(23, 59, 59, 999);
  const endWeek = new Date(endToday); endWeek.setDate(endWeek.getDate() + ((7 - endWeek.getDay()) % 7));
  const list = (items, emptyTitle) => (items.length ? items.map((t) => UI.taskCard(t)).join('') : UI.empty(emptyTitle));

  const timeline = byWork(data.timeline);

  if (filter === 'urgent') {
    return `${heading('Most urgent first', 'Bubble Sort · time left')}${list(byWork(data.by_urgency), 'Nothing urgent')}`;
  }
  if (filter === 'week') {
    const items = timeline.filter((t) => t.deadline_ts && t.deadline_ts * 1000 <= endWeek);
    return `${heading('Due this week', `${items.length} task${items.length === 1 ? '' : 's'}`)}${list(items, 'Nothing due this week')}`;
  }
  if (filter === 'soon') {
    const items = timeline.filter((t) => t.deadline_ts && t.deadline_ts - now <= 3 * 86400);
    return `${heading('Due in the next 3 days', 'Sorted by due date')}${list(items, 'Nothing due soon')}`;
  }

  const today = timeline.filter((t) => t.deadline_ts && t.deadline_ts * 1000 <= endToday);
  const later = timeline.filter((t) => !today.includes(t));
  // With "All", upcoming work is split: individual tasks first, then group work
  const upcoming = workFilter === 'all'
    ? `${heading('Individual tasks', `${later.filter((t) => t.work_mode !== 'group').length} upcoming`)}
       ${list(later.filter((t) => t.work_mode !== 'group'), 'No individual tasks coming up')}
       ${heading('Group work', `${later.filter((t) => t.work_mode === 'group').length} upcoming`)}
       ${list(later.filter((t) => t.work_mode === 'group'), 'No group work coming up')}`
    : `${heading(workFilter === 'group' ? 'Group work' : 'Individual tasks', 'Sorted by due date')}${list(later, 'Nothing coming up')}`;
  return `
    ${today.length ? `
      <div class="rounded-[1.75rem] border-2 border-[#F5C2BC] bg-danger-tint p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="flex items-center gap-2 text-xl font-bold"><span class="h-2.5 w-2.5 rounded-full bg-danger"></span>Urgent · due today</h2>
          <span class="chip bg-white">${today.length} task${today.length === 1 ? '' : 's'}</span>
        </div>
        <div class="space-y-3">${today.map((t) => UI.taskCard(t)).join('')}</div>
      </div>` : ''}
    ${upcoming}`;
}

function heading(title, sub) {
  return `<div class="flex items-baseline justify-between pt-1"><h2 class="text-xl font-bold">${title}</h2><span class="text-xs text-muted">${sub}</span></div>`;
}
