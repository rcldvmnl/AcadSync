// Notifications list with All / Unread tabs.

const KIND = {
  approved: ['check', 'bg-mint-tint'],
  rejected: ['x', 'bg-danger-tint'],
  due: ['clock', 'bg-sun-tint'],
  moved: ['calendar', 'bg-lavender-tint'],
  schedule: ['calendar', 'bg-lavender-tint'],
  group: ['users', 'bg-lavender-tint'],
  members: ['users', 'bg-lavender-tint'],
  request: ['inbox', 'bg-sun-tint'],
  announcement: ['bell', 'bg-peach-tint'],
  dues: ['clipboard', 'bg-sun-tint'],
};
let tab = 'all';
let items = [];

(async () => {
  await Shell.init({
    active: 'home',
    eyebrow: (m) => `${m.user.role_label} · ${m.section.name}`,
    title: 'Notifications',
    search: false,
  });
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#list').innerHTML = UI.errorBox(e); });

async function load() {
  const d = await API.get('notifications.php');
  items = d.notifications;
  $('[data-tab=unread]').textContent = `Unread (${d.unread})`;
  $('#read-all').hidden = !d.unread;
  render();
}

function render() {
  $$('[data-tab]').forEach((b) => {
    const on = b.dataset.tab === tab;
    b.className = `rounded-full py-3 text-sm font-semibold ${on ? 'bg-charcoal text-white' : 'text-muted'}`;
    b.setAttribute('aria-selected', String(on));
  });
  const shown = tab === 'unread' ? items.filter((n) => !n.is_read) : items;
  $('#list').innerHTML = shown.length ? `
    <div class="rounded-[1.75rem] bg-white p-2 shadow-card">
      ${shown.map((n) => {
        const [ic, bg] = KIND[n.type] || ['bell', 'bg-canvas'];
        return `<a ${n.link ? `href="${esc(n.link)}"` : ''} data-id="${n.id}" class="flex items-start gap-3 rounded-2xl p-3 ${n.link ? 'hover:bg-canvas' : ''}">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full ${bg}">${icon(ic, 18)}</span>
          <span class="min-w-0 flex-1"><span class="block text-[15px] font-medium leading-snug">${esc(n.message)}</span>
            <span class="mt-0.5 block text-xs text-muted">${esc(Fmt.ago(n.ts))}</span></span>
          ${n.is_read ? '' : '<span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-danger" aria-label="Unread"></span>'}
        </a>`;
      }).join('')}
    </div>` : UI.empty(tab === 'unread' ? 'You’re all caught up' : 'No notifications yet', '', 'bell');

  $$('#list [data-id]').forEach((a) => a.addEventListener('click', async (e) => {
    e.preventDefault();
    await API.post('notifications.php', { id: Number(a.dataset.id) }).catch(() => {});
    if (a.hasAttribute('href')) location.href = a.getAttribute('href');
    else await load();
  }));
}

$$('[data-tab]').forEach((b) => b.onclick = () => { tab = b.dataset.tab; render(); });
$('#read-all').onclick = async () => { await API.post('notifications.php', { action: 'read_all' }); await load(); };
