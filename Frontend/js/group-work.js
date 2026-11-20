// Group work panel for ONE group task — used on Task Details (and its sheet in Classmates › Groups).
//   - one sentence says how the prof set the groups up (written by the server's GroupRule subclass)
//   - your group first, then every other group as seats (members, open spots, the +1 extra spot)
//   - an EMPTY group is joined directly (you become its leader); a group with members is asked to join
//   - groups the prof assigned can't be changed by students
//   - officers move or remove anyone; the President can auto-group whoever is left

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
    const officer = Shell.can('manage_any_group');
    const r = d.rules;
    const total = d.members;
    const grouped = total - d.ungrouped.length;
    const pct = total ? Math.round((grouped / total) * 100) : 0;
    const mine = d.groups.find((g) => g.is_member);
    const asked = d.groups.filter((g) => g.my_request);
    const setup = UI.GROUP_SETUPS[r.mode] || UI.GROUP_SETUPS.size;

    // Your next step, in one line
    let next = '';
    if (!mine && !officer) {
      const empties = d.groups.filter((g) => g.can_join).length;
      const text = !r.students_organize ? 'You’re not in a group yet. An officer will add you to one.'
        : asked.length ? `Waiting for ${asked.map((g) => esc(g.name)).join(', ')} to accept you.`
        : empties ? `Join an empty group to start it, or ask to join a group that has space.`
        : r.can_create ? 'Grouped with classmates in person? Make the group and add them, or ask to join one below.'
        : 'Ask to join a group that has space.';
      next = `
        <div class="rounded-2xl bg-sun-tint p-3">
          <div class="flex items-start gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white">${icon('users', 18)}</span>
            <div class="min-w-0 flex-1"><div class="text-sm font-semibold">You don’t have a group yet</div>
              <div class="text-xs text-[#5C5C66]">${text}</div></div>
          </div>
          ${r.can_create ? `<button type="button" data-gw-create class="btn btn-dark mt-3 h-11 w-full">${icon('plus', 18)}Make a group</button>` : ''}
        </div>`;
    }

    // Your group first, then groups you can join, then empty ones, then full ones
    const order = (g) => (g.can_request ? 0 : g.can_join ? 1 : g.is_full ? 3 : 2);
    const others = d.groups.filter((g) => !g.is_member).sort((a, b) => order(a) - order(b));
    const groupsLine = r.count ? `${d.groups.length} of ${r.count} groups made` : plural(d.groups.length, 'group');

    return `
      <div class="space-y-4">
        ${title ? `<div class="flex items-center justify-between gap-2"><h3 class="text-lg font-semibold">Groups</h3><span class="chip bg-canvas">${esc(r.label)}</span></div>` : ''}
        <div class="rounded-2xl bg-lavender-tint p-4">
          <div class="flex items-start gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white">${icon(setup.icon, 18)}</span>
            <div class="min-w-0 flex-1">
              <div class="text-[11px] font-bold uppercase tracking-wide text-lavender-dark">${esc(setup.title)}</div>
              <p class="mt-0.5 text-sm">${esc(r.summary)}</p>
            </div>
          </div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-success" style="width:${pct}%"></div></div>
          <p class="mt-1.5 text-xs text-[#5C5C66]">${d.ungrouped.length
            ? `<b class="font-semibold text-charcoal">${grouped} of ${total}</b> classmates have a group · ${groupsLine}`
            : `<b class="font-semibold text-success">Everyone has a group</b> · ${groupsLine}`}</p>
          ${officer && r.unplaceable ? `<p class="mt-2 rounded-xl bg-white px-3 py-2 text-xs"><b>${plural(r.unplaceable, 'classmate')} won’t fit</b>, even with 1 extra in every group. Add a group or move people.</p>` : ''}
        </div>
        ${next}
        ${mine ? GroupWork.groupCard(mine, d, me) : ''}
        ${others.length ? `
          <div class="space-y-3">
            ${mine ? `<div class="px-1 pt-1 text-sm font-semibold">Other groups <span class="font-normal text-muted">(${others.length})</span></div>` : ''}
            ${others.map((g) => GroupWork.groupCard(g, d, me)).join('')}
          </div>`
          : (mine ? '' : '<p class="rounded-2xl bg-canvas p-4 text-sm text-muted">No groups yet. Be the first to make one.</p>')}
        ${d.ungrouped.length ? GroupWork.leftCard(d, me, officer) : ''}
      </div>`;
  },

  /** Classmates without a group. Officers tap a name to place them; the President can auto-group. */
  leftCard(d, me, officer) {
    const autoBtn = Shell.can('auto_group') && d.rules.students_organize && d.plan;
    const planText = autoBtn ? [d.plan.new.length ? `${d.plan.new.length} new group${d.plan.new.length === 1 ? '' : 's'} of ${d.plan.new.join(', ')}` : '',
      d.plan.joins_existing ? `${d.plan.joins_existing} join existing groups` : ''].filter(Boolean).join(' · ') : '';
    return `
      <div class="rounded-2xl border border-dashed border-line p-3">
        <div class="text-sm font-semibold">Still without a group <span class="font-normal text-muted">(${d.ungrouped.length})</span></div>
        ${officer ? '<p class="mt-0.5 text-xs text-muted">Tap a name to put them in a group.</p>' : ''}
        <div class="mt-2 flex flex-wrap gap-1.5">${d.ungrouped.map((m) => officer
          ? `<button type="button" data-gw-place="${m.id}" class="chip ${m.id === me ? 'bg-sun-tint' : 'bg-canvas'} hover:bg-line">${icon('plus', 12)}${esc(m.name)}${m.id === me ? ' (you)' : ''}</button>`
          : `<span class="chip ${m.id === me ? 'bg-sun-tint' : 'bg-canvas'}">${esc(m.name)}${m.id === me ? ' (you)' : ''}</span>`).join('')}</div>
        ${autoBtn ? `<button type="button" data-gw-auto class="btn btn-light mt-3 w-full border border-line">Auto-group the remaining ${d.ungrouped.length}
          ${planText ? `<span class="text-xs font-normal text-muted">→ ${planText}</span>` : ''}</button>` : ''}
      </div>`;
  },

  isFull(g) { return g.is_full ?? (UI.groupCap(g) !== null && g.members.length >= UI.groupCap(g)); },

  /** Same rule as the server: the lowest "Group N" not already taken. */
  nextName(groups) {
    const used = new Set(groups.map((g) => g.name.trim().toLowerCase()));
    let n = 1;
    while (used.has(`group ${n}`)) n++;
    return `Group ${n}`;
  },

  groupCard(g, d, me) {
    const btn = 'inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition';
    const target = g.target ?? g.max_members;
    // Open spots up to the normal size, then one "+1 extra" spot while an extra seat is free
    const open = target === null || target === undefined ? 0 : Math.max(0, target - g.members.length);
    const extraSpot = target !== null && target !== undefined && g.capacity > target && g.members.length >= target && g.members.length < g.capacity;
    const person = (m) => `
      <li class="flex items-center gap-2.5">
        ${UI.avatar(m.name, m.id === me ? 'bg-sun' : 'bg-white', 'h-8 w-8 text-[11px]')}
        <span class="min-w-0 flex-1 truncate text-sm font-medium">${esc(m.name)}${m.id === me ? ' <span class="text-muted">(you)</span>' : ''}</span>
        ${m.id === g.created_by && !g.assigned ? '<span class="shrink-0 text-[11px] text-muted">Leader</span>' : ''}
        ${g.can_move ? `<button type="button" data-gw-move="${g.id}:${m.id}" class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold hover:bg-line" aria-label="Move ${esc(m.name)}">Move</button>` : ''}
      </li>`;
    const seat = (label) => `
      <li class="flex items-center gap-2.5 text-muted">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border-2 border-dashed border-line">${icon('plus', 14)}</span>
        <span class="text-sm">${label}</span>
      </li>`;

    return `
      <div class="rounded-2xl ${g.is_member ? 'bg-lavender-tint ring-2 ring-lavender' : 'bg-canvas'} p-4">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            ${g.is_member ? '<div class="text-[11px] font-bold uppercase tracking-wide text-lavender-dark">Your group</div>' : ''}
            <div class="truncate text-base font-semibold">${esc(g.name)}</div>
            <div class="text-xs text-muted">${esc(UI.groupCount(g))}${g.assigned ? ' · assigned by your prof' : ''}</div>
          </div>
          <span class="chip shrink-0 ${UI.groupStateTone(g)} text-xs font-semibold">${esc(g.state_label || '')}</span>
        </div>
        <ul class="mt-3 space-y-2">${g.members.map(person).join('')}${seat(g.members.length ? 'Open spot' : 'Open spot · be the first').repeat(Math.min(open, 1))}${open > 1 ? seat(`${open - 1} more open spot${open - 1 === 1 ? '' : 's'}`) : ''}${extraSpot ? seat('Room for 1 extra (for a classmate left over)') : ''}</ul>
        ${g.requests.length ? `
          <div class="mt-3 space-y-2 rounded-xl bg-white p-3">
            <div class="text-xs font-semibold">Asking to join (${g.requests.length})</div>
            ${g.requests.map((rq) => `
              <div class="flex items-center gap-2">
                ${UI.avatar(rq.name, 'bg-sun-tint', 'h-8 w-8 text-[10px]')}
                <span class="min-w-0 flex-1 truncate text-sm font-medium">${esc(rq.name)}</span>
                <button type="button" data-gw-decline="${rq.id}" class="${btn} bg-canvas">Decline</button>
                <button type="button" data-gw-accept="${rq.id}" class="${btn} bg-charcoal text-white" ${GroupWork.isFull(g) ? 'disabled title="The group is full"' : ''}>Accept</button>
              </div>`).join('')}
          </div>` : ''}
        ${GroupWork.actions(g, d, btn)}
      </div>`;
  },

  /** The buttons under a group, or a one-line reason when there's nothing to do. */
  actions(g, d, btn) {
    const buttons = [];
    if (g.can_join) {
      buttons.push(`<button type="button" data-gw-join="${g.id}" class="${btn} bg-charcoal text-white hover:opacity-90">${icon('plus', 14)}Join this group</button>`);
    }
    if (!g.is_member && g.my_request) {
      buttons.push(`<span class="inline-flex items-center gap-1.5 rounded-full bg-sun-tint px-4 py-2 text-xs font-semibold">${icon('clock', 14)}Request sent</span>`,
        `<button type="button" data-gw-cancel="${g.id}" class="${btn} bg-white hover:bg-line">Cancel request</button>`);
    }
    if (g.can_request) {
      buttons.push(`<button type="button" data-gw-request="${g.id}" class="${btn} bg-charcoal text-white hover:opacity-90">${icon('users', 14)}Ask to join</button>`);
    }
    if (g.can_manage && (!GroupWork.isFull(g) || g.can_move) && d.ungrouped.length) {
      buttons.push(`<button type="button" data-gw-add="${g.id}" class="${btn} bg-white hover:bg-line">${icon('plus', 14)}Add classmates</button>`);
    }
    if (g.can_manage) {
      buttons.push(`<button type="button" data-gw-rename="${g.id}" class="${btn} bg-white hover:bg-line">${icon('edit', 14)}Rename</button>`);
    }
    if (g.can_leave) {
      buttons.push(`<button type="button" data-gw-leave="${g.id}" class="${btn} ml-auto border border-danger/30 bg-white text-danger hover:bg-danger-tint">${icon('logout', 14)}Leave group</button>`);
    }
    const note = buttons.length ? ''
      : g.assigned && g.is_member ? 'Your prof assigned this group. Ask an officer if you need to move.'
      : !g.is_member && GroupWork.isFull(g) ? 'This group is full.'
      : '';
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
    const on = (attr, fn) => $$(`[${attr}]`, box).forEach((b) => b.onclick = () => fn(b.getAttribute(attr)));
    on('data-gw-join', (id) => act({ action: 'join', group_id: Number(id) }, 'You joined the group'));
    on('data-gw-request', (id) => act({ action: 'request_join', group_id: Number(id) }, 'Request sent'));
    on('data-gw-cancel', (id) => act({ action: 'cancel_request', group_id: Number(id) }, 'Request cancelled'));
    on('data-gw-leave', (id) => act({ action: 'leave', group_id: Number(id) }, 'You left the group'));
    on('data-gw-accept', (id) => act({ action: 'respond_request', request_id: Number(id), accept: true }, 'Added to your group'));
    on('data-gw-decline', (id) => act({ action: 'respond_request', request_id: Number(id), accept: false }, 'Request declined'));
    on('data-gw-add', (id) => GroupWork.pickMembers(d, Number(id), reload));
    on('data-gw-rename', (id) => GroupWork.rename(d.groups.find((g) => g.id === Number(id)), reload));
    on('data-gw-move', (v) => { const [gid, uid] = v.split(':').map(Number); GroupWork.moveSheet(d, uid, gid, reload); });
    on('data-gw-place', (uid) => GroupWork.moveSheet(d, Number(uid), null, reload));
    $('[data-gw-create]', box)?.addEventListener('click', () => GroupWork.pickMembers(d, null, reload));
    $('[data-gw-auto]', box)?.addEventListener('click', () => act({ action: 'auto_assign', task_id: d.task.id }, 'Remaining classmates were grouped'));
  },

  /** Officers: move someone to another group of this task, or take them out of their group. */
  moveSheet(d, userId, fromGroupId, reload) {
    const from = d.groups.find((g) => g.id === fromGroupId) || null;
    const person = from?.members.find((m) => m.id === userId) || d.ungrouped.find((m) => m.id === userId);
    const targets = d.groups.filter((g) => g.id !== fromGroupId);
    UI.sheet(`
      <h2 class="text-2xl font-bold">${from ? 'Move' : 'Place'} ${esc(person?.name || 'classmate')}</h2>
      <p class="mt-1 text-sm text-muted">${from ? `Now in ${esc(from.name)}. ` : 'Not in a group yet. '}Pick a group. As an officer you can go past the size if you need to.</p>
      <div class="mt-4 max-h-72 space-y-2 overflow-y-auto">
        ${targets.map((g) => `
          <button type="button" data-to="${g.id}" class="flex w-full items-center gap-3 rounded-2xl bg-canvas p-3 text-left hover:bg-line">
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">${esc(g.name)}</span>
              <span class="block text-xs text-muted">${esc(UI.groupCount(g))}</span></span>
            <span class="chip ${UI.groupStateTone(g)} text-[11px]">${esc(g.state_label || '')}</span>
          </button>`).join('') || '<p class="text-sm text-muted">There’s no other group yet.</p>'}
      </div>
      ${from ? `<button type="button" data-out class="btn btn-danger mt-4 w-full">Take out of ${esc(from.name)}</button>` : ''}
      <button type="button" data-close class="btn btn-canvas mt-3 w-full">Cancel</button>`, {
      label: 'Move a classmate',
      onMount(el, close) {
        const run = async (body, msg) => {
          try { await API.post('groups.php', body); close(); UI.toast(msg); await reload(); } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
        };
        $$('[data-to]', el).forEach((b) => b.onclick = () => {
          const to = d.groups.find((g) => g.id === Number(b.dataset.to));
          run({ action: 'move_member', group_id: to.id, user_id: userId }, `${person?.name || 'They'} ${from ? 'moved' : 'added'} to ${to.name}`);
        });
        $('[data-out]', el)?.addEventListener('click', () => run({ action: 'remove_member', group_id: from.id, user_id: userId }, `${person?.name || 'They'} left ${from.name}`));
      },
    });
  },

  /** Rename a group (its leader or an officer). The server rejects a name another group of the task uses. */
  rename(group, reload) {
    UI.sheet(`
      <form>
        <h2 class="text-2xl font-bold">Rename group</h2>
        <p class="mt-1 text-sm text-muted">Everyone in ${esc(group.name)} gets a notification with the new name.</p>
        <div class="mt-4">${UI.field({ name: 'name', label: 'Group name', value: group.name, required: true })}</div>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">Save name</button>
      </form>`, {
      label: 'Rename group',
      onMount(el, close) {
        const input = $('input[name=name]', el);
        input.maxLength = 40;
        input.select();
        UI.bindForm($('form', el), async (f) => {
          await API.post('groups.php', { action: 'rename', group_id: group.id, name: f.name });
          close();
          UI.toast('Group renamed');
          reload();
        });
      },
    });
  },

  /** Make a group for this task (groupId = null) or add classmates to one — people you grouped with in person. */
  pickMembers(d, groupId, reload) {
    const me = Shell.me.user.id;
    const t = d.task;
    const rules = d.rules || t.rules || {};
    const officer = Shell.can('manage_any_group');
    const group = d.groups.find((g) => g.id === groupId);
    const newRoom = rules.base ? rules.base + (rules.extras_left > 0 ? 1 : 0) - 1 : 19;
    const room = officer ? 50 : group ? Math.max(0, UI.groupCap(group) - group.members.length) : newRoom;
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
        <h2 class="text-2xl font-bold">${group ? `Add to ${esc(group.name)}` : 'Make a group'}</h2>
        <p class="mt-1 text-sm text-muted">${group ? '' : 'You’ll be in it. '}${officer ? 'Pick classmates to add.' : `Pick up to ${room} classmate${room === 1 ? '' : 's'} you already grouped with.`} They’re added right away.</p>
        ${group ? '' : `<div class="mt-4">${UI.field({ name: 'name', label: 'Group name (optional)', placeholder: GroupWork.nextName(d.groups) })}</div>`}
        ${d.suggest && people.some((m) => hint(m)?.never) ? '<p class="mt-3 text-xs text-muted">Classmates you’ve never been in a group with are listed first.</p>' : ''}
        <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
          ${people.map((m) => `<label class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-canvas">
            <input type="checkbox" name="m" value="${m.id}" class="h-5 w-5 shrink-0 accent-charcoal">
            <span class="min-w-0 flex-1"><span class="block text-sm font-medium">${esc(m.name)}</span>
              <span class="mt-0.5 flex flex-wrap items-center gap-1.5"><span class="text-xs text-muted">${esc(m.student_id)}</span>${tag(m)}</span></span></label>`).join('') || '<p class="text-sm text-muted">Everyone else already has a group.</p>'}
        </div>
        <p class="mt-3 text-xs text-muted">Others can still ask to join later.</p>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">${group ? 'Add to group' : 'Make group'}</button>
      </form>`, {
      label: group ? 'Add classmates' : 'Make a group',
      onMount(el, close) {
        const form = $('form', el);
        UI.bindForm(form, async (f) => {
          const ids = $$('input[name=m]:checked', form).map((c) => Number(c.value));
          if (ids.length > room) throw new Error(`You can add at most ${room}.`);
          await API.post('groups.php', group
            ? { action: 'add_members', group_id: group.id, member_ids: ids }
            : { action: 'create', task_id: t.id, name: f.name, member_ids: ids });
          close();
          UI.toast(group ? 'Classmates added' : 'Group made');
          reload();
        });
      },
    });
  },
};
