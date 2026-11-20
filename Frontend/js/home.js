// Home dashboard — student and admin variants.

(async () => {
  const [me, data] = await Promise.all([
    API.get('auth.php'),
    API.get('schedule.php'),
  ]);
  const admin = me.user.role === 'admin';
  const now = new Date();
  await Shell.init({
    active: 'home',
    avatar: true,
    eyebrow: admin ? `Admin · ${me.section.name}` : `${DAYS_LONG[now.getDay()]}, ${Fmt.monthDay(now)}`,
    title: `${Fmt.greeting()}, ${me.user.first_name}`,
  });
  $('#main').innerHTML = admin ? adminView(data) : studentView(data);
  Announcements.mount($('#announcements'));
  if (!admin) { groupReminder(); duesReminder(); }
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

/** "You owe ₱150 in dues" — shown above the dashboard while a due is unpaid. */
async function duesReminder() {
  try {
    const d = await API.get('dues.php');
    if (!d.my_balance) return;
    $('#main').insertAdjacentHTML('afterbegin', `
      <a href="dues.html" class="mb-5 flex items-center gap-3 rounded-[1.5rem] bg-peach-tint p-4">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white">${icon('clipboard', 18)}</span>
        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">You have ${esc(d.my_balance_label)} in unpaid dues</span>
          <span class="block truncate text-xs text-muted">Pay the Treasurer in cash or by GCash</span></span>
        ${icon('chevronRight', 18, 'text-muted')}
      </a>`);
  } catch { /* the reminder is optional */ }
}

/**
 * Announcements on Home. Each one has a kind (server: an Announcement subclass) that did the real
 * change it announces. Dated ones are listed under "Coming up", soonest first; undated news under
 * "Notices"; past dated ones fold into "Earlier". Officers post from the composer.
 */
const ANN_TONE = {
  general: 'bg-canvas', payment: 'bg-peach-tint', no_class: 'bg-danger-tint',
  deadline_change: 'bg-lavender-tint', task: 'bg-sun-tint', event: 'bg-mint-tint',
};

const Announcements = {
  data: null,
  box: null,          // the Home card
  fillAll: null,      // re-draws the "See all" popup while it is open
  earlierOpen: false,

  async mount(box) {
    if (!box) return;
    Announcements.box = box;
    try {
      Announcements.data = await API.get('announcements.php');
    } catch {
      box.remove();   // Home still works without it
      return;
    }
    Announcements.render(box);
  },

  /**
   * "Fri, Nov 27" + "in 3 days" / "Today" / "Tomorrow" for a dated announcement. Uses the server's
   * calendar day (event_on, the section's time zone), so a browser in another time zone shows the same day.
   */
  when(a) {
    if (!a.event_on && !a.event_ts) return null;
    const d = a.event_on ? Fmt.date(a.event_on) : new Date(a.event_ts * 1000);
    // Count calendar days (Friday → Monday is "in 3 days"), not rounded hours
    const day = (x) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
    const days = Math.round((day(d) - day(new Date())) / 86400e3);
    const rel = days === 0 ? 'Today' : days === 1 ? 'Tomorrow' : days > 1 ? `in ${days} days` : days === -1 ? 'Yesterday' : `${-days} days ago`;
    return { date: Fmt.short(d), rel, soon: days >= 0 && days <= 1 };
  },

  /** One announcement. Every kind uses this same layout, so they read the same way. */
  card(a, { preview = false } = {}) {
    const w = Announcements.when(a);
    const long = (a.body || '').length > 160 || (a.body || '').split('\n').length > 3;
    const btn = 'rounded-full px-3 py-1.5 text-xs font-semibold';
    return `
      <li class="rounded-2xl border border-line bg-white p-4" data-ann="${a.id ?? 'preview'}">
        <div class="flex gap-3">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl ${ANN_TONE[a.kind] || 'bg-canvas'}" aria-hidden="true">${icon(a.icon || 'bell', 18)}</span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] font-bold uppercase tracking-wide">
              <span>${esc(a.kind_label)}</span>
              ${w ? `<span class="font-semibold normal-case text-muted">· ${esc(w.date)}</span>
                <span class="chip px-2 py-0.5 text-[10px] normal-case ${w.soon ? 'bg-charcoal text-white' : 'bg-canvas'}">${esc(w.rel)}</span>` : ''}
              ${a.pinned ? `<span class="chip bg-sun-tint px-2 py-0.5 text-[10px] normal-case">${icon('flag', 10)}Pinned</span>` : ''}
            </div>
            <h3 class="mt-1 text-[15px] font-semibold leading-snug">${esc(a.title)}</h3>
            ${(a.lines || []).map((l) => `<p class="mt-0.5 text-sm ${l === 'No longer available' ? 'italic text-muted' : 'text-[#45454D]'}">${esc(l)}</p>`).join('')}
            ${a.body ? `<p data-body class="mt-2 whitespace-pre-line text-sm text-[#5C5C66] ${long ? 'line-clamp-3' : ''}">${esc(a.body)}</p>
              ${long && !preview ? '<button type="button" data-more class="mt-1 text-xs font-semibold">Read more</button>' : ''}` : ''}
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <span class="text-xs text-muted">${esc(a.by || '')}${a.created_at ? ` · posted ${esc(Fmt.monthDay(a.created_at.slice(0, 10)))}` : ''}</span>
              <span class="ml-auto flex flex-wrap gap-1.5">
                ${a.action ? `<a href="${esc(a.action.href)}" class="${btn} bg-charcoal text-white" ${preview ? 'tabindex="-1"' : ''}>${esc(a.action.label)}</a>` : ''}
                ${!preview && Announcements.data?.can_post ? `<button type="button" data-ann-pin="${a.id}" data-pinned="${a.pinned ? 1 : 0}" class="${btn} bg-canvas">${a.pinned ? 'Unpin' : 'Pin'}</button>` : ''}
                ${!preview && a.can_delete ? `<button type="button" data-ann-delete="${a.id}" class="${btn} bg-canvas text-danger">Remove</button>` : ''}
              </span>
            </div>
          </div>
        </div>
      </li>`;
  },

  /** Fetch again, then re-draw the Home card and the popup if it is open. */
  async reload() {
    Announcements.data = await API.get('announcements.php');
    Announcements.render(Announcements.box);
    Announcements.fillAll?.();
  },

  /** One short row for Home: kind, date, title and one line. Tapping it opens every announcement. */
  row(a, more) {
    const w = Announcements.when(a);
    const sub = (a.lines || [])[0] || a.body || '';
    return `
      <div class="mt-3">
        <button type="button" data-ann-all class="relative z-[1] flex w-full items-center gap-3 rounded-2xl border border-line bg-white p-3 text-left transition hover:bg-canvas/60">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl ${ANN_TONE[a.kind] || 'bg-canvas'}" aria-hidden="true">${icon(a.icon || 'bell', 16)}</span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide">
              <span>${esc(a.kind_label)}</span>
              ${w ? `<span class="chip px-1.5 py-0 text-[10px] normal-case ${w.soon ? 'bg-charcoal text-white' : 'bg-canvas'}">${esc(w.rel)}</span>` : ''}
              ${a.pinned ? `<span class="chip bg-sun-tint px-1.5 py-0 text-[10px] normal-case">${icon('flag', 10)}Pinned</span>` : ''}
            </span>
            <span class="mt-0.5 block truncate text-[15px] font-semibold leading-snug">${esc(a.title)}</span>
            ${sub ? `<span class="block truncate text-xs text-muted">${esc(sub.split('\n')[0])}</span>` : ''}
          </span>
          ${icon('chevronRight', 18, 'shrink-0 text-muted')}
        </button>
        ${more ? `<div class="mx-3 h-2 rounded-b-xl border border-t-0 border-line bg-white" aria-hidden="true"></div>
          ${more > 1 ? '<div class="mx-6 h-1.5 rounded-b-lg border border-t-0 border-line bg-white" aria-hidden="true"></div>' : ''}` : ''}
      </div>`;
  },

  /**
   * Home shows one announcement (a pinned one first, else the soonest coming up, else the newest
   * notice). The rest sit behind "See all", in a popup, like notifications.
   */
  render(box) {
    const d = Announcements.data;
    const all = [...(d.coming_up || []), ...(d.notices || [])];
    if (!all.length && !d.earlier_total && !d.can_post) { box.innerHTML = ''; box.hidden = true; return; }
    box.hidden = false;
    const top = all.find((a) => a.pinned) || all[0];
    const more = all.length - (top ? 1 : 0);
    box.innerHTML = `
      <div class="rounded-[1.75rem] bg-white p-4 shadow-card sm:p-5">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-lg font-bold">Announcements</h2>
          <div class="flex items-center gap-2">
            ${all.length || d.earlier_total ? `<button type="button" data-ann-all class="inline-flex h-9 items-center gap-1.5 rounded-full bg-canvas px-3.5 text-sm font-semibold">See all
              ${all.length ? `<span class="grid h-5 min-w-5 place-items-center rounded-full bg-charcoal px-1.5 text-[11px] text-white">${all.length}</span>` : ''}</button>` : ''}
            ${d.can_post ? `<button type="button" data-ann-new class="btn btn-dark h-9 px-3.5 text-sm">${icon('plus', 16)}Post</button>` : ''}
          </div>
        </div>
        ${top ? Announcements.row(top, more) : '<p class="mt-2 text-sm text-muted">Nothing new right now.</p>'}
      </div>`;
    $$('[data-ann-all]', box).forEach((b) => b.onclick = () => Announcements.showAll());
    $('[data-ann-new]', box)?.addEventListener('click', () => Announcements.compose(Announcements.reload));
    // A notification about an announcement links to home.html#announcements: open the list
    if (location.hash === '#announcements' && !Announcements.scrolled) {
      Announcements.scrolled = true;
      box.scrollIntoView({ block: 'start' });
      if (all.length || d.earlier_total) Announcements.showAll();
    }
  },

  /** The popup with every announcement: Coming up, Notices, then Earlier (folded). */
  showAll() {
    const sheet = UI.sheet(`
      <div class="flex items-center justify-between gap-2">
        <h2 class="text-xl font-bold">Announcements</h2>
        <button type="button" data-close class="grid h-10 w-10 place-items-center rounded-full bg-canvas" aria-label="Close">${icon('x', 18)}</button>
      </div>
      <div data-ann-list class="mt-4"></div>`, { label: 'Announcements' });
    const list = $('[data-ann-list]', sheet.el);
    Announcements.fillAll = () => {
      if (!list.isConnected) { Announcements.fillAll = null; return; }
      Announcements.fillList(list);
    };
    Announcements.fillAll();
  },

  fillList(el) {
    const d = Announcements.data;
    const group = (title, items) => (items.length ? `
      <div>
        <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-muted">${title}</h3>
        <ul class="space-y-3">${items.map((a) => Announcements.card(a)).join('')}</ul>
      </div>` : '');
    const any = (d.coming_up || []).length + (d.notices || []).length;
    el.innerHTML = `
      ${any ? `<div class="space-y-5">${group('Coming up', d.coming_up || [])}${group('Notices', d.notices || [])}</div>`
        : '<p class="text-sm text-muted">Nothing new right now.</p>'}
      ${d.earlier_total ? `
        <div class="mt-5 border-t border-line pt-3">
          <button type="button" data-ann-earlier aria-expanded="${Announcements.earlierOpen}" class="flex w-full items-center justify-between text-sm font-semibold">
            <span>Earlier <span class="font-normal text-muted">(${d.earlier_total})</span></span>${icon('chevronDown', 18, Announcements.earlierOpen ? 'rotate-180' : '')}</button>
          ${Announcements.earlierOpen ? `<ul class="mt-3 space-y-3 opacity-80">${d.earlier.map((a) => Announcements.card(a)).join('')}</ul>
            ${d.earlier_total > d.earlier.length ? `<p class="mt-2 text-xs text-muted">Showing the ${d.earlier.length} most recent of ${d.earlier_total}.</p>` : ''}` : ''}
        </div>` : ''}`;
    const box = el;
    const reload = Announcements.reload;
    $('[data-ann-earlier]', box)?.addEventListener('click', () => { Announcements.earlierOpen = !Announcements.earlierOpen; Announcements.fillList(box); });
    $$('[data-more]', box).forEach((b) => b.onclick = () => {
      const p = $('[data-body]', b.closest('li'));
      const clamped = p.classList.toggle('line-clamp-3');
      b.textContent = clamped ? 'Read more' : 'Show less';
    });
    $$('[data-ann-pin]', box).forEach((b) => b.onclick = async () => {
      b.disabled = true;
      try { await API.post('announcements.php', { action: 'pin', id: Number(b.dataset.annPin), pinned: b.dataset.pinned !== '1' }); await reload(); }
      catch (e) { b.disabled = false; UI.toast(e.message, { iconName: 'alert' }); }
    });
    $$('[data-ann-delete]', box).forEach((b) => b.onclick = async () => {
      b.disabled = true;
      try { await API.post('announcements.php', { action: 'delete', id: Number(b.dataset.annDelete) }); UI.toast('Announcement removed'); await reload(); }
      catch (e) { b.disabled = false; UI.toast(e.message, { iconName: 'alert' }); }
    });
  },

  /* ------------------------------------------------------------------ composer */
  /**
   * Post an announcement. The kinds shown are only those this person may post; each kind's fields
   * come from the server's options, so the form never offers a choice the server would refuse.
   */
  compose(reload) {
    const { kinds, options: o } = Announcements.data;
    const s = { kind: kinds[0].kind, mode: { payment: o.dues.length ? 'existing' : 'new', event: o.activities.length ? 'existing' : 'new' } };
    const field = (name, label, attrs = '') => `<label class="block"><span class="mb-1.5 block text-xs text-muted">${label}</span><span class="field"><input name="${name}" ${attrs}></span></label>`;
    const select = (name, label, items, render) => `<label class="block"><span class="mb-1.5 block text-xs text-muted">${label}</span>
      <span class="field"><select name="${name}">${items.map(render).join('')}</select>${icon('chevronDown', 18)}</span></label>`;
    const toggle = (key, canNew, canExisting) => `<div class="grid grid-cols-2 rounded-full bg-canvas p-1 text-sm" role="radiogroup" aria-label="Existing or new">
      ${[['existing', 'Existing', canExisting], ['new', 'New', canNew]].map(([k, l, ok]) => `
        <button type="button" role="radio" data-mode="${key}:${k}" aria-checked="${s.mode[key] === k}" ${ok ? '' : 'disabled'}
          class="rounded-full py-2 ${s.mode[key] === k ? 'bg-charcoal text-white' : 'text-muted'} disabled:opacity-40">${l}</button>`).join('')}</div>`;
    const today = Fmt.iso(new Date());
    const weekdayName = (iso) => DAYS_LONG[Fmt.date(iso).getDay()];

    const fieldsFor = () => {
      if (s.kind === 'payment') {
        return `${toggle('payment', o.can_create_due, o.dues.length > 0)}
          ${s.mode.payment === 'existing'
            ? select('due_id', 'Which due', o.dues, (x) => `<option value="${x.id}">${esc(x.title)} · ${esc(x.amount_label)}${x.due_date ? ` · due ${esc(Fmt.monthDay(x.due_date))}` : ''}</option>`)
            : `${field('due_title', 'What is it for', 'placeholder="e.g. Christmas party" maxlength="60"')}
               <div class="grid grid-cols-2 gap-3">${field('due_amount', 'Amount (₱)', 'inputmode="decimal" placeholder="150"')}${field('due_date', 'Due date (optional)', `type="date" min="${today}"`)}</div>`}`;
      }
      if (s.kind === 'no_class') {
        return `${field('date', 'Which day', `type="date" min="${today}" value="${esc(s.date || '')}" required`)}
          <div data-noclass-subjects></div>`;
      }
      if (s.kind === 'deadline_change' || s.kind === 'task') {
        const list = s.kind === 'deadline_change' ? (o.deadline_tasks || o.tasks) : o.tasks;   // a deadline that just passed can still be moved
        if (!list.length) return '<p class="rounded-2xl bg-canvas p-3 text-sm text-muted">There are no upcoming tasks.</p>';
        return `${select('task_id', 'Which task', list, (t) => `<option value="${t.id}">${esc(t.title)} · ${esc(t.subject_code)} · ${t.overdue ? 'was ' : ''}${esc(t.deadline_label)}</option>`)}
          ${s.kind === 'deadline_change' ? '<div><span class="mb-1.5 block text-xs text-muted">New deadline</span><div data-picker></div><div data-times class="mt-3"></div></div>' : ''}`;
      }
      if (s.kind === 'event') {
        return `${toggle('event', o.can_create_activity, o.activities.length > 0)}
          ${s.mode.event === 'existing'
            ? select('activity_id', 'Which activity', o.activities, (x) => `<option value="${x.id}">${esc(x.kind_label)}: ${esc(x.title)} · ${esc(Fmt.short(x.starts_at.slice(0, 10)))}</option>`)
            : `${select('act_kind', 'Kind', o.activity_kinds, (k) => `<option value="${k.kind}">${esc(k.label)}</option>`)}
               ${field('act_title', 'Name', 'placeholder="e.g. Intro to Figma" maxlength="80"')}
               <div class="grid grid-cols-3 gap-3">${field('act_date', 'Date', `type="date" min="${today}"`)}${field('act_start', 'Starts', 'type="time" value="13:00"')}${field('act_end', 'Ends', 'type="time" value="15:00"')}</div>
               <div class="grid grid-cols-2 gap-3">${field('act_venue', 'Venue', 'placeholder="e.g. AVR Hall"')}${field('act_capacity', 'Seats (optional)', 'inputmode="numeric" placeholder="No limit"')}</div>`}`;
      }
      return '';
    };

    const sheet = UI.sheet(`
      <form novalidate>
        <h2 class="text-2xl font-bold">Post an announcement</h2>
        <p class="mt-1 text-sm text-muted">Everyone in ${esc(Shell.me.section.name)} gets a notification.</p>
        <div class="mt-4 flex flex-wrap gap-2" role="radiogroup" aria-label="Kind of announcement" data-kinds></div>
        <div class="mt-4 space-y-3" data-fields></div>
        <div class="mt-4 space-y-3">
          ${field('title', 'Title', 'maxlength="80"')}
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Message (optional)</span>
            <span class="field area"><textarea name="body" rows="3" maxlength="1000" placeholder="Details, time, place…"></textarea></span></label>
          <label class="flex items-center gap-3 rounded-2xl bg-canvas p-3 text-sm"><input type="checkbox" name="pinned" value="1" class="h-5 w-5 accent-charcoal">Pin it to the top</label>
        </div>
        <div class="mt-4"><div class="mb-1.5 text-xs text-muted">Preview</div><ul data-preview></ul></div>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">Post</button>
      </form>`, { label: 'Post an announcement' });
    const el = sheet.el;
    const form = $('form', el);
    let picker = null;
    let times = null;

    /** What the server would write as the title when it's left empty (shown as the placeholder). */
    const defaultTitle = (v) => {
      if (s.kind === 'payment') return `Payment: ${v.dueTitle || 'dues'}`;
      if (s.kind === 'no_class') return v.date ? (v.subject ? `No ${v.subject.code} class on ${weekdayName(v.date)}` : `No class on ${weekdayName(v.date)}`) : 'No class';
      if (s.kind === 'deadline_change') return `Deadline moved: ${v.task?.title || 'task'}`;
      if (s.kind === 'task') return `${v.task?.title || 'A task'} is coming up`;
      if (s.kind === 'event') return `${v.actKind || 'Event'}: ${v.actTitle || ''}`;
      return 'e.g. No classes on Friday';
    };

    /** The values in the form, the request body, and the preview card. */
    const read = () => {
      const f = form.elements;
      const v = {};
      const body = { action: 'post', kind: s.kind, title: f.title.value, body: f.body.value, pinned: f.pinned.checked };
      const card = { kind: s.kind, kind_label: kinds.find((k) => k.kind === s.kind).label, icon: kinds.find((k) => k.kind === s.kind).icon, lines: [], action: null, pinned: f.pinned.checked, by: Shell.me.user.name };
      if (s.kind === 'payment') {
        if (s.mode.payment === 'existing' && f.due_id) {
          const due = o.dues.find((x) => x.id === Number(f.due_id.value));
          body.due_id = due?.id; v.dueTitle = due?.title;
          card.lines = due ? [`${due.amount_label} · ${due.due_date ? `due ${Fmt.short(due.due_date)}` : 'no deadline'}`] : [];
          card.event_ts = due?.due_date ? Fmt.date(due.due_date).getTime() / 1000 + 86340 : null;
        } else if (f.due_title) {
          body.new_due = { title: f.due_title.value, amount: f.due_amount.value, due_date: f.due_date.value };
          v.dueTitle = f.due_title.value;
          card.lines = [`₱${f.due_amount.value || '0'} · ${f.due_date.value ? `due ${Fmt.short(f.due_date.value)}` : 'no deadline'}`];
          card.event_ts = f.due_date.value ? Fmt.date(f.due_date.value).getTime() / 1000 + 86340 : null;
        }
        card.action = { label: 'View dues', href: 'dues.html' };
      }
      if (s.kind === 'no_class' && f.date) {
        body.date = f.date.value; v.date = f.date.value;
        const sub = f.subject_id ? o.subjects.find((x) => x.id === Number(f.subject_id.value)) : null;
        if (sub && f.scope?.value === 'one') {
          body.subject_id = sub.id; v.subject = sub;
          // every meeting that day, like the server's card ("1:00–2:30 PM, 4:00–5:00 PM")
          const slots = sub.slots.filter((x) => x.dow === (Fmt.date(v.date).getDay() || 7));
          card.lines = [`${sub.code}${slots.length ? ` · ${slots.map((x) => `${Fmt.time(x.start).replace(/ [AP]M$/, '')}–${Fmt.time(x.end)}`).join(', ')}` : ''}`];
        } else card.lines = v.date ? ['All classes that day'] : [];
        card.event_ts = v.date ? Fmt.date(v.date).getTime() / 1000 : null;
        card.action = { label: 'Open schedule', href: 'schedule.html' };
      }
      if ((s.kind === 'deadline_change' || s.kind === 'task') && f.task_id) {
        const t = [...(o.deadline_tasks || []), ...o.tasks].find((x) => x.id === Number(f.task_id.value));
        body.task_id = t?.id; v.task = t;
        if (s.kind === 'deadline_change' && picker) {
          const p = picker.value();
          Object.assign(body, { deadline_type: p.type, deadline: p.start, deadline_end: p.type === 'range' ? p.end : null, deadline_time: p.type === 'unset' ? null : times.value() });
          const to = p.type === 'unset' ? 'Date not set yet' : p.start ? `${p.type === 'range' && p.end ? `${Fmt.monthDay(p.start)} – ${Fmt.monthDay(p.end)}` : Fmt.short(p.start)} · ${Fmt.time(times.value())}` : 'pick a date';
          card.lines = t ? [t.title, `${t.deadline_label} → ${to}`] : [];
          card.event_ts = p.start ? Fmt.date(p.type === 'range' && p.end ? p.end : p.start).getTime() / 1000 : null;
        } else if (t) {
          card.lines = [`${t.subject_code} · ${t.deadline_label}`];
          card.event_ts = t.deadline ? Fmt.date(t.deadline_end || t.deadline).getTime() / 1000 : null;
        }
        card.action = { label: 'Open task', href: '#' };
      }
      if (s.kind === 'event') {
        if (s.mode.event === 'existing' && f.activity_id) {
          const a = o.activities.find((x) => x.id === Number(f.activity_id.value));
          body.activity_id = a?.id; v.actKind = a?.kind_label; v.actTitle = a?.title;
          card.lines = a ? [`${Fmt.short(a.starts_at.slice(0, 10))} · ${Fmt.time(a.starts_at.slice(11, 16))}${a.venue ? ` · ${a.venue}` : ''}`] : [];
          card.event_ts = a ? Fmt.date(a.starts_at.slice(0, 10)).getTime() / 1000 : null;
        } else if (f.act_title) {
          body.new_activity = { kind: f.act_kind.value, title: f.act_title.value, date: f.act_date.value, start: f.act_start.value, end: f.act_end.value, venue: f.act_venue.value, capacity: f.act_capacity.value };
          v.actKind = o.activity_kinds.find((k) => k.kind === f.act_kind.value)?.label; v.actTitle = f.act_title.value;
          card.lines = f.act_date.value ? [`${Fmt.short(f.act_date.value)} · ${Fmt.time(f.act_start.value).replace(/ [AP]M$/, "")}–${Fmt.time(f.act_end.value)}${f.act_venue.value ? ` · ${f.act_venue.value}` : ''}`] : [];
          card.event_ts = f.act_date.value ? Fmt.date(f.act_date.value).getTime() / 1000 : null;
        }
        card.action = { label: 'RSVP', href: 'activities.html' };
      }
      f.title.placeholder = defaultTitle(v);
      card.title = f.title.value.trim() || (s.kind === 'general' ? 'Your title' : defaultTitle(v));
      card.body = f.body.value.trim();
      return { body, card };
    };

    const preview = () => { $('[data-preview]', el).innerHTML = Announcements.card(read().card, { preview: true }); };

    /** No class: offer only the subjects that meet on the chosen weekday. */
    const noClassSubjects = () => {
      const box = $('[data-noclass-subjects]', el);
      const date = form.elements.date?.value;
      if (!box) return;
      if (!date) { box.innerHTML = ''; preview(); return; }
      const dow = Fmt.date(date).getDay() || 7;
      const dayName = DAYS_LONG[Fmt.date(date).getDay()];
      // Already cancelled that day (by an earlier No class) isn't offered again
      const off = new Set(o.cancelled || []);
      const wholeDayOff = off.has(`${date}|*`);
      const meets = o.subjects.filter((x) => x.slots.some((sl) => sl.dow === dow) && !off.has(`${date}|${x.id}`));
      const anyMeets = o.subjects.some((x) => x.slots.some((sl) => sl.dow === dow));
      const times = (x) => x.slots.filter((q) => q.dow === dow).map((q) => Fmt.time(q.start)).join(', ');
      box.innerHTML = wholeDayOff
        ? `<p class="rounded-2xl bg-sun-tint p-3 text-sm">Every class on ${esc(Fmt.short(date))} is already cancelled. Pick another day.</p>`
        : !anyMeets ? `<p class="rounded-2xl bg-sun-tint p-3 text-sm">There are no classes on ${esc(dayName)}s. Pick another day.</p>`
        : `
        <fieldset class="space-y-2"><legend class="mb-1.5 text-xs text-muted">Which classes</legend>
          <label class="flex items-center gap-3 rounded-2xl bg-canvas p-3 text-sm"><input type="radio" name="scope" value="all" checked class="h-4 w-4 accent-charcoal">All classes on ${esc(dayName)}</label>
          ${meets.length ? `<label class="flex items-center gap-3 rounded-2xl bg-canvas p-3 text-sm"><input type="radio" name="scope" value="one" class="h-4 w-4 accent-charcoal">One subject
            <span class="field ml-auto h-9 min-w-0 flex-1 bg-white"><select name="subject_id" aria-label="Subject">${meets.map((x) => `<option value="${x.id}">${esc(x.code)} · ${esc(times(x))}</option>`).join('')}</select></span></label>`
            : '<p class="px-1 text-xs text-muted">Each class that day is already cancelled one by one.</p>'}
        </fieldset>`;
      $$('input[name=scope], select[name=subject_id]', box).forEach((x) => x.addEventListener('change', () => {
        if (x.name === 'subject_id') form.elements.scope.value = 'one';
        preview();
      }));
      preview();
    };

    const renderKind = () => {
      $('[data-kinds]', el).innerHTML = kinds.map((k) => `
        <button type="button" role="radio" data-kind="${k.kind}" aria-checked="${s.kind === k.kind}"
          class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-medium ${s.kind === k.kind ? 'bg-charcoal text-white' : `${ANN_TONE[k.kind]} hover:opacity-80`}">${icon(k.icon, 14)}${esc(k.label)}</button>`).join('');
      $('[data-fields]', el).innerHTML = fieldsFor();
      picker = times = null;
      const pk = $('[data-picker]', el);
      if (pk) {
        const t = [...(o.deadline_tasks || []), ...o.tasks].find((x) => x.id === Number(form.elements.task_id.value));
        picker = createDatePicker(pk, { type: t?.deadline_type === 'unset' ? 'exact' : (t?.deadline_type || 'exact'), start: null, end: null, onChange: preview });
        times = createTimeChips($('[data-times]', el), { value: t?.deadline_time || '23:59', onChange: preview });
      }
      $$('[data-kind]', el).forEach((b) => b.onclick = () => { s.kind = b.dataset.kind; renderKind(); });
      $$('[data-mode]', el).forEach((b) => b.onclick = () => { const [key, m] = b.dataset.mode.split(':'); s.mode[key] = m; renderKind(); });
      form.elements.date?.addEventListener('change', noClassSubjects);
      if (s.kind === 'no_class') noClassSubjects();
      $('[data-fields]', el).addEventListener('input', preview);
      $('[data-fields]', el).addEventListener('change', preview);
      preview();
    };

    form.addEventListener('input', preview);
    renderKind();
    UI.bindForm(form, async () => {
      const { body } = read();
      if (s.kind === 'general' && !body.title.trim()) throw new Error('Give the announcement a title.');
      await API.post('announcements.php', body);
      sheet.close();
      UI.toast('Announcement posted', { sub: 'Everyone was notified' });
      await reload();
    });
  },
};

/** "You still need a group for …" — shown above the dashboard while any group task is unclaimed. */
async function groupReminder() {
  try {
    const { upcoming } = await API.get('groups.php');
    const missing = upcoming.filter((t) => !t.in_group);
    if (!missing.length) return;
    $('#main').insertAdjacentHTML('afterbegin', `
      <a href="${missing.length === 1 ? `task.html?id=${missing[0].id}` : 'classmates.html#groups'}" class="mb-5 flex items-center gap-3 rounded-[1.5rem] bg-sun-tint p-4">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white">${icon('users', 18)}</span>
        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">You still need a group</span>
          <span class="block truncate text-xs text-muted">${missing.map((t) => esc(t.title)).join(' · ')}</span></span>
        ${icon('chevronRight', 18, 'text-muted')}
      </a>`);
  } catch { /* the reminder is optional */ }
}

const inLabel = (ts) => {
  if (!ts) return '';
  const hrs = Math.max(0, Math.round((ts * 1000 - Date.now()) / 3600e3));
  return hrs < 24 ? `IN ${hrs || '<1'}H` : `IN ${Math.round(hrs / 24)}D`;
};

/* --------------------------------------------------------------- student */
function studentView(d) {
  const open = d.timeline.filter((t) => !t.done);   // finished tasks are on Schedule / My tasks, not "next up"
  const next = open[0];
  const upcoming = open.slice(0, 4);
  return `
    <div class="grid gap-5 lg:grid-cols-[1fr_20rem] lg:gap-6">
      <a href="schedule.html" class="card-pastel lavender block min-h-[11rem] rounded-[1.75rem] p-5 lg:min-h-[13rem] lg:p-8">
        <span class="sparkle">✦</span>
        <span class="pointer-events-none absolute -right-10 top-4 h-72 w-72 rounded-full border border-white/40"></span>
        <img src="assets/badge-cap-yellow.png" alt="" width="110" height="110" class="absolute right-4 top-6 h-[6.5rem] w-[6.5rem] rounded-full lg:right-10 lg:top-8 lg:h-36 lg:w-36">
        <div class="relative max-w-[60%]">
          <div class="text-[11px] font-semibold uppercase tracking-wider">This week</div>
          <div class="mt-2 text-[28px] font-extrabold leading-tight tracking-tight lg:text-4xl">${d.stats.due_week} task${d.stats.due_week === 1 ? '' : 's'} due this week</div>
          ${next ? `<p class="mt-2 hidden text-sm lg:block">${esc(next.title)} (${esc(next.subject_code)}) is ${esc(Fmt.due(next).replace(/^Due /, 'due ').split(' · ')[0])}${next.location ? ` · ${esc(next.location)}` : ''}.</p>` : ''}
        </div>
        <div class="relative mt-5 flex items-center gap-3">
          <span class="grid h-12 w-12 place-items-center rounded-full bg-charcoal text-white ring-4 ring-white/40">${icon('arrowRight', 20)}</span>
          <span class="text-sm font-semibold">View schedule</span>
        </div>
      </a>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
        <a href="my-tasks.html#pending" class="relative overflow-hidden rounded-[1.75rem] border-2 border-lavender-light bg-white p-4 shadow-card">
          <span class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-lavender-tint"></span>
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white text-lavender-dark shadow-card">${icon('inbox', 18)}</span>
          <div class="text-xs text-muted">Pending</div>
          <div class="mt-1 text-4xl font-extrabold">${d.stats.my_pending}</div>
          <div class="mt-1 text-xs text-muted">request${d.stats.my_pending === 1 ? '' : 's'}<span class="hidden lg:inline"> waiting for admin review</span></div>
        </a>
        <a href="schedule.html#urgent" class="card-pastel yellow rounded-[1.75rem] p-4">
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white">${icon('clock', 18)}</span>
          <div class="text-xs">Due today</div>
          <div class="mt-1 text-4xl font-extrabold">${d.stats.due_today}</div>
          <div class="mt-1 flex items-center justify-between gap-1 text-xs"><span>deadline${d.stats.due_today === 1 ? '' : 's'}</span>${d.stats.due_today ? `<span class="chip bg-charcoal px-2 py-0.5 text-[10px] font-bold text-white">${inLabel(d.stats.next_due_ts)}</span>` : ''}</div>
        </a>
      </div>

      <section id="announcements" class="scroll-mt-6 lg:col-span-2" aria-label="Announcements"></section>

      <section>
        <div class="mb-3 flex items-baseline justify-between">
          <h2 class="text-xl font-bold">My subjects</h2>
          <span class="text-xs text-muted">${d.subjects.length} subjects</span>
        </div>
        ${d.subjects.length
          ? `<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">${d.subjects.map(subjectCard).join('')}</div>`
          : UI.empty('No subjects yet', 'Your class officer hasn’t added this section’s subjects.', 'bookOpen')}
      </section>

      <section class="lg:row-span-1 lg:rounded-[1.75rem] lg:bg-white lg:p-4 lg:shadow-card">
        <div class="mb-3 flex items-baseline justify-between">
          <h2 class="text-xl font-bold">Upcoming deadlines</h2>
          <a href="schedule.html" class="text-sm font-semibold">See all</a>
        </div>
        <div class="space-y-3">${upcoming.length ? upcoming.map((t) => UI.taskCard(t, { compact: true })).join('') : UI.empty('All caught up', 'No upcoming deadlines.')}</div>
        <a href="my-tasks.html#history" class="mt-3 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-card lg:bg-canvas lg:shadow-none">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-mint-tint text-success">${icon('check', 16)}</span>
          <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">Task history</span>
            <span class="block text-xs text-muted">${d.stats.done || 0} done · past deadlines · request results</span></span>
          ${icon('chevronRight', 18, 'text-muted')}
        </a>
      </section>
    </div>`;
}

function subjectCard(s) {
  const t = tone(s.color_tag);
  const badge = s.due
    ? `<span class="chip bg-white text-[11px]">${icon('clock', 12)}${s.due} due<span class="h-1.5 w-1.5 rounded-full bg-danger"></span></span>`
    : `<span class="chip bg-white text-[11px] text-success">${icon('check', 12)}All clear</span>`;
  return `
    <a href="subject.html?id=${s.id}" class="card-pastel ${t.soft} flex min-h-[8.5rem] flex-col rounded-[1.5rem] p-4 transition hover:-translate-y-0.5">
      <span class="ring-deco" style="width:90px;height:90px;right:-26px;bottom:-40px;border-width:12px"></span>
      <div class="flex items-start justify-between gap-1">
        <span class="grid h-9 w-9 place-items-center rounded-full bg-white ${t.icon}">${icon('notebook', 18)}</span>
        ${badge}
      </div>
      <div class="mt-auto pt-3 text-[10px] font-semibold tracking-wide text-muted">${esc(s.code)}</div>
      <div class="font-semibold leading-snug">${esc(s.title)}</div>
    </a>`;
}

/* --------------------------------------------------------------- admin */
function adminView(d) {
  const today = new Date();
  const dow = today.getDay();
  const day = d.week.days.find((x) => x.dow === dow);
  const classes = day ? day.classes : [];
  const urgentToday = d.stats.due_today;
  const oldest = d.stats.oldest_request ? `Oldest from ${Fmt.monthDay(d.stats.oldest_request.slice(0, 10))}` : 'Queue is empty';

  const quick = [
    ['add-subject.html', 'lavender-soft', 'bookOpen', 'Add subject', 'Set days & class time'],
    ['task-form.html', 'peach-soft', 'plus', 'Add task', 'Pick a deadline date'],
    ['classmates.html', 'mint-soft', 'users', 'Members', `${d.section.members} in ${d.section.name}`],
    ['week.html', 'yellow-soft', 'calendar', 'Week schedule', `${d.subjects.length} subjects`],
  ];

  return `
    <div class="grid gap-5 lg:grid-cols-[1fr_20rem] lg:gap-6">
      <a href="queue.html" class="card-pastel dark flex min-h-[9rem] items-center justify-between rounded-[1.75rem] p-5 lg:p-8">
        <span class="sparkle">✦</span>
        <span class="pointer-events-none absolute right-6 top-1/2 h-40 w-40 -translate-y-1/2 rounded-full border border-white/10"></span>
        <div class="relative">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-white/60">Waiting for you</div>
          <div class="mt-1 text-[28px] font-extrabold leading-tight lg:text-4xl">${d.stats.queue_size} request${d.stats.queue_size === 1 ? '' : 's'}</div>
          <div class="mt-1 text-xs text-white/60">${esc(oldest)}${d.stats.join_requests ? ` · ${plural(d.stats.join_requests, 'person', 'people')} waiting to join` : ''}</div>
        </div>
        <span class="btn btn-yellow relative h-14 px-7">Review</span>
      </a>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
        <a href="schedule.html#urgent" class="card-pastel danger-soft rounded-[1.75rem] border-2 border-[#F5C2BC] p-4">
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white">${icon('clock', 18)}${urgentToday ? '<span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-danger"></span>' : ''}</span>
          <div class="mt-1 text-4xl font-extrabold">${urgentToday}</div>
          <div class="mt-1 flex items-center justify-between gap-1 text-xs text-muted"><span>urgent today</span>${urgentToday ? `<span class="chip bg-charcoal px-2 py-0.5 text-[10px] font-bold text-white">${inLabel(d.stats.next_due_ts)}</span>` : ''}</div>
        </a>
        <a href="schedule.html#week" class="relative overflow-hidden rounded-[1.75rem] border-2 border-lavender-light bg-white p-4 shadow-card">
          <span class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-lavender-tint"></span>
          <span class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white text-lavender-dark shadow-card">${icon('inbox', 18)}</span>
          <div class="mt-1 text-4xl font-extrabold">${d.stats.due_week}</div>
          <div class="mt-1 text-xs text-muted">due this week</div>
        </a>
      </div>

      <section id="announcements" class="scroll-mt-6 lg:col-span-2" aria-label="Announcements"></section>

      <section>
        <h2 class="mb-3 text-xl font-bold">Quick actions</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          ${quick.map(([href, cls, ic, title, sub]) => `
            <a href="${href}" class="card-pastel ${cls} flex min-h-[8.5rem] flex-col rounded-[1.5rem] p-4 transition hover:-translate-y-0.5">
              <span class="ring-deco" style="width:90px;height:90px;right:-26px;bottom:-40px;border-width:12px"></span>
              <span class="grid h-10 w-10 place-items-center rounded-full bg-white">${icon(ic, 20)}</span>
              <div class="mt-auto pt-3 text-lg font-semibold">${title}</div>
              <div class="text-xs text-muted">${esc(sub)}</div>
            </a>`).join('')}
        </div>
      </section>

      <section>
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-xl font-bold">Classes today</h2>
          <span class="chip bg-white">${esc(Fmt.short(today))}</span>
        </div>
        <div class="space-y-3">${classes.length ? classes.map((c) => UI.classRow(c)).join('') : UI.empty('No classes today', 'Enjoy the free day.', 'calendar')}</div>
      </section>
    </div>`;
}
