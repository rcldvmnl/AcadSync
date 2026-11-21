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

/*
 * Each subject's card carries its own drawing (stored in subjects.design; the server keeps the same list in
 * api/subjects.php DESIGNS). Empty / 'auto' = picked from the subject's code and name by the `match` patterns.
 */
const SUBJECT_DESIGNS = {
  science: { label: 'Science', match: /scien|physic|chem|biolog|\bsts\b/i,
    svg: '<circle cx="12" cy="12" r="1.6"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/>' },
  algorithm: { label: 'Tree', match: /algorithm|data struct|\bdsa\b/i,
    svg: '<circle cx="12" cy="4.5" r="2.3"/><circle cx="6" cy="12" r="2.3"/><circle cx="18" cy="12" r="2.3"/><circle cx="3.5" cy="19.5" r="2"/><circle cx="9" cy="19.5" r="2"/><path d="m10.4 6.3-2.8 3.9M13.6 6.3l2.8 3.9M5 14l-1 3.6M7 14l1.2 3.6"/>' },
  database: { label: 'Database', match: /information manag|database|\bsql\b|data manag/i,
    svg: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>' },
  chart: { label: 'Chart', match: /quantitativ|math|statist|calculus|model|simulat|analytic|account/i,
    svg: '<path d="M3 21h18"/><rect x="4.5" y="12" width="3.5" height="6.5" rx="1"/><rect x="10.25" y="7" width="3.5" height="11.5" rx="1"/><rect x="16" y="3.5" width="3.5" height="15" rx="1"/>' },
  code: { label: 'Code', match: /program|object-orient|software|coding|web|app dev/i,
    svg: '<path d="m8 7-5 5 5 5M16 7l5 5-5 5M14 4l-4 16"/>' },
  chip: { label: 'Chip', match: /platform|network|hardware|operating|computer sys|architect/i,
    svg: '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>' },
  book: { label: 'Book', match: /literat|panitik|sosyedad|filipino|english|reading|writing|commun|histor|soslit/i,
    svg: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/><path d="M5 8.5h3M5 12h3M16 8.5h3M16 12h3"/>' },
  sport: { label: 'Sports', match: /pathfit|physical|sport|dance|fitness|martial|\bpe\b/i,
    svg: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3v18M5.6 5.6a9 9 0 0 1 0 12.8M18.4 5.6a9 9 0 0 0 0 12.8"/>' },
  notebook: { label: 'Notebook', match: null, svg: ICON_PATHS.notebook },
};
/** The design key for a subject: its saved choice, else the first design whose pattern fits its name, else one by id. */
function subjectDesign(s) {
  if (s.design && SUBJECT_DESIGNS[s.design]) return s.design;
  const text = `${s.code || ''} ${s.title || ''}`;
  const keys = Object.keys(SUBJECT_DESIGNS);
  const hit = keys.find((k) => SUBJECT_DESIGNS[k].match?.test(text));
  return hit || keys[Number(s.id || 0) % keys.length];
}
/** A subject design's drawing as an inline SVG (line art, currentColor). */
function subjectArt(key, size = 20, cls = '', width = 1.75) {
  const d = SUBJECT_DESIGNS[key] || SUBJECT_DESIGNS.notebook;
  return `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${width}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d.svg}</svg>`;
}
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
      <span class="m-toast-icon grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-charcoal">${icon(iconName, 18)}</span>
      <div class="min-w-0 flex-1 py-1"><div class="truncate text-sm font-semibold">${esc(message)}</div>${sub ? `<div class="truncate text-xs text-white/60">${esc(sub)}</div>` : ''}</div>
      ${action ? `<button class="btn btn-yellow h-10 px-5 text-sm">${esc(action.label)}</button>` : '<span class="w-2"></span>'}`;
    document.body.appendChild(el);
    if (action) $('button', el).onclick = () => { el.remove(); action.onClick(); };
    clearTimeout(UI._toastTimer);
    UI._toastTimer = setTimeout(() => Motion.leave(el, 'is-leaving', 180), duration);
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
    // Closing plays a short exit (Motion.leave) before the sheet leaves the page
    const close = () => { document.removeEventListener('keydown', onKey); Motion.leave(wrap, 'is-closing', 200); };
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
    Motion.enter();   // the page's cards arrive as a short list once its content renders
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
    wrap.addEventListener('click', (e) => e.target.closest('[data-close]') && Motion.leave(wrap, 'is-closing', 200));
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

/* ------------------------------------------------------------------ Motion: feedback, state and the cap toss
 * One authored moment: the graduation-cap toss (the intro, and every task marked done or request/task added).
 * Everything else acknowledges an action or explains a state change:
 *   - every saved change (API.post → 'acadsync:action') gets a sparkle burst from where you tapped,
 *     a bigger burst + cap toss for milestones, a soft ring for removals and cancellations;
 *   - buttons, chips, nav items and coloured cards ripple from the touch point;
 *   - coloured cards lean toward the pointer, catch the light, and their illustration drifts (mouse only);
 *   - a page's cards arrive as a short list on first render, and stat numbers count up;
 *   - sheets, drawers and toasts leave with a short exit.
 * prefers-reduced-motion: no travel, particles, tilt or intro; colour/state changes and the toast still confirm.
 * Content is visible by default: Motion only adds classes and temporary elements.
 */
const Motion = {
  last: { x: window.innerWidth / 2, y: window.innerHeight / 2 },   // where the last tap landed
  COLORS: ['#F58D42', '#A8A3F7', '#FFD34E', '#5F58D6', '#F9B886'],

  reduced() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; },

  layer() {
    let l = document.getElementById('m-fx');
    if (!l) {
      l = document.createElement('div');
      l.id = 'm-fx';
      l.className = 'm-fx';
      l.setAttribute('aria-hidden', 'true');
      document.body.appendChild(l);
    }
    return l;
  },

  /** Removes el after its exit class has played (immediately with reduced motion). */
  leave(el, cls, ms) {
    if (!el || !el.isConnected) return;
    if (Motion.reduced()) { el.remove(); return; }
    el.classList.add(cls);
    setTimeout(() => el.remove(), ms);
  },

  /** A burst of four-point sparkle stars (and a few dots) from x, y. */
  burst(x, y, { count = 12, spread = 90, big = false } = {}) {
    if (Motion.reduced()) return;
    const layer = Motion.layer();
    for (let i = 0; i < count; i += 1) {
      const el = document.createElement('span');
      el.className = i % 3 === 2 ? 'm-dot' : 'm-star';
      el.style.color = Motion.COLORS[i % Motion.COLORS.length];
      const size = (big ? 11 : 8) + Math.random() * (big ? 10 : 7);
      el.style.width = `${size}px`;
      el.style.height = `${size}px`;
      el.style.left = `${x - size / 2}px`;
      el.style.top = `${y - size / 2}px`;
      layer.appendChild(el);
      const a = (Math.PI * 2 * i) / count + Math.random() * 0.5;
      const d = spread * (0.55 + Math.random() * 0.6);
      const dx = Math.cos(a) * d;
      const dy = Math.sin(a) * d - (big ? 30 : 14);
      const spin = 120 + Math.random() * 160;
      el.animate([
        { transform: 'translate(0, 0) scale(.3) rotate(0deg)', opacity: 1 },
        { transform: `translate(${dx}px, ${dy}px) scale(1) rotate(${spin}deg)`, opacity: 1, offset: 0.55 },
        { transform: `translate(${dx * 1.15}px, ${dy + 28}px) scale(.55) rotate(${spin * 1.6}deg)`, opacity: 0 },
      ], { duration: 720 + Math.random() * 260, easing: 'cubic-bezier(.16,1,.3,1)' }).onfinish = () => el.remove();
    }
  },

  /** The graduation cap tossed up from x, y: it spins, peaks with a small burst of stars, and drifts down. */
  capToss(x, y, { height = 140 } = {}) {
    if (Motion.reduced()) return;
    const cap = document.createElement('img');
    cap.src = 'assets/illus/cap.svg';
    cap.alt = '';
    cap.className = 'm-cap';
    cap.style.left = `${x - 32}px`;
    cap.style.top = `${y - 32}px`;
    Motion.layer().appendChild(cap);
    const side = Math.random() < 0.5 ? -1 : 1;
    const drift = side * (18 + Math.random() * 26);
    cap.animate([
      { transform: 'translate(0, 0) rotate(0deg) scale(.6)', opacity: 0 },
      { transform: `translate(${drift * 0.4}px, ${-height}px) rotate(${side * 200}deg) scale(1)`, opacity: 1, offset: 0.45 },
      { transform: `translate(${drift}px, ${-height * 0.55}px) rotate(${side * 330}deg) scale(.95)`, opacity: 1, offset: 0.78 },
      { transform: `translate(${drift * 1.2}px, ${-height * 0.3}px) rotate(${side * 360}deg) scale(.9)`, opacity: 0 },
    ], { duration: 1100, easing: 'cubic-bezier(.33,0,.2,1)' }).onfinish = () => cap.remove();
    setTimeout(() => Motion.burst(x + drift * 0.4, y - height, { count: 10, spread: 70 }), 480);
  },

  /** A quiet ring for removals, cancellations and undo: acknowledged, not celebrated. */
  puff(x, y) {
    if (Motion.reduced()) return;
    const ring = document.createElement('span');
    ring.className = 'm-ring';
    ring.style.left = `${x}px`;
    ring.style.top = `${y}px`;
    Motion.layer().appendChild(ring);
    ring.animate([{ transform: 'scale(.4)', opacity: 0.8 }, { transform: 'scale(1.6)', opacity: 0 }],
      { duration: 420, easing: 'cubic-bezier(.16,1,.3,1)' }).onfinish = () => ring.remove();
  },

  /** level: 'cap' (milestone), 'burst' (a saved change), 'soft' (removal / cancel / undo). */
  celebrate(level, x = Motion.last.x, y = Motion.last.y) {
    if (!level) return;
    if (level === 'cap') {
      Motion.burst(x, y, { count: 16, spread: 110, big: true });
      Motion.capToss(x, y);
    } else if (level === 'burst') {
      Motion.burst(x, y);
    } else {
      Motion.puff(x, y);
    }
    // Many forms open another page right after saving: the next page plays the moment instead
    if (level !== 'soft') {
      try {
        sessionStorage.setItem('acadsync.fx', JSON.stringify({ level, t: Date.now() }));
        setTimeout(() => { try { sessionStorage.removeItem('acadsync.fx'); } catch { /* private mode */ } }, 1200);
      } catch { /* private mode */ }
    }
  },

  /** How much a saved change deserves, from the endpoint and its action. */
  levelFor(path, body) {
    const a = String(body.action || '').toLowerCase();
    if (/^(login|logout|check_email|check_clash|forgot|read|read_all|mark_read|seen)$/.test(a) || path.startsWith('notifications')) return null;
    if (path.startsWith('tasks.php') && a === 'done') return body.done ? 'cap' : 'soft';
    if (/reject|remove|delete|cancel|withdraw|leave|step_down|unpick|undo|unpin|decline|kick/.test(a) || body.done === false || body.present === false) return 'soft';
    if (path.startsWith('request_task') || path.startsWith('undo') === false && /^(approve|accept|admit|create|create_due|record_payment|post|publish|add|register|join)/.test(a)) return 'cap';
    return 'burst';
  },

  /** A page's cards arrive as a short list the first time #main has real content (stagger capped at 360 ms). */
  enter(root = document.getElementById('main')) {
    if (!root || Motion.reduced()) return;
    const run = () => {
      if (root.dataset.mEntered || !root.children.length || root.querySelector('.skeleton')) return false;
      root.dataset.mEntered = '1';
      const inView = (el) => el.getBoundingClientRect().top < window.innerHeight + 120;
      const picked = [];
      root.querySelectorAll('.card-pastel, .stat-card, .shadow-card, .act-card, article').forEach((el) => {
        if (picked.length < 14 && inView(el) && !picked.some((p) => p.contains(el) || el.contains(p))) picked.push(el);
      });
      root.querySelectorAll('section').forEach((el) => {
        if (picked.length < 14 && inView(el) && !picked.some((p) => p.contains(el) || el.contains(p))) picked.push(el);
      });
      if (!picked.length) picked.push(...[...root.children].slice(0, 8));
      picked.sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));
      picked.forEach((el, i) => {
        el.style.setProperty('--m-d', `${Math.min(i * 45, 360)}ms`);
        el.classList.add('m-enter');
        el.addEventListener('animationend', () => { el.classList.remove('m-enter'); el.style.removeProperty('--m-d'); }, { once: true });
      });
      Motion.countUp(root);
      Motion.replayPending();
      return true;
    };
    if (run()) return;
    const mo = new MutationObserver(() => { if (run()) mo.disconnect(); });
    mo.observe(root, { childList: true, subtree: true });
    setTimeout(() => mo.disconnect(), 8000);
  },

  /** Stat numbers count up from 0 (only plain integers at the start of a big number). */
  countUp(root) {
    if (Motion.reduced()) return;
    root.querySelectorAll('.stat-num, .text-4xl.font-extrabold, .font-extrabold').forEach((el) => {
      if (el.textContent.length > 30 || el.closest('button, a.btn')) return;
      const node = [...el.childNodes].find((n) => n.nodeType === 3 && n.textContent.trim());
      const m = node && /^(\s*)(\d{1,5})(.*)$/s.exec(node.textContent);
      if (!m || Number(m[2]) < 2) return;
      const target = Number(m[2]);
      const t0 = performance.now();
      const step = (t) => {
        const p = Math.min(1, (t - t0) / 650);
        node.textContent = `${m[1]}${Math.round(target * (1 - (1 - p) ** 3))}${m[3]}`;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  },

  /** A milestone saved just before this page opened (e.g. a task request) plays here. */
  replayPending() {
    let fx = null;
    try { fx = JSON.parse(sessionStorage.getItem('acadsync.fx') || 'null'); sessionStorage.removeItem('acadsync.fx'); } catch { /* private mode */ }
    if (!fx || Date.now() - fx.t > 5000) return;
    setTimeout(() => {
      if (fx.level === 'cap') {
        Motion.burst(window.innerWidth / 2, window.innerHeight * 0.38, { count: 16, spread: 110, big: true });
        Motion.capToss(window.innerWidth / 2, window.innerHeight * 0.38);
      } else {
        Motion.burst(window.innerWidth / 2, window.innerHeight * 0.38);
      }
    }, 320);
  },

  /** The intro, once per visit: the AcadSync tile rises and the cap is tossed above it. A tap or any key skips. */
  intro() {
    try {
      if (sessionStorage.getItem('acadsync.intro')) return;
      sessionStorage.setItem('acadsync.intro', '1');
    } catch { return; }
    if (Motion.reduced()) return;
    const el = document.createElement('div');
    el.className = 'm-intro';
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML = '<div class="m-intro-mark"><span class="m-intro-tile">AS</span><span class="m-intro-word">AcadSync</span></div>';
    document.body.appendChild(el);
    let finished = false;
    const done = () => {
      if (finished) return;
      finished = true;
      el.classList.add('is-done');
      setTimeout(() => el.remove(), 400);
      window.removeEventListener('keydown', done);
    };
    el.addEventListener('pointerdown', done);
    window.addEventListener('keydown', done);
    setTimeout(() => {
      const r = $('.m-intro-tile', el).getBoundingClientRect();
      Motion.capToss(r.left + r.width / 2, r.top + 6, { height: 110 });
    }, 220);
    setTimeout(done, 1250);
  },

  /** Ripple from the touch point on anything pressable that carries colour or a pill shape. */
  bindRipple() {
    document.addEventListener('pointerdown', (e) => {
      Motion.last = { x: e.clientX, y: e.clientY };
      if (Motion.reduced()) return;
      const el = e.target.closest?.('.btn, .chip-filter, .nav-item, .nav-fab, .day-pill, .card-pastel, .stat-card, .act-kind, [role="tab"]');
      if (!el || el.disabled) return;
      if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
      let host = [...el.children].find((c) => c.classList.contains('m-ripple-host'));
      if (!host) {
        host = document.createElement('span');
        host.className = 'm-ripple-host';
        host.setAttribute('aria-hidden', 'true');
        el.appendChild(host);
      }
      const r = el.getBoundingClientRect();
      const dot = document.createElement('span');
      dot.className = 'm-ripple';
      dot.style.left = `${e.clientX - r.left}px`;
      dot.style.top = `${e.clientY - r.top}px`;
      dot.style.setProperty('--m-r', String(Math.ceil(Math.hypot(r.width, r.height) / 6)));
      host.appendChild(dot);
      dot.addEventListener('animationend', () => dot.remove());
    }, { passive: true });
  },

  /** Coloured cards lean toward the mouse (up to 5°, 3° on wide cards); the light and the illustration follow. */
  bindTilt() {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    let cur = null;
    let ev = null;
    let raf = 0;
    const apply = () => {
      raf = 0;
      if (!cur || !ev) return;
      const r = cur.getBoundingClientRect();
      const px = Math.min(1, Math.max(0, (ev.clientX - r.left) / r.width));
      const py = Math.min(1, Math.max(0, (ev.clientY - r.top) / r.height));
      const max = r.width > 420 ? 3 : 5;
      cur.style.setProperty('--m-ry', `${((px - 0.5) * 2 * max).toFixed(2)}deg`);
      cur.style.setProperty('--m-rx', `${((0.5 - py) * 2 * max).toFixed(2)}deg`);
      cur.style.setProperty('--m-gx', `${(px * 100).toFixed(1)}%`);
      cur.style.setProperty('--m-gy', `${(py * 100).toFixed(1)}%`);
      cur.style.setProperty('--m-px', `${((px - 0.5) * 12).toFixed(1)}px`);
      cur.style.setProperty('--m-py', `${((py - 0.5) * 12).toFixed(1)}px`);
    };
    const release = () => {
      if (!cur) return;
      cur.classList.remove('m-tilting');
      ['--m-rx', '--m-ry', '--m-px', '--m-py'].forEach((p) => cur.style.removeProperty(p));
      cur = null;
    };
    document.addEventListener('pointermove', (e) => {
      if (e.pointerType !== 'mouse' || Motion.reduced()) return;
      const el = e.target.closest?.('.card-pastel, .stat-card');
      if (el !== cur) {
        release();
        if (el) { cur = el; el.classList.add('m-tilting'); }
      }
      ev = e;
      if (cur && !raf) raf = requestAnimationFrame(apply);
    }, { passive: true });
    document.documentElement.addEventListener('mouseleave', release);
    window.addEventListener('blur', release);
  },

  init() {
    Motion.bindRipple();
    Motion.bindTilt();
    // Every saved change is acknowledged where it happened; a task marked done also pops its check
    document.addEventListener('acadsync:action', (e) => {
      const { path, body } = e.detail;
      Motion.celebrate(Motion.levelFor(path, body));
      if (path.startsWith('tasks.php') && body.action === 'done' && body.done) {
        [80, 400].forEach((ms) => setTimeout(() => {
          $$(`[data-done="${Number(body.task_id)}"][aria-pressed="true"]`).forEach((b) => {
            b.classList.remove('m-pop');
            void b.offsetWidth;   // restart the pop
            b.classList.add('m-pop');
          });
        }, ms));
      }
    });
    Motion.intro();
    // Pages without Shell (login, sign-up) still replay a milestone saved on the page before
    if (!document.getElementById('main')) setTimeout(() => Motion.replayPending(), 0);
  },
};
Motion.init();
