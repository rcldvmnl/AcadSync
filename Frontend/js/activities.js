// Activities: the organization's meetings, seminars, outreach, events, competitions and section projects.
//   RSVP takes a seat; when an activity is full you join its waitlist (a QUEUE on the server),
//   and the first person waiting gets the next seat that opens.
//   Competitions and section projects are made of parts: you volunteer for a part, officers pick
//   (projects: officers can also assign), and project parts are marked done.
//   Officers add, edit and cancel activities; the Secretary and the President take attendance.
//   Each kind has its own rule (Meeting / Seminar / Outreach / Competition / SectionProject are classes on the server).

const KIND_TINT = { meeting: 'bg-lavender-tint', seminar: 'bg-peach-tint', outreach: 'bg-[#EDEDF2]', event: 'bg-sun-tint', competition: 'bg-sun-tint', project: 'bg-lavender-tint' };
let data = null;
let tab = location.hash === '#past' ? 'past' : 'upcoming';
// Cancelled activities are hidden unless the viewer asks to see them (remembered in this browser only)
let showCancelled = false;
try { showCancelled = localStorage.getItem('acadsync.showCancelled') === '1'; } catch { /* private mode */ }
const setShowCancelled = (on) => {
  showCancelled = on;
  try { localStorage.setItem('acadsync.showCancelled', on ? '1' : '0'); } catch { /* private mode */ }
};
// Cancelled over 3 days ago: sent apart by the server (old_cancelled), listed under Past in a closed box
let oldOpen = false;
const visible = (k) => data[k].filter((a) => showCancelled || !a.cancelled);
const cancelledIn = (k) => data[k].filter((a) => a.cancelled).length + (k === 'past' ? data.old_cancelled.length : 0);
const shownCount = (k) => visible(k).length + (k === 'past' && showCancelled ? data.old_cancelled.length : 0);

const hm = (d) => Fmt.time(`${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`);
const isParts = (a) => a.joins_by === 'parts';
const whenText = (a) => {
  const s = new Date(a.start_ts * 1000);
  const e = new Date(a.end_ts * 1000);
  if (a.multi_day) return `${rangeText(s, e)} · ${hm(s)} – ${hm(e)}`;
  return `${Fmt.short(s)} · ${hm(s)} – ${hm(e)}`;
};
/** "Nov 25–26", or "Nov 30 – Dec 2" across months. */
const rangeText = (s, e) => (s.getMonth() === e.getMonth()
  ? `${MONTHS[s.getMonth()]} ${s.getDate()}–${e.getDate()}`
  : `${Fmt.monthDay(s)} – ${Fmt.monthDay(e)}`);
/** Whole days from today to an ISO date (0 = today, negative = past). */
const daysUntil = (iso) => {
  const t = new Date(); t.setHours(0, 0, 0, 0);
  return Math.round((Fmt.date(iso) - t) / 86400e3);
};
const whenChip = (iso) => {
  const n = daysUntil(iso);
  if (n === 0) return UI.chip('Today', 'bg-sun-tint');
  if (n === 1) return UI.chip('Tomorrow', 'bg-sun-tint');
  return n > 1 ? `<span class="text-xs text-muted">in ${n} days</span>` : '';
};
const findActivity = (id) => [...data.upcoming, ...data.past, ...data.old_cancelled].find((x) => x.id === id);
const findPart = (partId) => {
  for (const a of [...data.upcoming, ...data.past]) {
    const p = (a.parts || []).find((x) => x.id === partId);
    if (p) return [p, a];
  }
  return [null, null];
};

