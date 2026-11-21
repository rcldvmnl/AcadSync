// Shared UI helpers: icons, formatting, app shell (header + nav), sheets, toasts, date picker.

const ICON_PATHS = {
  home: '<path d="M3 10.2 12 3l9 7.2V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
  calendar: '<rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/>',
  groups: '<circle cx="12" cy="8" r="3"/><path d="M6.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="5" cy="10" r="2.25"/><path d="M1.5 19a3.75 3.75 0 0 1 4.5-3.6"/><circle cx="19" cy="10" r="2.25"/><path d="M22.5 19a3.75 3.75 0 0 0-4.5-3.6"/>',
  globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
  file: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
  laptop: '<rect x="4" y="5" width="16" height="11" rx="2"/><path d="M2 20h20"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
  bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
  arrowLeft: '<path d="M19 12H5M12 19l-7-7 7-7"/>',
  arrowRight: '<path d="M5 12h14M12 5l7 7-7 7"/>',
  chevronRight: '<path d="m9 18 6-6-6-6"/>',
  chevronLeft: '<path d="m15 18-6-6 6-6"/>',
  chevronDown: '<path d="m6 9 6 6 6-6"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  mail: '<rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m22 7-10 6L2 7"/>',
  lock: '<rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  key: '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
  hash: '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
  copy: '<rect x="8" y="8" width="13" height="13" rx="2.5"/><path d="M4 16a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2"/>',
  search: '<circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/>',
  mapPin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
  bookOpen: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
  info: '<circle cx="12" cy="12" r="9.5"/><path d="M12 16v-4M12 8h.01"/>',
  undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
  alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
  help: '<circle cx="12" cy="12" r="9.5"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
  edit: '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
  sparkle: '<path d="M12 2c.6 4.6 2.4 6.4 7 7-4.6.6-6.4 2.4-7 7-.6-4.6-2.4-6.4-7-7 4.6-.6 6.4-2.4 7-7Z" fill="currentColor" stroke="none"/>',
  notebook: '<rect x="5" y="3" width="14" height="18" rx="2.5"/><path d="M9 3v18M12 8h4M12 12h4"/>',
  eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
  eyeOff: '<path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a17 17 0 0 1-2.16 3.19M6.6 6.6A17.5 17.5 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M14.1 14.1a3 3 0 0 1-4.2-4.2M2 2l20 20"/>',
  flag: '<path d="M5 21V4"/><path d="M5 4h11.5l-2.25 4.5L16.5 13H5"/>',
  clipboard: '<rect x="5" y="4" width="14" height="17" rx="2.5"/><path d="M9 4V2.75h6V4M9 10h6M9 14h6M9 18h3"/>',
  download: '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/>',
};

/**
 * Show/hide toggle for every password field — including ones added later
 * (sheets, dialogs), picked up by a MutationObserver.
 */
