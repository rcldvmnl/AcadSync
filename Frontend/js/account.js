// My Account — profile, stats, sections (switch), my requests, settings, change password.

const STATUS_CHIP = {
  pending: ['Waiting in queue', 'bg-sun-tint'],
  approved: ['Approved', 'bg-mint-tint text-success'],
  rejected: ['Not approved', 'bg-danger-tint'],
};

(async () => {
  const me = await Shell.init({
    active: 'account',
    eyebrow: (m) => `${m.user.role_label} · ${m.section.name}`,
    title: 'My Account',
  });
  render(me);
  if (me.user.role !== 'admin') loadRequests();
  if (location.hash === '#password') openPassword();
  if (location.hash === '#profile') openProfile();
  if (location.hash === '#requests') $('#requests')?.scrollIntoView();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function loadRequests() {
  const box = $('#requests-list');
  try {
    const { requests, pending, limit } = await API.get('tasks.php', { mine: 1 });
    const meter = `<div class="flex items-center gap-3 px-3 pb-2 pt-3">
      <div class="flex gap-1">${Array.from({ length: limit }, (_, i) => `<span class="h-2 w-5 rounded-full ${i < pending ? 'bg-charcoal' : 'bg-line'}"></span>`).join('')}</div>
      <span class="text-xs text-muted">${pending} of ${limit} requests waiting${pending >= limit ? ' — edit or withdraw one to send more' : ''}</span></div>`;
    box.innerHTML = meter + (requests.length ? requests.slice(0, 10).map((r) => {
      const [label, cls] = STATUS_CHIP[r.status] || [r.status, 'bg-canvas'];
      const where = r.status === 'pending' && r.queue_position ? ` · #${r.queue_position} in queue` : '';
      const link = r.status === 'approved' ? `task.html?id=${r.kind === 'change' ? r.target_task_id : r.id}` : null;
      return `<div class="rounded-2xl p-3 ${link ? 'hover:bg-canvas' : ''}">
        <div class="flex items-center gap-3">
          <${link ? `a href="${link}"` : 'span'} class="min-w-0 flex-1"><span class="block truncate text-[15px] font-medium">${esc(r.title)}${r.kind === 'change' ? ' <span class="text-xs text-muted">(edit request)</span>' : ''}</span>
            <span class="block text-xs text-muted">${esc(r.subject_code)} · ${esc(r.category)}${r.work_mode === 'group' ? ` · group work, ${UI.groupRule(r)}` : ''} · sent ${esc(Fmt.ago(r.created_ts))}${where}</span></${link ? 'a' : 'span'}>
          ${UI.chip(label, cls)}
        </div>
        ${r.status === 'pending' ? `<div class="mt-2 flex gap-2">
          <a href="task-form.html?request=${r.id}" class="chip bg-canvas px-3 py-1.5 text-xs font-semibold">${icon('edit', 12)}Edit</a>
          <button type="button" data-withdraw="${r.id}" class="chip bg-danger-tint px-3 py-1.5 text-xs font-semibold">Withdraw</button>
        </div>` : ''}
      </div>`;
    }).join('') : '<p class="p-3 text-sm text-muted">You haven’t requested any tasks yet. Tap + to send one to your admin.</p>');
    $$('[data-withdraw]', box).forEach((b) => b.onclick = async () => {
      try {
        await API.post('request_task.php', { action: 'withdraw', request_id: Number(b.dataset.withdraw) });
        UI.toast('Request withdrawn', { sub: 'It left the approval queue' });
        loadRequests();
      } catch (err) { UI.toast(err.message, { iconName: 'alert' }); }
    });
  } catch (e) {
    box.innerHTML = `<p class="p-3 text-sm">${esc(e.message)}</p>`;
  }
}

function render(me) {
  const admin = me.user.role === 'admin';
  const s = me.stats;
  const stats = admin
    ? [[s.subjects, 'Subjects'], [s.published, 'Published'], [s.reviewed, 'Reviewed']]
    : [[s.submitted, 'Submitted'], [s.approved, 'Approved'], [s.groups, 'Groups']];
  const settings = [
    ...(admin ? [['users', 'Manage members', 'classmates.html'], ['bookOpen', 'Subjects & schedule', 'week.html']] : []),
    ['clipboard', 'My member record', `records.html?id=${me.user.id}`],
    ...(Shell.can('view_member_records') ? [['users', 'Member records & officers', 'records.html']] : []),
    ['flag', 'Activities', 'activities.html'],
    ['clipboard', Shell.can('manage_dues') ? 'Dues & payments' : 'My dues', 'dues.html'],
    ['user', 'Edit profile', '#profile'],
    ['bell', 'Notifications', 'notifications.html'],
    ['lock', 'Change password', '#password'],
    ['help', 'Help & feedback', '#help'],
  ];

  $('#main').innerHTML = `
    <div class="grid gap-5 lg:grid-cols-2 lg:items-start lg:gap-6">
      <div class="space-y-5">
        ${Shell.profileCard(admin, true)}
        <div class="grid grid-cols-3 gap-3">
          ${stats.map(([n, l]) => `<div class="rounded-2xl bg-white p-4 shadow-card"><div class="text-xl font-bold">${n}</div><div class="text-xs text-muted">${l}</div></div>`).join('')}
        </div>
        <section>
          <h2 class="mb-3 text-xl font-bold">My sections</h2>
          <div class="rounded-[1.75rem] bg-white p-3 shadow-card" id="sections">${Shell.sectionList()}</div>
        </section>
      </div>
      <div class="space-y-5">
        ${admin ? '' : `<section id="requests">
          <h2 class="mb-3 text-xl font-bold">My requests</h2>
          <div class="rounded-[1.75rem] bg-white p-2 shadow-card" id="requests-list">${UI.skeleton(2, 'h-14')}</div>
        </section>`}
        <section>
          <h2 class="mb-3 text-xl font-bold">Settings</h2>
          <div class="rounded-[1.75rem] bg-white p-2 shadow-card">
            ${settings.map(([i, l, h]) => `<a href="${h}" data-link="${h}" class="flex items-center gap-3 rounded-2xl p-3 hover:bg-canvas">
              <span class="grid h-10 w-10 place-items-center rounded-full bg-canvas">${icon(i, 18)}</span>
              <span class="flex-1 text-[15px] font-medium">${l}</span>${icon('chevronRight', 18, 'text-muted')}</a>`).join('')}
          </div>
        </section>
        <button id="logout" class="btn btn-danger w-full">Log out</button>
      </div>
    </div>`;

  $('[data-link="#password"]').onclick = (e) => { e.preventDefault(); openPassword(); };
  $('[data-link="#profile"]').onclick = (e) => { e.preventDefault(); openProfile(); };
  $('[data-link="#help"]').onclick = (e) => { e.preventDefault(); openHelp(); };
  $('#logout').onclick = Shell.logout;
  Shell.bindSectionList($('#sections'));
}

function openPassword() {
  UI.sheet(`
    <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-lavender-tint">${icon('lock', 24)}</span>
    <form>
      <h2 class="text-2xl font-bold">Change password</h2>
      <div class="mt-4 space-y-4">
        ${UI.field({ name: 'current', label: 'Current password', type: 'password', iconName: 'lock', placeholder: '••••••••', required: true, autocomplete: 'current-password' })}
        ${UI.field({ name: 'password', label: 'New password', type: 'password', iconName: 'lock', placeholder: 'At least 8 characters', required: true, autocomplete: 'new-password' })}
        ${UI.field({ name: 'confirm', label: 'Confirm new password', type: 'password', iconName: 'lock', placeholder: 'Repeat password', required: true, autocomplete: 'new-password' })}
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Update password</button>
      <button type="button" data-close class="mt-3 w-full py-2 text-sm font-semibold">Cancel</button>
    </form>`, {
    label: 'Change password',
    onMount(el, close) {
      UI.bindForm($('form', el), async (d) => {
        await API.post('auth.php', { action: 'change_password', ...d });
        close();
        UI.toast('Password updated');
      });
    },
  });
}

function openProfile() {
  const u = Shell.me.user;
  UI.sheet(`
    <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-peach-tint">${icon('user', 24)}</span>
    <form>
      <h2 class="text-2xl font-bold">Edit profile</h2>
      <div class="mt-4 grid grid-cols-2 gap-3">
        ${UI.field({ name: 'first_name', label: 'First name', value: u.first_name, required: true })}
        ${UI.field({ name: 'last_name', label: 'Last name', value: u.last_name, required: true })}
      </div>
      <div class="mt-4 space-y-2 rounded-2xl bg-canvas p-4 text-sm">
        <div class="flex justify-between"><span class="text-muted">Username</span><span class="font-medium">${esc(u.username)}</span></div>
        <div class="flex justify-between"><span class="text-muted">Student ID</span><span class="font-medium">${esc(u.student_id)}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted">Email</span><span class="truncate font-medium">${esc(u.email)}</span></div>
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Save</button>
      <button type="button" data-close class="mt-3 w-full py-2 text-sm font-semibold">Cancel</button>
    </form>`, {
    label: 'Edit profile',
    onMount(el, close) {
      UI.bindForm($('form', el), async (d) => {
        const me = await API.post('auth.php', { action: 'update_profile', ...d });
        Shell.me = { ...Shell.me, ...me };
        close();
        render(Shell.me);
        if (Shell.me.user.role !== 'admin') loadRequests();
        UI.toast('Profile saved');
      });
    },
  });
}

function openHelp() {
  UI.sheet(`
    <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-mint-tint">${icon('help', 24)}</span>
    <h2 class="text-2xl font-bold">Help & feedback</h2>
    <ul class="mt-3 space-y-2 text-sm text-muted">
      <li><b class="text-charcoal">Request a task</b> with the + button. It joins the admin’s queue and is reviewed first-come, first-served.</li>
      <li><b class="text-charcoal">Expired deadlines</b> drop off your schedule automatically.</li>
      <li><b class="text-charcoal">Something wrong?</b> Ask your class officer, or open the task and tap “Request changes”.</li>
    </ul>
    <button data-close class="btn btn-dark mt-6 w-full">Got it</button>`, { label: 'Help' });
}
