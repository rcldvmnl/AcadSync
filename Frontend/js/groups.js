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
    if (groups && !this.data) this.load();
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
    $('#groups-panel').innerHTML = `
      <div class="grid gap-6 lg:grid-cols-[22rem_1fr] lg:items-start">
        <div class="space-y-4">
          <button id="create-group" class="btn btn-dark w-full">${icon('plus', 18)}New group</button>
          <section>
            <h2 class="mb-3 text-xl font-bold">Upcoming group work</h2>
            <div class="space-y-3">${d.upcoming.length ? d.upcoming.map((t) => this.upcomingCard(t, d.members)).join('') : UI.empty('No group work coming up', 'Group tasks will show here with who still needs a group.', 'users')}</div>
          </section>
        </div>
        <div class="space-y-6">
          <section>
            <h2 class="mb-3 text-xl font-bold">My groups <span class="text-sm font-medium text-muted">${d.mine.length}</span></h2>
            <div class="grid gap-3 sm:grid-cols-2">${d.mine.length ? d.mine.map((g) => this.groupCard(g)).join('') : `<div class="sm:col-span-2">${UI.empty('You’re not in a group yet', 'Create one or join a classmate’s group below.', 'users')}</div>`}</div>
          </section>
          <section>
            <h2 class="mb-3 text-xl font-bold">Other groups <span class="text-sm font-medium text-muted">${d.others.length}</span></h2>
            <div class="grid gap-3 sm:grid-cols-2">${d.others.length ? d.others.map((g) => this.groupCard(g)).join('') : '<p class="text-sm text-muted">No other groups yet.</p>'}</div>
          </section>
        </div>
      </div>`;
    this.bind();
  },

  upcomingCard(t, total) {
    const pct = total ? Math.round((t.grouped_count / total) * 100) : 0;
    return `
      <a href="task.html?id=${t.id}" class="block rounded-[1.5rem] bg-white p-4 shadow-card transition hover:-translate-y-0.5">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0"><div class="truncate font-semibold">${esc(t.title)}</div>
            <div class="text-xs text-muted">${esc(t.subject_code)} · ${esc(Fmt.due(t))}</div></div>
          ${t.in_group ? UI.chip('In a group', 'bg-mint-tint text-success') : UI.chip('Needs a group', 'bg-sun-tint')}
        </div>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-canvas"><div class="h-full rounded-full bg-success" style="width:${pct}%"></div></div>
        <div class="mt-1.5 flex justify-between text-xs text-muted"><span>${t.grouped_count}/${total} grouped · ${t.groups_formed} group${t.groups_formed === 1 ? '' : 's'}</span><span>${UI.groupSize(t)} per group</span></div>
      </a>`;
  },

  groupCard(g) {
    const me = Shell.me.user.id;
    const admin = Shell.me.user.role === 'admin';
    const full = g.max_members !== null && g.members.length >= g.max_members;
    const canManage = admin || g.created_by === me;
    const taskConflict = g.task && this.data.upcoming.find((t) => t.id === g.task.id)?.in_group;
    const tint = g.subject ? tone(g.subject.color).chip : 'bg-canvas';
    return `
      <article class="rounded-[1.5rem] bg-white p-4 shadow-card">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="font-semibold">${esc(g.name)}</div>
            ${g.purpose ? `<div class="text-xs text-muted">${esc(g.purpose)}</div>` : ''}
          </div>
          <span class="chip bg-canvas">${g.members.length}${g.max_members ? `/${g.max_members}` : ''}</span>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
          ${g.subject ? UI.chip(g.subject.code, tint) : ''}
          ${g.task ? `<a href="task.html?id=${g.task.id}" class="chip bg-charcoal text-white">${icon('users', 12)}${esc(g.task.title)}${g.task.deadline_ts ? ` · ${esc(Fmt.monthDay(new Date(g.task.deadline_ts * 1000)))}` : ''}</a>` : ''}
        </div>
        <div class="mt-3 flex -space-x-2">${g.members.slice(0, 6).map((m) => `<span title="${esc(m.name)}" class="grid h-8 w-8 place-items-center rounded-full border-2 border-white bg-lavender-tint text-[10px] font-semibold">${esc(Fmt.initials(m.name))}</span>`).join('')}${g.members.length > 6 ? `<span class="grid h-8 w-8 place-items-center rounded-full border-2 border-white bg-canvas text-[10px]">+${g.members.length - 6}</span>` : ''}</div>
        <div class="mt-1 truncate text-xs text-muted">${g.members.map((m) => esc(m.name)).join(', ')}</div>
        <div class="mt-3 flex flex-wrap gap-2">
          ${g.is_member ? `<button data-g-leave="${g.id}" class="chip bg-canvas px-3 py-1.5 text-xs font-semibold">Leave</button>` : ''}
          ${!g.is_member && !full && !taskConflict ? `<button data-g-join="${g.id}" class="chip bg-charcoal px-3 py-1.5 text-xs font-semibold text-white">Join</button>` : ''}
          ${!g.is_member && full ? '<span class="chip bg-canvas text-xs text-muted">Full</span>' : ''}
          ${canManage && !full ? `<button data-g-add="${g.id}" class="chip bg-canvas px-3 py-1.5 text-xs font-semibold">+ Add</button>` : ''}
          ${canManage ? `<button data-g-delete="${g.id}" class="chip bg-danger-tint px-3 py-1.5 text-xs font-semibold">Delete</button>` : ''}
        </div>
      </article>`;
  },

  bind() {
    const all = [...this.data.mine, ...this.data.others];
    const act = async (body, msg) => {
      try {
        await API.post('groups.php', body);
        UI.toast(msg);
        await this.load();
      } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
    };
    $('#create-group').onclick = () => this.openCreate();
    $$('[data-g-join]').forEach((b) => b.onclick = () => act({ action: 'join', group_id: Number(b.dataset.gJoin) }, 'You joined the group'));
    $$('[data-g-leave]').forEach((b) => b.onclick = () => act({ action: 'leave', group_id: Number(b.dataset.gLeave) }, 'You left the group'));
    $$('[data-g-delete]').forEach((b) => b.onclick = () => {
      const g = all.find((x) => x.id === Number(b.dataset.gDelete));
      UI.sheet(`<h2 class="text-2xl font-bold">Delete “${esc(g.name)}”?</h2>
        <p class="mt-2 text-sm text-muted">The group and its member list will be removed.</p>
        <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Cancel</button><button data-ok class="btn btn-dark">Delete</button></div>`, {
        label: 'Delete group',
        onMount: (el, close) => { $('[data-ok]', el).onclick = () => { close(); act({ action: 'delete', group_id: g.id }, 'Group deleted'); }; },
      });
    });
    $$('[data-g-add]').forEach((b) => b.onclick = () => this.openAdd(all.find((x) => x.id === Number(b.dataset.gAdd))));
  },

  memberChecklist(exclude = []) {
    const me = Shell.me.user.id;
    return this.people.filter((p) => p.id !== me && !exclude.includes(p.id)).map((p) => `
      <label class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-canvas">
        <input type="checkbox" name="m" value="${p.id}" class="h-5 w-5 accent-charcoal">
        <span class="flex-1 text-sm font-medium">${esc(p.last_name)}, ${esc(p.first_name)}</span>
        <span class="text-xs text-muted">${esc(p.student_id)}</span></label>`).join('');
  },

  /** New group: name, subject, what it's for, optional group task, size limit, members. */
  async openCreate() {
    await this.ready();
    if (!this.data) await this.load();
    const tasks = this.data.upcoming.filter((t) => !t.in_group);
    UI.sheet(`
      <form>
        <h2 class="text-2xl font-bold">New group</h2>
        <p class="mt-1 text-sm text-muted">You’ll be in it automatically.</p>
        <div class="mt-4 space-y-4">
          ${UI.field({ name: 'name', label: 'Group name', placeholder: 'e.g. Capstone B or Quiz Reviewers' })}
          <label class="block"><span class="mb-1.5 block text-xs text-muted">For a group task? (optional)</span>
            <span class="field"><select name="task_id"><option value="">No — a study / project group</option>
              ${tasks.map((t) => `<option value="${t.id}">${esc(t.title)} · ${esc(t.subject_code)} · ${UI.groupSize(t)} per group</option>`).join('')}
            </select>${icon('chevronDown', 18)}</span></label>
          <div data-free>
            <label class="block"><span class="mb-1.5 block text-xs text-muted">Subject (optional)</span>
              <span class="field"><select name="subject_id"><option value="">Any subject</option>
                ${this.subjects.map((s) => `<option value="${s.id}">${esc(s.code)} · ${esc(s.title)}</option>`).join('')}
              </select>${icon('chevronDown', 18)}</span></label>
            <label class="mt-4 block"><span class="mb-1.5 block text-xs text-muted">Size limit (optional)</span>
              <span class="field"><input name="max_members" type="number" min="2" max="50" placeholder="No limit"></span></label>
          </div>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">What is this group for?</span>
            <span class="field area"><textarea name="purpose" rows="2" maxlength="200" placeholder="e.g. Review for the OOP quiz every Wednesday"></textarea></span></label>
        </div>
        <div class="mt-4 text-xs text-muted">Add classmates</div>
        <div class="mt-2 max-h-56 space-y-1 overflow-y-auto">${this.memberChecklist()}</div>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">Create group</button>
      </form>`, {
      label: 'New group',
      onMount: (el, close) => {
        const form = $('form', el);
        form.elements.task_id.onchange = () => { $('[data-free]', form).hidden = !!form.elements.task_id.value; };
        UI.bindForm(form, async (f) => {
          const ids = $$('input[name=m]:checked', form).map((c) => Number(c.value));
          if (!f.task_id && !f.name.trim()) throw new Error('Give the group a name.');
          await API.post('groups.php', {
            action: 'create',
            name: f.name,
            task_id: f.task_id ? Number(f.task_id) : null,
            subject_id: f.subject_id ? Number(f.subject_id) : null,
            max_members: f.max_members ? Number(f.max_members) : null,
            purpose: f.purpose,
            member_ids: ids,
          });
          close();
          UI.toast('Group created', { sub: `${ids.length + 1} member${ids.length ? 's' : ''}` });
          this.data = null;
          this.show('groups');
        });
      },
    });
  },

  openAdd(g) {
    UI.sheet(`
      <form>
        <h2 class="text-2xl font-bold">Add to ${esc(g.name)}</h2>
        ${g.max_members ? `<p class="mt-1 text-sm text-muted">${g.max_members - g.members.length} spot${g.max_members - g.members.length === 1 ? '' : 's'} left.</p>` : ''}
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
GroupsTab.ready().then(() => GroupsTab.show(location.hash === '#groups' ? 'groups' : 'people'));
