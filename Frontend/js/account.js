// My Account — profile, stats, section, settings, change password.

(async () => {
  const me = await Shell.init({
    active: 'account',
    eyebrow: (m) => (m.user.role === 'admin' ? 'Admin · class officer' : `Client · ${m.section.name}`),
    title: 'My Account',
  });
  render(me);
  if (location.hash === '#password') openPassword();
  if (location.hash === '#profile') openProfile();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

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
          <h2 class="mb-3 text-xl font-bold">${admin ? 'My section' : 'My sections'}</h2>
          <div class="rounded-[1.75rem] bg-white p-4 shadow-card">
            <div class="flex items-center gap-3">
              <span class="grid h-11 w-11 place-items-center rounded-xl bg-charcoal text-sm font-semibold text-white">${esc(me.section.name.slice(-2))}</span>
              <div class="min-w-0 flex-1"><div class="font-semibold">${esc(me.section.name)}</div>
                <div class="text-xs text-muted">${admin ? `Code ${esc(me.section.class_code)} · ` : ''}${me.section.members} members${admin ? '' : ` · Joined ${esc(Shell.joined())}`}</div></div>
              <span class="chip bg-mint-tint text-success">Active</span>
            </div>
            ${admin ? '' : `<a href="section.html#join" class="mt-3 flex items-center gap-3 border-t border-line pt-3">
              <span class="grid h-11 w-11 place-items-center rounded-full bg-canvas">${icon('plus', 18)}</span>
              <span class="flex-1 text-sm font-medium">Join another section</span>${icon('chevronRight', 18, 'text-muted')}</a>`}
          </div>
        </section>
      </div>
      <div class="space-y-5">
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
