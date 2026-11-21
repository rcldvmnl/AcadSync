// Subject Details — schedule, room, instructor and the subject's upcoming tasks.

const subjectId = new URLSearchParams(location.search).get('id');

(async () => {
  const d = await API.get('subjects.php', { id: subjectId });
  const admin = d.role === 'admin';
  await Shell.init({
    active: 'schedule',
    eyebrow: (me) => `${admin ? 'Admin' : me.section.name} › ${d.subject.code}`,
    title: 'Subject Details',
  });
  document.title = `AcadSync · ${d.subject.code}`;
  $('#main').innerHTML = render(d, admin);
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

/** "Mon & Thu · 1:00 – 2:30 PM" */
function scheduleLine(slots) {
  if (!slots.length) return 'No class times yet';
  const groups = {};
  slots.forEach((s) => { (groups[`${s.start_time}-${s.end_time}`] ||= []).push(s.day_of_week); });
  return Object.entries(groups).map(([k, days]) => {
    const [a, b] = k.split('-').map(Fmt.time);
    const from = a.slice(-2) === b.slice(-2) ? a.slice(0, -3) : a;   // "1:00 – 2:30 PM"
    return `${days.map((x) => DAYS[x]).join(' & ')} · ${from} – ${b}`;
  }).join(' · ');
}

function render(d, admin) {
  const s = d.subject;
  const strong = tone(s.color_tag).strong;
  return `
    <div class="grid gap-5 lg:grid-cols-[1.1fr_1fr] lg:items-start lg:gap-6">
      <div class="space-y-4">
        <div class="card-pastel ${strong} rounded-[1.75rem] p-5 lg:p-7">
          <span class="ring-deco" style="width:200px;height:200px;right:-20px;bottom:-50px"></span>
          <div class="flex items-start justify-between">
            ${UI.chip(s.code, 'bg-white text-sm')}
            ${d.tasks.length ? `<span class="chip bg-white text-[11px]">${icon('clock', 12)}${d.tasks.length} due<span class="h-1.5 w-1.5 rounded-full bg-danger"></span></span>` : ''}
          </div>
          <h2 class="mt-3 text-[28px] font-extrabold leading-tight tracking-tight lg:text-4xl">${esc(s.title)}</h2>
          <ul class="mt-3 space-y-1.5 text-[15px]">
            <li class="flex items-center gap-2">${icon('clock', 18)}${esc(scheduleLine(d.slots))}</li>
            ${s.room ? `<li class="flex items-center gap-2">${icon('mapPin', 18)}${esc(s.room)}</li>` : ''}
            ${s.instructor ? `<li class="flex items-center gap-2">${icon('user', 18)}${esc(s.instructor)}</li>` : ''}
          </ul>
          ${admin ? `<div class="mt-4 grid grid-cols-2 gap-3">
            <a href="add-subject.html?id=${s.id}" class="btn btn-light">Edit subject</a>
            <a href="task-form.html?subject=${s.id}" class="btn btn-dark">Add task</a>
          </div>` : ''}
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="relative overflow-hidden rounded-[1.75rem] bg-white p-4 shadow-card">
            <div class="relative text-4xl font-extrabold">${d.tasks.length}</div>
            <div class="relative mt-1 text-xs text-muted">Upcoming tasks</div>
          </div>
          <a href="${admin ? 'queue.html' : 'task-form.html?subject=' + s.id}" class="card-pastel yellow rounded-[1.75rem] p-4">
            <div class="relative text-4xl font-extrabold">${d.pending}</div>
            <div class="relative mt-1 text-xs">${admin ? 'Request' : 'Pending request'}${d.pending === 1 ? '' : 's'}${admin ? ' to review' : ''}</div>
          </a>
        </div>
      </div>
      <section>
        <div class="mb-3 flex items-baseline justify-between">
          <h2 class="text-xl font-bold">Upcoming tasks</h2>
          <span class="text-xs text-muted">Sorted by due date</span>
        </div>
        <div class="space-y-3">${d.tasks.length ? d.tasks.map((t) => UI.taskCard(t)).join('') : UI.empty('All clear', 'No upcoming tasks for this subject.')}</div>
      </section>
    </div>`;
}
