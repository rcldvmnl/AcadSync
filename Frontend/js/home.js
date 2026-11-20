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
  Announcements.mount($('#announcements'));
  if (!admin) { groupReminder(); duesReminder(); }
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

/** "You owe ₱150 in dues" — shown above the dashboard while a due is unpaid. */
async function duesReminder() {
  try {
    const d = await API.get('dues.php');
    if (!d.my_balance) return;
    $('#main').insertAdjacentHTML('afterbegin', `
      <a href="dues.html" class="mb-5 flex items-center gap-3 rounded-[1.5rem] bg-peach-tint p-4">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white">${icon('clipboard', 18)}</span>
        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">You have ${esc(d.my_balance_label)} in unpaid dues</span>
          <span class="block truncate text-xs text-muted">Pay the Treasurer in cash or by GCash</span></span>
        ${icon('chevronRight', 18, 'text-muted')}
      </a>`);
  } catch { /* the reminder is optional */ }
}

/** Officers' announcements: pinned first, then newest. Officers can post, pin and remove. */
const Announcements = {
  data: null,
  open: false,

  async mount(box) {
    if (!box) return;
    try {
      Announcements.data = await API.get('announcements.php');
    } catch {
      box.remove();   // Home still works without it
      return;
    }
    Announcements.render(box);
  },

  render(box) {
    const { announcements: list, can_post: canPost } = Announcements.data;
    if (!list.length && !canPost) { box.innerHTML = ''; box.hidden = true; return; }
    box.hidden = false;
    const shown = Announcements.open ? list : list.slice(0, 3);
    const item = (a) => `
      <li class="rounded-2xl ${a.pinned ? 'bg-sun-tint' : 'bg-canvas'} p-4">
        <div class="flex items-start gap-2">
          <div class="min-w-0 flex-1">
            ${a.pinned ? `<div class="mb-0.5 flex items-center gap-1 text-[11px] font-bold uppercase tracking-wide">${icon('flag', 12)}Pinned</div>` : ''}
            <h3 class="font-semibold leading-snug">${esc(a.title)}</h3>
            ${a.body ? `<p class="mt-1 whitespace-pre-line text-sm text-[#5C5C66]">${esc(a.body)}</p>` : ''}
            <p class="mt-2 text-xs text-muted">${esc(a.by)} · ${esc(Fmt.monthDay(a.created_at.slice(0, 10)))}</p>
          </div>
          ${canPost || a.can_delete ? `<div class="flex shrink-0 gap-1">
            ${canPost ? `<button type="button" data-ann-pin="${a.id}" data-pinned="${a.pinned ? 1 : 0}" class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold">${a.pinned ? 'Unpin' : 'Pin'}</button>` : ''}
            ${a.can_delete ? `<button type="button" data-ann-delete="${a.id}" class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-danger">Remove</button>` : ''}
          </div>` : ''}
        </div>
      </li>`;
    box.innerHTML = `
      <div class="rounded-[1.75rem] bg-white p-5 shadow-card">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-xl font-bold">Announcements</h2>
          ${canPost ? `<button type="button" data-ann-new class="btn btn-dark h-10 px-4 text-sm">${icon('plus', 16)}Post</button>` : ''}
        </div>
        ${list.length ? `<ul class="mt-4 space-y-3">${shown.map(item).join('')}</ul>`
          : '<p class="mt-3 text-sm text-muted">Nothing posted yet. Post the first update for your section.</p>'}
        ${list.length > 3 ? `<button type="button" data-ann-more class="mt-3 text-sm font-semibold">${Announcements.open ? 'Show less' : `Show all ${list.length}`}</button>` : ''}
      </div>`;
    const reload = async () => { Announcements.data = await API.get('announcements.php'); Announcements.render(box); };
    $('[data-ann-more]', box)?.addEventListener('click', () => { Announcements.open = !Announcements.open; Announcements.render(box); });
    $('[data-ann-new]', box)?.addEventListener('click', () => Announcements.compose(reload));
    $$('[data-ann-pin]', box).forEach((b) => b.onclick = async () => {
      b.disabled = true;
      try { await API.post('announcements.php', { action: 'pin', id: Number(b.dataset.annPin), pinned: b.dataset.pinned !== '1' }); await reload(); }
      catch (e) { b.disabled = false; UI.toast(e.message, { iconName: 'alert' }); }
    });
    $$('[data-ann-delete]', box).forEach((b) => b.onclick = async () => {
      b.disabled = true;
      try { await API.post('announcements.php', { action: 'delete', id: Number(b.dataset.annDelete) }); UI.toast('Announcement removed'); await reload(); }
      catch (e) { b.disabled = false; UI.toast(e.message, { iconName: 'alert' }); }
    });
    if (location.hash === '#announcements') box.scrollIntoView({ block: 'start' });
  },

  compose(reload) {
    UI.sheet(`
      <form>
        <h2 class="text-2xl font-bold">Post an announcement</h2>
        <p class="mt-1 text-sm text-muted">Everyone in ${esc(Shell.me.section.name)} gets a notification.</p>
        <div class="mt-4 space-y-4">
          ${UI.field({ name: 'title', label: 'Title', placeholder: 'e.g. No classes on Friday', required: true })}
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Message (optional)</span>
            <span class="field area"><textarea name="body" rows="4" maxlength="1000" placeholder="Details, time, place…"></textarea></span></label>
          <label class="flex items-center gap-3 rounded-2xl bg-canvas p-3 text-sm">
            <input type="checkbox" name="pinned" value="1" class="h-5 w-5 accent-charcoal">Pin it to the top</label>
        </div>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">Post</button>
      </form>`, {
      label: 'Post an announcement',
      onMount(el, close) {
        $('input[name=title]', el).maxLength = 80;
        UI.bindForm($('form', el), async (f) => {
          await API.post('announcements.php', { action: 'post', title: f.title, body: f.body, pinned: f.pinned === '1' });
          close();
          UI.toast('Announcement posted', { sub: 'Everyone was notified' });
          await reload();
        });
      },
    });
  },
};

