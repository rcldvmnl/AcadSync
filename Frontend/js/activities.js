// Activities: the organization's meetings, seminars and outreach.
//   RSVP takes a seat; when an activity is full you join its waitlist (a QUEUE on the server),
//   and the first person waiting gets the next seat that opens.
//   Officers add and cancel activities; the Secretary and the President take attendance.
//   Each kind has its own attendance rule (Meeting / Seminar / Outreach are classes on the server).

const KIND_TINT = { meeting: 'bg-lavender-tint', seminar: 'bg-peach-tint', outreach: 'bg-mint-tint' };
let data = null;
let tab = location.hash === '#past' ? 'past' : 'upcoming';

const hm = (d) => Fmt.time(`${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`);
const whenText = (a) => {
  const s = new Date(a.start_ts * 1000);
  const e = new Date(a.end_ts * 1000);
  return `${Fmt.short(s)} · ${hm(s)} – ${hm(e)}`;
};

(async () => {
  await Shell.init({ active: 'activities', eyebrow: sectionEyebrow, title: 'Activities' });
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function load() {
  data = await API.get('activities.php');
  render();
}

/* ------------------------------------------------------------------ page */
function render() {
  const list = data[tab];
  $('#main').innerHTML = `
    <div class="mb-5 flex flex-wrap items-center gap-2">
      <div class="flex gap-2" role="tablist" aria-label="Which activities">
        ${[['upcoming', 'Upcoming'], ['past', 'Past']].map(([k, l]) => `
          <button type="button" role="tab" data-tab="${k}" aria-selected="${tab === k}" aria-pressed="${tab === k}" class="chip chip-filter">${l} <span class="opacity-70">${data[k].length}</span></button>`).join('')}
      </div>
      ${data.can_manage ? `<button type="button" id="new-activity" class="btn btn-dark ml-auto h-11 px-5 text-sm">${icon('plus', 18)}New activity</button>` : ''}
    </div>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
      <section class="space-y-3" aria-label="${tab === 'upcoming' ? 'Upcoming activities' : 'Past activities'}">
        ${list.length ? list.map(card).join('') : emptyState()}
      </section>
      <aside class="space-y-4">${myAttendanceCard()}${rulesCard()}</aside>
    </div>`;
  bind();
}

function emptyState() {
  if (tab === 'past') return UI.empty('No past activities yet', 'Finished meetings, seminars and outreach show here with who attended.', 'flag');
  return UI.empty('Nothing planned yet', data.can_manage ? 'Add the first meeting, seminar or outreach with “New activity”.' : 'When an officer adds an activity, you can RSVP here.', 'flag');
}

function card(a) {
  const d = new Date(a.start_ts * 1000);
  const pct = a.capacity ? Math.min(100, Math.round((a.going / a.capacity) * 100)) : 0;
  const seats = a.capacity
    ? `${a.going} of ${plural(a.capacity, 'seat')} taken${a.waitlist ? ` · ${a.waitlist} waiting` : ''}`
    : `${plural(a.going, 'person', 'people')} going · no seat limit`;
  return `
    <article class="act-card ${a.cancelled ? 'is-cancelled' : ''}" id="act-${a.id}" aria-labelledby="act-${a.id}-h">
      <div class="flex gap-4">
        <div class="act-date ${KIND_TINT[a.kind]}" aria-hidden="true">
          <span class="text-xl font-bold leading-none">${String(d.getDate()).padStart(2, '0')}</span>
          <span class="mt-1 text-[11px] font-semibold text-muted">${MONTHS[d.getMonth()]}</span>
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-1.5">
            ${UI.chip(a.kind_label, KIND_TINT[a.kind])}
            ${a.cancelled ? UI.chip('Cancelled', 'bg-danger-tint') : ''}
            ${a.venue ? `<span class="inline-flex items-center gap-1 text-xs text-muted">${icon('mapPin', 13)}${esc(a.venue)}</span>` : ''}
          </div>
          <h3 id="act-${a.id}-h" class="mt-1.5 text-lg font-semibold leading-snug">${esc(a.title)}</h3>
          <p class="text-sm text-muted">${esc(whenText(a))}</p>
          ${a.description ? `<p class="mt-2 text-sm leading-relaxed">${esc(a.description)}</p>` : ''}
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

/** What I can do with this activity, or where I stand. */
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
  if (!me.can_rsvp) return `<span class="text-sm text-muted">${esc(data.my_status.label)} members can’t RSVP</span>`;
  return a.full
    ? `<button type="button" data-rsvp="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">Join the waitlist</button>`
    : `<button type="button" data-rsvp="${a.id}" class="btn btn-dark h-10 px-4 text-sm">I’m going</button>`;
}

function officerActions(a) {
  if (a.cancelled) return '';
  const parts = [];
  if (data.can_record || data.can_manage) {
    parts.push(`<button type="button" data-attendance="${a.id}" class="btn btn-light h-10 border border-line px-4 text-sm">${icon('clipboard', 16)}Attendance${a.checked_in ? ` · ${a.checked_in}` : ''}</button>`);
  }
  if (data.can_manage && !a.is_over) {
    parts.push(`<button type="button" data-cancel-activity="${a.id}" class="h-10 px-2 text-sm font-semibold text-muted hover:text-charcoal">Cancel activity</button>`);
  }
  return parts.length ? `<span class="act-officer">${parts.join('')}</span>` : '';
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
      <h2 id="rules-h" class="text-lg font-semibold">How attendance counts</h2>
      <ul class="mt-3 space-y-3">
        ${data.kinds.map((k) => `<li>${UI.chip(k.label, KIND_TINT[k.kind])}<p class="mt-1 text-sm">${esc(k.rule)}</p></li>`).join('')}
      </ul>
      <p class="mt-3 text-xs text-muted">When an activity is full, RSVPs join a waitlist. The first person waiting gets the next seat that opens.</p>
    </section>`;
}

/* ------------------------------------------------------------------ actions */
function bind() {
  $$('[data-tab]').forEach((b) => b.onclick = () => { tab = b.dataset.tab; history.replaceState(null, '', tab === 'past' ? '#past' : location.pathname); render(); });
  $('#new-activity')?.addEventListener('click', openCreate);
  const act = async (btn, body, msg) => {
    btn.disabled = true;
    try {
      const r = await API.post('activities.php', body);
      UI.toast(typeof msg === 'function' ? msg(r) : msg);
      await load();
    } catch (e) {
      btn.disabled = false;
      UI.toast(e.message, { iconName: 'alert' });
    }
  };
  $$('[data-rsvp]').forEach((b) => b.onclick = () => act(b, { action: 'rsvp', id: Number(b.dataset.rsvp) },
    (r) => (r.status === 'going' ? 'You’re going' : `You’re on the waitlist, #${r.position}`)));
  $$('[data-cancel-rsvp]').forEach((b) => b.onclick = () => act(b, { action: 'cancel_rsvp', id: Number(b.dataset.cancelRsvp) },
    (r) => (r.promoted ? 'RSVP cancelled. The next person waiting got your seat.' : 'RSVP cancelled')));
  $$('[data-attendance]').forEach((b) => b.onclick = () => openAttendance(Number(b.dataset.attendance)));
  $$('[data-cancel-activity]').forEach((b) => b.onclick = () => {
    const a = data.upcoming.find((x) => x.id === Number(b.dataset.cancelActivity));
    UI.sheet(`<h2 class="text-2xl font-bold">Cancel “${esc(a.title)}”?</h2>
      <p class="mt-2 text-sm text-muted">Everyone going or waiting gets a notification. It stays in the list, marked cancelled.</p>
      <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Keep it</button><button data-ok class="btn btn-dark">Cancel activity</button></div>`, {
      label: 'Cancel activity',
      onMount: (el, close) => { $('[data-ok]', el).onclick = () => { close(); act(b, { action: 'cancel', id: a.id }, 'Activity cancelled'); }; },
    });
  });
}

/** New activity: the kind decides the attendance rule (shown as you pick it). */
function openCreate() {
  const today = Fmt.iso(new Date());
  let kind = 'meeting';
  UI.sheet(`
    <form novalidate>
      <h2 class="text-2xl font-bold">New activity</h2>
      <div class="mt-4 grid grid-cols-3 gap-2" role="radiogroup" aria-label="Kind of activity">
        ${data.kinds.map((k) => `<button type="button" role="radio" data-kind="${k.kind}" aria-checked="${k.kind === kind}" class="act-kind ${KIND_TINT[k.kind]}">${esc(k.label)}</button>`).join('')}
      </div>
      <p data-rule class="mt-2 text-xs text-muted"></p>
      <div class="mt-4 space-y-4">
        ${UI.field({ name: 'title', label: 'Title', placeholder: 'e.g. General Assembly', required: true, iconName: 'flag' })}
        <div class="grid grid-cols-3 gap-2">
          <label class="col-span-3 block sm:col-span-1"><span class="mb-1.5 block text-xs text-muted">Date</span>
            <span class="field"><input type="date" name="date" min="${today}" value="${today}" required></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Starts</span>
            <span class="field"><input type="time" name="start" value="15:00" required></span></label>
          <label class="block"><span class="mb-1.5 block text-xs text-muted">Ends</span>
            <span class="field"><input type="time" name="end" value="16:00" required></span></label>
        </div>
        ${UI.field({ name: 'venue', label: 'Venue (optional)', placeholder: 'e.g. Room 204', iconName: 'mapPin' })}
        <label class="block"><span class="mb-1.5 block text-xs text-muted">Seats (leave empty for no limit)</span>
          <span class="field">${icon('users', 18)}<input type="number" name="capacity" min="1" max="1000" inputmode="numeric" placeholder="No limit"></span></label>
        <label class="block"><span class="mb-1.5 block text-xs text-muted">Details (optional)</span>
          <span class="field area"><textarea name="description" rows="3" maxlength="2000" placeholder="What to bring, what it’s about"></textarea></span></label>
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Add activity</button>
    </form>`, {
    label: 'New activity',
    onMount: (el, close) => {
      const showRule = () => {
        $$('[data-kind]', el).forEach((b) => b.setAttribute('aria-checked', String(b.dataset.kind === kind)));
        $('[data-rule]', el).textContent = data.kinds.find((k) => k.kind === kind).rule;
      };
      $$('[data-kind]', el).forEach((b) => b.onclick = () => { kind = b.dataset.kind; showRule(); });
      showRule();
      UI.bindForm($('form', el), async (f) => {
        await API.post('activities.php', { action: 'create', kind, ...f, capacity: f.capacity || null });
        close();
        UI.toast('Activity added', { sub: 'Everyone in the section was notified' });
        tab = 'upcoming';
        await load();
      });
    },
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
