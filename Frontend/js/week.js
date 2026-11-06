// Whole week (students) / Week Schedule editor (admin).

const LENGTHS = [[60, '1 hr'], [90, '1.5 hrs'], [120, '2 hrs'], [180, '3 hrs']];
const toMin = (hhmm) => { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; };
const toHHMM = (min) => `${String(Math.floor(min / 60) % 24).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;
let state = { data: null, subjects: [], admin: false, adding: null };

(async () => {
  const me = await Shell.init({
    active: 'schedule',
    eyebrow: (m) => (m.user.role === 'admin' ? 'Admin · set classes for the whole week' : ''),
    title: (m) => (m.user.role === 'admin' ? 'Week Schedule' : 'Whole Week'),
  });
  state.admin = me.user.role === 'admin';
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function load() {
  const [data, subj] = await Promise.all([API.get('schedule.php'), API.get('subjects.php')]);
  state.data = data;
  state.subjects = subj.subjects;
  if (!state.admin) {
    const total = data.week.days.reduce((n, d) => n + d.classes.length, 0);
    const first = data.week.days[0].date;
    const last = data.week.days[data.week.days.length - 1].date;
    $('#header .text-xs').textContent = `${Fmt.monthDay(first)} – ${Fmt.date(last).getDate()} · ${total} classes`;
  }
  render();
}

function render() {
  // Classes meet Monday–Saturday; Sunday only appears on the Class Schedule day picker.
  const days = state.data.week.days.filter((d) => d.dow <= 6 && (d.dow <= 5 || d.classes.length || state.admin));
  if (!state.admin) { $('#main').innerHTML = weekCalendar(days); return; }
  $('#main').innerHTML = `
    <div class="mb-5 flex gap-3 rounded-[1.5rem] bg-lavender-tint p-4 lg:max-w-xl">
      ${icon('calendar', 20, 'mt-0.5 shrink-0')}
      <div><div class="text-sm font-semibold">Classes repeat every week</div><div class="text-xs text-muted">Tap + to add a class to a day, or × to remove one. Each class can have its own time and length.</div></div>
    </div>${unscheduled()}
    <div class="grid gap-5 lg:grid-cols-2 lg:items-start">${days.map(adminDay).join('')}</div>`;
  bindAdmin();
}

/* ------------------------------------------------------------------ student: week calendar
 * Days across the top, hours down the side, each class a block sized by its length.
 * On phones the grid scrolls sideways inside its card; the page itself never scrolls sideways.
 */
const HOUR_PX = 64;

function weekCalendar(days) {
  const all = days.flatMap((d) => d.classes);
  let startH = 7;
  let endH = 17;
  if (all.length) {
    startH = Math.min(...all.map((c) => Math.floor(toMin(c.start_time) / 60)));
    endH = Math.max(...all.map((c) => Math.ceil(toMin(c.end_time) / 60)));
  }
  const hours = Array.from({ length: endH - startH }, (_, i) => startH + i);
  const height = hours.length * HOUR_PX;
  const today = Fmt.iso(new Date());
  const cols = `3.25rem repeat(${days.length}, minmax(6.5rem, 1fr))`;
  const hourLabel = (h) => `${((h + 11) % 12) + 1} ${h < 12 ? 'AM' : 'PM'}`;
  const now = new Date();
  const nowMin = now.getHours() * 60 + now.getMinutes();
  const nowTop = ((nowMin - startH * 60) / 60) * HOUR_PX;
  const showNow = nowMin >= startH * 60 && nowMin <= endH * 60;

  return `
    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-muted">
      ${legend(all)}
    </div>
    <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-card">
      <div class="overflow-x-auto">
        <div class="min-w-max lg:min-w-0">
          <div class="grid border-b border-line" style="grid-template-columns:${cols}">
            <div></div>
            ${days.map((d) => {
              const isToday = d.date === today;
              return `<div class="flex flex-col items-center gap-0.5 border-l border-line py-3">
                <span class="text-[10px] font-semibold uppercase ${isToday ? 'text-charcoal' : 'text-muted'}">${DAYS[d.dow % 7]}</span>
                <span class="grid h-8 w-8 place-items-center rounded-full text-base font-bold ${isToday ? 'bg-charcoal text-white' : ''}">${Fmt.date(d.date).getDate()}</span>
              </div>`;
            }).join('')}
          </div>
          <div class="grid" style="grid-template-columns:${cols}">
            <div class="relative" style="height:${height}px">
              ${hours.map((h, i) => `<span class="absolute right-2 -translate-y-1/2 text-[10px] font-medium text-muted" style="top:${i * HOUR_PX}px">${i ? hourLabel(h) : ''}</span>`).join('')}
            </div>
            ${days.map((d) => `
              <div class="relative border-l border-line ${d.date === today ? 'bg-lavender-tint/30' : ''}" style="height:${height}px;background-image:linear-gradient(to bottom,#ECE8E2 1px,transparent 1px);background-size:100% ${HOUR_PX}px">
                ${layout(d.classes).map(({ c, lane, lanes }) => classBlock(c, startH, lane, lanes)).join('')}
                ${d.date === today && showNow ? `<div class="pointer-events-none absolute inset-x-0 z-10 flex items-center" style="top:${nowTop}px"><span class="-ml-1 h-2 w-2 rounded-full bg-danger"></span><span class="h-0.5 flex-1 bg-danger"></span></div>` : ''}
              </div>`).join('')}
          </div>
        </div>
      </div>
    </div>
    ${all.length ? '' : `<div class="mt-4">${UI.empty('No classes yet', 'Your class officer hasn’t set this week’s classes.', 'calendar')}</div>`}`;
}

/** Puts overlapping classes side by side (simple lane assignment). */
function layout(classes) {
  const sorted = [...classes].sort((a, b) => toMin(a.start_time) - toMin(b.start_time));
  const out = [];
  let cluster = [];
  let clusterEnd = -1;
  const flush = () => {
    const laneEnds = [];
    const placed = cluster.map((c) => {
      let lane = laneEnds.findIndex((end) => end <= toMin(c.start_time));
      if (lane === -1) { lane = laneEnds.length; laneEnds.push(0); }
      laneEnds[lane] = toMin(c.end_time);
      return { c, lane };
    });
    placed.forEach((p) => out.push({ ...p, lanes: laneEnds.length }));
    cluster = [];
  };
  sorted.forEach((c) => {
    if (cluster.length && toMin(c.start_time) >= clusterEnd) flush();
    cluster.push(c);
    clusterEnd = Math.max(clusterEnd, toMin(c.end_time));
  });
  if (cluster.length) flush();
  return out;
}

function classBlock(c, startH, lane, lanes) {
  const top = ((toMin(c.start_time) - startH * 60) / 60) * HOUR_PX;
  const h = Math.max(28, ((toMin(c.end_time) - toMin(c.start_time)) / 60) * HOUR_PX);
  const t = tone(c.color);
  const width = 100 / lanes;
  const short = h < 56;
  return `
    <a href="subject.html?id=${c.subject_id}" title="${esc(`${c.code} · ${c.title} · ${Fmt.time(c.start_time)}–${Fmt.time(c.end_time)}${c.room ? ` · ${c.room}` : ''}`)}"
      class="absolute overflow-hidden rounded-xl ${t.chip} px-2 py-1.5 leading-tight transition hover:z-20 hover:shadow-card"
      style="top:${top + 2}px;height:${h - 4}px;left:calc(${lane * width}% + 3px);width:calc(${width}% - 6px)">
      <div class="flex items-center gap-1 text-[11px] font-bold"><span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background:${t.bar}"></span><span class="truncate">${esc(c.code)}</span></div>
      ${short ? '' : `<div class="mt-0.5 line-clamp-2 text-[11px] font-medium">${esc(c.title)}</div>`}
      <div class="mt-0.5 truncate text-[10px] text-muted">${Fmt.time(c.start_time).replace(' ', '')}–${Fmt.time(c.end_time).replace(' ', '')}</div>
      ${!short && c.room ? `<div class="truncate text-[10px] text-muted">${esc(c.room)}</div>` : ''}
    </a>`;
}

/** Subject colour key above the calendar. */
function legend(all) {
  const seen = new Map();
  all.forEach((c) => { if (!seen.has(c.code)) seen.set(c.code, c); });
  return [...seen.values()].map((c) => `<span class="chip bg-white"><span class="h-2 w-2 rounded-full" style="background:${tone(c.color).bar}"></span>${esc(c.code)}</span>`).join('');
}

function adminDay(d) {
  const adding = state.adding && state.adding.dow === d.dow;
  return `
    <section class="rounded-[1.75rem] bg-white p-4 shadow-card">
      <div class="mb-3 flex items-center justify-between">
        <div><h2 class="text-lg font-bold">${DAYS_LONG[d.dow]} · ${esc(Fmt.monthDay(d.date))}</h2><div class="text-xs text-muted">${d.classes.length} class${d.classes.length === 1 ? '' : 'es'}</div></div>
        <button data-add="${d.dow}" class="grid h-10 w-10 place-items-center rounded-full bg-charcoal text-white" aria-label="Add a class on ${DAYS_LONG[d.dow]}">${icon('plus', 18)}</button>
      </div>
      <div class="space-y-2">
        ${d.classes.map((c) => `
          <div class="flex items-center gap-3 rounded-2xl p-2.5 ${tone(c.color).chip}">
            <span class="shrink-0 rounded-full bg-white px-3 py-2 text-xs font-medium">${Fmt.time(c.start_time)} – ${Fmt.time(c.end_time)}</span>
            <div class="min-w-0 flex-1"><div class="text-[10px] font-semibold text-muted">${esc(c.code)}</div><div class="text-sm font-semibold leading-snug">${esc(c.title)}</div></div>
            <button data-remove="${c.id}" class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white" aria-label="Remove ${esc(c.code)} on ${DAYS_LONG[d.dow]}">${icon('x', 14)}</button>
          </div>`).join('')}
        ${adding ? newClassForm(d) : ''}
      </div>
    </section>`;
}

/** Subjects that have no class meeting yet (e.g. right after loading the preset). */
function unscheduled() {
  const list = state.subjects.filter((s) => !s.slots.length);
  if (!list.length) return '';
  return `<div class="mb-5 rounded-[1.5rem] bg-sun-tint p-4 lg:max-w-xl">
    <div class="text-sm font-semibold">${list.length} subject${list.length === 1 ? '' : 's'} without class times</div>
    <div class="mt-2 flex flex-wrap gap-2">${list.map((s) => `<a href="add-subject.html?id=${s.id}" class="chip bg-white">${esc(s.code)} · set times</a>`).join('')}</div>
  </div>`;
}

function newClassForm() {
  const a = state.adding;
  const len = toMin(a.end) - toMin(a.start);
  return `
    <form id="new-class" class="mt-2 rounded-2xl border-2 border-dashed border-charcoal/60 p-4">
      <div class="flex items-center justify-between"><div class="font-semibold">New class</div><span class="chip bg-sun-tint">Not saved yet</span></div>
      <label class="mt-3 block"><span class="mb-1.5 block text-xs text-muted">Subject</span>
        <span class="field">${icon('bookOpen', 18)}<select name="subject_id">${state.subjects.map((s) => `<option value="${s.id}" ${String(s.id) === String(a.subject_id) ? 'selected' : ''}>${esc(s.code)} · ${esc(s.title)}</option>`).join('')}</select>${icon('chevronDown', 18)}</span></label>
      <div class="mt-3 grid grid-cols-2 gap-2">
        <label class="block"><span class="mb-1.5 block text-xs text-muted">Starts</span>
          <span class="field h-11">${icon('clock', 16)}<input type="time" data-start value="${a.start}"></span></label>
        <label class="block"><span class="mb-1.5 block text-xs text-muted">Ends</span>
          <span class="field h-11">${icon('clock', 16)}<input type="time" data-end value="${a.end}"></span></label>
      </div>
      <div class="mt-2 flex flex-wrap gap-1.5">${LENGTHS.map(([m, l]) => `<button type="button" data-len="${m}" class="rounded-full px-3 py-1.5 text-xs font-medium ${m === len ? 'bg-charcoal text-white' : 'bg-canvas'}">${l}</button>`).join('')}</div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <div class="mt-4 grid grid-cols-2 gap-2">
        <button type="button" data-cancel class="btn btn-canvas">Cancel</button>
        <button type="submit" class="btn btn-dark">Save class</button>
      </div>
    </form>`;
}

function bindAdmin() {
  $$('[data-add]').forEach((b) => b.onclick = () => {
    state.adding = { dow: Number(b.dataset.add), subject_id: state.subjects[0]?.id, start: '08:00', end: '09:00' };
    if (!state.subjects.length) { UI.toast('Add a subject first', { iconName: 'alert' }); state.adding = null; return; }
    render();
    $('#new-class')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  $$('[data-remove]').forEach((b) => b.onclick = async () => {
    try {
      await API.post('subjects.php', { action: 'remove_slot', slot_id: Number(b.dataset.remove) });
      UI.toast('Class removed');
      await load();
    } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
  });
  const form = $('#new-class');
  if (!form) return;
  const keep = () => { state.adding.subject_id = form.elements.subject_id.value; render(); };
  $('[data-start]', form).onchange = (e) => {
    const len = Math.max(30, toMin(state.adding.end) - toMin(state.adding.start));
    state.adding.start = e.target.value;
    state.adding.end = toHHMM(toMin(e.target.value) + len);
    keep();
  };
  $('[data-end]', form).onchange = (e) => { state.adding.end = e.target.value; keep(); };
  $$('[data-len]', form).forEach((b) => b.onclick = () => { state.adding.end = toHHMM(toMin(state.adding.start) + Number(b.dataset.len)); keep(); });
  $('[data-cancel]', form).onclick = () => { state.adding = null; render(); };
  UI.bindForm(form, async (d) => {
    await API.post('subjects.php', { action: 'add_slot', subject_id: Number(d.subject_id), day_of_week: state.adding.dow, start_time: state.adding.start, end_time: state.adding.end });
    state.adding = null;
    UI.toast('Class added to the week');
    await load();
  });
}