/** "You still need a group for …" — shown above the dashboard while any group task is unclaimed. */
async function groupReminder() {
  try {
    const { upcoming } = await API.get('groups.php');
    const missing = upcoming.filter((t) => !t.in_group);
    if (!missing.length) return;
    $('#main').insertAdjacentHTML('afterbegin', `
      <a href="${missing.length === 1 ? `task.html?id=${missing[0].id}` : 'classmates.html#groups'}" class="mb-5 flex items-center gap-3 rounded-[1.5rem] bg-sun-tint p-4">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white">${icon('users', 18)}</span>
        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">You still need a group</span>
          <span class="block truncate text-xs text-muted">${missing.map((t) => esc(t.title)).join(' · ')}</span></span>
        ${icon('chevronRight', 18, 'text-muted')}
      </a>`);
  } catch { /* the reminder is optional */ }
}

const inLabel = (ts) => {
  if (!ts) return '';
  const hrs = Math.max(0, Math.round((ts * 1000 - Date.now()) / 3600e3));
  return hrs < 24 ? `IN ${hrs || '<1'}H` : `IN ${Math.round(hrs / 24)}D`;
};

/* --------------------------------------------------------------- student */
function studentView(d) {
  const open = d.timeline.filter((t) => !t.done);   // finished tasks are on Schedule / My tasks, not "next up"
  const next = open[0];
  const upcoming = open.slice(0, 4);
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
        <a href="my-tasks.html#pending" class="relative overflow-hidden rounded-[1.75rem] border-2 border-lavender-light bg-white p-4 shadow-card">
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

      <section id="announcements" class="scroll-mt-6 lg:col-span-2" aria-label="Announcements"></section>

      <section>
        <div class="mb-3 flex items-baseline justify-between">
          <h2 class="text-xl font-bold">My subjects</h2>
          <span class="text-xs text-muted">${d.subjects.length} subjects</span>
        </div>
        ${d.subjects.length
          ? `<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">${d.subjects.map(subjectCard).join('')}</div>`
          : UI.empty('No subjects yet', 'Your class officer hasn’t added this section’s subjects.', 'bookOpen')}
      </section>

      <section class="lg:row-span-1 lg:rounded-[1.75rem] lg:bg-white lg:p-4 lg:shadow-card">
        <div class="mb-3 flex items-baseline justify-between">
          <h2 class="text-xl font-bold">Upcoming deadlines</h2>
          <a href="schedule.html" class="text-sm font-semibold">See all</a>
        </div>
        <div class="space-y-3">${upcoming.length ? upcoming.map((t) => UI.taskCard(t, { compact: true })).join('') : UI.empty('All caught up', 'No upcoming deadlines.')}</div>
        <a href="my-tasks.html#history" class="mt-3 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-card lg:bg-canvas lg:shadow-none">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-mint-tint text-success">${icon('check', 16)}</span>
          <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">Task history</span>
            <span class="block text-xs text-muted">${d.stats.done || 0} done · past deadlines · request results</span></span>
          ${icon('chevronRight', 18, 'text-muted')}
        </a>
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
          <div class="mt-1 text-xs text-white/60">${esc(oldest)}${d.stats.join_requests ? ` · ${plural(d.stats.join_requests, 'person', 'people')} waiting to join` : ''}</div>
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

      <section id="announcements" class="scroll-mt-6 lg:col-span-2" aria-label="Announcements"></section>

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