(async () => {
  await Shell.init({ active: 'activities', eyebrow: sectionEyebrow, title: 'Activities' });
  await load();
  goToHash();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function load() {
  data = await API.get('activities.php');
  render();
}

/** activities.html#a12 (from a notification, Home or the month strip): open the right tab and scroll to the card. */
function goToHash() {
  const m = /^#a(\d+)$/.exec(location.hash);
  if (m) scrollToActivity(Number(m[1]));
}

function scrollToActivity(id) {
  const a = findActivity(id);
  if (!a) return;
  const old = data.old_cancelled.some((x) => x.id === id);
  const want = old || data.past.some((x) => x.id === id) ? 'past' : 'upcoming';
  // Set everything first, then draw once. A link to a cancelled one turns Show cancelled on (and keeps it on).
  let changed = false;
  if (a.cancelled && !showCancelled) { setShowCancelled(true); changed = true; }
  if (old && !oldOpen) { oldOpen = true; changed = true; }
  if (tab !== want) { tab = want; changed = true; }
  if (changed) render();
  const el = document.getElementById(`a${id}`);
  if (!el) return;
  el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  el.classList.add('act-flash');
  setTimeout(() => el.classList.remove('act-flash'), 1600);
}

/* ------------------------------------------------------------------ page */
function render() {
  const list = visible(tab);
  const hidden = cancelledIn(tab);
  $('#main').innerHTML = `
    <div class="mb-5 flex flex-wrap items-center gap-2">
      <div class="flex gap-2" role="tablist" aria-label="Which activities">
        ${[['upcoming', 'Upcoming'], ['past', 'Past']].map(([k, l]) => `
          <button type="button" role="tab" data-tab="${k}" aria-selected="${tab === k}" aria-pressed="${tab === k}" class="chip chip-filter">${l} <span class="opacity-70">${shownCount(k)}</span></button>`).join('')}
      </div>
      ${hidden ? `<button type="button" id="toggle-cancelled" aria-pressed="${showCancelled}" class="chip chip-filter">${icon(showCancelled ? 'eyeOff' : 'eye', 14)}${showCancelled ? 'Hide cancelled' : `Show cancelled (${hidden})`}</button>` : ''}
      ${data.can_manage ? `<button type="button" id="new-activity" class="btn btn-dark ml-auto h-11 px-5 text-sm">${icon('plus', 18)}New activity</button>` : ''}
    </div>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
      <section class="min-w-0 space-y-3" aria-label="${tab === 'upcoming' ? 'Upcoming activities' : 'Past activities'}">
        ${list.length ? list.map(card).join('') : emptyState()}
        ${oldCancelledBox()}
      </section>
      <aside class="min-w-0 space-y-4">${mySignupsCard()}${needsPeopleCard()}${monthCard()}${myAttendanceCard()}${rulesCard()}</aside>
    </div>`;
  bind();
}

/** Past tab, Show cancelled on: the activities cancelled over 3 days ago, in a box that opens (officers can Remove them). */
function oldCancelledBox() {
  const old = data.old_cancelled;
  if (tab !== 'past' || !showCancelled || !old.length) return '';
  return `
    <details data-old-cancelled class="rounded-[1.75rem] bg-white p-4 shadow-card" ${oldOpen ? 'open' : ''}>
      <summary class="cursor-pointer text-sm font-semibold">${old.length === 1 ? '1 older cancelled activity' : `${old.length} older cancelled activities`}</summary>
      <div class="mt-3 space-y-3">${old.map(card).join('')}</div>
    </details>`;
}

function emptyState() {
  if (tab === 'past') return UI.empty('No past activities yet', 'Finished activities show here with who attended and their results.', 'flag');
  const results = data.latest_results.length ? `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="latest-h">
      <h2 id="latest-h" class="text-lg font-semibold">Latest results</h2>
      <ul class="mt-3 space-y-3">${data.latest_results.map((r) => `
        <li><a href="#a${r.activity_id}" data-goto="${r.activity_id}" class="font-semibold hover:underline">${esc(r.title)}</a>
          ${r.result ? `<p class="text-sm">${esc(r.result)}</p>` : ''}
          ${r.parts.map((p) => `<p class="text-sm text-muted">${esc(p.name)}: ${esc(p.result)}</p>`).join('')}</li>`).join('')}
      </ul>
    </section>` : '';
  const starters = data.can_manage ? `
    <div class="rounded-[1.75rem] bg-white p-6 text-center shadow-card">
      <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-mint-tint text-success">${icon('flag', 22)}</div>
      <div class="mt-3 font-semibold">Nothing planned yet</div>
      <p class="mt-1 text-sm text-muted">Start something for the section.</p>
      <div class="mt-4 grid gap-2 sm:grid-cols-3">
        <button type="button" data-start="meeting" class="btn btn-canvas h-11 text-sm">Plan a meeting</button>
        <button type="button" data-start="competition" class="btn btn-canvas h-11 text-sm">Post a competition</button>
        <button type="button" data-start="project" class="btn btn-canvas h-11 text-sm">Start a project</button>
      </div>
    </div>` : UI.empty('Nothing planned yet', 'When officers post an activity, it shows here.', 'flag');
  return starters + results;
}

function card(a) {
  if (isParts(a)) return partsCard(a);
  const d = new Date(a.start_ts * 1000);
  const pct = a.capacity ? Math.min(100, Math.round((a.going / a.capacity) * 100)) : 0;
  const seats = a.capacity
    ? `${a.going} of ${plural(a.capacity, 'seat')} taken${a.waitlist ? ` · ${a.waitlist} waiting` : ''}`
    : `${plural(a.going, 'person', 'people')} going · no seat limit`;
  return `
    <article class="act-card ${a.cancelled ? 'is-cancelled' : ''}" id="a${a.id}" aria-labelledby="act-${a.id}-h">
      <div class="flex gap-4">
        ${dateBlock(a, d)}
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-1.5">
            ${UI.chip(a.kind_label, KIND_TINT[a.kind])}
            ${a.cancelled ? UI.chip('Cancelled', 'bg-danger-tint') : ''}
            ${audienceChip(a)}
            ${a.venue ? `<span class="inline-flex items-center gap-1 text-xs text-muted">${icon('mapPin', 13)}${esc(a.venue)}</span>` : ''}
          </div>
          <h3 id="act-${a.id}-h" class="mt-1.5 text-lg font-semibold leading-snug">${esc(a.title)}</h3>
          <p class="text-sm text-muted">${esc(whenText(a))}</p>
          ${a.link ? `<div class="mt-2">${UI.linkChip(a.link, a.link_host)}</div>` : ''}
          ${a.description ? `<p class="mt-2 text-sm leading-relaxed">${esc(a.description)}</p>` : ''}
          ${invitedLine(a)}
          ${resultBlock(a)}
          ${!a.cancelled ? `
            <div class="mt-3">
              ${a.capacity ? `<div class="act-bar" role="img" aria-label="${esc(seats)}"><span style="width:${pct}%"></span></div>` : ''}
              <p class="mt-1.5 text-xs text-muted">${a.is_over ? `${plural(a.checked_in, 'person', 'people')} checked in` : esc(seats)}</p>
            </div>` : ''}
          <div class="mt-3 flex flex-wrap items-center gap-2">${myAction(a)}${officerActions(a)}</div>
        </div>
      </div>
    </article>`;
}

function dateBlock(a, d) {
  return `<div class="act-date ${KIND_TINT[a.kind]}" aria-hidden="true">
    <span class="text-xl font-bold leading-none">${String(d.getDate()).padStart(2, '0')}</span>
    <span class="mt-1 text-[11px] font-semibold text-muted">${MONTHS[d.getMonth()]}</span>
  </div>`;
}

function resultBlock(a) {
  const partResults = (a.parts || []).filter((p) => p.result);
  if (!a.result && !partResults.length) return '';
  return `<div class="mt-3 rounded-2xl bg-canvas px-4 py-3">
    <p class="text-xs font-semibold uppercase tracking-wide text-muted">Result</p>
    ${a.result ? `<p class="mt-0.5 text-sm font-semibold">${esc(a.result)}</p>` : ''}
    ${partResults.map((p) => `<p class="mt-0.5 text-sm"><span class="text-muted">${esc(p.name)}:</span> ${esc(p.result)}</p>`).join('')}
  </div>`;
}

/** "Sign up by Fri, Nov 20 · Today", or "Sign-ups closed". */
function signupLine(a) {
  if (a.cancelled || a.is_over) return '';
  if (!a.signup_open) return '<span class="text-xs font-semibold text-muted">Sign-ups closed</span>';
  if (!a.signup_deadline) return `<span class="text-xs text-muted">Sign up by ${esc(Fmt.short(a.signup_closes_at))}</span>`;
  return `<span class="text-xs font-semibold">Sign up by ${esc(Fmt.short(a.signup_deadline))}</span> ${whenChip(a.signup_deadline)}`;
}

/** A competition or section project: its parts, who is on them, and what I can do. */
function partsCard(a) {
  const d = new Date(a.start_ts * 1000);
  const prog = a.progress;
  const pct = prog && prog.total ? Math.round((prog.done / prog.total) * 100) : 0;
  const cantVolunteer = !a.cancelled && !a.is_over && a.signup_open && !a.me.can_volunteer
    ? `<p class="mt-2 text-xs text-muted">${a.invite_only && !a.me.invited ? 'Only the people the poster invited can volunteer.' : `${esc(data.my_status.label)} members can’t volunteer.`}</p>` : '';
  return `
    <article class="act-card ${a.cancelled ? 'is-cancelled' : ''}" id="a${a.id}" aria-labelledby="act-${a.id}-h">
      <div class="flex gap-4">
        ${dateBlock(a, d)}
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-1.5">
            ${UI.chip(a.kind_label, KIND_TINT[a.kind])}
            ${a.cancelled ? UI.chip('Cancelled', 'bg-danger-tint') : ''}
            ${audienceChip(a)}
            ${signupLine(a)}
          </div>
          <h3 id="act-${a.id}-h" class="mt-1.5 text-lg font-semibold leading-snug">${esc(a.title)}</h3>
          <p class="text-sm text-muted">${esc(whenText(a))}${a.venue ? ` · ${esc(a.venue)}` : ''}</p>
          ${a.link ? `<div class="mt-2">${UI.linkChip(a.link, a.link_host)}</div>` : ''}
          ${a.description ? `<p class="mt-2 text-sm leading-relaxed">${esc(a.description)}</p>` : ''}
          ${invitedLine(a)}
          ${resultBlock(a)}
          ${prog && !a.cancelled ? `
            <div class="mt-3">
              <div class="act-bar" role="img" aria-label="${prog.done} of ${prog.total} parts done"><span style="width:${pct}%"></span></div>
              <p class="mt-1.5 text-xs text-muted">${prog.done} of ${plural(prog.total, 'part')} done</p>
            </div>` : ''}
        </div>
      </div>
      <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-muted">Parts</h4>
      <ul class="mt-2 space-y-2">${a.parts.map((p) => partRow(a, p)).join('')}</ul>
      ${cantVolunteer}
      ${!a.cancelled && data.can_manage ? `
        <div class="mt-3 flex flex-wrap items-center gap-2">
          ${!a.is_over ? `<button type="button" data-add-part="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">${icon('plus', 16)}Add part</button>
            <button type="button" data-edit-activity="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">${icon('edit', 16)}Edit</button>` : ''}
          ${a.is_over ? `<button type="button" data-result="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">${icon('flag', 16)}${a.result ? 'Edit result' : 'Add result'}</button>` : ''}
          ${!a.is_over ? `<button type="button" data-cancel-activity="${a.id}" class="ml-auto h-10 px-2 text-sm font-semibold text-muted hover:text-charcoal">Cancel activity</button>` : ''}
        </div>` : ''}
      ${a.cancelled ? `<div class="mt-3 flex">${removeButton(a)}</div>` : ''}
    </article>`;
}

function partRow(a, p) {
  const counts = [p.spots_label];
  if (p.spots !== null) counts.push(`${p.picked_count} picked`);
  else if (p.picked_count) counts.push(`${p.picked_count} picked`);
  if (p.volunteer_count) counts.push(plural(p.volunteer_count, 'volunteer'));
  const due = p.due_date ? `<span class="${p.overdue ? 'font-semibold text-danger' : ''}">Due ${esc(Fmt.short(p.due_date))}${p.overdue ? ' · overdue' : ''}</span>` : '';
  const people = p.picked.length
    ? `<p class="mt-1 text-xs"><span class="text-muted">${a.kind === 'project' ? 'On it:' : 'Picked:'}</span> ${p.picked.map((x) => `${esc(x.name)}${x.role ? ` · ${esc(x.role)}` : ''}`).join(', ')}</p>` : '';
  return `
    <li class="part-row ${p.done ? 'is-done' : ''}">
      <div class="min-w-[12rem] flex-1"><!-- at least 12rem: on a phone the buttons wrap below instead of squeezing the text -->
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span class="font-semibold">${esc(p.name)}</span>
          ${p.done ? `<span class="chip bg-mint-tint text-success">${icon('check', 13)}Done</span>` : ''}
        </div>
        <p class="text-xs text-muted">${esc(counts.join(' · '))}${p.roles.length ? ` · roles: ${esc(p.roles.join(', '))}` : ''}${due ? ' · ' : ''}${due}</p>
        ${p.details ? `<p class="mt-1 text-sm">${esc(p.details)}</p>` : ''}
        ${people}
      </div>
      <div class="flex flex-wrap items-center justify-end gap-2">${myPartAction(a, p)}${data.can_manage && !a.cancelled ? `<button type="button" data-manage="${p.id}" class="btn btn-light h-10 border border-line px-4 text-sm">Manage${p.volunteers?.length ? ` · ${p.volunteers.length}` : ''}</button>` : ''}</div>
    </li>`;
}

/** Where I stand on one part: Volunteer / Volunteered — the officers will pick / Picked · role. */
function myPartAction(a, p) {
  if (a.cancelled) return '';
  const st = p.me.status;
  // Only the people on the part see Mark done on the card (officers have it in Manage), to keep the card quiet
  const markDone = a.kind === 'project' && !a.is_over && st === 'picked'
    ? `<button type="button" data-done="${p.id}" data-value="${p.done ? '0' : '1'}" class="btn ${p.done ? 'btn-canvas' : 'btn-dark'} h-10 px-4 text-sm">${p.done ? 'Not done yet' : `${icon('check', 16)}Mark done`}</button>` : '';
  if (st === 'picked') {
    return `<span class="chip bg-mint-tint text-success">${icon('check', 13)}${esc(`Picked${p.me.role ? ` · ${p.me.role}` : ''}`)}</span>
      ${markDone}${!a.is_over ? `<button type="button" data-step-down="${p.id}" class="h-10 px-2 text-sm font-semibold text-muted hover:text-charcoal">Step down</button>` : ''}`;
  }
  if (st === 'volunteer') {
    return `<span class="chip bg-sun-tint">${icon('clock', 13)}${esc(`Volunteered — the officers will pick${p.me.role ? ` · ${p.me.role}` : ''}`)}</span>
      ${markDone}${!a.is_over ? `<button type="button" data-withdraw="${p.id}" class="h-10 px-2 text-sm font-semibold text-muted hover:text-charcoal">Withdraw</button>` : ''}`;
  }
  if (a.is_over || p.done) return markDone;
  if (!a.me.can_volunteer) return markDone;
  if (p.full) return `<span class="text-sm text-muted">Full</span>${markDone}`;
  return `<button type="button" data-volunteer="${p.id}" class="btn btn-dark h-10 px-4 text-sm">Volunteer</button>${markDone}`;
}

/** What I can do with an RSVP activity, or where I stand. */
function myAction(a) {
  const me = a.me;
  if (a.cancelled) return '';
  if (a.is_over) {
    return me.checked_in
      ? `<span class="chip bg-mint-tint text-success">${icon('check', 13)}You attended</span>`
      : '<span class="text-sm text-muted">You weren’t checked in</span>';
  }
  if (me.going) {
    return `<span class="chip bg-mint-tint text-success">${icon('check', 13)}You’re going</span>
      <button type="button" data-cancel-rsvp="${a.id}" class="btn btn-canvas h-10 px-4 text-sm">Cancel RSVP</button>`;
  }
  if (me.waitlist_position) {
    return `<span class="chip bg-sun-tint">${icon('clock', 13)}Waitlist · #${me.waitlist_position}</span>
      <button type="button" data-cancel-rsvp="${a.id}" class="btn btn-canvas h-10 px-4 text-sm">Leave waitlist</button>`;
  }
  if (!me.can_rsvp) {
    return a.invite_only && !me.invited
      ? '<span class="text-sm text-muted">Only for the people the poster invited</span>'
      : `<span class="text-sm text-muted">${esc(data.my_status.label)} members can’t RSVP</span>`;
  }
  return a.full
    ? `<button type="button" data-rsvp="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">Join the waitlist</button>`
    : `<button type="button" data-rsvp="${a.id}" class="btn btn-dark h-10 px-4 text-sm">I’m going</button>`;
}

function officerActions(a) {
  if (a.cancelled) return removeButton(a);
  const parts = [];
  if (data.can_record || data.can_manage) {
    parts.push(`<button type="button" data-attendance="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">${icon('clipboard', 16)}Attendance${a.checked_in ? ` · ${a.checked_in}` : ''}</button>`);
  }
  if (data.can_manage && !a.is_over) {
    parts.push(`<button type="button" data-edit-activity="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">${icon('edit', 16)}Edit</button>`);
    parts.push(`<button type="button" data-cancel-activity="${a.id}" class="h-10 px-2 text-sm font-semibold text-muted hover:text-charcoal">Cancel activity</button>`);
  }
  if (data.can_manage && a.is_over) {
    parts.push(`<button type="button" data-result="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">${icon('flag', 16)}${a.result ? 'Edit result' : 'Add result'}</button>`);
  }
  return parts.length ? `<span class="act-officer">${parts.join('')}</span>` : '';
}

/* ------------------------------------------------------------------ right column */
function mySignupsCard() {
  const list = data.my_signups;
  if (!list.length) return '';
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="signups-h">
      <h2 id="signups-h" class="text-lg font-semibold">Your sign-ups</h2>
      <ul class="mt-3 space-y-2">${list.map((s) => `
        <li><a href="#a${s.activity_id}" data-goto="${s.activity_id}" class="block rounded-2xl px-3 py-2 hover:bg-canvas">
          <span class="block truncate text-sm font-semibold">${esc(s.part_name)}</span>
          <span class="block truncate text-xs text-muted">${esc(s.activity_title)}</span>
          <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
            ${s.status === 'picked'
              ? `<span class="chip bg-mint-tint text-success">${esc(`Picked${s.role ? ` · ${s.role}` : ''}`)}</span>`
              : '<span class="chip bg-sun-tint">Volunteered — the officers will pick</span>'}
            ${s.done ? '<span class="text-success">Done</span>' : s.due_date ? `<span class="${s.overdue ? 'font-semibold text-danger' : 'text-muted'}">Due ${esc(Fmt.short(s.due_date))}</span>` : ''}
          </span>
        </a></li>`).join('')}
      </ul>
    </section>`;
}

function needsPeopleCard() {
  const list = data.needs_people;
  if (!list.length || tab !== 'upcoming') return '';
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="needs-h">
      <h2 id="needs-h" class="text-lg font-semibold">Needs people</h2>
      <ul class="mt-3 space-y-1">${list.slice(0, 6).map((n) => `
        <li><a href="#a${n.activity_id}" data-goto="${n.activity_id}" class="flex items-center gap-3 rounded-2xl px-3 py-2 hover:bg-canvas">
          <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">${esc(n.part_name)}</span>
            <span class="block truncate text-xs text-muted">${esc(n.activity_title)} · sign up by ${esc(Fmt.short(n.closes_at))}</span></span>
          <span class="chip bg-sun-tint shrink-0">${plural(n.open_spots, 'spot')} open</span>
        </a></li>`).join('')}
      </ul>
    </section>`;
}

/** This month: a 7-column calendar with a dot on each day that has an activity. */
function monthCard() {
  const m = data.month;
  const first = Fmt.date(m.first);
  const daysIn = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
  const has = new Set(m.days);
  const cells = [];
  for (let i = 0; i < first.getDay(); i++) cells.push('<span></span>');
  for (let day = 1; day <= daysIn; day++) {
    const iso = `${m.first.slice(0, 8)}${String(day).padStart(2, '0')}`;
    const isToday = iso === m.today;
    cells.push(has.has(iso)
      ? `<button type="button" data-day-goto="${m.first_activity[iso]}" class="mini-day has ${isToday ? 'is-today' : ''}" aria-label="${esc(Fmt.short(iso))}: open the activity">${day}<span class="mini-dot" aria-hidden="true"></span></button>`
      : `<span class="mini-day ${isToday ? 'is-today' : ''}">${day}</span>`);
  }
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="month-h">
      <h2 id="month-h" class="text-lg font-semibold">This month <span class="text-sm font-medium text-muted">${MONTHS[first.getMonth()]} ${first.getFullYear()}</span></h2>
      <div class="mini-cal mt-3" aria-hidden="false">
        ${DAYS.map((d) => `<span class="mini-head">${d[0]}</span>`).join('')}
        ${cells.join('')}
      </div>
      ${m.days.length ? '' : '<p class="mt-2 text-xs text-muted">No activities this month yet.</p>'}
    </section>`;
}

function myAttendanceCard() {
  const m = data.my_attendance;
  const status = data.my_status;
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="my-att-h">
      <h2 id="my-att-h" class="text-lg font-semibold">Your attendance</h2>
      <div class="mt-3 flex items-end gap-3">
        <span class="text-4xl font-extrabold tracking-tight">${m && m.rate !== null ? `${m.rate}%` : '—'}</span>
        <span class="pb-1 text-sm text-muted">${m && m.expected ? `${m.attended} of ${plural(m.expected, 'activity', 'activities')}` : 'No finished activities yet'}</span>
      </div>
      ${m && m.expected ? `<div class="act-bar mt-3"><span style="width:${m.rate}%"></span></div>` : ''}
      <p class="mt-3 text-xs text-muted"><b class="text-charcoal">${esc(status.label)}.</b> ${esc(status.meaning)}</p>
      <a href="records.html?id=${Shell.me.user.id}" class="btn btn-canvas mt-4 h-10 w-full text-sm">Open my record</a>
    </section>`;
}

function rulesCard() {
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="rules-h">
      <h2 id="rules-h" class="text-lg font-semibold">How it counts</h2>
      <ul class="mt-3 space-y-3">
        ${data.kinds.map((k) => `<li>${UI.chip(k.label, KIND_TINT[k.kind])}<p class="mt-1 text-sm">${esc(k.rule)}</p></li>`).join('')}
      </ul>
      <p class="mt-3 text-xs text-muted">When an activity is full, RSVPs join a waitlist. The first person waiting gets the next seat that opens. Competitions and projects never change your attendance rate.</p>
    </section>`;
}

/* ------------------------------------------------------------------ actions */
/** POSTs one action, shows a toast, reloads. Returns true when it worked. */
async function post(body, msg, btn = null) {
  if (btn) btn.disabled = true;
  try {
    const r = await API.post('activities.php', body);
    if (msg) UI.toast(typeof msg === 'function' ? msg(r) : msg);
    await load();
    return true;
  } catch (e) {
    if (btn) btn.disabled = false;
    UI.toast(e.message, { iconName: 'alert' });
    return false;
  }
}

function bind() {
  $$('[data-tab]').forEach((b) => b.onclick = () => { tab = b.dataset.tab; history.replaceState(null, '', tab === 'past' ? '#past' : location.pathname); render(); });
  $('#new-activity')?.addEventListener('click', () => openCreate());
  $$('[data-start]').forEach((b) => b.onclick = () => openCreate(b.dataset.start));
  $$('[data-goto]').forEach((b) => b.onclick = (e) => { e.preventDefault(); scrollToActivity(Number(b.dataset.goto)); });
  $$('[data-day-goto]').forEach((b) => b.onclick = () => scrollToActivity(Number(b.dataset.dayGoto)));
  $$('[data-rsvp]').forEach((b) => b.onclick = () => post({ action: 'rsvp', id: Number(b.dataset.rsvp) },
    (r) => (r.status === 'going' ? 'You’re going' : `You’re on the waitlist, #${r.position}`), b));
  $$('[data-cancel-rsvp]').forEach((b) => b.onclick = () => post({ action: 'cancel_rsvp', id: Number(b.dataset.cancelRsvp) },
    (r) => (r.promoted ? 'RSVP cancelled. The next person waiting got your seat.' : 'RSVP cancelled'), b));
  $$('[data-attendance]').forEach((b) => b.onclick = () => openAttendance(Number(b.dataset.attendance)));
  $$('[data-cancel-activity]').forEach((b) => b.onclick = () => {
    const a = findActivity(Number(b.dataset.cancelActivity));
    const who = isParts(a) ? 'Everyone who volunteered or was picked' : 'Everyone going or waiting';
    UI.sheet(`<h2 class="text-2xl font-bold">Cancel “${esc(a.title)}”?</h2>
      <p class="mt-2 text-sm text-muted">${who} gets a notification. It stays in the list, marked cancelled.</p>
      <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Keep it</button><button data-ok class="btn btn-dark">Cancel activity</button></div>`, {
      label: 'Cancel activity',
      onMount: (el, close) => { $('[data-ok]', el).onclick = () => { close(); post({ action: 'cancel', id: a.id }, 'Activity cancelled', b); }; },
    });
  });
  $$('[data-edit-activity]').forEach((b) => b.onclick = () => openEdit(findActivity(Number(b.dataset.editActivity))));
  $('#toggle-cancelled')?.addEventListener('click', () => { setShowCancelled(!showCancelled); render(); });
  $('[data-old-cancelled]')?.addEventListener('toggle', (e) => { oldOpen = e.target.open; });
  $$('[data-remove-activity]').forEach((b) => b.onclick = () => {
    const a = findActivity(Number(b.dataset.removeActivity));
    UI.sheet(`<h2 class="text-2xl font-bold">Remove “${esc(a.title)}” for good?</h2>
      <p class="mt-2 text-sm text-muted">It was cancelled. Removing it also deletes its RSVPs${isParts(a) ? ', parts and who volunteered' : ''} and invites. This can’t be undone.</p>
      <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Keep it</button><button data-ok class="btn btn-dark">Remove</button></div>`, {
      label: 'Remove activity',
      onMount: (el, close) => { $('[data-ok]', el).onclick = () => { close(); post({ action: 'delete', id: a.id }, 'Activity removed', b); }; },
    });
  });
  $$('[data-result]').forEach((b) => b.onclick = () => openResult(findActivity(Number(b.dataset.result))));
  $$('[data-add-part]').forEach((b) => b.onclick = () => openPartForm(findActivity(Number(b.dataset.addPart)), null));
  $$('[data-volunteer]').forEach((b) => b.onclick = () => openVolunteer(Number(b.dataset.volunteer)));
  $$('[data-withdraw]').forEach((b) => b.onclick = () => post({ action: 'withdraw', part_id: Number(b.dataset.withdraw) }, 'You withdrew', b));
  $$('[data-step-down]').forEach((b) => b.onclick = () => {
    const [p, a] = findPart(Number(b.dataset.stepDown));
    UI.sheet(`<h2 class="text-2xl font-bold">Step down from “${esc(p.name)}”?</h2>
      <p class="mt-2 text-sm text-muted">The officers are told, and your spot opens for someone else in “${esc(a.title)}”.</p>
      <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Stay on it</button><button data-ok class="btn btn-dark">Step down</button></div>`, {
      label: 'Step down',
      onMount: (el, close) => { $('[data-ok]', el).onclick = () => { close(); post({ action: 'step_down', part_id: p.id }, 'You stepped down', b); }; },
    });
  });
  $$('[data-done]').forEach((b) => b.onclick = () => post({ action: 'mark_done', part_id: Number(b.dataset.done), done: b.dataset.value === '1' },
    b.dataset.value === '1' ? 'Marked done' : 'Marked not done', b));
  $$('[data-manage]').forEach((b) => b.onclick = () => openManage(Number(b.dataset.manage)));
}

/* ------------------------------------------------------------------ sheets: parts */
const roleSelect = (name, roles, selected = null, placeholder = 'No preference') => (roles.length ? `
  <label class="block"><span class="mb-1.5 block text-xs text-muted">Role</span>
    <span class="field"><select name="${name}" class="w-full bg-transparent outline-none">
      <option value="">${esc(placeholder)}</option>
      ${roles.map((r) => `<option value="${esc(r)}" ${r === selected ? 'selected' : ''}>${esc(r)}</option>`).join('')}
    </select></span></label>` : '');

function openVolunteer(partId) {
  const [p, a] = findPart(partId);
  UI.sheet(`
    <form novalidate>
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">${esc(a.title)}</p>
      <h2 class="mt-1 text-2xl font-bold">Volunteer for ${esc(p.name)}</h2>
      <p class="mt-1 text-sm text-muted">${esc(p.spots_label)}. Officers pick who represents the section; you’ll be told if you’re picked.</p>
      <div class="mt-4 space-y-4">
        <label class="block"><span class="mb-1.5 block text-xs text-muted">What you can do (optional)</span>
          <span class="field area"><textarea name="note" rows="3" maxlength="300" placeholder="e.g. I sew my own costumes"></textarea></span></label>
        ${roleSelect('role', p.roles)}
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Send</button>
    </form>`, {
    label: `Volunteer for ${p.name}`,
    onMount: (el, close) => UI.bindForm($('form', el), async (f) => {
      await API.post('activities.php', { action: 'volunteer', part_id: p.id, note: f.note, role: f.role || '' });
      close();
      UI.toast('Volunteered — the officers will pick', { sub: p.name });
      await load();
    }),
  });
}

/** Officers: the part's volunteers (earliest first), who is picked, assign (projects), edit / remove. */
function openManage(partId) {
  const sheet = UI.sheet('<div data-body></div>', { label: 'Manage part' });
  const body = $('[data-body]', sheet.el);
  const run = async (btn, payload, msg) => {
    btn.disabled = true;
    try {
      await API.post('activities.php', payload);
      UI.toast(msg);
      await load();
      draw();
    } catch (e) {
      btn.disabled = false;
      UI.toast(e.message, { iconName: 'alert' });
    }
  };
  const draw = () => {
    const [p, a] = findPart(partId);
    if (!p) { sheet.close(); return; }
    const onIt = new Set([...p.picked.map((x) => x.id), ...(p.volunteers || []).map((x) => x.id)]);
    const canAssign = a.kind === 'project';
    body.innerHTML = `
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">${esc(a.title)}</p>
      <h2 class="mt-1 text-2xl font-bold leading-tight">${esc(p.name)}</h2>
      <p class="mt-1 text-sm text-muted">${esc(p.spots_label)} · ${p.picked_count} picked${p.open_spots !== null ? ` · ${plural(p.open_spots, 'spot')} open` : ''}</p>

      <h3 class="mt-5 text-sm font-semibold">Picked</h3>
      ${p.picked.length ? `<ul class="mt-2 space-y-2">${p.picked.map((x) => `
        <li class="rounded-2xl bg-canvas p-3">
          <div class="flex items-center gap-3">
            ${UI.avatar(x.name, 'bg-lavender-tint', 'h-9 w-9 text-xs')}
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">${esc(x.name)}</span>
              <span class="block text-xs text-muted">${x.assigned ? 'Assigned' : 'Volunteered'}${x.role ? ` · ${esc(x.role)}` : ''}</span></span>
            <button type="button" data-unpick="${x.id}" class="btn btn-light h-10 border border-line px-3 text-sm">Un-pick</button>
          </div>
          ${p.roles.length ? `<div class="mt-2 flex gap-2"><span class="field h-10 flex-1"><select data-role-for="${x.id}" class="w-full bg-transparent text-sm outline-none" aria-label="Role for ${esc(x.name)}">
            <option value="">No role</option>${p.roles.map((r) => `<option value="${esc(r)}" ${r === x.role ? 'selected' : ''}>${esc(r)}</option>`).join('')}</select></span></div>` : ''}
        </li>`).join('')}</ul>` : '<p class="mt-1 text-sm text-muted">Nobody yet.</p>'}

      <h3 class="mt-5 text-sm font-semibold">Volunteers <span class="font-normal text-muted">· earliest first</span></h3>
      ${(p.volunteers || []).length ? `<ol class="mt-2 space-y-2">${p.volunteers.map((v, i) => `
        <li class="rounded-2xl border border-line p-3">
          <div class="flex items-center gap-3">
            <span class="w-5 text-center text-sm font-bold text-muted">${i + 1}</span>
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">${esc(v.name)}</span>
              ${v.role ? `<span class="block text-xs text-muted">Prefers ${esc(v.role)}</span>` : ''}</span>
            <button type="button" data-pick="${v.id}" ${p.full ? 'disabled' : ''} class="btn btn-dark h-10 px-4 text-sm ${p.full ? 'opacity-40' : ''}">Pick</button>
          </div>
          ${v.note ? `<p class="mt-2 text-sm">“${esc(v.note)}”</p>` : ''}
        </li>`).join('')}</ol>${p.full ? '<p class="mt-2 text-xs text-muted">This part is full. Un-pick someone first.</p>' : ''}` : '<p class="mt-1 text-sm text-muted">No volunteers yet.</p>'}

      ${canAssign ? `
        <h3 class="mt-5 text-sm font-semibold">Assign someone</h3>
        <div class="mt-2 flex flex-wrap gap-2">
          <span class="field h-11 min-w-0 flex-1"><select data-assign-who class="w-full bg-transparent text-sm outline-none" aria-label="Who to assign">
            <option value="">Pick a member</option>
            ${data.people.filter((m) => !onIt.has(m.id) && (!a.invite_only || (a.invited || []).some((x) => x.id === m.id))).map((m) => `<option value="${m.id}">${esc(m.name)}</option>`).join('')}
          </select></span>
          <button type="button" data-assign ${p.full ? 'disabled' : ''} class="btn btn-dark h-11 px-4 text-sm ${p.full ? 'opacity-40' : ''}">Assign</button>
        </div>` : ''}

      ${canAssign && !a.is_over ? `<button type="button" data-sheet-done class="btn ${p.done ? 'btn-canvas' : 'btn-dark'} mt-6 w-full">${p.done ? 'Not done yet' : `${icon('check', 16)}Mark done`}</button>` : ''}
      <div class="mt-3 grid grid-cols-2 gap-3">
        <button type="button" data-edit-part class="btn btn-canvas">${icon('edit', 16)}Edit part</button>
        <button type="button" data-remove-part class="btn btn-canvas text-danger">Remove part</button>
      </div>
      <button data-close class="btn btn-dark mt-3 w-full">Done</button>`;
    $$('[data-pick]', body).forEach((b) => b.onclick = () => {
      const v = p.volunteers.find((x) => x.id === Number(b.dataset.pick));
      run(b, { action: 'pick', part_id: p.id, user_id: v.id, role: v.role || '' }, `${v.name} picked`);
    });
    $$('[data-unpick]', body).forEach((b) => b.onclick = () => run(b, { action: 'unpick', part_id: p.id, user_id: Number(b.dataset.unpick) }, 'Un-picked'));
    $$('[data-role-for]', body).forEach((s) => s.onchange = () => run(s, { action: 'set_role', part_id: p.id, user_id: Number(s.dataset.roleFor), role: s.value }, 'Role saved'));
    $('[data-assign]', body)?.addEventListener('click', (e) => {
      const who = Number($('[data-assign-who]', body).value);
      if (!who) { UI.toast('Pick a member first', { iconName: 'alert' }); return; }
      run(e.currentTarget, { action: 'assign', part_id: p.id, user_id: who }, 'Assigned');
    });
    $('[data-sheet-done]', body)?.addEventListener('click', (e) => run(e.currentTarget, { action: 'mark_done', part_id: p.id, done: !p.done }, p.done ? 'Marked not done' : 'Marked done'));
    $('[data-edit-part]', body).onclick = () => { sheet.close(); openPartForm(a, p); };
    $('[data-remove-part]', body).onclick = (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      API.post('activities.php', { action: 'remove_part', part_id: p.id })
        .then(async () => { sheet.close(); UI.toast('Part removed'); await load(); })
        .catch((ex) => { btn.disabled = false; UI.toast(ex.message, { iconName: 'alert' }); });
    };
  };
  draw();
}

/** One part's fields, for the "New activity" builder and the Add / Edit part form. */
function partFields(kind, p = {}, lastDay = '') {
  return `
    <div class="space-y-3">
      <label class="block"><span class="mb-1.5 block text-xs text-muted">Part</span>
        <span class="field"><input name="p_name" maxlength="80" placeholder="e.g. Cosplay Competition" value="${esc(p.name || '')}" required></span></label>
      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="mb-1.5 block text-xs text-muted">How many people</span>
          <span class="field"><input name="p_spots" type="number" min="1" max="100" inputmode="numeric" placeholder="Any number" value="${p.spots ?? ''}"></span></label>
        ${kind === 'project' ? `<label class="block"><span class="mb-1.5 block text-xs text-muted">Due</span>
          <span class="field"><input name="p_due" type="date" ${lastDay ? `max="${lastDay}"` : ''} value="${esc(p.due_date || '')}" required></span></label>` : '<span></span>'}
      </div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="p_together" class="h-5 w-5 accent-charcoal" ${p.together ? 'checked' : ''}> Works as one team</label>
      <label class="block"><span class="mb-1.5 block text-xs text-muted">Roles (optional, comma-separated)</span>
        <span class="field"><input name="p_roles" placeholder="e.g. Director, Editor, Camera" value="${esc((p.roles || []).join(', '))}"></span></label>
      <label class="block"><span class="mb-1.5 block text-xs text-muted">What to do (optional)</span>
        <span class="field area"><textarea name="p_details" rows="2" maxlength="1000">${esc(p.details || '')}</textarea></span></label>
    </div>`;
}

/** Reads one part's fields from a container. */
function readPart(box) {
  const v = (n) => $(`[name="${n}"]`, box)?.value ?? '';
  return {
    name: v('p_name').trim(), details: v('p_details').trim(), spots: v('p_spots') === '' ? '' : Number(v('p_spots')),
    together: !!$('[name="p_together"]', box)?.checked,
    roles: v('p_roles').split(',').map((s) => s.trim()).filter(Boolean),
    due_date: v('p_due') || null,
  };
}

function openPartForm(a, p) {
  const lastDay = Fmt.iso(new Date(a.end_ts * 1000));
  UI.sheet(`
    <form novalidate>
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">${esc(a.title)}</p>
      <h2 class="mt-1 text-2xl font-bold">${p ? 'Edit part' : 'Add a part'}</h2>
      <div class="mt-4">${partFields(a.kind, p || {}, lastDay)}</div>
      ${p && p.picked_count ? `<p class="mt-3 text-xs text-muted">${plural(p.picked_count, 'person', 'people')} picked: it can’t go below that.</p>` : ''}
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">${p ? 'Save part' : 'Add part'}</button>
    </form>`, {
    label: p ? 'Edit part' : 'Add a part',
    onMount: (el, close) => UI.bindForm($('form', el), async () => {
      const fields = readPart(el);
      if (a.kind !== 'project') delete fields.due_date;
      await API.post('activities.php', p ? { action: 'edit_part', part_id: p.id, ...fields } : { action: 'add_part', id: a.id, ...fields });
      close();
      UI.toast(p ? 'Part saved' : 'Part added');
      await load();
    }),
  });
}

/* ------------------------------------------------------------------ sheets: activities */
/** New activity: the kind decides the rule (shown as you pick it); competitions and projects get parts. */
function openCreate(startKind = 'meeting') {
  const today = Fmt.iso(new Date());
  let kind = startKind;
  UI.sheet(`
    <form novalidate>
      <h2 class="text-2xl font-bold">New activity</h2>
      <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Kind of activity">
        ${data.kinds.map((k) => `<button type="button" role="radio" data-kind="${k.kind}" aria-checked="${k.kind === kind}" class="act-kind ${KIND_TINT[k.kind]}">${esc(k.label)}</button>`).join('')}
      </div>
      <p data-rule class="mt-2 text-xs text-muted"></p>
      <div class="mt-4 space-y-4">
        ${UI.field({ name: 'title', label: 'Title', placeholder: 'e.g. General Assembly', required: true, iconName: 'flag' })}
        <div class="grid grid-cols-2 gap-2">
          ${dateRangeFields(today, '', today)}
          <label class="block"><span class="mb-1.5 block text-xs text-muted" data-start-label>Starts</span>
            <span class="field"><input type="time" name="start" value="15:00" required></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted" data-end-label>Ends</span>
            <span class="field"><input type="time" name="end" value="16:00" required></span></label>
        </div>
        ${UI.field({ name: 'venue', label: 'Venue (optional)', placeholder: 'e.g. Room 204', iconName: 'mapPin' })}
        ${whoField()}
        <label class="block" data-seats><span class="mb-1.5 block text-xs text-muted">Seats (leave empty for no limit)</span>
          <span class="field">${icon('users', 18)}<input type="number" name="capacity" min="1" max="1000" inputmode="numeric" placeholder="No limit"></span></label>
        ${UI.field({ name: 'link', label: 'Link to the post (optional)', type: 'url', placeholder: 'https://…', iconName: 'globe' })}
        <label class="block" data-signup><span class="mb-1.5 block text-xs text-muted">Sign up by (optional)</span>
          <span class="field"><input type="date" name="signup_deadline" min="${today}"></span></label>
        <label class="block"><span class="mb-1.5 block text-xs text-muted">Details (optional)</span>
          <span class="field area"><textarea name="description" rows="3" maxlength="2000" placeholder="What to bring, what it’s about"></textarea></span></label>
        <div data-parts-box hidden>
          <h3 class="text-sm font-semibold">Parts</h3>
          <ol data-parts class="mt-2 space-y-3"></ol>
          <button type="button" data-add-row class="btn btn-canvas mt-3 h-11 w-full text-sm">${icon('plus', 16)}Add a part</button>
        </div>
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Add activity</button>
    </form>`, {
    label: 'New activity',
    onMount: (el, close) => {
      const partsList = $('[data-parts]', el);
      const addRow = () => {
        const li = document.createElement('li');
        li.className = 'rounded-2xl border border-line p-3';
        li.innerHTML = `<div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold text-muted" data-n></span>
          <button type="button" data-remove-row class="text-xs font-semibold text-muted hover:text-charcoal">Remove</button></div>${partFields(kind)}`;
        $('[data-remove-row]', li).onclick = () => { if (partsList.children.length > 1) { li.remove(); number(); } };
        partsList.appendChild(li);
        number();
      };
      const number = () => [...partsList.children].forEach((li, i) => { $('[data-n]', li).textContent = `Part ${i + 1}`; });
      const showRule = () => {
        const k = data.kinds.find((x) => x.kind === kind);
        $$('[data-kind]', el).forEach((b) => b.setAttribute('aria-checked', String(b.dataset.kind === kind)));
        $('[data-rule]', el).textContent = k.rule;
        const parts = k.joins_by === 'parts';
        $('[data-seats]', el).hidden = parts;
        $('[data-signup]', el).hidden = !parts;
        $('[data-parts-box]', el).hidden = !parts;
        if (parts) {
          // Keep what was typed when switching kinds. A competition row has no due date field, so each row
          // also remembers its due date (data-due) for when the kind goes back to a project.
          const saved = [...partsList.children].map((li) => ({ ...readPart(li), due_date: readPart(li).due_date || li.dataset.due || null }));
          partsList.innerHTML = '';
          (saved.length ? saved : [{}]).forEach(() => addRow());
          [...partsList.children].forEach((li, i) => {
            const s = saved[i];
            if (!s) return;
            $('[name="p_name"]', li).value = s.name; $('[name="p_details"]', li).value = s.details;
            $('[name="p_spots"]', li).value = s.spots; $('[name="p_together"]', li).checked = s.together;
            $('[name="p_roles"]', li).value = s.roles.join(', ');
            if (s.due_date) {
              li.dataset.due = s.due_date;
              const dueInput = $('[name="p_due"]', li);
              if (dueInput) dueInput.value = s.due_date;
            }
          });
        }
        if (kind === 'project') {
          $('[name="start"]', el).value = '08:00'; $('[name="end"]', el).value = '17:00';
          // 08:00 today has usually passed: start the project tomorrow unless a later date was picked
          const dateInput = $('[name="date"]', el);
          if (dateInput.value === today && new Date().getHours() >= 8) {
            const t = new Date(); t.setDate(t.getDate() + 1);
            dateInput.value = Fmt.iso(t);
          }
        }
      };
      $$('[data-kind]', el).forEach((b) => b.onclick = () => { kind = b.dataset.kind; showRule(); });
      const who = bindWho(el);
      bindRange(el);
      $('[data-add-row]', el).onclick = addRow;
      showRule();
      UI.bindForm($('form', el), async (f) => {
        const k = data.kinds.find((x) => x.kind === kind);
        const body = { action: 'create', kind, ...f, capacity: f.capacity || null, ...who() };
        if (k.joins_by === 'parts') {
          body.capacity = null;
          body.parts = [...partsList.children].map(readPart).map((p) => (kind === 'project' ? p : { ...p, due_date: null }));
        } else {
          delete body.signup_deadline;
        }
        Object.keys(body).filter((key) => key.startsWith('p_')).forEach((key) => delete body[key]);
        const r = await API.post('activities.php', body);
        close();
        UI.toast('Activity added', { sub: 'Everyone in the section was notified' });
        tab = 'upcoming';
        await load();
        scrollToActivity(r.id);
      });
    },
  });
}

function openEdit(a) {
  const s = new Date(a.start_ts * 1000);
  const e = new Date(a.end_ts * 1000);
  const two = (n) => String(n).padStart(2, '0');
  UI.sheet(`
    <form novalidate>
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">${esc(a.kind_label)}</p>
      <h2 class="mt-1 text-2xl font-bold">Edit activity</h2>
      <div class="mt-4 space-y-4">
        ${UI.field({ name: 'title', label: 'Title', value: a.title, required: true, iconName: 'flag' })}
        <div class="grid grid-cols-2 gap-2">
          ${dateRangeFields(Fmt.iso(s), Fmt.iso(e), Fmt.iso(new Date()) < Fmt.iso(s) ? Fmt.iso(new Date()) : Fmt.iso(s))}
          <label class="block"><span class="mb-1.5 block text-xs text-muted" data-start-label>Starts</span>
            <span class="field"><input type="time" name="start" value="${two(s.getHours())}:${two(s.getMinutes())}" required></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted" data-end-label>Ends</span>
            <span class="field"><input type="time" name="end" value="${two(e.getHours())}:${two(e.getMinutes())}" required></span></label>
        </div>
        ${UI.field({ name: 'venue', label: 'Venue (optional)', value: a.venue || '', iconName: 'mapPin' })}
        ${UI.field({ name: 'link', label: 'Link to the post (optional)', type: 'url', value: a.link || '', placeholder: 'https://…', iconName: 'globe' })}
        ${isParts(a) ? `<label class="block"><span class="mb-1.5 block text-xs text-muted">Sign up by (optional)</span>
          <span class="field"><input type="date" name="signup_deadline" value="${esc(a.signup_deadline || '')}"></span></label>` : ''}
        <label class="block"><span class="mb-1.5 block text-xs text-muted">Details (optional)</span>
          <span class="field area"><textarea name="description" rows="3" maxlength="2000">${esc(a.description || '')}</textarea></span></label>
        ${whoField(a.invite_only, a.invited || [])}
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Save</button>
    </form>`, {
    label: 'Edit activity',
    onMount: (el, close) => { const who = bindWho(el, { onlyIfTouched: true }); const range = bindRange(el); UI.bindForm($('form', el), async (f) => {
      // One day: the last day is the first day. A range: To already moved with From (bindRange).
      if (!range.multi()) f.end_date = f.date;
      Object.assign(f, who());   // nothing when the picker wasn't touched: the server keeps the invites as they are
      await API.post('activities.php', { action: 'edit', id: a.id, ...f });
      close();
      UI.toast('Activity saved');
      await load();
    }); },
  });
}

/* ------------------------------------------------------------------ who can join */
/** "Invite only" on the card; "You're invited" when the poster chose me. */
function audienceChip(a) {
  if (!a.invite_only) return '';
  return a.me.invited
    ? `<span class="chip bg-mint-tint text-success">${icon('check', 13)}You’re invited</span>`
    : `<span class="chip border border-line bg-white">${icon('lock', 13)}Invite only</span>`;
}

/** Officers: who the poster chose ("Invited: Ana Bautista, Marco Cruz +3"). */
function invitedLine(a) {
  if (!a.invite_only || !a.invited) return '';
  const names = a.invited.map((x) => x.name);
  const shown = names.slice(0, 4).join(', ') + (names.length > 4 ? ` +${names.length - 4}` : '');
  return `<p class="mt-2 text-xs"><span class="text-muted">Invited (${names.length}):</span> ${esc(shown)}</p>`;
}

/**
 * "Who can join": everyone, or only the members the poster picks from a searchable list.
 * chosen: [{id, name}] already invited. Anyone among them who isn't in data.people (alumni, or who left the
 * section) is listed too, so saving the form never drops them by accident.
 */
function whoField(inviteOnly = false, chosen = []) {
  const picked = new Set(chosen.map((x) => x.id));
  const listed = new Set(data.people.map((m) => m.id));
  const rows = [...data.people, ...chosen.filter((x) => !listed.has(x.id)).map((x) => ({ ...x, inactive: true }))];
  return `
    <fieldset data-who>
      <legend class="mb-1.5 block text-xs text-muted">Who can join</legend>
      <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Who can join">
        <button type="button" role="radio" data-aud="everyone" aria-checked="${!inviteOnly}" class="act-kind bg-canvas">${icon('users', 16, 'inline -mt-0.5 mr-1')}Everyone</button>
        <button type="button" role="radio" data-aud="invited" aria-checked="${inviteOnly}" class="act-kind bg-canvas">${icon('user', 16, 'inline -mt-0.5 mr-1')}People I choose</button>
      </div>
      <div data-picker class="mt-2 rounded-2xl border border-line p-3" ${inviteOnly ? '' : 'hidden'}>
        <div class="flex items-center gap-2">
          <span class="field h-10 min-w-0 flex-1">${icon('search', 16)}<input data-who-search type="search" placeholder="Find a classmate" aria-label="Find a classmate" autocomplete="off"></span>
          <button type="button" data-who-all class="h-10 shrink-0 rounded-full px-3 text-sm font-semibold hover:bg-canvas">Select all</button>
        </div>
        <p data-who-count class="mt-2 text-xs text-muted" aria-live="polite"></p>
        <ul class="mt-1 max-h-56 space-y-0.5 overflow-y-auto" aria-label="Members to invite">
          ${rows.map((m) => `
            <li data-who-row="${esc(m.name.toLowerCase())}">
              <label class="flex cursor-pointer items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-canvas">
                <input type="checkbox" data-who-id="${m.id}" class="h-5 w-5 accent-charcoal" ${picked.has(m.id) ? 'checked' : ''}>
                ${UI.avatar(m.name, 'bg-lavender-tint', 'h-8 w-8 text-xs')}
                <span class="min-w-0 flex-1 truncate text-sm">${esc(m.name)}</span>
                ${m.inactive ? '<span class="shrink-0 text-xs text-muted">Not active</span>' : ''}
              </label>
            </li>`).join('')}
        </ul>
        <p class="mt-2 text-xs text-muted">Only the people you choose are notified, can RSVP or volunteer, and count for attendance.</p>
      </div>
    </fieldset>`;
}

/**
 * Wires a whoField inside el; returns a function that reads {audience, invited}.
 * onlyIfTouched (Edit): the function returns {} until the officer changes something in the field.
 */
function bindWho(el, { onlyIfTouched = false } = {}) {
  const box = $('[data-who]', el);
  let audience = $('[data-aud="invited"]', box).getAttribute('aria-checked') === 'true' ? 'invited' : 'everyone';
  let touched = false;
  const boxes = () => $$('[data-who-id]', box);
  const count = () => {
    const n = boxes().filter((c) => c.checked).length;
    $('[data-who-count]', box).textContent = n ? `${plural(n, 'person', 'people')} chosen` : 'Nobody chosen yet';
  };
  $$('[data-aud]', box).forEach((b) => b.onclick = () => {
    touched = true;
    audience = b.dataset.aud;
    $$('[data-aud]', box).forEach((x) => x.setAttribute('aria-checked', String(x === b)));
    $('[data-picker]', box).hidden = audience !== 'invited';
  });
  $('[data-who-search]', box).oninput = (e) => {
    const q = e.target.value.trim().toLowerCase();
    $$('[data-who-row]', box).forEach((li) => { li.hidden = !!q && !li.dataset.whoRow.includes(q); });
  };
  $('[data-who-all]', box).onclick = () => {
    const visible = boxes().filter((c) => !c.closest('li').hidden);
    const all = visible.every((c) => c.checked);
    visible.forEach((c) => { c.checked = !all; });
    touched = true;
    count();
  };
  boxes().forEach((c) => c.onchange = () => { touched = true; count(); });
  count();
  return () => (onlyIfTouched && !touched ? {} : {
    audience,
    invited: audience === 'invited' ? boxes().filter((c) => c.checked).map((c) => Number(c.dataset.whoId)) : [],
  });
}

/** Results (plain text) for a finished activity and, for competitions, each part. */
function openResult(a) {
  UI.sheet(`
    <form novalidate>
      <p class="text-xs font-semibold uppercase tracking-wide text-muted">${esc(a.title)}</p>
      <h2 class="mt-1 text-2xl font-bold">Result</h2>
      <div class="mt-4 space-y-4">
        <label class="block"><span class="mb-1.5 block text-xs text-muted">Overall (optional)</span>
          <span class="field"><input name="result" maxlength="300" placeholder="e.g. 2nd place overall" value="${esc(a.result || '')}"></span></label>
        ${(a.parts || []).map((p) => `<label class="block"><span class="mb-1.5 block text-xs text-muted">${esc(p.name)}</span>
          <span class="field"><input name="part_${p.id}" maxlength="300" placeholder="e.g. 1st place" value="${esc(p.result || '')}"></span></label>`).join('')}
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Save result</button>
    </form>`, {
    label: 'Result',
    onMount: (el, close) => UI.bindForm($('form', el), async (f) => {
      await API.post('activities.php', { action: 'set_result', id: a.id, result: f.result });
      for (const p of a.parts || []) {
        if ((f[`part_${p.id}`] || '') !== (p.result || '')) {
          await API.post('activities.php', { action: 'set_result', id: a.id, part_id: p.id, result: f[`part_${p.id}`] });
        }
      }
      close();
      UI.toast('Result saved');
      await load();
    }),
  });
}

/** The attendance sheet: tick people in as they arrive (Secretary / President). */
async function openAttendance(id) {
  const sheet = UI.sheet(`<div data-body>${UI.skeleton(4, 'h-12 mt-2')}</div>`, { label: 'Attendance' });
  const body = $('[data-body]', sheet.el);
  let d;
  try {
    d = await API.get('activities.php', { id, roster: 1 });
  } catch (e) {
    body.innerHTML = UI.errorBox(e);
    return;
  }
  const a = d.activity;
  const notOpen = Date.now() / 1000 < d.opens_at;
  const draw = () => {
    const count = d.people.filter((p) => p.checked_in).length;
    body.innerHTML = `
      <div class="flex flex-wrap items-center gap-1.5">${UI.chip(a.kind_label, KIND_TINT[a.kind])}<span class="text-xs text-muted">${esc(whenText(a))}</span></div>
      <h2 class="mt-2 text-2xl font-bold leading-tight">${esc(a.title)}</h2>
      <p class="mt-1 text-sm text-muted">${esc(a.rule)}</p>
      <p class="mt-3 text-sm font-semibold" aria-live="polite">${count} checked in${a.capacity ? ` · ${a.going} of ${a.capacity} seats taken` : ''}</p>
      ${notOpen ? `<p class="mt-3 rounded-2xl bg-sun-tint px-4 py-3 text-sm">Attendance opens 1 hour before the start, at ${esc(hm(new Date(d.opens_at * 1000)))}.</p>` : ''}
      ${!d.can_record ? '<p class="mt-3 rounded-2xl bg-canvas px-4 py-3 text-sm">The Secretary or the President takes attendance. You can see who came.</p>' : ''}
      <ul class="mt-3 max-h-[55vh] space-y-1 overflow-y-auto">
        ${d.people.map((p) => {
          // Only Active members are expected at a meeting; Inactive / Alumni absences don't count
          const note = p.has_seat ? 'Has a seat' : p.waitlist_position ? `Waitlist #${p.waitlist_position}`
            : a.kind === 'meeting' ? (p.status === 'Active' ? 'Expected' : 'Optional') : 'No RSVP';
          const off = !d.can_record || notOpen || (!p.can_check_in && !p.checked_in);
          return `
          <li class="flex items-center gap-3 rounded-2xl px-1 py-1.5">
            ${UI.avatar(p.name, 'bg-lavender-tint', 'h-9 w-9 text-xs')}
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">${esc(p.name)}</span>
              <span class="block text-xs text-muted">${esc(note)}${p.status !== 'Active' ? ` · ${esc(p.status)}` : ''}${!p.can_check_in && !p.checked_in ? ' · can’t check in without a seat' : ''}</span></span>
            <button type="button" data-present="${p.id}" aria-pressed="${p.checked_in}" ${off ? 'disabled' : ''}
              aria-label="${p.checked_in ? 'Undo check-in for' : 'Check in'} ${esc(p.name)}"
              class="grid h-11 w-11 shrink-0 place-items-center rounded-full transition ${p.checked_in ? 'bg-success text-white' : 'border-2 border-line bg-white text-subtle'} ${off ? 'opacity-40' : 'hover:border-success hover:text-success'}">${icon('check', 18)}</button>
          </li>`;
        }).join('')}
      </ul>
      <button data-close class="btn btn-dark mt-5 w-full">Done</button>`;
    $$('[data-present]', body).forEach((b) => b.onclick = async () => {
      const p = d.people.find((x) => x.id === Number(b.dataset.present));
      b.disabled = true;
      try {
        await API.post('activities.php', { action: 'check_in', id, user_id: p.id, present: !p.checked_in });
        p.checked_in = !p.checked_in;
        draw();
      } catch (e) {
        b.disabled = false;
        UI.toast(e.message, { iconName: 'alert' });
      }
    });
  };
  draw();
  // Refresh the list behind the sheet once it closes
  new MutationObserver((_, obs) => { if (!document.body.contains(sheet.el)) { obs.disconnect(); load(); } })
    .observe(document.body, { childList: true });
}

/** Officers: a cancelled activity can be removed for good. */
function removeButton(a) {
  if (!data.can_manage) return '';
  return `<span class="act-officer"><button type="button" data-remove-activity="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm text-danger">${icon('x', 16)}Remove</button></span>`;
}

/* ------------------------------------------------------------------ date range */
/**
 * The date of an activity: one day, or (ticking "Several days") a range From – To.
 * The start time is on the first day and the end time on the last day.
 */
function dateRangeFields(from, to, min) {
  const multi = !!to && to !== from;
  return `
    <div class="col-span-2 space-y-2" data-range>
      <label class="flex w-fit cursor-pointer items-center gap-2 text-sm"><input type="checkbox" data-multi class="h-5 w-5 accent-charcoal" ${multi ? 'checked' : ''}> Several days (a date range)</label>
      <div class="grid grid-cols-2 gap-2">
        <label class="block ${multi ? '' : 'col-span-2'}" data-from-wrap><span class="mb-1.5 block text-xs text-muted" data-from-label>${multi ? 'From' : 'Date'}</span>
          <span class="field"><input type="date" name="date" min="${min}" value="${from}" required></span></label>
        <label class="block" data-to-wrap ${multi ? '' : 'hidden'}><span class="mb-1.5 block text-xs text-muted">To</span>
          <span class="field"><input type="date" name="end_date" min="${from}" value="${multi ? to : ''}" ${multi ? '' : 'disabled'}></span></label>
      </div>
      <p class="text-xs text-muted" data-range-hint ${multi ? '' : 'hidden'}>It starts on the first day at the start time and ends on the last day at the end time.</p>
    </div>`;
}

/** Wires dateRangeFields inside el. To never goes before From; moving From moves To by the same number of days. */
function bindRange(el) {
  const box = $('[data-range]', el);
  const check = $('[data-multi]', box);
  const from = $('[name="date"]', box);
  const to = $('[name="end_date"]', box);
  const addDays = (iso, n) => { const d = Fmt.date(iso); d.setDate(d.getDate() + n); return Fmt.iso(d); };
  const days = (a, b) => Math.round((Fmt.date(b) - Fmt.date(a)) / 86400e3);
  let lastFrom = from.value;
  const sync = () => {
    const multi = check.checked;
    $('[data-to-wrap]', box).hidden = !multi;
    $('[data-from-wrap]', box).classList.toggle('col-span-2', !multi);
    $('[data-from-label]', box).textContent = multi ? 'From' : 'Date';
    $('[data-range-hint]', box).hidden = !multi;
    to.disabled = !multi;
    $('[data-start-label]', el).textContent = multi ? 'Starts (first day)' : 'Starts';
    $('[data-end-label]', el).textContent = multi ? 'Ends (last day)' : 'Ends';
    to.min = from.value;
    if (multi && (!to.value || to.value <= from.value)) to.value = addDays(from.value, 1);
  };
  check.onchange = sync;
  from.onchange = () => {
    if (check.checked && to.value && lastFrom) to.value = addDays(to.value, days(lastFrom, from.value));   // keep the length
    if (to.value && to.value < from.value) to.value = from.value;
    lastFrom = from.value;
    to.min = from.value;
  };
  to.onchange = () => { if (to.value && to.value < from.value) to.value = from.value; };
  sync();
  return { multi: () => check.checked };
}