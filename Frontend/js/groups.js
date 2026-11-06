// Classmates › Groups tab: upcoming group work, my groups, other groups, create/join/leave.
// Groups are stored in study_groups + group_members; on the server every pair of
// members becomes an edge in the classmates GRAPH.

const GroupsTab = {
  data: null,
  subjects: [],
  people: [],

  async ready() {
    while (!Shell.me) await new Promise((r) => setTimeout(r, 50));
  },

  show(tab) {
    const groups = tab === 'groups';
    $('#groups-panel').hidden = !groups;
    $('#main').hidden = groups;
    $$('[data-tab]').forEach((b) => {
      const on = b.dataset.tab === tab;
      b.className = `rounded-full py-2.5 text-sm font-semibold ${on ? 'bg-charcoal text-white' : 'text-muted'}`;
      b.setAttribute('aria-selected', String(on));
    });
    history.replaceState(null, '', groups ? '#groups' : location.pathname + location.search);
    // Admin nav has separate Members / Groups buttons: light up the one for this tab
    if ($('[data-nav="groups"]')) {
      $$('[data-nav="classmates"], [data-nav="groups"]').forEach((a) => {
        const on = a.dataset.nav === (groups ? 'groups' : 'classmates');
        a.classList.toggle('active', on);
        if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
      });
    }
    if (groups && !this.data) return this.load();
  },

  async load() {
    const panel = $('#groups-panel');
    if (!this.data) panel.innerHTML = UI.skeleton(3, 'h-28');
    try {
      const [d, subj, people] = await Promise.all([
        API.get('groups.php'),
        API.get('subjects.php'),
        API.get('classmates.php'),
      ]);
      this.data = d;
      this.subjects = subj.subjects;
      this.people = people.members;
      this.render();
    } catch (e) {
      panel.innerHTML = UI.errorBox(e);
    }
  },

  render() {
    const d = this.data;
    // Current = due within the next 7 days (or a date range that has already started); Upcoming = later / no date yet
    const soon = Date.now() / 1000 + 7 * 86400;
    const today = Fmt.iso(new Date());
    const isCurrent = (t) => t.deadline_ts && (t.deadline_ts <= soon || (t.deadline_type === 'range' && t.deadline <= today));
    const current = d.upcoming.filter(isCurrent);
    const later = d.upcoming.filter((t) => !isCurrent(t));
    // Admin monitors the whole section; students see their own status
    const admin = Shell.me.user.role === 'admin';
    const needs = admin
      ? d.upcoming.reduce((n, t) => n + Math.max(0, d.members - t.grouped_count), 0)
      : d.upcoming.filter((t) => !t.in_group).length;
    const waiting = admin
      ? [...d.mine, ...d.others].reduce((n, g) => n + (g.requests?.length || 0), 0)
      : d.upcoming.reduce((n, t) => n + t.requests_waiting, 0);
    const list = (items, empty) => (items.length
      ? `<div class="space-y-3">${items.map((t) => this.upcomingCard(t, d.members)).join('')}</div>`
      : `<p class="rounded-2xl bg-white/70 p-4 text-sm text-muted">${empty}</p>`);
    $('#groups-panel').innerHTML = `
      <div class="grid gap-6 lg:grid-cols-[22rem_1fr] lg:items-start">
        <div class="space-y-4">
          ${d.upcoming.length ? `<div class="grid grid-cols-2 gap-3">
            <div class="rounded-[1.5rem] ${needs ? 'bg-sun-tint' : 'bg-mint-tint'} p-4"><div class="text-3xl font-extrabold">${needs}</div><div class="text-xs text-muted">${admin ? 'classmate slots still without a group' : 'group work without your group'}</div></div>
            <div class="rounded-[1.5rem] ${waiting ? 'bg-lavender-tint' : 'bg-white shadow-card'} p-4"><div class="text-3xl font-extrabold">${waiting}</div><div class="text-xs text-muted">join request${waiting === 1 ? '' : 's'} ${admin ? 'waiting in the section' : 'for your groups'}</div></div>
          </div>` : ''}
          <section>
            <div class="mb-3 flex items-baseline justify-between"><h2 class="text-xl font-bold">Current group work</h2><span class="text-xs text-muted">next 7 days</span></div>
            ${list(current, 'No group work due this week.')}
          </section>
          <section>
            <div class="mb-3 flex items-baseline justify-between"><h2 class="text-xl font-bold">Upcoming group work</h2><span class="text-xs text-muted">later</span></div>
            ${d.upcoming.length ? list(later, 'Nothing else coming up.') : UI.empty('No group work yet', 'Group tasks will show here with who still needs a group.', 'users')}
          </section>
        </div>
        <div class="space-y-6">
          <section>
            <h2 class="mb-3 text-xl font-bold">My groups <span class="text-sm font-medium text-muted">${d.mine.length}</span></h2>
            <div class="grid gap-3 lg:grid-cols-2">${d.mine.length ? d.mine.map((g) => this.groupCard(g)).join('') : `<div class="lg:col-span-2">${UI.empty('You’re not in a group yet', 'Create one or join a classmate’s group below.', 'users')}</div>`}</div>
          </section>
          <section>
            <h2 class="mb-3 text-xl font-bold">Other groups <span class="text-sm font-medium text-muted">${d.others.length}</span></h2>
            <div class="grid gap-3 lg:grid-cols-2">${d.others.length ? d.others.map((g) => this.groupCard(g)).join('') : '<p class="text-sm text-muted">No other groups yet.</p>'}</div>
          </section>
        </div>
      </div>`;
    this.bind();
  },

  upcomingCard(t, total) {
    const pct = total ? Math.round((t.grouped_count / total) * 100) : 0;
    const left = Math.max(0, total - t.grouped_count);
    const status = Shell.me.user.role === 'admin'
      ? `<div class="flex items-center gap-2 rounded-xl ${left ? 'bg-sun-tint' : 'bg-mint-tint'} px-3 py-2 text-xs">${icon(left ? 'users' : 'check', 14)}<span class="min-w-0 flex-1 truncate"><b>${left ? `${left} classmate${left === 1 ? '' : 's'} still need a group` : 'Everyone has a group'}</b>${left ? ' · tap to auto-group or add' : ''}</span></div>`
      : t.my_group
      ? `<div class="flex items-center gap-2 rounded-xl bg-mint-tint px-3 py-2 text-xs"><span class="text-success">${icon('check', 14)}</span><span class="min-w-0 flex-1 truncate"><b>You’re in ${esc(t.my_group.name)}</b> · ${t.my_group.size} member${t.my_group.size === 1 ? '' : 's'}</span>
          ${t.requests_waiting ? `<span class="chip bg-charcoal px-2 py-0.5 text-[10px] font-bold text-white">${t.requests_waiting} want${t.requests_waiting === 1 ? 's' : ''} to join</span>` : ''}</div>`
      : `<div class="flex items-center gap-2 rounded-xl bg-sun-tint px-3 py-2 text-xs">${icon('users', 14)}<span class="min-w-0 flex-1 truncate"><b>No group yet</b>${t.my_requests ? ` · request sent, waiting` : ' · tap to create or join'}</span></div>`;
    return `
      <button type="button" data-open-task="${t.id}" class="block w-full rounded-[1.5rem] bg-white p-4 text-left shadow-card ring-1 ${(Shell.me.user.role === 'admin' ? !left : t.in_group) ? 'ring-transparent' : 'ring-sun'} transition hover:-translate-y-0.5">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0"><div class="truncate font-semibold">${esc(t.title)}</div>
            <div class="text-xs text-muted">${esc(t.subject_code)} · ${esc(Fmt.due(t))}</div></div>
          ${icon('chevronRight', 18, 'mt-0.5 shrink-0 text-muted')}
        </div>
        <div class="mt-3">${status}</div>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-canvas"><div class="h-full rounded-full bg-success" style="width:${pct}%"></div></div>
        <div class="mt-1.5 flex justify-between text-xs text-muted"><span>${t.grouped_count}/${total} grouped · ${t.groups_formed} group${t.groups_formed === 1 ? '' : 's'}</span><span>${UI.groupRule(t)}</span></div>
      </button>`;
  },

  /** One group task: every group, create a group, request to join, answer requests. */
  openTask(taskId) {
    const t = this.data?.upcoming.find((x) => x.id === taskId);
    let changed = false;
    const sheet = UI.sheet(`
      <div class="mb-4">
        <div class="text-xs text-muted">${t ? `${esc(t.subject_code)} · ${esc(Fmt.due(t))}` : 'Group work'}</div>
        <h2 class="text-2xl font-bold leading-tight">${t ? esc(t.title) : 'Groups'}</h2>
        <a href="task.html?id=${taskId}" class="mt-1 inline-block text-xs font-semibold underline">Open task details</a>
      </div>
      <div id="gw-sheet">${UI.skeleton(2, 'h-24')}</div>`, { label: 'Group work' });
    GroupWork.mount($('#gw-sheet', sheet.el), taskId, { onChange: () => { changed = true; } });
    // Refresh the tab once the sheet is closed, if anything changed
    new MutationObserver((_, obs) => {
      if (!document.body.contains(sheet.el)) { obs.disconnect(); if (changed) this.load(); }
    }).observe(document.body, { childList: true });
  },

  /** Subject on top, then the group, the task it is grouped for, members. Tap to see full details. */
  groupCard(g) {
    const full = UI.groupCap(g) !== null && g.members.length >= UI.groupCap(g);
    const t = g.subject ? tone(g.subject.color) : null;
    return `
      <article class="flex flex-col overflow-hidden rounded-[1.5rem] bg-white shadow-card">
        <button type="button" data-g-open="${g.id}" class="block w-full min-w-0 text-left transition hover:bg-canvas/50" aria-label="See details of ${esc(g.name)}">
          <div class="flex items-center gap-2 px-4 py-2.5 ${t ? t.chip : 'bg-canvas'}">
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-white ${t ? t.icon : 'text-muted'}">${icon(g.subject ? 'notebook' : 'users', 14)}</span>
            <span class="min-w-0 flex-1 leading-tight">
              <span class="block text-[10px] font-bold uppercase tracking-wide">${g.subject ? esc(g.subject.code) : 'Any subject'}</span>
              <span class="block truncate text-xs text-muted">${g.subject ? esc(g.subject.title) : 'Study group'}</span>
            </span>
            ${icon('chevronRight', 16, 'shrink-0 text-muted')}
          </div>
          <div class="px-4 pt-3">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 font-semibold leading-snug">${esc(g.name)}${g.is_member ? ' <span class="text-xs font-medium text-lavender-dark">· yours</span>' : ''}</div>
              <span class="chip shrink-0 ${full ? 'bg-charcoal text-white' : 'bg-canvas'}">${UI.groupBadge(g)}</span>
            </div>
            ${g.task
              ? `<div class="mt-1.5 flex min-w-0 items-center gap-1.5 text-xs"><span class="shrink-0 text-muted">Part of</span>
                  <span class="flex min-w-0 items-center gap-1 rounded-full bg-sun-tint px-2 py-0.5 font-semibold">${icon('users', 11, 'shrink-0')}<span class="truncate">${esc(g.task.title)}</span></span>
                  ${g.task.deadline_ts ? `<span class="shrink-0 text-muted">· ${esc(Fmt.monthDay(new Date(g.task.deadline_ts * 1000)))}</span>` : ''}</div>`
              : '<div class="mt-1.5 text-xs text-muted">Study group · not tied to a task</div>'}
            ${g.purpose ? `<div class="mt-1 line-clamp-2 text-xs text-muted">${esc(g.purpose)}</div>` : ''}
            <div class="mt-3 flex items-center gap-2">
              <div class="flex shrink-0 -space-x-2">${g.members.slice(0, 4).map((m) => `<span title="${esc(m.name)}" class="grid h-7 w-7 place-items-center rounded-full border-2 border-white bg-lavender-tint text-[9px] font-semibold">${esc(Fmt.initials(m.name))}</span>`).join('')}${g.members.length > 4 ? `<span class="grid h-7 w-7 place-items-center rounded-full border-2 border-white bg-canvas text-[9px]">+${g.members.length - 4}</span>` : ''}</div>
              <span class="min-w-0 flex-1 truncate text-xs text-muted">${g.members.map((m) => esc(m.name.split(' ')[0])).join(', ')}</span>
            </div>
          </div>
        </button>
        ${g.requests?.length ? `<div class="mx-4 mt-3 rounded-xl bg-lavender-tint px-3 py-2 text-xs"><b>${g.requests.length}</b> want${g.requests.length === 1 ? 's' : ''} to join · tap the card to answer</div>` : ''}
        <div class="mt-auto flex flex-wrap items-center gap-2 px-4 pb-4 pt-3">${this.actions(g, { compact: true })}</div>
      </article>`;
  },

  /** Leave / request / cancel / add / delete buttons — shared by the card and the details sheet. */
  actions(g, { compact = false } = {}) {
    const me = Shell.me.user.id;
    const admin = Shell.me.user.role === 'admin';
    const full = UI.groupCap(g) !== null && g.members.length >= UI.groupCap(g);
    const canManage = admin || g.created_by === me;
    const taskConflict = g.task && this.data.upcoming.find((t) => t.id === g.task.id)?.in_group;
    const b = 'chip px-3 py-1.5 text-xs font-semibold';
    return `
      ${g.is_member ? `<button data-g-leave="${g.id}" class="${b} bg-canvas">Leave</button>` : ''}
      ${!g.is_member && g.my_request ? `<span class="chip bg-sun-tint px-3 py-1.5 text-xs">${icon('clock', 12)}Request sent</span><button data-g-cancel="${g.id}" class="${b} bg-canvas">Cancel</button>` : ''}
      ${!g.is_member && !g.my_request && !full && !taskConflict ? `<button data-g-join="${g.id}" class="${b} bg-charcoal text-white">${icon('users', 12)}Request to join</button>` : ''}
      ${canManage && !full ? `<button data-g-add="${g.id}" class="${b} bg-canvas">+ Add</button>` : ''}
      ${canManage && !compact ? `<button data-g-delete="${g.id}" class="${b} bg-danger-tint">Delete group</button>` : ''}`;
  },

  /** Group details: subject, which grouping (task) it belongs to, purpose, everyone in it, join requests. */
  openGroup(g) {
    const me = Shell.me.user.id;
    const full = UI.groupCap(g) !== null && g.members.length >= UI.groupCap(g);
    const t = g.subject ? tone(g.subject.color) : null;
    const task = g.task ? this.data.upcoming.find((x) => x.id === g.task.id) : null;
    UI.sheet(`
      <div class="mb-4 rounded-2xl px-4 py-3 ${t ? t.chip : 'bg-canvas'}">
        <div class="text-[11px] font-bold uppercase tracking-wide">${g.subject ? esc(g.subject.code) : 'Any subject'}</div>
        <div class="text-sm">${g.subject ? esc(g.subject.title) : 'Study group'}</div>
      </div>
      <div class="flex items-start justify-between gap-3">
        <h2 class="text-2xl font-bold leading-tight">${esc(g.name)}</h2>
        <span class="chip mt-1 shrink-0 ${full ? 'bg-charcoal text-white' : 'bg-canvas'}">${UI.groupBadge(g)} members</span>
      </div>
      ${g.purpose ? `<p class="mt-1 text-sm text-muted">${esc(g.purpose)}</p>` : ''}

      <div class="mt-4 rounded-2xl ${g.task ? 'bg-sun-tint' : 'bg-canvas'} p-4">
        <div class="text-xs text-muted">Part of</div>
        ${g.task ? `
          <div class="mt-0.5 flex items-center gap-2 font-semibold">${icon('users', 16, 'shrink-0')}<span class="min-w-0">${esc(g.task.title)}</span></div>
          <div class="mt-0.5 text-xs text-muted">${task ? esc(Fmt.due(task)) : g.task.deadline_ts ? `Due ${esc(Fmt.short(new Date(g.task.deadline_ts * 1000)))}` : 'Date not set yet'}${task ? ` · ${task.groups_formed} group${task.groups_formed === 1 ? '' : 's'} · ${UI.groupRule(task)}` : ''}</div>
          <div class="mt-3 grid grid-cols-2 gap-2">
            ${task ? `<button type="button" data-g-alltask="${g.task.id}" class="btn btn-dark h-10 text-sm">All groups</button>` : ''}
            <a href="task.html?id=${g.task.id}" class="btn btn-light h-10 text-sm ${task ? '' : 'col-span-2'}">Task details</a>
          </div>`
          : '<div class="mt-0.5 text-sm font-semibold">A study / project group — not tied to a task</div>'}
      </div>

      <h3 class="mt-5 text-sm font-semibold">Members</h3>
      <div class="mt-2 space-y-1">
        ${g.members.map((m) => `
          <div class="flex items-center gap-3 rounded-2xl px-1 py-1.5">
            ${UI.avatar(m.name, 'bg-lavender-tint', 'h-9 w-9 text-xs')}
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">${esc(m.name)}${m.id === me ? ' <span class="text-muted">(you)</span>' : ''}</span>
              <span class="block text-xs text-muted">${esc(m.student_id)}</span></span>
            ${m.id === g.created_by ? '<span class="chip bg-canvas text-[11px]">Made the group</span>' : ''}
          </div>`).join('')}
      </div>

      ${g.requests?.length ? `
        <div class="mt-4 space-y-2 rounded-2xl bg-lavender-tint p-3">
          <div class="text-sm font-semibold">Wants to join (${g.requests.length})</div>
          ${g.requests.map((r) => `<div class="flex items-center gap-2">
            ${UI.avatar(r.name, 'bg-white', 'h-8 w-8 text-[10px]')}
            <span class="min-w-0 flex-1 truncate text-sm font-medium">${esc(r.name)}</span>
            <button data-g-decline="${r.id}" class="chip bg-white px-3 py-1.5 text-xs font-semibold">Decline</button>
            <button data-g-accept="${r.id}" class="chip bg-charcoal px-3 py-1.5 text-xs font-semibold text-white" ${full ? 'disabled title="Group is full"' : ''}>Accept</button></div>`).join('')}
        </div>` : ''}
      <div class="mt-5 flex flex-wrap gap-2">${this.actions(g)}</div>`, {
      label: g.name,
      onMount: (el, close) => {
        this.bindActions(el, close);
        $('[data-g-alltask]', el)?.addEventListener('click', () => { close(); this.openTask(g.task.id); });
      },
    });
  },

  bind() {
    $$('[data-open-task]').forEach((b) => b.onclick = () => this.openTask(Number(b.dataset.openTask)));
    const all = [...this.data.mine, ...this.data.others];
    $$('[data-g-open]').forEach((b) => b.onclick = () => this.openGroup(all.find((x) => x.id === Number(b.dataset.gOpen))));
    this.bindActions($('#groups-panel'));
  },

  /** Wires the group action buttons inside root; close() shuts the sheet they're in (if any). */
  bindActions(root, close = () => {}) {
    const all = [...this.data.mine, ...this.data.others];
    const act = async (body, msg) => {
      try {
        await API.post('groups.php', body);
        UI.toast(msg);
        close();
        await this.load();
      } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
    };
    const on = (attr, fn) => $$(`[${attr}]`, root).forEach((b) => b.onclick = () => fn(Number(b.getAttribute(attr))));
    on('data-g-join', (id) => act({ action: 'request_join', group_id: id }, 'Request sent'));
    on('data-g-cancel', (id) => act({ action: 'cancel_request', group_id: id }, 'Request cancelled'));
    on('data-g-accept', (id) => act({ action: 'respond_request', request_id: id, accept: true }, 'Added to your group'));
    on('data-g-decline', (id) => act({ action: 'respond_request', request_id: id, accept: false }, 'Request declined'));
    on('data-g-leave', (id) => act({ action: 'leave', group_id: id }, 'You left the group'));
    on('data-g-add', (id) => { close(); this.openAdd(all.find((x) => x.id === id)); });
    on('data-g-delete', (id) => {
      const g = all.find((x) => x.id === id);
      close();
      UI.sheet(`<h2 class="text-2xl font-bold">Delete “${esc(g.name)}”?</h2>
        <p class="mt-2 text-sm text-muted">The group and its member list will be removed.</p>
        <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Cancel</button><button data-ok class="btn btn-dark">Delete</button></div>`, {
        label: 'Delete group',
        onMount: (el, closeDel) => { $('[data-ok]', el).onclick = () => { closeDel(); act({ action: 'delete', group_id: g.id }, 'Group deleted'); }; },
      });
    });
  },

  memberChecklist(exclude = []) {
    const me = Shell.me.user.id;
    return this.people.filter((p) => p.id !== me && !exclude.includes(p.id)).map((p) => `
      <label class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-canvas">
        <input type="checkbox" name="m" value="${p.id}" class="h-5 w-5 accent-charcoal">
        <span class="flex-1 text-sm font-medium">${esc(p.last_name)}, ${esc(p.first_name)}</span>
        <span class="text-xs text-muted">${esc(p.student_id)}</span></label>`).join('');
  },

  openAdd(g) {
    UI.sheet(`
      <form>
        <h2 class="text-2xl font-bold">Add to ${esc(g.name)}</h2>
        ${UI.groupCap(g) ? `<p class="mt-1 text-sm text-muted">${UI.groupCap(g) - g.members.length} spot${UI.groupCap(g) - g.members.length === 1 ? '' : 's'} left${UI.groupCap(g) > g.max_members ? ' (including 1 extra for leftover classmates)' : ''}.</p>` : ''}
        <div class="mt-4 max-h-64 space-y-1 overflow-y-auto">${this.memberChecklist(g.members.map((m) => m.id))}</div>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">Add</button>
      </form>`, {
      label: 'Add classmates',
      onMount: (el, close) => {
        const form = $('form', el);
        UI.bindForm(form, async () => {
          const ids = $$('input[name=m]:checked', form).map((c) => Number(c.value));
          const res = await API.post('groups.php', { action: 'add_members', group_id: g.id, member_ids: ids });
          close();
          UI.toast(res.added ? `Added ${res.added}` : 'No one added', { sub: res.added < ids.length ? 'Some were already in a group for this task' : '' });
          await this.load();
        });
      },
    });
  },
};

$$('[data-tab]').forEach((b) => b.addEventListener('click', () => GroupsTab.show(b.dataset.tab)));
// "#groups" opens the tab; "#groups&task=12" also opens that group task (links from Schedule / notifications)
const groupsHash = location.hash.match(/^#groups(?:&task=(d+))?/);
GroupsTab.ready().then(async () => {
  await GroupsTab.show(groupsHash ? 'groups' : 'people');
  if (groupsHash && groupsHash[1]) GroupsTab.openTask(Number(groupsHash[1]));
});

// Tapping Members / Groups in the nav while already on this page only changes the hash
window.addEventListener('hashchange', () => {
  if (Shell.me) GroupsTab.show(location.hash.startsWith('#groups') ? 'groups' : 'people');
});
