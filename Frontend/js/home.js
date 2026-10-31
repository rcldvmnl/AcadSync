// Home dashboard — student and admin variants.

(async () => {
  const [me, data] = await Promise.all([
    API.get('auth.php'),
    API.get('schedule.php'),
  ]);
  const admin = me.user.role === 'admin';
  const now = new Date();
  await Shell.init({
    active: 'home',
    avatar: true,
    eyebrow: admin ? `Admin · ${me.section.name}` : `${DAYS_LONG[now.getDay()]}, ${Fmt.monthDay(now)}`,
    title: `${Fmt.greeting()}, ${me.user.first_name}`,
  });
  $('#main').innerHTML = admin ? adminView(data) : studentView(data);
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

const inLabel = (ts) => {
  if (!ts) return '';
  const hrs = Math.max(0, Math.round((ts * 1000 - Date.now()) / 3600e3));
  return hrs < 24 ? `IN ${hrs || '<1'}H` : `IN ${Math.round(hrs / 24)}D`;
};

/* --------------------------------------------------------------- student */
function studentView(d) {
  const next = d.timeline[0];
  const upcoming = d.timeline.slice(0, 4);
  return `
    <div class="grid gap-5 lg:grid-cols-[1fr_20rem] lg:gap-6">
      <a href="schedule.html" class="card-pastel lavender block min-h-[11rem] rounded-[1.75rem] p-5 lg:min-h-[13rem] lg:p-8">
        <span class="sparkle">✦</span>
        <span class="pointer-events-none absolute -right-10 top-4 h-72 w-72 rounded-full border border-white/40"></span>
        <img src="assets/badge-cap-yellow.png" alt="" width="110" height="110" class="absolute right-4 top-6 h-[6.5rem] w-[6.5rem] rounded-full lg:right-10 lg:top-8 lg:h-36 lg:w-36">
        <div class="relative max-w-[60%]">
          <div class="text-[11px] font-semibold uppercase tracking-wider">This week</div>
          <div class="mt-2 text-[28px] font-extrabold leading-tight tracking-tight lg:text-4xl">${d.stats.due_week} task${d.stats.due_week === 1 ? '' : 's'} due this week</div>
          ${next ? `<p class="mt-2 hidden text-sm lg:block">${esc(next.title)} (${esc(next.subject_code)}) is ${esc(Fmt.due(next).replace(/^Due /, 'due ').split(' · ')[0])}${next.location ? ` · ${esc(next.location)}` : ''}.</p>` : ''}
        </div>
        <div class="relative mt-5 flex items-center gap-3">
          <span class="grid h-12 w-12 place-items-center rounded-full bg-charcoal text-white ring-4 ring-white/40">${icon('arrowRight', 20)}</span>
          <span class="text-sm font-semibold">View schedule</span>
        </div>
      </a>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
        <a href="notifications.html" class="relative overflow-hidden rounded-[1.75rem] border-2 border-lavender-light bg-white p-4 shadow-card">
          <span class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-lavender-tint"></span>
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white text-lavender-dark shadow-card">${icon('inbox', 18)}</span>
          <div class="text-xs text-muted">Pending</div>
          <div class="mt-1 text-4xl font-extrabold">${d.stats.my_pending}</div>
          <div class="mt-1 text-xs text-muted">request${d.stats.my_pending === 1 ? '' : 's'}<span class="hidden lg:inline"> waiting for admin review</span></div>
        </a>
        <a href="schedule.html#urgent" class="card-pastel yellow rounded-[1.75rem] p-4">
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white">${icon('clock', 18)}</span>
          <div class="text-xs">Due today</div>
          <div class="mt-1 text-4xl font-extrabold">${d.stats.due_today}</div>
          <div class="mt-1 flex items-center justify-between gap-1 text-xs"><span>deadline${d.stats.due_today === 1 ? '' : 's'}</span>${d.stats.due_today ? `<span class="chip bg-charcoal px-2 py-0.5 text-[10px] font-bold text-white">${inLabel(d.stats.next_due_ts)}</span>` : ''}</div>
        </a>
      </div>

      <section>
        <div class="mb-3 flex items-baseline justify-between">
          <h2 class="text-xl font-bold">My subjects</h2>
          <span class="text-xs text-muted">${d.subjects.length} subjects</span>
        </div>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">${d.subjects.map(subjectCard).join('')}</div>
      </section>

      <section class="lg:row-span-1 lg:rounded-[1.75rem] lg:bg-white lg:p-4 lg:shadow-card">
        <div class="mb-3 flex items-baseline justify-between">
          <h2 class="text-xl font-bold">Upcoming deadlines</h2>
          <a href="schedule.html" class="text-sm font-semibold">See all</a>
        </div>
        <div class="space-y-3">${upcoming.length ? upcoming.map((t) => UI.taskCard(t, { compact: true })).join('') : UI.empty('All caught up', 'No upcoming deadlines.')}</div>
      </section>
    </div>`;
}

function subjectCard(s) {
  const t = tone(s.color_tag);
  const badge = s.due
    ? `<span class="chip bg-white text-[11px]">${icon('clock', 12)}${s.due} due<span class="h-1.5 w-1.5 rounded-full bg-danger"></span></span>`
    : `<span class="chip bg-white text-[11px] text-success">${icon('check', 12)}All clear</span>`;
  return `
    <a href="subject.html?id=${s.id}" class="card-pastel ${t.soft} flex min-h-[8.5rem] flex-col rounded-[1.5rem] p-4 transition hover:-translate-y-0.5">
      <span class="ring-deco" style="width:90px;height:90px;right:-26px;bottom:-40px;border-width:12px"></span>
      <div class="flex items-start justify-between gap-1">
        <span class="grid h-9 w-9 place-items-center rounded-full bg-white ${t.icon}">${icon('notebook', 18)}</span>
        ${badge}
      </div>
      <div class="mt-auto pt-3 text-[10px] font-semibold tracking-wide text-muted">${esc(s.code)}</div>
      <div class="font-semibold leading-snug">${esc(s.title)}</div>
    </a>`;
}

/* --------------------------------------------------------------- admin */
function adminView(d) {
  const today = new Date();
  const dow = today.getDay();
  const day = d.week.days.find((x) => x.dow === dow);
  const classes = day ? day.classes : [];
  const urgentToday = d.stats.due_today;
  const oldest = d.stats.oldest_request ? `Oldest from ${Fmt.monthDay(d.stats.oldest_request.slice(0, 10))}` : 'Queue is empty';

  const quick = [
    ['add-subject.html', 'lavender-soft', 'bookOpen', 'Add subject', 'Set days & class time'],
    ['task-form.html', 'peach-soft', 'plus', 'Add task', 'Pick a deadline date'],
    ['classmates.html', 'mint-soft', 'users', 'Members', `${d.section.members} in ${d.section.name}`],
    ['week.html', 'yellow-soft', 'calendar', 'Week schedule', `${d.subjects.length} subjects`],
  ];

  return `
    <div class="grid gap-5 lg:grid-cols-[1fr_20rem] lg:gap-6">
      <a href="queue.html" class="card-pastel dark flex min-h-[9rem] items-center justify-between rounded-[1.75rem] p-5 lg:p-8">
        <span class="sparkle">✦</span>
        <span class="pointer-events-none absolute right-6 top-1/2 h-40 w-40 -translate-y-1/2 rounded-full border border-white/10"></span>
        <div class="relative">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-white/60">Waiting for you</div>
          <div class="mt-1 text-[28px] font-extrabold leading-tight lg:text-4xl">${d.stats.queue_size} request${d.stats.queue_size === 1 ? '' : 's'}</div>
          <div class="mt-1 text-xs text-white/60">${esc(oldest)}</div>
        </div>
        <span class="btn btn-yellow relative h-14 px-7">Review</span>
      </a>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
        <a href="schedule.html#urgent" class="card-pastel danger-soft rounded-[1.75rem] border-2 border-[#F5C2BC] p-4">
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white">${icon('clock', 18)}${urgentToday ? '<span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-danger"></span>' : ''}</span>
          <div class="mt-1 text-4xl font-extrabold">${urgentToday}</div>
          <div class="mt-1 flex items-center justify-between gap-1 text-xs text-muted"><span>urgent today</span>${urgentToday ? `<span class="chip bg-charcoal px-2 py-0.5 text-[10px] font-bold text-white">${inLabel(d.stats.next_due_ts)}</span>` : ''}</div>
        </a>
        <a href="schedule.html#week" class="relative overflow-hidden rounded-[1.75rem] border-2 border-lavender-light bg-white p-4 shadow-card">
          <span class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-lavender-tint"></span>
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white text-lavender-dark shadow-card">${icon('inbox', 18)}</span>
          <div class="mt-1 text-4xl font-extrabold">${d.stats.due_week}</div>
          <div class="mt-1 text-xs text-muted">due this week</div>
        </a>
      </div>

      <section>
        <h2 class="mb-3 text-xl font-bold">Quick actions</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          ${quick.map(([href, cls, ic, title, sub]) => `
            <a href="${href}" class="card-pastel ${cls} flex min-h-[8.5rem] flex-col rounded-[1.5rem] p-4 transition hover:-translate-y-0.5">
              <span class="ring-deco" style="width:90px;height:90px;right:-26px;bottom:-40px;border-width:12px"></span>
              <span class="grid h-10 w-10 place-items-center rounded-full bg-white">${icon(ic, 20)}</span>
              <div class="mt-auto pt-3 text-lg font-semibold">${title}</div>
              <div class="text-xs text-muted">${esc(sub)}</div>
            </a>`).join('')}
        </div>
      </section>

      <section>
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-xl font-bold">Classes today</h2>
          <span class="chip bg-white">${esc(Fmt.short(today))}</span>
        </div>
        <div class="space-y-3">${classes.length ? classes.map((c) => UI.classRow(c)).join('') : UI.empty('No classes today', 'Enjoy the free day.', 'calendar')}</div>
      </section>
    </div>`;
}
