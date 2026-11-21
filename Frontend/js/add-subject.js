// Add / Edit Subject (admin). Each class day keeps its own start and end time.

const form = $('#subject-form');
const editId = new URLSearchParams(location.search).get('id');
const SWATCH = { lavender: 'bg-lavender', peach: 'bg-peach', yellow: 'bg-sun', mint: 'bg-[#C9C9D3]' };   // 'mint' is the stored name; it shows as the reference's grey
const SWATCH_LABEL = { lavender: 'Periwinkle', peach: 'Orange', yellow: 'Yellow', mint: 'Grey' };
const LENGTHS = [[60, '1 hr'], [90, '1.5 hrs'], [120, '2 hrs'], [180, '3 hrs']];
let color = 'lavender';
let design = 'auto';   // 'auto' = picked from the code and name (subjectDesign in ui.js)
/** day (1–6) -> { start: 'HH:MM', end: 'HH:MM', room: '' } (room: that day's own room; blank = the subject's room) */
const schedule = new Map();

$$('[data-icon]').forEach((el) => { el.outerHTML = icon(el.dataset.icon, 18); });

const toMin = (hhmm) => { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; };
const toHHMM = (min) => `${String(Math.floor(min / 60) % 24).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;

(async () => {
  const me = await Shell.init({
    active: 'home',
    eyebrow: (m) => `Admin · ${m.section.name}`,
    title: editId ? 'Edit Subject' : 'Add Subject',
  });
  if (!Shell.can('manage_section')) {
    $('#main').innerHTML = UI.errorBox('Only the class admin can add or edit subjects.');
    return;
  }
  if (editId) {
    document.title = 'AcadSync · Edit Subject';
    const d = await API.get('subjects.php', { id: editId });
    const s = d.subject;
    form.elements.code.value = s.code;
    form.elements.title.value = s.title;
    form.elements.instructor.value = s.instructor || '';
    form.elements.room.value = s.room || '';
    color = s.color_tag || 'lavender';
    design = SUBJECT_DESIGNS[s.design] ? s.design : 'auto';
    d.slots.forEach((slot) => schedule.set(slot.day_of_week, { start: slot.start_time, end: slot.end_time, room: slot.slot_room || '' }));
    $('button[type=submit]', form).textContent = 'Save changes';
  } else {
    schedule.set(1, { start: '13:00', end: '14:00', room: '' });
  }
  renderColors();
  renderDesigns();
  renderDays();
  // The preview and the "Auto" drawing follow the code and name as they're typed
  [form.elements.code, form.elements.title].forEach((el) => el.addEventListener('input', () => { renderDesigns(); }));
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

function renderColors() {
  $('#colors').innerHTML = Object.entries(SWATCH).map(([k, cls]) => `
    <button type="button" data-color="${k}" aria-label="${SWATCH_LABEL[k]}" aria-pressed="${k === color}" class="h-10 w-10 rounded-full ${cls} ${k === color ? 'ring-2 ring-charcoal ring-offset-2' : ''}"></button>`).join('');
  $$('[data-color]').forEach((b) => b.onclick = () => { color = b.dataset.color; renderColors(); renderPreview(); });
}

/** The drawing the card would get on "Auto", from what's typed so far. */
const autoDesign = () => subjectDesign({ code: form.elements.code.value, title: form.elements.title.value, id: Number(editId || 0) });

function renderDesigns() {
  const auto = autoDesign();
  const btn = (key, label, art) => `
    <button type="button" data-design="${key}" aria-pressed="${key === design}" aria-label="${key === 'auto' ? `Auto (${SUBJECT_DESIGNS[art].label})` : label}" title="${label}"
      class="flex h-11 items-center gap-1.5 rounded-full px-3 text-xs font-semibold transition-colors ${key === design ? 'bg-charcoal text-white' : 'bg-canvas text-charcoal hover:bg-line'}">
      ${subjectArt(art, 18)}${key === 'auto' ? `<span>${label}</span>` : ''}</button>`;
  $('#designs').innerHTML = btn('auto', 'Auto', auto)
    + Object.entries(SUBJECT_DESIGNS).map(([k, v]) => btn(k, v.label, k)).join('');
  $$('[data-design]').forEach((b) => b.onclick = () => { design = b.dataset.design; renderDesigns(); });
  renderPreview();
}

/** The card as it will look on Home (same markup and classes as home.js subjectCard). */
function renderPreview() {
  const t = tone(color);
  const key = design === 'auto' ? autoDesign() : design;
  const code = form.elements.code.value.trim().toUpperCase() || 'CODE';
  const title = form.elements.title.value.trim() || 'Subject name';
  $('#subject-preview').innerHTML = `
    <div class="card-pastel ${t.soft} flex min-h-[8.5rem] flex-col rounded-[1.5rem] p-4">
      ${subjectArt(key, 96, 'subject-art', 1.4)}
      <div class="flex items-start justify-between gap-1">
        <span class="subject-icon grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white ${t.icon}">${subjectArt(key, 18)}</span>
        <span class="chip bg-white text-[11px] text-success">${icon('check', 12)}All clear</span>
      </div>
      <div class="mt-auto pt-3 text-[10px] font-semibold tracking-wide text-muted">${esc(code)}</div>
      <div class="line-clamp-3 font-semibold leading-snug">${esc(title)}</div>
    </div>`;
}

function renderDays() {
  $('#days').innerHTML = [1, 2, 3, 4, 5, 6].map((d) => `
    <button type="button" data-day="${d}" aria-pressed="${schedule.has(d)}" class="aspect-square rounded-full text-[11px] font-semibold uppercase ${schedule.has(d) ? 'bg-charcoal text-white' : 'bg-canvas text-muted'}">${DAYS[d]}</button>`).join('');
  $$('[data-day]').forEach((b) => b.onclick = () => {
    const d = Number(b.dataset.day);
    if (schedule.has(d)) schedule.delete(d);
    else {
      // New day starts with the same time as the last one added, as a starting point
      const last = [...schedule.values()].pop() || { start: '08:00', end: '09:30' };
      schedule.set(d, { ...last, room: '' });
    }
    renderDays();
  });
  renderRows();
}

function renderRows() {
  const days = [...schedule.keys()].sort((a, b) => a - b);
  $('#day-rows').innerHTML = days.length ? days.map((d) => {
    const { start, end, room = '' } = schedule.get(d);
    const len = toMin(end) - toMin(start);
    return `
      <div class="rounded-2xl bg-canvas p-3" data-row="${d}">
        <div class="flex items-center justify-between">
          <div class="font-semibold">${DAYS_LONG[d]}</div>
          <div class="text-xs ${len > 0 ? 'text-muted' : 'text-danger'}">${len > 0 ? `${Fmt.time(start)} – ${Fmt.time(end)}` : 'End must be after start'}</div>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2">
          <label class="block"><span class="mb-1 block text-[11px] text-muted">Starts</span>
            <span class="field h-11 bg-white">${icon('clock', 16)}<input type="time" data-start value="${start}" aria-label="${DAYS_LONG[d]} start time"></span></label>
          <label class="block"><span class="mb-1 block text-[11px] text-muted">Ends</span>
            <span class="field h-11 bg-white">${icon('clock', 16)}<input type="time" data-end value="${end}" aria-label="${DAYS_LONG[d]} end time"></span></label>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
          ${LENGTHS.map(([m, l]) => `<button type="button" data-len="${m}" class="rounded-full px-3 py-1.5 text-xs font-medium ${m === len ? 'bg-charcoal text-white' : 'bg-white'}">${l}</button>`).join('')}
        </div>
        <label class="mt-2 block"><span class="mb-1 block text-[11px] text-muted">Room this day (optional)</span>
          <span class="field h-11 bg-white">${icon('mapPin', 16)}<input data-room value="${esc(room)}" maxlength="60" placeholder="Same as the subject's room" aria-label="${DAYS_LONG[d]} room"></span></label>
        <div data-clash="${d}" aria-live="polite"></div>
      </div>`;
  }).join('') : '<p class="rounded-2xl bg-canvas p-4 text-sm text-muted">No class days yet — tap a day above. You can also save now and set times later.</p>';

  $$('[data-row]').forEach((row) => {
    const d = Number(row.dataset.row);
    const slot = schedule.get(d);
    $('[data-start]', row).onchange = (e) => {
      const len = Math.max(30, toMin(slot.end) - toMin(slot.start));
      slot.start = e.target.value;
      slot.end = toHHMM(toMin(slot.start) + len);   // keep the same length when the start moves
      renderRows();
    };
    $('[data-end]', row).onchange = (e) => { slot.end = e.target.value; renderRows(); };
    $('[data-room]', row).oninput = (e) => { slot.room = e.target.value; };
    $$('[data-len]', row).forEach((b) => b.onclick = () => { slot.end = toHHMM(toMin(slot.start) + Number(b.dataset.len)); renderRows(); });
  });
  checkClashes();
}

/* ---------------------------------------------------------------- clash check (server: 2D array WeekGrid) */
let clashes = [];
let clashTimer;
const slotList = () => [...schedule.entries()].map(([day, s]) => ({ day, start: s.start, end: s.end, room: (s.room || '').trim() }));

/** Asks the server which other subjects these times overlap, then marks each clashing day. */
function checkClashes() {
  clearTimeout(clashTimer);
  clashTimer = setTimeout(async () => {
    const slots = slotList().filter((s) => toMin(s.end) > toMin(s.start));
    try {
      const r = await API.post('subjects.php', { action: 'check_clash', id: editId ? Number(editId) : undefined, slots });
      clashes = r.clashes;
    } catch { clashes = []; }   // the live check is a convenience; saving checks again
    showClashes();
  }, 250);
}

const span = (a, b) => (a && b ? `${Fmt.time(a)}–${Fmt.time(b)}` : '');
function showClashes() {
  $$('[data-clash]').forEach((el) => {
    const mine = clashes.filter((c) => c.day === Number(el.dataset.clash));
    el.innerHTML = mine.length ? `
      <div class="mt-3 flex gap-2.5 rounded-xl bg-peach-tint px-3 py-2.5 text-sm">
        ${icon('alert', 16, 'mt-0.5 shrink-0 text-peach-dark')}
        <div class="min-w-0">${mine.map((c) => `<div>Overlaps <b>${esc(c.code)}</b> ${esc(c.title)}${c.their_start ? `, ${esc(span(c.their_start, c.their_end))}` : ''}</div>`).join('')}</div>
      </div>` : '';
  });
  if (!clashes.length) $('#clash-confirm').hidden = true;
}

async function save(d, allowClash = false) {
  const res = await API.post('subjects.php', {
    action: editId ? 'update' : 'add', id: editId ? Number(editId) : undefined, ...d, color, design, slots: slotList(), allow_clash: allowClash || undefined,
  });
  location.href = `subject.html?id=${res.id}`;
}

UI.bindForm(form, async (d) => {
  const bad = slotList().find((s) => toMin(s.end) <= toMin(s.start));
  if (bad) throw new Error(`On ${DAYS_LONG[bad.day]}, the end time must be after the start time.`);
  try {
    await save(d);
  } catch (e) {
    if (e.status !== 409 || !e.data?.clashes) throw e;
    // The server found overlaps: show them and let the admin decide
    clashes = e.data.clashes;
    showClashes();
    const box = $('#clash-confirm');
    box.hidden = false;
    box.innerHTML = `
      <div class="font-semibold">${clashes.length === 1 ? 'One class time overlaps' : `${clashes.length} class times overlap`} another subject</div>
      <ul class="mt-1.5 space-y-1 text-sm">${clashes.map((c) => `<li>${esc(c.day_name)} ${esc(span(c.start, c.end))} overlaps <b>${esc(c.code)}</b>${c.their_start ? ` (${esc(span(c.their_start, c.their_end))})` : ''}</li>`).join('')}</ul>
      <p class="mt-2 text-xs text-muted">Change the times above, or save anyway if the classes really share a slot (e.g. a lab split between two groups).</p>
      <button type="button" data-save-anyway class="btn btn-light mt-3 h-11 w-full">Save anyway</button>`;
    $('[data-save-anyway]', box).onclick = async (ev) => {
      ev.target.disabled = true;
      try { await save(Object.fromEntries(new FormData(form)), true); } catch (err) { ev.target.disabled = false; UI.toast(err.message, { iconName: 'alert' }); }
    };
    box.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
});