function addPasswordToggles(root = document) {
  root.querySelectorAll?.('input[type=password]:not([data-toggle-ready])').forEach((input) => {
    input.dataset.toggleReady = '1';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted hover:bg-line/60 hover:text-charcoal';
    const sync = () => {
      const shown = input.type === 'text';
      btn.innerHTML = icon(shown ? 'eyeOff' : 'eye', 18);
      btn.setAttribute('aria-label', shown ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', String(shown));
    };
    btn.addEventListener('click', () => {
      input.type = input.type === 'password' ? 'text' : 'password';
      sync();
      input.focus();
    });
    sync();
    input.insertAdjacentElement('afterend', btn);
  });
}
addPasswordToggles();
new MutationObserver((records) => records.forEach((r) => r.addedNodes.forEach((n) => n.nodeType === 1 && addPasswordToggles(n))))
  .observe(document.documentElement, { childList: true, subtree: true });

function icon(name, size = 20, cls = '') {
  return `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICON_PATHS[name] || ''}</svg>`;
}

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** "1 task", "3 tasks" */
const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

/* ------------------------------------------------------------------ format */
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const DAYS_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const Fmt = {
  date(d) { return d instanceof Date ? d : new Date(typeof d === 'number' ? d * 1000 : `${d}T00:00:00`); },
  iso(d) { const x = Fmt.date(d); return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`; },
  time(hhmm) {
    if (!hhmm) return '';
    const [h, m] = hhmm.split(':').map(Number);
    return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
  },
  short(d) { const x = Fmt.date(d); return `${DAYS[x.getDay()]}, ${MONTHS[x.getMonth()]} ${x.getDate()}`; },
  monthDay(d) { const x = Fmt.date(d); return `${MONTHS[x.getMonth()]} ${x.getDate()}`; },
  long(d) { const x = Fmt.date(d); return `${DAYS_LONG[x.getDay()]}, ${MONTHS_LONG[x.getMonth()]} ${x.getDate()}`; },
  isToday(d) { return Fmt.iso(d) === Fmt.iso(new Date()); },
  isTomorrow(d) { const t = new Date(); t.setDate(t.getDate() + 1); return Fmt.iso(d) === Fmt.iso(t); },
  /** "in 3 hrs", "in 2 days", "2 hrs ago" */
  relative(ts) {
    const diff = ts * 1000 - Date.now();
    const abs = Math.abs(diff);
    const unit = abs < 3600e3 ? [Math.max(1, Math.round(abs / 60e3)), 'min'] : abs < 86400e3 ? [Math.round(abs / 3600e3), 'hr'] : [Math.round(abs / 86400e3), 'day'];
    const label = `${unit[0]} ${unit[1]}${unit[0] === 1 ? '' : 's'}`;
    return diff >= 0 ? `in ${label}` : `${label} ago`;
  },
  ago(ts) {
    const diff = Date.now() - ts * 1000;
    if (diff < 3600e3) return `${Math.max(1, Math.round(diff / 60e3))} min ago`;
    if (diff < 86400e3) return `${Math.round(diff / 3600e3)} hr ago`;
    if (diff < 2 * 86400e3) return 'Yesterday';
    return Fmt.monthDay(new Date(ts * 1000));
  },
  /** One-line deadline summary used on task cards. */
  due(task) {
    if (task.deadline_type === 'unset' || !task.deadline_ts) return 'Date not set yet';
    const end = task.deadline_end || task.deadline;
    const time = task.deadline_time ? ` · ${Fmt.time(task.deadline_time)}` : '';
    if (task.deadline_type === 'range') {
      if (!Fmt.isOpenRange(task)) return `Any day ${Fmt.monthDay(task.deadline)} – ${Fmt.monthDay(end)}${time}`;
      // Open now: say plainly that today counts, and which day is the last
      if (Fmt.isToday(end)) return `Last day today${time}`;
      // The badge already shows the dates, so a short weekday is enough here (fits a phone card)
      const last = Fmt.date(end);
      const within6 = (task.deadline_ts * 1000 - Date.now()) < 6 * 86400e3;
      return `Open today · ends ${Fmt.isTomorrow(end) ? 'tomorrow' : within6 ? DAYS[last.getDay()] : Fmt.monthDay(last)}`;
    }
    const day = Fmt.isToday(end) ? 'today' : Fmt.isTomorrow(end) ? 'tomorrow' : Fmt.short(end);
    const soon = task.deadline_ts * 1000 - Date.now() < 86400e3 ? ` · ${Fmt.relative(task.deadline_ts)}` : '';
    return `Due ${day}${time}${soon}`;
  },
  /**
   * Where a task belongs on the schedule (Unix seconds), same rule as Deadline::attentionAt() on the
   * server: an exact date at its deadline; a date range that is already open counts from now (it can
   * be submitted or called today); a range that opens later counts from its first day.
   */
  placeTs(task, now = Date.now() / 1000) {
    if (!task.deadline_ts) return null;
    const opens = task.opens_ts ?? task.deadline_ts;
    return Math.max(opens, Math.min(now, task.deadline_ts));
  },
  /** A date range whose first day has come (and hasn't ended). */
  isOpenRange(task, now = Date.now() / 1000) {
    return task.deadline_type === 'range' && !!task.opens_ts && task.opens_ts <= now && task.deadline_ts >= now;
  },
  initials(name) { return String(name || '?').split(/[\s,]+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join(''); },
  greeting() { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'; },
};

/* Subject colour tokens */
const TONES = {
  lavender: { soft: 'lavender-soft', strong: 'lavender', chip: 'bg-lavender-tint', bar: '#A8A3F7', dot: 'bg-lavender', icon: 'text-lavender-dark' },
  peach: { soft: 'peach-soft', strong: 'peach', chip: 'bg-peach-tint', bar: '#F58D42', dot: 'bg-peach', icon: 'text-peach-dark' },
  yellow: { soft: 'yellow-soft', strong: 'yellow', chip: 'bg-sun-tint', bar: '#FFD34E', dot: 'bg-sun', icon: 'text-[#8A6A00]' },
  mint: { soft: 'grey-soft', strong: 'grey-soft', chip: 'bg-[#EDEDF2]', bar: '#1C1C21', dot: 'bg-charcoal', icon: 'text-charcoal' },   // stored as 'mint'; shown as the reference grey (mint marks success only)
};
const tone = (c) => TONES[c] || TONES.lavender;
const CATEGORY_CHIP = { Reporting: 'bg-lavender-tint', Quiz: 'bg-peach-tint', Exam: 'bg-peach-tint', Activity: 'bg-[#EDEDF2]', Project: 'bg-sun-tint' };

/*
 * How a task is passed. Stored in the task's `location` text as
 * "Hardcopy + Softcopy · Room 204 / email prof" so no database change is needed.
 */
const SUBMIT_MODES = {
  'In class': { icon: 'mapPin', hint: 'e.g. Room 204', chip: 'bg-lavender-tint' },
  Hardcopy: { icon: 'file', hint: 'e.g. Pass to the instructor’s table · Room 204', chip: 'bg-sun-tint' },
  'Softcopy / Online': { icon: 'laptop', hint: 'e.g. Google Classroom, or PDF to prof@lspu.edu.ph', chip: 'bg-[#EDEDF2]' },
};
/** Older saved names that now mean the combined option. */
const SUBMIT_ALIASES = { Softcopy: 'Softcopy / Online', Online: 'Softcopy / Online' };
const ONLINE_SUGGESTIONS = ['Google Classroom', 'MS Teams', 'Google Drive', 'Messenger group chat', 'Email', 'Canvas'];
const Submission = {
  /** "Softcopy / Online · Google Classroom" -> { modes: ['Softcopy / Online'], detail: 'Google Classroom' } (old free text -> detail only). */
  parse(text) {
    const s = String(text || '').trim();
    const cut = s.indexOf(' · ');
    const head = cut === -1 ? s : s.slice(0, cut);
    const parts = head.split(' + ').map((p) => SUBMIT_ALIASES[p.trim()] || p.trim());
    if (head && parts.every((p) => SUBMIT_MODES[p])) return { modes: [...new Set(parts)], detail: cut === -1 ? '' : s.slice(cut + 3).trim() };
    return { modes: [], detail: s };
  },
  format(modes, detail) {
    const d = String(detail || '').trim();
    return [modes.join(' + '), d].filter(Boolean).join(' · ');
  },
};

/* ------------------------------------------------------------------ pieces */
const UI = {
  icon, esc,
  /** Icon name → hand-drawn academic illustration (assets/illus/*.svg) used by empty states. */
  ILLUS: { check: 'trophy', flag: 'trophy', bookOpen: 'books', notebook: 'books', calendar: 'books', clipboard: 'diploma', inbox: 'diploma', users: 'pencil' },

  chip(text, cls = 'bg-white') { return `<span class="chip ${cls}">${esc(text)}</span>`; },

  /**
   * A link to the original post, shown by its site name ("facebook.com ↗"). Always opens in a new tab
   * without access to this page (noopener). Only http(s) links are drawn (the server refuses the rest).
   */
  linkChip(url, host, cls = 'bg-white border border-line') {
    if (!url || !/^https?:\/\//i.test(String(url))) return '';
    return `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer" class="chip ${cls} hover:border-charcoal" aria-label="Open the original post on ${esc(host)} (new tab)">${esc(host)} ↗</a>`;
  },

  /**
   * How the prof set the groups up, in a few words: "5 groups", "4 per group", "8 groups of 4",
   * "Assigned · 6 groups". The server's GroupRule subclass writes it (group_label).
   */
  groupRule(t) {
    if (t.group_label) return t.group_label;
    if (t.group_count && t.group_max) return `${t.group_count} groups of ${t.group_max}`;
    return t.group_count ? `${t.group_count} groups` : `${t.group_max ?? '?'} per group`;
  },
  /** The four setups, for the task form and the explanations. */
  GROUP_SETUPS: {
    count: { title: 'Number of groups', hint: 'The prof said “make 5 groups”. Classmates form them.', icon: 'groups' },
    size: { title: 'Members per group', hint: 'The prof said “4 per group”. Classmates form them.', icon: 'users' },
    size_count: { title: 'Both', hint: 'The prof said “8 groups of 4”. Classmates form them.', icon: 'hash' },
    assigned: { title: 'Prof assigned the groups', hint: 'The prof gave the groups and their members.', icon: 'clipboard' },
  },
  /** Spots a group really has right now: its normal size, or +1 while an extra seat is free. */
  groupCap(g) { return g.capacity ?? g.max_members; },
  /** "4 of 6 members", "7 members (6 + 1 extra)", or "3 members" when there's no set size. */
  groupCount(g) {
    const n = g.members.length;
    const target = g.target ?? g.max_members;
    if (target === null || target === undefined) return plural(n, 'member');
    if (n > target) return `${n} members (${target} + ${n - target} extra)`;
    return `${n} of ${target} members`;
  },
  /** Chip colour for a group's status line (server: state / state_label). */
  groupStateTone(g) {
    return { empty: 'bg-white text-muted', open: 'bg-mint-tint', full: 'bg-white text-muted', extra_seat: 'bg-sun-tint', extra: 'bg-white text-muted' }[g.state] || 'bg-white';
  },
  workBadge(t) {
    return t.work_mode === 'group'
      ? `<span class="chip gap-1 bg-charcoal px-2 text-white">${icon('users', 12)}Group · ${esc(UI.groupRule(t))}</span>`
      : '';
  },

  /**
   * Date badge + title + category chip + due line + arrow — "Task Card v2".
   * toggle: show a "mark as done" check button instead of the arrow.
   * Done tasks stay in the list; they're just dimmed with a check.
   */
  taskCard(t, { compact = false, toggle = false } = {}) {
    const d = t.deadline_ts ? new Date(t.deadline_ts * 1000) : null;
    const done = !!t.done;
    const group = t.work_mode === 'group';
    const badgeBg = done ? 'bg-mint-tint' : group ? 'bg-charcoal text-white' : 'bg-lavender-tint';
    const sub = group && !done ? 'text-white/70' : 'text-muted';
    // A date range shows all its days ("20–23 NOV"), so it never looks like it's due on the last day only
    const first = t.deadline_type === 'range' && d && t.deadline ? Fmt.date(t.deadline) : null;
    const badge = !d ? `<div class="text-xs font-bold ${sub}">TBA</div>`
      : first ? `<div><div class="text-[13px] font-bold tracking-tight">${first.getDate()}–${d.getDate()}</div>
          <div class="mt-1 text-[9px] font-semibold uppercase ${sub}">${first.getMonth() === d.getMonth() ? MONTHS[d.getMonth()] : `${MONTHS[first.getMonth()]}–${MONTHS[d.getMonth()]}`}</div></div>`
      : `<div><div class="text-xl font-bold">${String(d.getDate()).padStart(2, '0')}</div><div class="mt-1 text-[10px] font-semibold uppercase ${sub}">${MONTHS[d.getMonth()]}</div></div>`;
    const body = `
        <div class="relative grid h-14 w-12 shrink-0 place-items-center rounded-2xl ${badgeBg} text-center leading-none" ${first ? `aria-label="Any day ${Fmt.monthDay(first)} to ${Fmt.monthDay(d)}"` : ''}>
          ${badge}
          ${group ? `<span class="absolute -bottom-1.5 -right-1.5 grid h-6 w-6 place-items-center rounded-full bg-sun text-charcoal ring-2 ring-white">${icon('users', 12)}</span>` : ''}
        </div>
        <div class="min-w-0 flex-1">
          <div class="truncate font-semibold ${done ? 'text-muted line-through decoration-1' : ''}">${esc(t.title)}</div>
          <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">${done ? UI.chip('Done', 'bg-mint-tint text-success') : ''}${UI.chip(t.category, CATEGORY_CHIP[t.category] || 'bg-canvas')}<span class="text-xs text-muted">${esc(t.subject_code)}</span></div>
          <div class="mt-1.5 truncate text-xs text-muted">${esc(Fmt.due(t))}</div>
        </div>`;
    // Group work gets a strip across the top: size + whether you already have a group
    const strip = group ? `
        <a href="classmates.html#groups&task=${t.id}" class="flex items-center gap-2 rounded-t-3xl bg-sun-tint px-4 py-2 text-xs">
          ${icon('users', 14)}<span class="font-bold uppercase tracking-wide">Group work</span><span class="text-muted">· ${UI.groupRule(t)}</span>
          <span class="ml-auto">${t.my_group
            ? `<span class="chip bg-white px-2 py-0.5 text-[11px] font-semibold text-success">${icon('check', 11)}${esc(t.my_group)}</span>`
            : t.my_group === null && !done ? '<span class="chip bg-charcoal px-2 py-0.5 text-[11px] font-semibold text-white">Needs a group</span>' : ''}</span>
        </a>` : '';
    const shell = `rounded-3xl bg-white shadow-card transition ${compact ? 'lg:bg-canvas lg:shadow-none' : ''} ${done ? 'opacity-75' : ''} ${group ? 'ring-1 ring-sun' : ''}`;
    if (toggle) {
      return `
      <div class="${shell}">
        ${strip}
        <div class="flex items-center gap-2 pr-3">
          <a href="task.html?id=${t.id}" class="flex min-w-0 flex-1 items-center gap-3 p-4 pr-1">${body}</a>
          ${UI.doneButton(t)}
        </div>
      </div>`;
    }
    return `
      <div class="hover:-translate-y-0.5 ${shell}">
        ${strip}
        <a href="task.html?id=${t.id}" class="group flex items-center gap-3 p-4">
          ${body}
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full ${done ? 'bg-success' : 'bg-charcoal'} text-white">${icon(done ? 'check' : 'arrowRight', 18)}</span>
        </a>
      </div>`;
  },

  /** Round check button that marks a task as done / not done (wire with UI.bindDone). */
  doneButton(t, cls = 'h-10 w-10') {
    const done = !!t.done;
    return `<button type="button" data-done="${t.id}" aria-pressed="${done}" title="${done ? 'Mark as not done' : 'Mark as done'}"
      aria-label="${done ? 'Mark as not done' : 'Mark as done'}: ${esc(t.title)}"
      class="grid ${cls} shrink-0 place-items-center rounded-full transition ${done ? 'bg-success text-white' : 'border-2 border-line bg-white text-subtle hover:border-success hover:text-success'}">${icon('check', 18)}</button>`;
  },

  /** Wires every [data-done] button in root. onChange(taskId, done) runs after the server saves it. */
  bindDone(root, onChange) {
    $$('[data-done]', root).forEach((b) => b.onclick = async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const id = Number(b.dataset.done);
      const next = b.getAttribute('aria-pressed') !== 'true';
      b.disabled = true;
      try {
        const res = await API.post('tasks.php', { action: 'done', task_id: id, done: next });
        UI.undoToast(next ? 'Marked as done' : 'Marked as not done', res, () => onChange?.(id, !next),
          { sub: next ? 'It stays on your schedule, checked off' : '' });
        onChange?.(id, next);
      } catch (err) {
        UI.toast(err.message, { iconName: 'alert' });
      } finally {
        b.disabled = false;
      }
    });
  },

  /** One-line class row for tight spaces (the Class Schedule day view). */
  classRowCompact(c) {
    const now = new Date();
    const mins = now.getHours() * 60 + now.getMinutes();
    const toM = (s) => { const [h, m] = s.split(':').map(Number); return h * 60 + m; };
    const off = !!c.cancelled;   // a "No class" announcement cancelled it on this date
    const live = !off && c.today && mins >= toM(c.start_time) && mins < toM(c.end_time);
    return `
      <a href="subject.html?id=${c.subject_id}" class="flex items-center gap-3 rounded-2xl bg-white px-3 py-2.5 shadow-card transition hover:-translate-y-0.5 ${live ? 'ring-2 ring-charcoal' : ''}" ${off ? `title="${esc(c.cancel_note || 'No class')}"` : ''}>
        <span class="h-8 w-1 shrink-0 rounded-full ${off ? 'opacity-30' : ''}" style="background:${tone(c.color).bar}"></span>
        <div class="w-[4.25rem] shrink-0 leading-tight ${off ? 'text-muted line-through' : ''}">
          <div class="text-xs font-semibold">${Fmt.time(c.start_time)}</div>
          <div class="text-[10px] text-muted">${Fmt.time(c.end_time)}</div>
        </div>
        <div class="min-w-0 flex-1 leading-tight ${off ? 'text-muted' : ''}">
          <div class="truncate text-sm font-semibold ${off ? 'line-through' : ''}">${esc(c.title)}</div>
          <div class="truncate text-[11px] text-muted">${off ? esc(c.cancel_note || 'No class') : esc([c.code, c.room].filter(Boolean).join(' · '))}</div>
        </div>
        ${off ? '<span class="chip shrink-0 bg-danger-tint px-2 py-0.5 text-[10px] font-bold">No class</span>'
          : live ? '<span class="chip shrink-0 bg-charcoal px-2 py-0.5 text-[10px] font-bold text-white">NOW</span>' : ''}
      </a>`;
  },

  /** Time column + coloured bar + subject — "Class Row". */
  classRow(c, { href = `subject.html?id=${c.subject_id}`, trailing = icon('chevronRight', 18, 'text-muted') } = {}) {
    const off = !!c.cancelled;   // a "No class" announcement cancelled it on this date
    return `
      <a href="${href}" class="flex items-center gap-4 rounded-3xl bg-white px-4 py-4 shadow-card transition hover:-translate-y-0.5">
        <div class="w-[4.5rem] shrink-0 ${off ? 'text-muted line-through' : ''}">
          <div class="text-[15px] font-medium">${Fmt.time(c.start_time)}</div>
          <div class="text-xs text-muted">${Fmt.time(c.end_time)}</div>
        </div>
        <span class="w-1 self-stretch rounded-full ${off ? 'opacity-30' : ''}" style="background:${tone(c.color).bar}"></span>
        <div class="min-w-0 flex-1 ${off ? 'text-muted' : ''}">
          <div class="text-[10px] font-semibold tracking-wide text-muted">${esc(c.code)}</div>
          <div class="font-semibold leading-snug ${off ? 'line-through' : ''}">${esc(c.title)}</div>
          <div class="mt-0.5 text-xs text-muted">${off ? esc(c.cancel_note || 'No class') : esc([c.room, c.instructor].filter(Boolean).join(' · '))}</div>
        </div>
        ${off ? '<span class="chip shrink-0 bg-danger-tint text-xs font-bold">No class</span>' : trailing}
      </a>`;
  },

  avatar(name, cls = 'bg-peach', size = 'h-11 w-11 text-sm') {
    return `<span class="grid ${size} shrink-0 place-items-center rounded-full ${cls} font-semibold text-charcoal">${esc(Fmt.initials(name))}</span>`;
  },

  empty(title, text = '', iconName = 'check') {
    // An academic illustration stands in for the icon where one fits the subject; otherwise a periwinkle icon tile
    const art = UI.ILLUS[iconName];
    return `<div class="rounded-3xl bg-white p-8 text-center shadow-card">
      ${art ? `<img src="assets/illus/${art}.svg" alt="" width="56" height="56" class="illus mx-auto h-14 w-14">`
        : `<div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-lavender-tint text-lavender-dark">${icon(iconName, 22)}</div>`}
      <div class="mt-3 font-semibold">${esc(title)}</div>
      ${text ? `<p class="mt-1 text-sm text-muted">${esc(text)}</p>` : ''}
    </div>`;
  },

  skeleton(n = 3, h = 'h-20') {
    return Array.from({ length: n }, () => `<div class="skeleton ${h}"></div>`).join('');
  },

  errorBox(err) {
    return `<div class="rounded-3xl bg-danger-tint p-5 text-sm">${icon('alert', 18, 'inline mr-1 -mt-0.5')} ${esc(err.message || err)}</div>`;
  },

  /* ---------------------------------------------------------- toast */
  toast(message, { sub = '', action = null, duration = 4000, iconName = 'check' } = {}) {
    $('#toast')?.remove();
    const el = document.createElement('div');
    el.id = 'toast';
    el.setAttribute('role', 'status');
    const lifted = $('#undo-bar')?.children.length;   // keep clear of the queue's undo banner
    el.className = `toast fixed inset-x-4 z-[60] mx-auto flex max-w-sm items-center gap-3 rounded-full bg-charcoal py-2 pl-2 pr-2 text-white shadow-float ${lifted ? 'bottom-44 lg:bottom-28' : 'bottom-28 lg:bottom-8'}`;
    el.innerHTML = `
      <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-charcoal">${icon(iconName, 18)}</span>
      <div class="min-w-0 flex-1 py-1"><div class="truncate text-sm font-semibold">${esc(message)}</div>${sub ? `<div class="truncate text-xs text-white/60">${esc(sub)}</div>` : ''}</div>
      ${action ? `<button class="btn btn-yellow h-10 px-5 text-sm">${esc(action.label)}</button>` : '<span class="w-2"></span>'}`;
    document.body.appendChild(el);
    if (action) $('button', el).onclick = () => { el.remove(); action.onClick(); };
    clearTimeout(UI._toastTimer);
    UI._toastTimer = setTimeout(() => el.remove(), duration);
    return el;
  },

  /**
   * Toast for an action the server can reverse (its reply has undo: true). "Undo" POPs the
   * student's UndoStack on the server (api/undo.php), then onUndone() refreshes the page's view.
   */
  undoToast(message, res, onUndone, { sub = '' } = {}) {
    if (!res?.undo) { UI.toast(message, { sub }); return; }
    UI.toast(message, {
      sub,
      duration: 6000,
      action: {
        label: 'Undo',
        onClick: async () => {
          try {
            const r = await API.post('undo.php', {});
            UI.toast('Undone', { sub: r.undone, iconName: 'undo' });
            await onUndone?.();
          } catch (e) {
            UI.toast(e.message, { iconName: 'alert' });
          }
        },
      },
    });
  },

  /* ---------------------------------------------------------- sheet (bottom on mobile, dialog on desktop) */
  sheet(html, { onMount, label = 'Dialog' } = {}) {
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-50 flex items-end justify-center lg:items-center';
    wrap.innerHTML = `
      <div class="sheet-backdrop absolute inset-0" data-close></div>
      <div class="sheet relative max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-[2.25rem] bg-white px-6 pb-8 pt-3 lg:rounded-[2rem] lg:pt-6" role="dialog" aria-modal="true" aria-label="${esc(label)}">
        <div class="mx-auto mb-4 h-1 w-9 rounded-full bg-line lg:hidden"></div>
        ${html}
      </div>`;
    const close = () => { wrap.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (e) => e.key === 'Escape' && close();
    document.addEventListener('keydown', onKey);
    wrap.addEventListener('click', (e) => e.target.closest('[data-close]') && close());
    document.body.appendChild(wrap);
    $('input,button:not([data-close])', wrap)?.focus?.();
    onMount?.(wrap, close);
    return { el: wrap, close };
  },

  /** Wires a form inside a sheet to an async submit with error display. */
  bindForm(form, submit) {
    const err = $('[data-error]', form);
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = $('button[type=submit]', form);
      if (err) err.hidden = true;
      btn.disabled = true;
      try {
        await submit(Object.fromEntries(new FormData(form)));
      } catch (ex) {
        if (err) { err.textContent = ex.message; err.hidden = false; } else UI.toast(ex.message, { iconName: 'alert' });
      } finally {
        btn.disabled = false;
      }
    });
  },

  field({ name, label, type = 'text', placeholder = '', iconName, value = '', required = false, autocomplete = '' }) {
    return `
      <label class="block">
        <span class="mb-1.5 block text-xs text-muted">${esc(label)}</span>
        <span class="field">${iconName ? icon(iconName, 18) : ''}<input name="${name}" type="${type}" placeholder="${esc(placeholder)}" value="${esc(value)}" ${required ? 'required' : ''} ${autocomplete ? `autocomplete="${autocomplete}"` : ''}></span>
      </label>`;
  },
};

/** "President · BSINFO-2A" for officers, or "BSINFO-2A · 2nd Semester" */
const sectionEyebrow = (me) => (me.user.is_officer ? `${me.user.role_label} · ${me.section.name}` : [me.section.name, me.section.semester].filter(Boolean).join(' · '));

/* ===================================================================== SHELL */
const Shell = {
  me: null,

  /** May the logged-in person do this? (The server sends the list; it still checks every action.) */
  can(permission) {
    return (Shell.me?.user.permissions || []).includes(permission);
  },

  /**
   * Nav for this person. The phone bar is always 3 | + | 3:
   *   member:  Home, Schedule, Classmates | + | Groups, Activities, My account
   *   officer: Home, Schedule, Queue      | + | Members/Classmates, Groups, Activities
   * Classmates always comes before Groups. Officers open My account from the avatar in the phone
   * header instead (the queue keeps its badge on the bar); the desktop rail has the avatar for everyone.
   */
  navItems() {
    const president = Shell.can('manage_section');
    const officer = Shell.can('review_requests');
    const classmates = { key: 'classmates', href: 'classmates.html', icon: 'users', label: president ? 'Members' : 'Classmates' };
    const groups = { key: 'groups', href: 'classmates.html#groups', icon: 'groups', label: 'Groups' };
    return [
      { key: 'home', href: 'home.html', icon: 'home', label: 'Home' },
      { key: 'schedule', href: 'schedule.html', icon: 'calendar', label: 'Schedule' },
      officer ? { key: 'queue', href: 'queue.html', icon: 'inbox', label: 'Approval queue', badge: true } : classmates,
      { key: 'add', href: 'task-form.html', icon: 'plus', label: president ? 'Add task' : 'Request a task', fab: true },
      ...(officer ? [classmates, groups] : [groups]),
      { key: 'activities', href: 'activities.html', icon: 'flag', label: 'Activities' },
      { key: 'account', href: 'account.html', icon: 'user', label: 'My account', desktopOnly: officer },
    ];
  },

  /** Officers have no Account button on the phone bar: the phone header shows their avatar instead. */
  accountInHeader() {
    return Shell.navItems().some((it) => it.key === 'account' && it.desktopOnly);
  },

  /**
   * Renders the header, the floating bottom nav (mobile) and the side rail (desktop).
   * opts: { active, eyebrow, title, back, avatar, actions }
   */
  async init(opts = {}) {
    const me = await API.get('auth.php');
    if (!me.section) { location.href = 'section.html'; throw new Error('no section'); }
    Shell.me = me;
    const role = me.user.role;
    document.body.dataset.role = role;

    const [notes, queue] = await Promise.all([
      API.get('notifications.php').catch(() => ({ unread: 0 })),
      // The queue badge counts task requests and people waiting to join
      Shell.can('review_requests') ? API.get('schedule.php').then((s) => s.stats.queue_size + (s.stats.join_requests || 0)).catch(() => 0) : Promise.resolve(0),
    ]);
    const resolve = (v) => (typeof v === 'function' ? v(me) : v);
    Shell.renderHeader({ ...opts, eyebrow: resolve(opts.eyebrow), title: resolve(opts.title), unread: notes.unread });
    Shell.renderNav(opts.active, queue);
    $$('[data-role-only]').forEach((el) => { el.hidden = el.dataset.roleOnly !== role; });
    return me;
  },

  renderHeader({ eyebrow = '', title = '', back = null, avatar = false, unread = 0, search = true }) {
    const header = $('#header');
    if (!header) return;
    const name = Shell.me.user.name;
    const lead = avatar
      ? `<button type="button" data-open-account class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-peach font-semibold lg:hidden" aria-label="Open my account">${esc(Fmt.initials(name))}</button>`
      : back !== false
        ? `<button type="button" data-back class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white shadow-card" aria-label="Go back">${icon('arrowLeft', 20)}</button>`
        : '';
    header.className = 'mb-5 flex items-center gap-3';
    header.innerHTML = `
      ${lead}
      <div class="min-w-0 flex-1">
        <div class="truncate text-xs text-muted">${esc(eyebrow)}</div>
        <h1 class="truncate text-xl font-bold tracking-tight lg:text-[28px] lg:leading-tight">${esc(title)}</h1>
      </div>
      ${search ? `<form action="search.html" class="hidden w-80 items-center gap-2 rounded-full bg-white px-4 py-3 shadow-card lg:flex" role="search">
        ${icon('search', 18, 'text-muted')}<input name="q" class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-hint" placeholder="Search tasks, subjects, classmates" aria-label="Search">
      </form>
      <a href="search.html" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white shadow-card lg:hidden" aria-label="Search">${icon('search', 20)}</a>` : ''}
      <a href="notifications.html" class="relative grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white shadow-card" aria-label="Notifications${unread ? ` (${unread} unread)` : ''}">
        ${icon('bell', 20)}${unread ? '<span class="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-danger ring-2 ring-white"></span>' : ''}
      </a>
      ${!avatar && Shell.accountInHeader() ? `<button type="button" data-open-account class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-peach text-sm font-semibold lg:hidden" aria-label="Open my account">${esc(Fmt.initials(name))}</button>` : ''}`;
    $('[data-back]', header)?.addEventListener('click', () => {
      if (typeof back === 'string') location.href = back;
      else if (history.length > 1 && document.referrer.includes(location.host)) history.back();
      else location.href = 'home.html';
    });
    $('[data-open-account]', header)?.addEventListener('click', () => Shell.openAccountDrawer());
  },

  renderNav(active, queueCount) {
    const items = Shell.navItems();
    // Classmates page opened on its Groups tab (e.g. coming back from My account): highlight Groups, not Members
    if (active === 'classmates' && location.hash.startsWith('#groups') && items.some((it) => it.key === 'groups')) active = 'groups';
    const badge = (it) => (it.badge && queueCount ? `<span class="nav-badge">${queueCount}</span>` : '');

    const mobile = document.createElement('nav');
    mobile.setAttribute('aria-label', 'Main');
    // Left side | + | right side — both sides take equal width so the + stays centred.
    // navItems() keeps 3 | + | 3 for everyone; desktop-only items stay off the phone bar.
    mobile.className = 'nav-pill fixed inset-x-4 bottom-4 z-40 mx-auto flex h-16 max-w-[24rem] items-center rounded-full px-1.5 lg:hidden';
    const phoneItems = items.filter((it) => !it.desktopOnly);
    const fabAt = phoneItems.findIndex((it) => it.fab);
    const phoneActive = active;
    const link = (it) => `<a href="${it.href}" data-nav="${it.key}" class="nav-item ${phoneActive === it.key ? 'active' : ''}" aria-label="${it.label}" ${phoneActive === it.key ? 'aria-current="page"' : ''}>${icon(it.icon, 20)}${badge(it)}</a>`;
    const side = (list) => `<div class="flex min-w-0 flex-1 items-center justify-evenly">${list.map(link).join('')}</div>`;
    mobile.innerHTML = `${side(phoneItems.slice(0, fabAt))}
      <a href="${phoneItems[fabAt].href}" class="nav-fab mx-1 shrink-0" aria-label="${phoneItems[fabAt].label}">${icon('plus', 22)}</a>
      ${side(phoneItems.slice(fabAt + 1))}`;

    const rail = document.createElement('nav');
    rail.setAttribute('aria-label', 'Main');
    rail.className = 'nav-pill fixed bottom-4 left-4 top-4 z-40 hidden w-[4.5rem] flex-col items-center rounded-[2.25rem] py-4 lg:flex';
    const railItems = items.filter((it) => it.key !== 'account' && !it.fab);
    const fab = items.find((it) => it.fab);
    const acct = items.find((it) => it.key === 'account');
    rail.innerHTML = `
      <a href="home.html" class="mb-8 grid h-11 w-11 place-items-center rounded-xl bg-lavender text-sm font-bold text-charcoal" aria-label="AcadSync home">AS</a>
      <div class="flex flex-col items-center gap-3">
        ${railItems.map((it) => `<a href="${it.href}" data-nav="${it.key}" class="nav-item ${active === it.key ? 'active' : ''}" title="${it.label}" aria-label="${it.label}">${icon(it.icon, 20)}${badge(it)}</a>`).join('')}
        <a href="${fab.href}" class="nav-fab mt-1" title="${fab.label}" aria-label="${fab.label}">${icon('plus', 22)}</a>
      </div>
      <button type="button" data-open-account class="mt-auto grid h-12 w-12 place-items-center rounded-full ${active === 'account' ? 'bg-sun' : 'bg-peach'} text-charcoal" title="${acct.label}" aria-label="${acct.label}">${icon('user', 20)}</button>`;
    $('[data-open-account]', rail).onclick = () => Shell.openAccountDrawer();

    document.body.append(mobile, rail);
  },

  /* "My account" side drawer (from avatar / rail) */
  openAccountDrawer() {
    const { user, section, stats } = Shell.me;
    const admin = user.role === 'admin';
    const statCells = admin
      ? [[stats.subjects, 'Subjects'], [stats.published, 'Published'], [stats.reviewed, 'Reviewed']]
      : [[stats.submitted, 'Submitted'], [stats.approved, 'Approved'], [stats.groups, 'Groups']];
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-50';
    wrap.innerHTML = `
      <div class="sheet-backdrop absolute inset-0" data-close></div>
      <aside class="drawer absolute bottom-0 right-0 top-0 flex w-[85%] max-w-sm flex-col overflow-y-auto rounded-l-[2.25rem] bg-white p-5" role="dialog" aria-modal="true" aria-label="My account">
        <div class="mb-4 mt-6 flex items-center justify-between">
          <h2 class="text-xl font-bold">My account</h2>
          <button data-close class="grid h-10 w-10 place-items-center rounded-full bg-canvas" aria-label="Close">${icon('x', 18)}</button>
        </div>
        ${Shell.profileCard(admin)}
        <div class="mt-4 grid grid-cols-3 gap-2">
          ${statCells.map(([n, l]) => `<div class="rounded-2xl bg-canvas p-3"><div class="text-lg font-bold">${n}</div><div class="text-xs text-muted">${l}</div></div>`).join('')}
        </div>
        <h3 class="mt-5 font-semibold">My sections</h3>
        <div class="mt-2">${Shell.sectionList()}</div>
        <div class="mt-5 space-y-1">
          ${[['user', 'Edit profile', 'account.html#profile'], ...(admin ? [] : [['inbox', 'My tasks · pending & history', 'my-tasks.html']]),
            ['clipboard', 'My member record', `records.html?id=${user.id}`],
            ...(Shell.can('view_member_records') ? [['users', 'Member records & officers', 'records.html']] : []),
            ['flag', 'Activities', 'activities.html'],
            ['clipboard', Shell.can('manage_dues') ? 'Dues & payments' : 'My dues', 'dues.html'],
            ['bell', 'Notifications', 'notifications.html'], ['lock', 'Change password', 'account.html#password']]
            .map(([i, l, h]) => `<a href="${h}" class="flex items-center gap-3 rounded-2xl px-1 py-2 hover:bg-canvas"><span class="grid h-9 w-9 place-items-center rounded-full bg-canvas">${icon(i, 18)}</span><span class="flex-1 text-sm font-medium">${l}</span>${icon('chevronRight', 18, 'text-muted')}</a>`).join('')}
        </div>
        <div class="mt-auto pt-6"><button data-logout class="btn btn-danger w-full">Log out</button></div>
      </aside>`;
    wrap.addEventListener('click', (e) => e.target.closest('[data-close]') && wrap.remove());
    $('[data-logout]', wrap).onclick = Shell.logout;
    Shell.bindSectionList(wrap);
    document.body.appendChild(wrap);
  },

  /** Every section the user belongs to; the open one is marked, others switch on click. */
  sectionList() {
    const { section, sections } = Shell.me;
    return `<div class="space-y-2">
      ${sections.map((s) => `
        <button type="button" data-switch="${s.id}" class="flex w-full items-center gap-3 rounded-2xl bg-canvas p-3 text-left">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-charcoal text-sm font-semibold text-white">${esc(s.name.slice(-2))}</span>
          <span class="min-w-0 flex-1"><span class="block font-semibold">${esc(s.name)}</span>
            <span class="block truncate text-xs text-muted">${s.role === 'admin' ? `President · code ${esc(s.class_code)}` : 'Member'} · ${s.members} members · Joined ${esc(Shell.monthYear(s.joined_at))}</span></span>
          ${s.id === section.id ? '<span class="chip bg-mint-tint text-success">Active</span>' : '<span class="chip bg-white">Open</span>'}
        </button>`).join('')}
      <a href="section.html" class="flex items-center gap-3 rounded-2xl p-3 hover:bg-canvas">
        <span class="grid h-10 w-10 place-items-center rounded-full bg-canvas">${icon('plus', 18)}</span>
        <span class="flex-1 text-sm font-medium">Join or create another section</span>${icon('chevronRight', 18, 'text-muted')}
      </a>
    </div>`;
  },

  bindSectionList(root) {
    $$('[data-switch]', root).forEach((b) => b.onclick = async () => {
      const id = Number(b.dataset.switch);
      if (id === Shell.me.section.id) return;
      await API.post('section.php', { action: 'switch', section_id: id });
      location.href = 'home.html';
    });
  },

  profileCard(admin, compact = false) {
    const { user, section } = Shell.me;
    const roleLabel = `${user.role_label || (admin ? 'President' : 'Member')} · ${section.name}`;
    return `
      <div class="card-pastel ${admin ? 'yellow' : 'peach'} rounded-[1.75rem] p-5">
        <span class="sparkle" aria-hidden="true"></span><span class="ring-deco"></span>
        <div class="${compact ? 'flex items-center gap-4' : ''}">
          <span class="grid ${compact ? 'h-14 w-14' : 'h-14 w-14 mb-3'} place-items-center rounded-full bg-white text-lg font-semibold">${esc(Fmt.initials(user.name))}</span>
          <div class="min-w-0">
            <div class="truncate text-lg font-bold">${esc(user.name)}</div>
            <div class="truncate text-xs">${esc(user.email)}</div>
          </div>
        </div>
        <span class="chip mt-3 bg-charcoal text-white">${esc(roleLabel)}</span>
      </div>`;
  },

  monthYear(value) {
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d) ? '' : `${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
  },

  async logout() {
    await API.post('auth.php', { action: 'logout' }, { redirect: false }).catch(() => {});
    location.href = 'index.html';
  },
};

/* ================================================================= DATE PICKER
 * Exact day / Date range / Not set yet + month grid, as in "Request a Task".
 */
function createDatePicker(root, { type = 'exact', start = null, end = null, markers = new Set(), allowModes = true, onChange } = {}) {
  const state = { type, start, end, view: Fmt.date(start || Fmt.iso(new Date())) };
  state.view = new Date(state.view.getFullYear(), state.view.getMonth(), 1);

  function render() {
    const y = state.view.getFullYear();
    const m = state.view.getMonth();
    const first = new Date(y, m, 1).getDay();
    const days = new Date(y, m + 1, 0).getDate();
    const cells = [];
    for (let i = 0; i < first; i++) cells.push({ d: new Date(y, m, i - first + 1), out: true });
    for (let i = 1; i <= days; i++) cells.push({ d: new Date(y, m, i) });
    while (cells.length % 7) cells.push({ d: new Date(y, m + 1, cells.length - first - days + 1), out: true });
    const today = Fmt.iso(new Date());

    root.innerHTML = `
      <div class="rounded-[1.75rem] bg-canvas p-3">
        ${allowModes ? `<div class="mb-3 grid grid-cols-3 rounded-full bg-white p-1 text-sm" role="tablist">
          ${[['exact', 'Exact day'], ['range', 'Date range'], ['unset', 'Not set yet']].map(([k, l]) => `<button type="button" role="tab" data-mode="${k}" aria-selected="${state.type === k}" class="rounded-full py-2.5 ${state.type === k ? 'bg-charcoal text-white' : 'text-muted'}">${l}</button>`).join('')}
        </div>` : ''}
        ${state.type === 'unset' ? `<div class="rounded-2xl bg-sun-tint p-4 text-sm"><div class="font-semibold">We’ll show “Date not set yet”</div><div class="text-xs text-muted">Edit the task once the professor announces the date.</div></div>` : `
        <div class="flex items-center justify-between px-2 py-1">
          <div class="font-semibold">${MONTHS_LONG[m]} ${y}</div>
          <div class="flex gap-2">
            <button type="button" data-nav="-1" class="grid h-8 w-8 place-items-center rounded-full bg-white" aria-label="Previous month">${icon('chevronLeft', 16)}</button>
            <button type="button" data-nav="1" class="grid h-8 w-8 place-items-center rounded-full bg-white" aria-label="Next month">${icon('chevronRight', 16)}</button>
          </div>
        </div>
        <div class="mt-2 grid grid-cols-7 gap-y-1 text-center text-xs text-muted">${'SMTWTFS'.split('').map((c) => `<div class="py-1">${c}</div>`).join('')}</div>
        <div class="grid grid-cols-7 gap-y-1">
          ${cells.map(({ d, out }) => {
            const iso = Fmt.iso(d);
            const sel = iso === state.start || iso === state.end;
            const inRange = state.type === 'range' && state.start && state.end && iso > state.start && iso < state.end;
            const past = iso < today;
            return `<button type="button" data-day="${iso}" ${past ? 'disabled' : ''} class="cal-day ${out || past ? 'muted' : ''} ${sel ? 'selected' : ''} ${inRange ? 'in-range' : ''} ${markers.has(iso) ? 'has-task' : ''}" aria-pressed="${sel}" aria-label="${Fmt.long(d)}">${d.getDate()}</button>`;
          }).join('')}
        </div>
        <div class="mt-2 flex items-center gap-2 px-2 text-xs text-muted">
          ${state.type === 'range' ? '<span class="h-2 w-2 rounded-full bg-lavender"></span>Students can submit on any day in the range' : '<span class="h-2 w-2 rounded-full bg-lavender-tint ring-1 ring-lavender"></span>Another task is due'}
        </div>`}
      </div>`;

    $$('[data-mode]', root).forEach((b) => b.onclick = () => { state.type = b.dataset.mode; if (state.type !== 'range') state.end = null; render(); emit(); });
    $$('[data-nav]', root).forEach((b) => b.onclick = () => { state.view = new Date(y, m + Number(b.dataset.nav), 1); render(); });
    $$('[data-day]', root).forEach((b) => b.onclick = () => {
      const iso = b.dataset.day;
      if (state.type === 'range') {
        if (!state.start || state.end || iso < state.start) { state.start = iso; state.end = null; }
        else if (iso !== state.start) state.end = iso;
      } else {
        state.start = iso;
      }
      render(); emit();
    });
  }
  const emit = () => onChange?.(api.value());
  const api = {
    value: () => ({ type: state.type, start: state.start, end: state.end }),
    set(v) { Object.assign(state, v); if (v.start) state.view = new Date(Fmt.date(v.start).getFullYear(), Fmt.date(v.start).getMonth(), 1); render(); },
  };
  render();
  return api;
}

/** Row of selectable time chips with a "Custom…" option. Values are "HH:MM". */
function createTimeChips(root, { options = ['07:00', '08:00', '10:00', '13:00', '15:00', '17:00', '23:59'], value = null, onChange } = {}) {
  let current = value;
  function render() {
    const custom = current && !options.includes(current);
    root.innerHTML = `<div class="flex flex-wrap gap-2">
      ${options.map((o) => `<button type="button" data-time="${o}" aria-pressed="${current === o}" class="rounded-full px-4 py-2.5 text-sm font-medium ${current === o ? 'bg-charcoal text-white' : 'bg-canvas'}">${Fmt.time(o)}</button>`).join('')}
      <label class="flex items-center gap-1 rounded-full px-4 py-2 text-sm font-medium ${custom ? 'bg-charcoal text-white' : 'bg-canvas'}">
        <span>${custom ? Fmt.time(current) : 'Custom…'}</span>
        <input type="time" class="w-0 opacity-0 focus:w-auto focus:opacity-100 text-charcoal" value="${custom ? current : ''}" aria-label="Custom time">
      </label>
    </div>`;
    $$('[data-time]', root).forEach((b) => b.onclick = () => { current = b.dataset.time; render(); onChange?.(current); });
    const input = $('input[type=time]', root);
    $('label', root).onclick = () => input.showPicker?.();
    input.onchange = () => { if (input.value) { current = input.value; render(); onChange?.(current); } };
  }
  render();
  return { value: () => current, set(v) { current = v; render(); } };
}
