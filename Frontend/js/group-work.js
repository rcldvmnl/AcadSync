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
      ? `<div class="flex items-center gap-3 rounded-2xl bg-mint-tint p-3">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-success">${icon('check', 18)}</span>
          <div class="min-w-0 flex-1"><div class="text-sm font-semibold">You’re in ${esc(mine.name)}</div>
            <div class="truncate text-xs text-muted">${mine.members.map((m) => esc(m.name)).join(', ')}</div></div>
        </div>`
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

    return `
      <div class="space-y-4">
        ${title ? `<div class="flex items-center justify-between"><h3 class="text-lg font-semibold">Groups</h3><span class="chip bg-canvas">${UI.groupRule(t)}</span></div>` : ''}
        <div>
          <div class="h-2 overflow-hidden rounded-full bg-canvas"><div class="h-full rounded-full bg-success" style="width:${pct}%"></div></div>
          <p class="mt-1.5 text-xs text-muted">${grouped} of ${total} classmates are in a group · ${d.rules.count ? `${d.groups.length} of ${d.rules.count} groups made` : `${d.groups.length} group${d.groups.length === 1 ? '' : 's'}`}</p>
          ${d.rules.overflow ? `<p class="mt-2 rounded-xl bg-peach-tint px-3 py-2 text-xs"><b>Groups can take 1 extra member.</b> ${d.rules.can_create ? 'Not enough classmates are left for another group' : `All ${d.rules.count} groups are made`}, so the ${d.ungrouped.length} left can join a group as its +1.</p>` : ''}
        </div>
        ${status}
        <div class="space-y-3">
          ${d.groups.length ? d.groups.map((g) => GroupWork.groupCard(g, d, me, admin)).join('') : '<p class="rounded-2xl bg-canvas p-4 text-sm text-muted">No groups yet. Be the first to make one.</p>'}
        </div>
        ${d.ungrouped.length ? `
          <div>
            <div class="text-sm font-semibold">Still without a group (${d.ungrouped.length})</div>
            <div class="mt-2 flex flex-wrap gap-1.5">${d.ungrouped.map((m) => `<span class="chip ${m.id === me ? 'bg-sun-tint' : 'bg-canvas'}">${esc(m.name)}${m.id === me ? ' (you)' : ''}</span>`).join('')}</div>
            ${admin ? `<button type="button" data-gw-auto class="btn btn-light mt-3 w-full border border-line">Auto-group the remaining ${d.ungrouped.length}
              <span class="text-xs font-normal text-muted">→ ${[d.plan.new.length ? `${d.plan.new.length} new group${d.plan.new.length === 1 ? '' : 's'} of ${d.plan.new.join(', ')}` : '', d.plan.joins_existing ? `${d.plan.joins_existing} join existing groups` : ''].filter(Boolean).join(' · ')}</span></button>` : ''}
          </div>` : '<p class="rounded-2xl bg-mint-tint p-3 text-sm">Everyone has a group. 🎉</p>'}
      </div>`;
  },

  groupCard(g, d, me, admin) {
    const full = UI.groupCap(g) !== null && g.members.length >= UI.groupCap(g);
    const under = g.min_members !== null && g.members.length < g.min_members;
    const canManage = admin || g.created_by === me;
    const btn = 'chip px-3 py-1.5 text-xs font-semibold';
    return `
      <div class="rounded-2xl ${g.is_member ? 'bg-lavender-tint' : 'bg-canvas'} p-3">
        <div class="flex items-center justify-between gap-2">
          <div class="font-semibold">${esc(g.name)}${g.is_member ? ' <span class="text-xs font-medium text-lavender-dark">· your group</span>' : ''}</div>
          <span class="chip ${under ? 'bg-sun-tint' : 'bg-white'}">${under ? `${g.members.length}/${g.max_members} · needs more` : UI.groupBadge(g)}</span>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">${g.members.map((m) => `<span class="chip bg-white">${esc(m.name)}${m.id === g.created_by ? ' ★' : ''}</span>`).join('')}</div>
        ${g.requests.length ? `
          <div class="mt-3 space-y-2 rounded-xl bg-white p-2.5">
            <div class="text-xs font-semibold">Wants to join (${g.requests.length})</div>
            ${g.requests.map((r) => `
              <div class="flex items-center gap-2">
                ${UI.avatar(r.name, 'bg-sun-tint', 'h-8 w-8 text-[10px]')}
                <span class="min-w-0 flex-1 truncate text-sm font-medium">${esc(r.name)}</span>
                <button type="button" data-gw-decline="${r.id}" class="${btn} bg-canvas">Decline</button>
                <button type="button" data-gw-accept="${r.id}" class="${btn} bg-charcoal text-white" ${full ? 'disabled title="Your group is full"' : ''}>Accept</button>
              </div>`).join('')}
          </div>` : ''}
        <div class="mt-2.5 flex flex-wrap gap-2">
          ${g.is_member ? `<button type="button" data-gw-leave="${g.id}" class="${btn} bg-white">Leave</button>` : ''}
          ${!g.is_member && g.my_request ? `<span class="chip bg-sun-tint px-3 py-1.5 text-xs">${icon('clock', 12)}Request sent</span><button type="button" data-gw-cancel="${g.id}" class="${btn} bg-white">Cancel request</button>` : ''}
          ${!g.is_member && !g.my_request && !d.in_group && !full ? `<button type="button" data-gw-request="${g.id}" class="${btn} bg-charcoal text-white">${icon('users', 12)}Request to join</button>` : ''}
          ${canManage && !full && d.ungrouped.length ? `<button type="button" data-gw-add="${g.id}" class="${btn} bg-white">+ Add classmates</button>` : ''}
        </div>
      </div>`;
  },

  bind(box, d, reload) {
    const act = async (body, msg) => {
      try {
        await API.post('groups.php', body);
        if (msg) UI.toast(msg);
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
    const people = d.ungrouped.filter((m) => m.id !== me);
    UI.sheet(`
      <form>
        <h2 class="text-2xl font-bold">${group ? `Add to ${esc(group.name)}` : 'Create a group'}</h2>
        <p class="mt-1 text-sm text-muted">${group ? '' : 'You’ll be in it. '}Pick up to ${room} classmate${room === 1 ? '' : 's'} you already grouped with. They’re added right away.</p>
        ${group ? '' : `<div class="mt-4">${UI.field({ name: 'name', label: 'Group name (optional)', placeholder: `Group ${d.groups.length + 1}` })}</div>`}
        <div class="mt-4 max-h-64 space-y-1 overflow-y-auto">
          ${people.map((m) => `<label class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-canvas">
            <input type="checkbox" name="m" value="${m.id}" class="h-5 w-5 accent-charcoal">
            <span class="flex-1 text-sm font-medium">${esc(m.name)}</span><span class="text-xs text-muted">${esc(m.student_id)}</span></label>`).join('') || '<p class="text-sm text-muted">Everyone else already has a group.</p>'}
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
