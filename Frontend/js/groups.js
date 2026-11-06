// Classmates › Groups tab: one block per group assignment. Each group is drawn as its seats
// (filled seats are members, dashed seats are still open), next to the classmates who have no
// group yet. Join, leave, answer requests, create and (admin) auto-group all happen in place.
// Groups are stored in study_groups + group_members; on the server every pair of members is an
// edge in the classmates GRAPH. Data comes from classmates.js (Data).

const GroupsTab = {
  tab: 'people',

  boot() {
    $$('[data-tab]').forEach((b) => b.addEventListener('click', () => GroupsTab.show(b.dataset.tab)));
    // Arrow keys move between the two tabs
    $('.cm-tabs').addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      const next = GroupsTab.tab === 'people' ? 'groups' : 'people';
      GroupsTab.show(next);
      $(`[data-tab="${next}"]`).focus();
    });
    GroupsTab.fromHash();
    // Tapping Members / Groups in the nav while already on this page only changes the hash
    window.addEventListener('hashchange', () => GroupsTab.fromHash());
  },

  /** "#groups" opens the tab; "#groups&task=12" jumps to that assignment, "#groups&group=4" to that group. */
  fromHash() {
    const m = location.hash.match(/^#groups(?:&(task|group)=(\d+))?/);
    GroupsTab.show(m ? 'groups' : 'people', { keepHash: true });
    if (m && m[1] === 'task') GroupsTab.focus(`task-${m[2]}`, Number(m[2]));
    if (m && m[1] === 'group') GroupsTab.focus(`group-${m[2]}`);
  },

  show(tab, { keepHash = false } = {}) {
    GroupsTab.tab = tab;
    const groups = tab === 'groups';
    $('#groups-panel').hidden = !groups;
    $('#main').hidden = groups;
    $$('[data-tab]').forEach((b) => {
      const on = b.dataset.tab === tab;
      b.setAttribute('aria-selected', String(on));
      b.tabIndex = on ? 0 : -1;
    });
    if (!keepHash) history.replaceState(null, '', groups ? '#groups' : location.pathname + location.search);
    // Admin nav has separate Members / Groups buttons: light up the one for this tab
    $$('[data-nav="classmates"], [data-nav="groups"]').forEach((a) => {
      if (!$('[data-nav="groups"]')) return;
      const on = a.dataset.nav === (groups ? 'groups' : 'classmates');
      a.classList.toggle('active', on);
      if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    if (groups) GroupsTab.render();
  },

  /** Scroll to a block or group and mark it for a moment. */
  focus(id, taskId) {
    const el = document.getElementById(id);
    if (!el) { if (taskId) GroupsTab.openTask(taskId); return; }
    // A link into a folded subject opens it first
    const wrap = el.closest('[data-fold-wrap]');
    if (wrap && !wrap.classList.contains('is-expanded')) GroupsTab.setFold(wrap.dataset.foldWrap, true);
    el.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    el.classList.remove('cm-flash'); void el.offsetWidth; el.classList.add('cm-flash');
    el.focus({ preventScroll: true });
  },

  focusGroup(id) {
    GroupsTab.show('groups', { keepHash: true });
    history.replaceState(null, '', `#groups&group=${id}`);
    GroupsTab.focus(`group-${id}`);
  },

  async reload() {
    await Data.load();
    renderCounts();
    await People.search();
    if (GroupsTab.tab === 'groups') GroupsTab.render();
  },

  /* ------------------------------------------------------------ render */
  render() {
    if (!Data.groups) return;
    const panel = $('#groups-panel');
    const tasks = Data.assignments;
    const forTask = (id) => Data.allGroups.filter((g) => g.task && g.task.id === id);
    const ungrouped = (id) => Data.people.filter((p) => !Data.groupOf(p.id, id));
    const needs = (t) => (Data.admin ? ungrouped(t.id).length > 0 : !t.in_group);
    // What needs doing comes first, then the rest in due-date order
    const ordered = [...tasks.filter(needs), ...tasks.filter((t) => !needs(t))];

    const upcomingIds = new Set(tasks.map((t) => t.id));
    const earlier = Data.allGroups.filter((g) => g.task && !upcomingIds.has(g.task.id));
    const study = Data.allGroups.filter((g) => !g.task);

    // One fold per subject. A subject where you already have a group for everything starts folded.
    const subjects = [];
    ordered.forEach((t) => {
      let s = subjects.find((x) => x.code === t.subject_code);
      if (!s) subjects.push(s = { code: t.subject_code, title: t.subject_title || '', tasks: [] });
      s.tasks.push(t);
    });

    const folds = subjects.map((s) => {
      const doneAll = !s.tasks.some(needs);
      const chips = s.tasks.map((t) => {
        if (Data.admin) {
          const n = ungrouped(t.id).length;
          return `<span class="cm-fold-chip ${n ? 'is-todo' : ''}">${esc(t.title)}: ${n ? `${n} without a group` : 'everyone grouped'}</span>`;
        }
        const mine = forTask(t.id).find((g) => g.is_member);
        return `<span class="cm-fold-chip ${mine ? 'is-mine' : 'is-todo'}">${esc(t.title)}: ${mine ? esc(mine.label) : 'no group yet'}</span>`;
      }).join('');
      const head = `
        <span class="cm-fold-code">${esc(s.code)}</span>
        <span class="cm-fold-text">
          <span class="cm-fold-title">${esc(s.title || s.code)}</span>
          <span class="cm-fold-chips">${chips}</span>
        </span>`;
      const body = s.tasks.map((t) => GroupsTab.block(t, forTask(t.id), ungrouped(t.id))).join('');
      return GroupsTab.fold(`subject-${s.code}`, !doneAll, head, body, doneAll ? 'is-done' : 'is-open');
    }).join('');

    const plainHead = (title, note) => `<span class="cm-fold-text"><span class="cm-fold-title">${title}</span><span class="cm-fold-chips"><span class="cm-fold-chip">${note}</span></span></span>`;
    panel.innerHTML = `
      ${GroupsTab.summary(tasks, needs)}
      ${folds}
      ${!tasks.length ? `<section class="cm-block"><div class="cm-block-head"><div><h2 class="cm-block-title">Group assignments</h2>
          <p class="cm-sub">No group assignments are coming up. When one is published, its groups and who still needs one show here.</p></div></div></section>` : ''}
      ${study.length ? GroupsTab.fold('study', true, plainHead('Study groups', `${plural(study.length, 'group')}, not tied to an assignment`),
          `<section class="cm-block"><div class="cm-board">${study.map((g) => GroupsTab.tile(g, null)).join('')}</div></section>`, 'is-plain') : ''}
      ${earlier.length ? GroupsTab.fold('earlier', false, plainHead('Earlier group work', `${plural(earlier.length, 'group')} from past or unlisted assignments`),
          `<section class="cm-block"><div class="cm-board">${earlier.map((g) => GroupsTab.tile(g, null, { past: true })).join('')}</div></section>`, 'is-plain') : ''}`;
    GroupsTab.bind(panel);
    $$('[data-fold]', panel).forEach((b) => b.onclick = () => GroupsTab.setFold(b.dataset.fold, b.getAttribute('aria-expanded') !== 'true'));
  },

  /** A subject (or section) that folds open and shut like a dropdown. */
  fold(key, openByDefault, head, body, tone = '') {
    const open = GroupsTab.foldState()[key] ?? openByDefault;
    const id = `fold-${key.replace(/[^a-z0-9]+/gi, '-')}`;
    return `
      <div class="cm-fold ${tone} ${open ? 'is-expanded' : ''}" data-fold-wrap="${esc(key)}">
        <button type="button" class="cm-fold-head" data-fold="${esc(key)}" aria-expanded="${open}" aria-controls="${id}">
          ${head}
          <span class="cm-fold-toggle"><span class="cm-fold-toggle-label">${open ? 'Hide' : 'Show'}</span>${icon('chevronDown', 18)}</span>
        </button>
        <div class="cm-fold-body" id="${id}" ${open ? '' : 'hidden'}>${body}</div>
      </div>`;
  },

  /** Open or close one fold; the choice is remembered in this browser (works without storage too). */
  setFold(key, open) {
    const st = GroupsTab.foldState();
    st[key] = open;
    try { localStorage.setItem('acadsync.groupFolds', JSON.stringify(st)); } catch { /* storage blocked */ }
    const wrap = $(`[data-fold-wrap="${CSS.escape(key)}"]`);
    if (!wrap) return;
    wrap.classList.toggle('is-expanded', open);
    $('[data-fold]', wrap).setAttribute('aria-expanded', String(open));
    $('.cm-fold-toggle-label', wrap).textContent = open ? 'Hide' : 'Show';
    $('.cm-fold-body', wrap).hidden = !open;
  },
  foldState() {
    if (!GroupsTab._folds) {
      try { GroupsTab._folds = JSON.parse(localStorage.getItem('acadsync.groupFolds') || '{}'); } catch { GroupsTab._folds = {}; }
    }
    return GroupsTab._folds;
  },

  /** One line that says what to do, before any block. */
  summary(tasks, needs) {
    if (!tasks.length) return '';
    const todo = tasks.filter(needs);
    const waiting = Data.allGroups.filter((g) => (Data.admin || g.is_member)).reduce((n, g) => n + (g.requests?.length || 0), 0);
    const done = tasks.length - todo.length;
    const lead = Data.admin
      ? `<b>${plural(done, 'assignment')}</b> fully grouped, ${todo.length ? `<b>${todo.length}</b> still open` : 'nothing left to sort out'}`
      : `Grouped for <b>${done} of ${tasks.length}</b> ${tasks.length === 1 ? 'assignment' : 'assignments'}`;
    return `
      <div class="cm-summary">
        <span class="cm-summary-icon">${icon('sparkle', 16)}</span>
        <p>${lead}</p>
        ${waiting ? `<span class="cm-pill cm-pill-dark">${plural(waiting, 'join request')} to answer</span>` : ''}
      </div>`;
  },

  /** One group assignment: its rule, how many are grouped, every group as seats, and who is left. */
  block(t, groups, left) {
    const total = Data.people.length;
    const grouped = total - left.length;
    const mine = groups.find((g) => g.is_member);
    const asked = groups.filter((g) => g.my_request);
    const meLeft = left.some((p) => p.id === Data.me);
    const arrow = (name) => `<span class="cm-arrow">${icon(name, 18)}</span>`;
    let status;
    if (Data.admin) {
      status = left.length
        ? `<button type="button" data-auto="${t.id}" class="cm-cta">Auto-group the ${left.length} left${arrow('users')}</button>`
        : `<span class="cm-pill cm-pill-white">${icon('check', 14)}Everyone has a group</span>`;
    } else if (mine) {
      const short = mine.min_members !== null && mine.members.length < mine.min_members ? mine.min_members - mine.members.length : 0;
      status = `<span class="cm-pill cm-pill-dark">${icon('check', 14)}You’re in ${esc(mine.label)}</span>${short ? `<span class="cm-pill cm-pill-white">Needs ${short} more</span>` : ''}`;
    } else if (asked.length) {
      status = `<span class="cm-pill cm-pill-white">${icon('clock', 14)}Waiting on ${asked.map((g) => esc(g.label)).join(', ')}</span>`;
    } else if (t.can_create) {
      status = `<button type="button" data-create="${t.id}" class="cm-cta">Create a group${arrow('plus')}</button>`;
    } else {
      status = '<span class="cm-pill cm-pill-white">All groups are made, ask to join one below</span>';
    }

    // The class as a cluster of seats: filled bubbles have a group, dashed ones don't. You are ringed.
    const cap = 14;
    const order = [...Data.people.filter((p) => !left.includes(p)), ...left];
    const bubbles = order.slice(0, cap).map((p) => {
      const free = left.includes(p);
      return `<span class="cm-bubble ${free ? 'is-free' : ''} ${p.id === Data.me ? 'is-me' : ''}" style="${free ? '' : `background:${personTint(p.id)}`}" title="${esc(p.first_name)} ${esc(p.last_name)}${free ? ', no group yet' : ''}">${initialsOf(p.name)}</span>`;
    }).join('') + (order.length > cap ? `<span class="cm-bubble is-more">+${order.length - cap}</span>` : '');

    const tone = (Data.admin ? left.length : !mine) ? 'is-open' : 'is-done';
    return `
      <section class="cm-block ${tone}" id="task-${t.id}" tabindex="-1" aria-labelledby="task-${t.id}-h">
        <div class="cm-hero">
          <svg class="cm-hero-path" viewBox="0 0 400 200" preserveAspectRatio="none" aria-hidden="true"><path d="M-10 170 C 80 60, 150 210, 230 110 S 360 20, 420 70" /></svg>
          <div class="cm-hero-text">
            <p class="cm-hero-subject"><span>${esc(t.subject_code)}</span>${esc(Fmt.due(t))}</p>
            <h2 id="task-${t.id}-h" class="cm-hero-title">${esc(t.title)}</h2>
            <div class="cm-hero-stats">
              <span class="cm-pill cm-pill-dark cm-num">${grouped} of ${total} grouped</span>
              <span class="cm-pill cm-pill-soft cm-num">${t.group_count ? `${groups.length} of exactly ${t.group_count} groups` : plural(groups.length, 'group')}</span>
              <span class="cm-pill cm-pill-soft">${esc(UI.groupSize(t))} per group</span>
              ${t.extra_slots ? `<span class="cm-pill cm-pill-white">${t.extra_slots === 1 ? 'One full group' : `${t.extra_slots} full groups`} can take +1</span>` : ''}
            </div>
            ${t.group_count && t.extra_slots ? `<p class="cm-hero-rule">Only ${t.group_count} groups are allowed and ${plural(t.group_count * t.group_max, 'seat')} don’t fit all ${total} classmates, so ${t.extra_slots === 1 ? 'one full group' : `${t.extra_slots} full groups`} may add one extra member (${t.group_max + 1} instead of ${t.group_max}).</p>` : ''}
            ${Data.admin && t.unplaceable ? `<p class="cm-hero-rule"><b>${plural(t.unplaceable, 'classmate')} can’t fit</b> even with +1 in every group. Raise the group size or the number of groups in Task details.</p>` : ''}
            <div class="cm-hero-actions">${status}<a href="task.html?id=${t.id}" class="cm-link-btn">Task details</a></div>
          </div>
          <div class="cm-cluster" role="img" aria-label="${grouped} of ${total} classmates have a group">${bubbles}</div>
        </div>
        <div class="cm-board">
          ${groups.map((g) => GroupsTab.tile(g, t)).join('')}
          ${GroupsTab.pile(left, meLeft, groups.length)}
        </div>
      </section>`;
  },

  /** A group drawn as its seats. task = the upcoming assignment it belongs to (null for study/past groups). */
  tile(g, t, { past = false } = {}) {
    const me = Data.me;
    const cap = UI.groupCap(g);
    const open = cap === null ? 0 : Math.max(0, cap - g.members.length);
    const extraSeat = cap !== null && g.max_members !== null && cap > g.max_members;
    const full = cap !== null && g.members.length >= cap;
    const canManage = Data.admin || g.created_by === me;
    const inOther = t ? t.in_group && !g.is_member : false;
    const others = GroupsTab.addable(g).length;

    const seats = [
      ...g.members.map((m) => `
        <li class="cm-seat">
          ${avatar(m)}
          <span class="cm-seat-name">${esc(m.name)}${m.id === me ? ' <span class="cm-you">you</span>' : ''}</span>
          ${m.id === g.created_by ? '<span class="cm-seat-note">made it</span>' : ''}
        </li>`),
      ...Array.from({ length: open }, (_, i) => `<li class="cm-seat is-open"><span class="cm-avatar cm-avatar-open" aria-hidden="true">${icon('plus', 14)}</span><span class="cm-seat-name">${extraSeat && i === open - 1 ? '+1 seat for the extra classmate' : 'Open seat'}</span></li>`),
    ].join('');

    const reqs = g.requests?.length ? `
      <div class="cm-requests">
        <div class="cm-requests-title">${plural(g.requests.length, 'classmate')} ${g.requests.length === 1 ? 'wants' : 'want'} to join</div>
        ${g.requests.map((r) => `
          <div class="cm-request">
            ${avatar({ id: r.user_id, name: r.name })}
            <span class="cm-seat-name">${esc(r.name)}</span>
            <button type="button" data-decline="${r.id}" class="cm-btn cm-btn-ghost cm-btn-sm">Decline</button>
            <button type="button" data-accept="${r.id}" class="cm-btn cm-btn-primary cm-btn-sm" ${full ? 'disabled title="The group is full"' : ''}>Accept</button>
          </div>`).join('')}
      </div>` : '';

    const actions = past ? '' : [
      g.is_member ? `<button type="button" data-leave="${g.id}" class="cm-btn cm-btn-ghost cm-btn-sm">Leave</button>` : '',
      !g.is_member && g.my_request ? `<span class="cm-tag cm-tag-warn">${icon('clock', 12)}Request sent</span><button type="button" data-cancel="${g.id}" class="cm-btn cm-btn-ghost cm-btn-sm">Cancel</button>` : '',
      !g.is_member && !g.my_request && !full && !inOther && !Data.admin ? `<button type="button" data-join="${g.id}" class="cm-cta cm-cta-sm">Ask to join<span class="cm-arrow">${icon('arrowRight', 16)}</span></button>` : '',
      canManage && !full && others ? `<button type="button" data-add="${g.id}" class="cm-btn cm-btn-ghost cm-btn-sm">${icon('plus', 14)}Add</button>` : '',
      canManage ? `<button type="button" data-delete="${g.id}" class="cm-btn cm-btn-text cm-btn-sm">Delete</button>` : '',
    ].join('');

    const sizeLabel = cap === null ? `${g.members.length}` : `${g.members.length}/${g.max_members ?? cap}${extraSeat ? ' +1' : ''}`;
    const under = g.min_members !== null && g.members.length < g.min_members;
    return `
      <article class="cm-group ${g.is_member ? 'is-mine' : ''}" id="group-${g.id}" tabindex="-1" aria-labelledby="group-${g.id}-h">
        <header class="cm-group-head">
          <h3 id="group-${g.id}-h" class="cm-group-name">${esc(g.label)}</h3>
          <span class="cm-group-size cm-num" aria-label="${g.members.length} members${cap !== null ? ` of ${cap} seats` : ''}">${sizeLabel}</span>
        </header>
        ${!t ? `<p class="cm-group-meta">${g.subject ? esc(g.subject.code) : 'Any subject'}${g.task ? `, for ${esc(g.task.title)}` : ''}</p>` : ''}
        ${g.purpose ? `<p class="cm-group-meta">${esc(g.purpose)}</p>` : ''}
        <ul class="cm-seats">${seats}</ul>
        ${under && !past ? `<p class="cm-group-meta cm-warn-ink">Needs ${g.min_members - g.members.length} more to reach ${g.min_members}</p>` : ''}
        ${reqs}
        ${actions.trim() ? `<div class="cm-group-actions">${actions}</div>` : ''}
      </article>`;
  },

  /** The classmates with no group yet for this assignment. */
  pile(left, meLeft, groupCount) {
    if (!left.length) {
      return `<div class="cm-pile is-done"><div class="cm-group-head"><h3 class="cm-group-name">No group yet</h3><span class="cm-group-size cm-num">0</span></div>
        <p class="cm-group-meta">Everyone is in one of the ${plural(groupCount, 'group')}.</p></div>`;
    }
    return `
      <div class="cm-pile">
        <div class="cm-group-head"><h3 class="cm-group-name">No group yet</h3><span class="cm-group-size cm-num">${left.length}</span></div>
        <ul class="cm-seats">${left.map((p) => `
          <li class="cm-seat">${avatar(p)}<span class="cm-seat-name">${esc(p.first_name)} ${esc(p.last_name)}${p.id === Data.me ? ' <span class="cm-you">you</span>' : ''}</span></li>`).join('')}
        </ul>
        ${meLeft && !Data.admin ? '<p class="cm-group-meta">Grouped with someone in person? Create the group and add them, or ask to join a group with an open seat.</p>' : ''}
      </div>`;
  },

  /** Classmates who could still be added to g (not in it, and without a group for its assignment). */
  addable(g) {
    return Data.people.filter((p) => !g.members.some((m) => m.id === p.id)
      && (g.task ? !Data.groupOf(p.id, g.task.id) : true));
  },

  /* ------------------------------------------------------------ actions */
  bind(root) {
    const act = async (btn, body, msg) => {
      btn.disabled = true;
      try {
        await API.post('groups.php', body);
        UI.toast(msg);
        await GroupsTab.reload();
      } catch (e) {
        btn.disabled = false;
        UI.toast(e.message, { iconName: 'alert' });
      }
    };
    const on = (attr, fn) => $$(`[${attr}]`, root).forEach((b) => b.onclick = () => fn(Number(b.getAttribute(attr)), b));
    const find = (id) => Data.allGroups.find((g) => g.id === id);
    on('data-join', (id, b) => act(b, { action: 'request_join', group_id: id }, 'Request sent'));
    on('data-cancel', (id, b) => act(b, { action: 'cancel_request', group_id: id }, 'Request cancelled'));
    on('data-accept', (id, b) => act(b, { action: 'respond_request', request_id: id, accept: true }, 'Added to the group'));
    on('data-decline', (id, b) => act(b, { action: 'respond_request', request_id: id, accept: false }, 'Request declined'));
    on('data-leave', (id, b) => GroupsTab.confirm(`Leave ${find(id).label}?`, 'Your seat opens for someone else. You can ask to join again later.', 'Leave group',
      () => act(b, { action: 'leave', group_id: id }, 'You left the group')));
    on('data-delete', (id, b) => GroupsTab.confirm(`Delete “${find(id).label}”?`, `The group and its member list (${plural(find(id).members.length, 'person', 'people')}) will be removed.`, 'Delete group',
      () => act(b, { action: 'delete', group_id: id }, 'Group deleted')));
    on('data-auto', (id, b) => {
      const t = Data.assignments.find((x) => x.id === id);
      const n = Data.people.filter((p) => !Data.groupOf(p.id, id)).length;
      GroupsTab.confirm(`Auto-group the ${n} left?`, `They’re shuffled into groups of ${UI.groupSize(t)} for “${t.title}”, filling open seats first.`, 'Auto-group',
        () => act(b, { action: 'auto_assign', task_id: id }, 'Remaining classmates were grouped'));
    });
    on('data-create', (id) => GroupsTab.pick(id, null));
    on('data-add', (id) => { const g = find(id); GroupsTab.pick(g.task?.id ?? null, g); });
  },

  /** Create a group for assignment taskId, or add classmates to group g. Uses the shared picker. */
  pick(taskId, g) {
    const t = Data.assignments.find((x) => x.id === taskId) || { id: taskId, group_max: UI.groupCap(g) ?? 99 };
    const d = {
      task: t,
      groups: Data.allGroups.filter((x) => (taskId ? x.task?.id === taskId : x.id === g?.id)),
      ungrouped: (g ? GroupsTab.addable(g) : Data.people.filter((p) => !Data.groupOf(p.id, taskId)))
        .map((p) => ({ id: p.id, name: `${p.first_name} ${p.last_name}`, student_id: p.student_id })),
    };
    GroupWork.pickMembers(d, g ? g.id : null, () => GroupsTab.reload());
  },

  confirm(title, text, ok, run) {
    UI.sheet(`<h2 class="text-2xl font-bold">${esc(title)}</h2>
      <p class="mt-2 text-sm text-[#5C5C66]">${esc(text)}</p>
      <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Cancel</button><button data-ok class="btn btn-dark">${esc(ok)}</button></div>`, {
      label: title,
      onMount: (el, close) => { $('[data-ok]', el).onclick = () => { close(); run(); }; },
    });
  },

  /** Fallback for links to an assignment that isn't listed here (e.g. not upcoming): the per-task sheet. */
  openTask(taskId) {
    const sheet = UI.sheet(`
      <div class="mb-4"><h2 class="text-2xl font-bold leading-tight">Group work</h2>
        <a href="task.html?id=${taskId}" class="mt-1 inline-block text-xs font-semibold underline">Open task details</a></div>
      <div id="gw-sheet">${UI.skeleton(2, 'h-24')}</div>`, { label: 'Group work' });
    let changed = false;
    GroupWork.mount($('#gw-sheet', sheet.el), taskId, { onChange: () => { changed = true; } });
    new MutationObserver((_, obs) => {
      if (!document.body.contains(sheet.el)) { obs.disconnect(); if (changed) GroupsTab.reload(); }
    }).observe(document.body, { childList: true });
  },
};
