// Group work panel for ONE group task — used on Task Details and in Classmates › Groups.
//   - shows whether you have a group, every group and its members
//   - Create a group: you pick the classmates you already grouped with in person (added right away)
//   - Request to join: asks an existing group; any of its members can Accept / Decline
//   - Admin: auto-group everyone left

const GroupWork = {
  /** Loads and renders the panel into box. opts.onChange() runs after any change. */
  async mount(box, taskId, opts = {}) {
    let d;
    try {
      d = await API.get('groups.php', { task_id: taskId });
    } catch (e) {
      box.innerHTML = UI.errorBox(e);
      return;
    }
    const reload = () => { opts.onChange?.(); return GroupWork.mount(box, taskId, opts); };
    box.innerHTML = GroupWork.html(d, opts);
    GroupWork.bind(box, d, reload);
  },

  html(d, { title = true } = {}) {
    const me = Shell.me.user.id;
    const admin = Shell.me.user.role === 'admin';
    const t = d.task;
    const total = d.groups.reduce((n, g) => n + g.members.length, 0) + d.ungrouped.length;
    const grouped = total - d.ungrouped.length;
    const pct = total ? Math.round((grouped / total) * 100) : 0;
    const mine = d.groups.find((g) => g.is_member);
    const asked = d.groups.filter((g) => g.my_request);

    const status = admin && !mine
      ? `<div class="flex items-center gap-3 rounded-2xl ${d.ungrouped.length ? 'bg-sun-tint' : 'bg-mint-tint'} p-3">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white">${icon(d.ungrouped.length ? 'users' : 'check', 18)}</span>
          <div class="min-w-0 flex-1"><div class="text-sm font-semibold">${d.ungrouped.length ? `${d.ungrouped.length} still without a group` : 'Everyone is grouped'}</div>
            <div class="text-xs text-muted">Admin view · add people to any group, answer join requests, or auto-group the rest below.</div></div>
        </div>`
      : mine
      ? ''   // your group's card comes right below, highlighted, so no separate banner
      : `<div class="rounded-2xl bg-sun-tint p-3">
          <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white">${icon('users', 18)}</span>
            <div class="min-w-0 flex-1"><div class="text-sm font-semibold">You don’t have a group yet</div>
              <div class="text-xs text-muted">${asked.length ? `Waiting for ${asked.map((g) => esc(g.name)).join(', ')} to accept you.` : 'Grouped with classmates in person? Create the group and add them. Or ask to join one below.'}</div></div>
          </div>
          ${d.rules.can_create
            ? `<button type="button" data-gw-create class="btn btn-dark mt-3 h-11 w-full">${icon('plus', 18)}Create a group</button>`
            : `<p class="mt-3 rounded-xl bg-white px-3 py-2 text-xs">All ${d.rules.count} groups are made. Ask to join one below.</p>`}
        </div>`;

    // Your group first, then groups with room, then full ones — so the ones you can act on come first
    const order = (g) => (g.is_member ? 0 : GroupWork.isFull(g) ? 2 : 1);
    const others = d.groups.filter((g) => !g.is_member).sort((a, b) => order(a) - order(b));
    const groupCount = d.rules.count ? `${d.groups.length} of ${d.rules.count} groups made` : plural(d.groups.length, 'group');

    return `
      <div class="space-y-4">
        ${title ? `<div class="flex items-center justify-between gap-2"><h3 class="text-lg font-semibold">Groups</h3><span class="chip bg-canvas">${UI.groupRule(t)}</span></div>` : ''}
        <div>
          <div class="h-2 overflow-hidden rounded-full bg-canvas"><div class="h-full rounded-full bg-success" style="width:${pct}%"></div></div>
          <p class="mt-1.5 text-xs text-muted">${d.ungrouped.length
            ? `<b class="font-semibold text-charcoal">${grouped} of ${total}</b> classmates have a group · ${groupCount}`
            : `<b class="font-semibold text-success">Everyone has a group</b> · ${groupCount}`}</p>
          ${d.rules.overflow ? `<p class="mt-2 rounded-xl bg-peach-tint px-3 py-2 text-xs"><b>Groups can take 1 extra member.</b> ${d.rules.can_create ? 'Not enough classmates are left for another group' : `All ${d.rules.count} groups are made`}, so the ${d.ungrouped.length} left can join a group as its +1.</p>` : ''}
        </div>
        ${status}
        ${mine ? GroupWork.groupCard(mine, d, me, admin) : ''}
        ${others.length ? `
          <div class="space-y-3">
            ${mine ? `<div class="px-1 pt-1 text-sm font-semibold">Other groups <span class="font-normal text-muted">(${others.length})</span></div>` : ''}
            ${others.map((g) => GroupWork.groupCard(g, d, me, admin)).join('')}
          </div>`
          : (mine ? '' : '<p class="rounded-2xl bg-canvas p-4 text-sm text-muted">No groups yet. Be the first to make one.</p>')}
        ${d.ungrouped.length ? `
          <div class="rounded-2xl border border-dashed border-line p-3">
            <div class="text-sm font-semibold">Still without a group <span class="font-normal text-muted">(${d.ungrouped.length})</span></div>
            <div class="mt-2 flex flex-wrap gap-1.5">${d.ungrouped.map((m) => `<span class="chip ${m.id === me ? 'bg-sun-tint' : 'bg-canvas'}">${esc(m.name)}${m.id === me ? ' (you)' : ''}</span>`).join('')}</div>
            ${admin ? `<button type="button" data-gw-auto class="btn btn-light mt-3 w-full border border-line">Auto-group the remaining ${d.ungrouped.length}
              <span class="text-xs font-normal text-muted">→ ${[d.plan.new.length ? `${d.plan.new.length} new group${d.plan.new.length === 1 ? '' : 's'} of ${d.plan.new.join(', ')}` : '', d.plan.joins_existing ? `${d.plan.joins_existing} join existing groups` : ''].filter(Boolean).join(' · ')}</span></button>` : ''}
          </div>` : ''}
      </div>`;
  },

  isFull(g) { return UI.groupCap(g) !== null && g.members.length >= UI.groupCap(g); },

  /** Same rule as the server: the lowest "Group N" not already taken. */
  nextName(groups) {
    const used = new Set(groups.map((g) => g.name.trim().toLowerCase()));
    let n = 1;
    while (used.has(`group ${n}`)) n++;
    return `Group ${n}`;
  },

  /** Plain-language spot count: "Full", "1 spot left", "Needs 1 more". */
  spots(g) {
    const n = g.members.length;
    if (g.min_members !== null && n < g.min_members) {
      return { text: `Needs ${g.min_members - n} more`, cls: 'bg-sun-tint' };
    }
    if (g.max_members !== null && n > g.max_members) return { text: 'Full · +1 extra', cls: 'bg-white text-muted' };
    if (GroupWork.isFull(g)) return { text: 'Full', cls: 'bg-white text-muted' };
    if (UI.groupCap(g) === null) return { text: plural(n, 'member'), cls: 'bg-white' };
    const left = UI.groupCap(g) - n;
    return { text: `${plural(left, 'spot')} left`, cls: 'bg-mint-tint' };
  },

  groupCard(g, d, me, admin) {
    const full = GroupWork.isFull(g);
    const canManage = admin || g.created_by === me;
    const spots = GroupWork.spots(g);
    const btn = 'inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition';
    // Empty seats up to the normal size (not the +1), so you can see how many people still fit
    const open = g.max_members === null ? 0 : Math.max(0, g.max_members - g.members.length);
    const person = (m) => `
      <li class="flex items-center gap-2.5">
        ${UI.avatar(m.name, m.id === me ? 'bg-sun' : 'bg-white', 'h-8 w-8 text-[11px]')}
        <span class="min-w-0 flex-1 truncate text-sm font-medium">${esc(m.name)}${m.id === me ? ' <span class="text-muted">(you)</span>' : ''}</span>
        ${m.id === g.created_by ? '<span class="shrink-0 text-[11px] text-muted">Made the group</span>' : ''}
      </li>`;
    const seat = `
      <li class="flex items-center gap-2.5 text-muted">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border-2 border-dashed border-line">${icon('plus', 14)}</span>
        <span class="text-sm">Open spot</span>
      </li>`;

    return `
      <div class="rounded-2xl ${g.is_member ? 'bg-lavender-tint ring-2 ring-lavender' : 'bg-canvas'} p-4">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            ${g.is_member ? '<div class="text-[11px] font-bold uppercase tracking-wide text-lavender-dark">Your group</div>' : ''}
            <div class="truncate text-base font-semibold">${esc(g.name)}</div>
            <div class="text-xs text-muted">${g.max_members !== null ? `${g.members.length} of ${g.max_members} members` : plural(g.members.length, 'member')}</div>
          </div>
          <span class="chip shrink-0 ${spots.cls} text-xs font-semibold">${spots.text}</span>
        </div>
        <ul class="mt-3 space-y-2">${g.members.map(person).join('')}${seat.repeat(open)}</ul>
        ${g.requests.length ? `
          <div class="mt-3 space-y-2 rounded-xl bg-white p-3">
            <div class="text-xs font-semibold">Asking to join (${g.requests.length})</div>
            ${g.requests.map((r) => `
              <div class="flex items-center gap-2">
                ${UI.avatar(r.name, 'bg-sun-tint', 'h-8 w-8 text-[10px]')}
                <span class="min-w-0 flex-1 truncate text-sm font-medium">${esc(r.name)}</span>
                <button type="button" data-gw-decline="${r.id}" class="${btn} bg-canvas">Decline</button>
                <button type="button" data-gw-accept="${r.id}" class="${btn} bg-charcoal text-white" ${full ? 'disabled title="Your group is full"' : ''}>Accept</button>
              </div>`).join('')}
          </div>` : ''}
        ${GroupWork.actions(g, d, { full, canManage, btn })}
      </div>`;
  },

  /** The buttons under a group, or a one-line reason when there's nothing to do. */
  actions(g, d, { full, canManage, btn }) {
    const buttons = [];
    if (!g.is_member && g.my_request) {
      buttons.push(`<span class="inline-flex items-center gap-1.5 rounded-full bg-sun-tint px-4 py-2 text-xs font-semibold">${icon('clock', 14)}Request sent</span>`,
        `<button type="button" data-gw-cancel="${g.id}" class="${btn} bg-white hover:bg-line">Cancel request</button>`);
    }
    if (!g.is_member && !g.my_request && !d.in_group && !full) {
      buttons.push(`<button type="button" data-gw-request="${g.id}" class="${btn} bg-charcoal text-white hover:opacity-90">${icon('users', 14)}Ask to join</button>`);
    }
    if (canManage && !full && d.ungrouped.length) {
      buttons.push(`<button type="button" data-gw-add="${g.id}" class="${btn} bg-white hover:bg-line">${icon('plus', 14)}Add classmates</button>`);
    }
    if (g.is_member) {
      buttons.push(`<button type="button" data-gw-leave="${g.id}" class="${btn} ml-auto border border-danger/30 bg-white text-danger hover:bg-danger-tint">${icon('logout', 14)}Leave group</button>`);
    }
    const note = !buttons.length && !g.is_member && full ? 'This group is full.' : '';
    if (!buttons.length) return note ? `<p class="mt-3 text-xs text-muted">${note}</p>` : '';
    return `<div class="mt-4 flex flex-wrap items-center gap-2 border-t border-black/5 pt-3">${buttons.join('')}</div>`;
  },

  bind(box, d, reload) {
    const act = async (body, msg) => {
      try {
        const res = await API.post('groups.php', body);
        if (msg) UI.undoToast(msg, res, reload);
        await reload();
      } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
    };
    const on = (attr, fn) => $$(`[${attr}]`, box).forEach((b) => b.onclick = () => fn(Number(b.getAttribute(attr))));
    on('data-gw-request', (id) => act({ action: 'request_join', group_id: id }, 'Request sent'));
    on('data-gw-cancel', (id) => act({ action: 'cancel_request', group_id: id }, 'Request cancelled'));
    on('data-gw-leave', (id) => act({ action: 'leave', group_id: id }, 'You left the group'));
    on('data-gw-accept', (id) => act({ action: 'respond_request', request_id: id, accept: true }, 'Added to your group'));
    on('data-gw-decline', (id) => act({ action: 'respond_request', request_id: id, accept: false }, 'Request declined'));
    on('data-gw-add', (id) => GroupWork.pickMembers(d, id, reload));
    $('[data-gw-create]', box)?.addEventListener('click', () => GroupWork.pickMembers(d, null, reload));
    $('[data-gw-auto]', box)?.addEventListener('click', () => act({ action: 'auto_assign', task_id: d.task.id }, 'Remaining classmates were grouped'));
  },

  /** Create a group for this task (groupId = null) or add classmates to one — people you grouped with in person. */
  pickMembers(d, groupId, reload) {
    const me = Shell.me.user.id;
    const t = d.task;
    const group = d.groups.find((g) => g.id === groupId);
    const room = group ? UI.groupCap(group) - group.members.length : t.group_max - 1;
    // [Graph] classmates you've never been in a group with are listed first, with how you're connected
    const hint = (m) => d.suggest?.[m.id];
    const people = d.ungrouped.filter((m) => m.id !== me);
    if (d.suggest) people.sort((a, b) => Number(!!hint(b)?.never) - Number(!!hint(a)?.never));
    const tag = (m) => {
      const s = hint(m);
      if (!s) return '';
      if (!s.never) return '<span class="chip bg-canvas text-[11px]">Grouped before</span>';
      return `<span class="chip bg-mint-tint text-[11px]">New teammate${s.via.length ? ` · via ${esc(s.via.join(', '))}` : ''}</span>`;
    };
    UI.sheet(`
      <form>
        <h2 class="text-2xl font-bold">${group ? `Add to ${esc(group.name)}` : 'Create a group'}</h2>
        <p class="mt-1 text-sm text-muted">${group ? '' : 'You’ll be in it. '}Pick up to ${room} classmate${room === 1 ? '' : 's'} you already grouped with. They’re added right away.</p>
        ${group ? '' : `<div class="mt-4">${UI.field({ name: 'name', label: 'Group name (optional)', placeholder: GroupWork.nextName(d.groups) })}</div>`}
        ${d.suggest && people.some((m) => hint(m)?.never) ? '<p class="mt-3 text-xs text-muted">Classmates you’ve never been in a group with are listed first.</p>' : ''}
        <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
          ${people.map((m) => `<label class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-canvas">
            <input type="checkbox" name="m" value="${m.id}" class="h-5 w-5 shrink-0 accent-charcoal">
            <span class="min-w-0 flex-1"><span class="block text-sm font-medium">${esc(m.name)}</span>
              <span class="mt-0.5 flex flex-wrap items-center gap-1.5"><span class="text-xs text-muted">${esc(m.student_id)}</span>${tag(m)}</span></span></label>`).join('') || '<p class="text-sm text-muted">Everyone else already has a group.</p>'}
        </div>
        <p class="mt-3 text-xs text-muted">Others can still send a request to join later.</p>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">${group ? 'Add to group' : 'Create group'}</button>
      </form>`, {
      label: group ? 'Add classmates' : 'Create a group',
      onMount(el, close) {
        const form = $('form', el);
        UI.bindForm(form, async (f) => {
          const ids = $$('input[name=m]:checked', form).map((c) => Number(c.value));
          if (ids.length > room) throw new Error(`You can add at most ${room}.`);
          await API.post('groups.php', group
            ? { action: 'add_members', group_id: group.id, member_ids: ids }
            : { action: 'create', task_id: t.id, name: f.name, member_ids: ids });
          close();
          UI.toast(group ? 'Classmates added' : 'Group created');
          reload();
        });
      },
    });
  },
};
