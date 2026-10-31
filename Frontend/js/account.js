// My Account — profile, stats, sections (switch), my requests, settings, change password.

const STATUS_CHIP = {
  pending: ['Waiting in queue', 'bg-sun-tint'],
  approved: ['Approved', 'bg-mint-tint text-success'],
  rejected: ['Not approved', 'bg-danger-tint'],
};

(async () => {
  const me = await Shell.init({
    active: 'account',
    eyebrow: (m) => `${m.user.role === 'admin' ? 'Admin' : 'Student'} · ${m.section.name}`,
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
    const { requests } = await API.get('tasks.php', { mine: 1 });
    box.innerHTML = requests.length ? requests.slice(0, 8).map((r) => {
      const [label, cls] = STATUS_CHIP[r.status] || [r.status, 'bg-canvas'];
      const where = r.status === 'pending' && r.queue_position ? ` · #${r.queue_position} in queue` : '';
      return `<a ${r.status === 'approved' ? `href="task.html?id=${r.kind === 'change' ? r.target_task_id : r.id}"` : ''} class="flex items-center gap-3 rounded-2xl p-3 ${r.status === 'approved' ? 'hover:bg-canvas' : ''}">
        <span class="min-w-0 flex-1"><span class="block truncate text-[15px] font-medium">${esc(r.title)}${r.kind === 'change' ? ' <span class="text-xs text-muted">(edit)</span>' : ''}</span>
          <span class="block text-xs text-muted">${esc(r.subject_code)} · sent ${esc(Fmt.ago(r.created_ts))}${where}</span></span>
        ${UI.chip(label, cls)}
      </a>`;
    }).join('') : '<p class="p-3 text-sm text-muted">You haven’t requested any tasks yet. Tap + to send one to your admin.</p>';
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
