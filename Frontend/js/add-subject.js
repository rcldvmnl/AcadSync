// Add Subject (admin) — adds to the curriculum Array plus its weekly class slots.

const form = $('#subject-form');
const chosenDays = new Set([1, 3]);
let color = 'lavender';
const SWATCH = { lavender: 'bg-lavender', peach: 'bg-peach', yellow: 'bg-sun', mint: 'bg-mint' };
const LENGTHS = [[60, '1 hr'], [90, '1.5 hrs'], [120, '2 hrs'], [180, '3 hrs']];

$$('[data-icon]').forEach((el) => { el.outerHTML = icon(el.dataset.icon, 18); });

(async () => {
  const me = await Shell.init({ active: 'home', eyebrow: (m) => `Admin · ${m.section.name}`, title: 'Add Subject' });
  if (me.user.role !== 'admin') {
    $('#main').innerHTML = UI.errorBox('Only the class admin can add subjects.');
  }
})().catch(() => {});

function renderDays() {
  $('#days').innerHTML = [1, 2, 3, 4, 5, 6].map((d) => `
    <button type="button" data-day="${d}" aria-pressed="${chosenDays.has(d)}" class="aspect-square rounded-full text-[11px] font-semibold uppercase ${chosenDays.has(d) ? 'bg-charcoal text-white' : 'bg-canvas text-muted'}">${DAYS[d]}</button>`).join('');
  $$('[data-day]').forEach((b) => b.onclick = () => {
    const d = Number(b.dataset.day);
    chosenDays.has(d) ? chosenDays.delete(d) : chosenDays.add(d);
    renderDays();
  });
}

function renderColors() {
  $('#colors').innerHTML = Object.entries(SWATCH).map(([k, cls]) => `
    <button type="button" data-color="${k}" aria-label="${k}" aria-pressed="${k === color}" class="h-10 w-10 rounded-full ${cls} ${k === color ? 'ring-2 ring-charcoal ring-offset-2' : ''}"></button>`).join('');
  $$('[data-color]').forEach((b) => b.onclick = () => { color = b.dataset.color; renderColors(); });
}

function minutes(hhmm) { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; }
function renderLengths() {
  const len = minutes(form.elements.end.value || '00:00') - minutes(form.elements.start.value || '00:00');
  $('#lengths').innerHTML = LENGTHS.map(([m, l]) => `<button type="button" data-len="${m}" class="rounded-full px-4 py-2.5 text-sm font-medium ${m === len ? 'bg-charcoal text-white' : 'bg-canvas'}">${l}</button>`).join('');
  $$('[data-len]').forEach((b) => b.onclick = () => {
    const end = minutes(form.elements.start.value) + Number(b.dataset.len);
    form.elements.end.value = `${String(Math.floor(end / 60) % 24).padStart(2, '0')}:${String(end % 60).padStart(2, '0')}`;
    renderLengths();
  });
}
form.elements.start.addEventListener('change', renderLengths);
form.elements.end.addEventListener('change', renderLengths);

renderDays();
renderColors();
renderLengths();

UI.bindForm(form, async (d) => {
  const { id } = await API.post('subjects.php', {
    action: 'add', ...d, color, days: [...chosenDays].sort(), start: d.start, end: d.end,
  });
  location.href = `subject.html?id=${id}`;
});
