// Global search: Tree-based search over Section › Subjects › Tasks + Linear Search of classmates.

const form = $('#search');
const input = form.elements.q;
$$('[data-icon]').forEach((el) => { el.outerHTML = icon(el.dataset.icon, 18, 'text-muted'); });
input.value = new URLSearchParams(location.search).get('q') || '';

(async () => {
  await Shell.init({ active: 'home', eyebrow: (m) => m.section.name, title: 'Search', search: false });
  input.focus();
  run();
})().catch(() => {});

let timer;
input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 250); });
form.addEventListener('submit', (e) => { e.preventDefault(); run(); });

async function run() {
  const q = input.value.trim();
  history.replaceState(null, '', q ? `?q=${encodeURIComponent(q)}` : location.pathname);
  if (q.length < 2) {
    $('#results').innerHTML = '<p class="px-2 text-sm text-muted">Type at least 2 letters.</p>';
    return;
  }
  try {
    const r = await API.get('search.php', { q });
    if (input.value.trim() !== q) return;
    const path = (p) => `<div class="mt-0.5 truncate text-[11px] text-muted">${p.map(esc).join(' › ')}</div>`;
    const blocks = [];
    if (r.tasks.length) {
      blocks.push(`<section><h2 class="mb-2 text-lg font-bold">Tasks <span class="text-sm font-medium text-muted">${r.tasks.length}</span></h2>
        <div class="space-y-3">${r.tasks.map((t) => `<div>${UI.taskCard(t)}<div class="px-4">${path(t.path)}</div></div>`).join('')}</div></section>`);
    }
    if (r.subjects.length) {
      blocks.push(`<section><h2 class="mb-2 text-lg font-bold">Subjects <span class="text-sm font-medium text-muted">${r.subjects.length}</span></h2>
        <div class="space-y-2">${r.subjects.map((s) => `
          <a href="subject.html?id=${s.id}" class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-card">
            <span class="h-10 w-1 rounded-full" style="background:${tone(s.color).bar}"></span>
            <span class="min-w-0 flex-1"><span class="block font-semibold">${esc(s.code)} · ${esc(s.title)}</span>${path(s.path)}</span>
            ${icon('chevronRight', 18, 'text-muted')}
          </a>`).join('')}</div></section>`);
    }
    if (r.classmates.length) {
      blocks.push(`<section><h2 class="mb-2 text-lg font-bold">Classmates <span class="text-sm font-medium text-muted">${r.classmates.length}</span></h2>
        <div class="divide-y divide-line rounded-[1.5rem] bg-white px-4 shadow-card">${r.classmates.map((m) => `
          <a href="classmates.html?q=${encodeURIComponent(m.last_name)}" class="flex items-center gap-3 py-3">
            ${UI.avatar(m.name, 'bg-lavender-tint', 'h-10 w-10 text-xs')}
            <span class="flex-1"><span class="block font-semibold">${esc(m.last_name)}, ${esc(m.first_name)}</span><span class="block text-xs text-muted">${esc(m.student_id)}</span></span>
          </a>`).join('')}</div></section>`);
    }
    $('#results').innerHTML = blocks.join('') || UI.empty('Nothing found', `No task, subject or classmate matches “${q}”.`, 'search');
  } catch (e) {
    $('#results').innerHTML = UI.errorBox(e);
  }
}
