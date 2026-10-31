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
  $('#main').innerHTML = `
    ${state.admin ? `<div class="mb-5 flex gap-3 rounded-[1.5rem] bg-lavender-tint p-4 lg:max-w-xl">
      ${icon('calendar', 20, 'mt-0.5 shrink-0')}
      <div><div class="text-sm font-semibold">Classes repeat every week</div><div class="text-xs text-muted">Tap + to add a class to a day, or × to remove one. Each class can have its own time and length.</div></div>
    </div>${unscheduled()}` : ''}
    <div class="grid gap-5 lg:grid-cols-2 lg:items-start">${days.map(state.admin ? adminDay : studentDay).join('')}</div>`;
  if (state.admin) bindAdmin();
}

function studentDay(d) {
  return `
    <section>
      <div class="mb-3 flex items-baseline justify-between">
        <h2 class="text-xl font-bold">${DAYS_LONG[d.dow]} · ${esc(Fmt.monthDay(d.date))}</h2>
        <span class="text-xs text-muted">${d.classes.length} class${d.classes.length === 1 ? '' : 'es'}</span>
      </div>
      <div class="space-y-3">${d.classes.length ? d.classes.map((c) => UI.classRow(c)).join('') : '<div class="rounded-3xl bg-white/60 p-4 text-sm text-muted">No classes</div>'}</div>
    </section>`;
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
  $('[data-len]', form).forEach((b) => b.onclick = () => { state.adding.end = toHHMM(toMin(state.adding.start) + Number(b.dataset.len)); keep(); });
  $('[data-cancel]', form).onclick = () => { state.adding = null; render(); };
  UI.bindForm(form, async (d) => {
    await API.post('subjects.php', { action: 'add_slot', subject_id: Number(d.subject_id), day_of_week: state.adding.dow, start_time: state.adding.start, end_time: state.adding.end });
    state.adding = null;
    UI.toast('Class added to the week');
    await load();
  });
}
