// Task checklist: items with sub-items (a TREE on the server, api/subtasks.php).
// A group task's checklist is shared by the group; otherwise it is your own.
// Progress is rolled up post-order on the server, so a parent shows how many of its sub-items are done.

const Checklist = {
  task: null,
  data: null,
  box: null,
  adding: null,   // id of the item an inline "add sub-item" field is open under

  async mount(box, task) {
    if (!box) return;
    Checklist.box = box;
    Checklist.task = task;
    box.innerHTML = `<div class="cl-card"><h3 class="text-lg font-semibold">Checklist</h3>${UI.skeleton(2, 'h-10 mt-3')}</div>`;
    try {
      Checklist.data = await API.get('subtasks.php', { task_id: task.id });
      Checklist.render();
    } catch (e) {
      box.innerHTML = `<div class="cl-card"><h3 class="text-lg font-semibold">Checklist</h3><p class="mt-2 text-sm">Couldn’t load the checklist. ${esc(e.message)}</p></div>`;
    }
  },

  async post(body) {
    try {
      Checklist.data = await API.post('subtasks.php', { task_id: Checklist.task.id, ...body });
      Checklist.render();
      return true;
    } catch (e) {
      UI.toast(e.message, { iconName: 'alert' });
      return false;
    }
  },

  render() {
    const d = Checklist.data;
    const pct = d.total ? Math.round((d.done / d.total) * 100) : 0;
    const scope = d.scope === 'group'
      ? `Shared with ${esc(d.group.name)}. Everyone in it can tick and add items.`
      : Checklist.task.work_mode === 'group' ? 'Only you see this until you join a group.' : 'Only you see this list.';
    Checklist.box.innerHTML = `
      <section class="cl-card" aria-labelledby="cl-h">
        <div class="flex items-baseline justify-between gap-3">
          <h3 id="cl-h" class="text-lg font-semibold">Checklist</h3>
          ${d.total ? `<span class="text-sm font-semibold">${d.done} of ${d.total} done</span>` : ''}
        </div>
        <p class="mt-0.5 text-xs text-muted">${scope}</p>
        ${d.total ? `<div class="cl-bar mt-3" role="progressbar" aria-valuemin="0" aria-valuemax="${d.total}" aria-valuenow="${d.done}" aria-label="Checklist progress"><span style="width:${pct}%"></span></div>` : ''}
        ${d.tree.length ? `<ul class="cl-list mt-3">${d.tree.map((n) => Checklist.item(n, 1)).join('')}</ul>`
          : '<p class="mt-3 rounded-2xl bg-canvas p-4 text-sm text-muted">Break the work into steps. Add a step, then add sub-steps under it, e.g. Chapter 1 › Introduction.</p>'}
        <form class="cl-add mt-3" data-add-root>
          <input name="title" maxlength="120" placeholder="Add a step" aria-label="New checklist step" autocomplete="off">
          <button type="submit" class="btn btn-dark h-10 px-4 text-sm">Add</button>
        </form>
      </section>`;
    Checklist.bind();
  },

  item(n, depth) {
    const d = Checklist.data;
    const parent = n.children.length > 0;
    return `
      <li class="cl-item ${n.done ? 'is-done' : ''}">
        <div class="cl-row">
          <button type="button" data-toggle="${n.id}" class="cl-check" aria-pressed="${n.done}" aria-label="${n.done ? 'Untick' : 'Tick'} ${esc(n.title)}${parent ? ' and its sub-steps' : ''}">${n.done ? icon('check', 14) : ''}</button>
          <span class="cl-title">${esc(n.title)}</span>
          ${parent ? `<span class="cl-count">${n.done_count}/${n.total}</span>` : ''}
          ${d.scope === 'group' ? `
            <label class="cl-who"><span class="sr-only">Who does “${esc(n.title)}”</span>
              <select data-assign="${n.id}">
                <option value="">Anyone</option>
                ${d.members.map((m) => `<option value="${m.id}" ${m.id === n.assignee_id ? 'selected' : ''}>${esc(m.name.split(' ')[0])}</option>`).join('')}
              </select></label>` : ''}
          ${depth < 3 ? `<button type="button" data-sub="${n.id}" class="cl-icon" aria-label="Add a sub-step under ${esc(n.title)}" title="Add a sub-step">${icon('plus', 16)}</button>` : ''}
          <button type="button" data-del="${n.id}" class="cl-icon" aria-label="Delete ${esc(n.title)}${parent ? ' and its sub-steps' : ''}" title="Delete">${icon('x', 16)}</button>
        </div>
        ${parent || Checklist.adding === n.id ? `<ul class="cl-list cl-sub">
          ${n.children.map((c) => Checklist.item(c, depth + 1)).join('')}
          ${Checklist.adding === n.id ? `<li><form class="cl-add" data-add-under="${n.id}">
            <input name="title" maxlength="120" placeholder="Sub-step of ${esc(n.title)}" aria-label="New sub-step under ${esc(n.title)}" autocomplete="off">
            <button type="submit" class="btn btn-dark h-9 px-3 text-sm">Add</button>
            <button type="button" data-cancel-sub class="btn btn-canvas h-9 px-3 text-sm">Cancel</button></form></li>` : ''}
        </ul>` : ''}
      </li>`;
  },

  bind() {
    const box = Checklist.box;
    $$('[data-toggle]', box).forEach((b) => b.onclick = () => Checklist.post({ action: 'toggle', id: Number(b.dataset.toggle) }));
    $$('[data-assign]', box).forEach((s) => s.onchange = () => Checklist.post({ action: 'assign', id: Number(s.dataset.assign), assignee_id: s.value ? Number(s.value) : null }));
    $$('[data-sub]', box).forEach((b) => b.onclick = () => { Checklist.adding = Number(b.dataset.sub); Checklist.render(); $('[data-add-under] input', box)?.focus(); });
    $('[data-cancel-sub]', box)?.addEventListener('click', () => { Checklist.adding = null; Checklist.render(); });
    $$('[data-del]', box).forEach((b) => b.onclick = () => Checklist.post({ action: 'delete', id: Number(b.dataset.del) }));
    $$('form.cl-add', box).forEach((f) => f.onsubmit = async (e) => {
      e.preventDefault();
      const title = f.elements.title.value.trim();
      if (!title) { f.elements.title.focus(); return; }
      const parentId = f.dataset.addUnder ? Number(f.dataset.addUnder) : undefined;
      if (await Checklist.post({ action: 'add', title, parent_id: parentId })) {
        Checklist.adding = null;
        Checklist.render();
        (parentId ? $(`[data-sub="${parentId}"]`, box) : $('[data-add-root] input', box))?.focus();
      }
    });
  },
};
