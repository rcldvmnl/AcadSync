// "How AcadSync works" — live view of the 7 data structures for the open section.

(async () => {
  await Shell.init({ active: 'account', eyebrow: (m) => `${m.section.name} · live data`, title: 'How it works' });
  const d = await API.get('dsa.php');
  $('#main').innerHTML = `
    <p class="mb-5 max-w-2xl text-sm text-muted">Each card shows one data structure built from scratch in <code class="rounded bg-white px-1.5 py-0.5 text-charcoal">backend/custom_dsa.php</code>, filled with ${esc(d.section.name)}’s real records right now.</p>
    <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
      ${card(1, 'Array', 'Storing and accessing collections of records.', 'SubjectArray holds this section’s subject records in fixed, indexed slots. A second, 2D array (WeekGrid) is the week itself: 6 days × 288 five-minute cells. Saving a subject reads only the cells its class times cover, so a clash is found without comparing against every other subject.', arrayView(d.array) + weekGridView(d.week_grid))}
      ${card(2, 'Linked List', 'Managing sequences of records through insertion and deletion.', `TaskLinkedList keeps active tasks in date order: approved tasks are inserted in place (Insertion Sort); expired or removed ones are deleted by re-pointing <i>next</i>. It is doubly linked, so each task also points back (<i>prev</i>): that is how Task Details shows the previous and next task. ${d.linked_list.expired_dropped} expired task${d.linked_list.expired_dropped === 1 ? ' was' : 's were'} dropped from the head.`, listView(d.linked_list.items))}
      ${card(3, 'HashSet', 'Preventing duplicate entries or maintaining unique records.', `Each e-mail is hashed (djb2) into one of ${d.hashset.buckets} buckets, so checking “is this e-mail taken?” is O(1). The same set keeps student IDs, section names, class codes and subject codes unique.`, hashView(d.hashset) + paidSetView(d.paid_set))}
      ${card(4, 'Stack', 'Implementing undo operations, history, or backtracking.', 'UndoStack holds rejected requests; the admin’s Undo pops the most recent one back into the queue. Every student has their own stack too: leaving a group, cancelling a join request or ticking a task pushes its opposite, and the Undo button pops it.', stackView(d.stack) + undoMineView(d.undo_mine))}
      ${card(5, 'Queue', 'Managing waiting lists, service requests, or tasks in order.', 'ApprovalQueue lines up student requests first-come, first-served. The admin can only decide the front. The same queue holds people asking to join the section, and the volunteers for each part of a competition or project, in the order they signed up.', queueView(d.queue) + joinQueueView(d.join_queue) + volunteerQueueView(d.volunteer_queue))}
      ${card(6, 'Tree', 'Organizing hierarchical data or implementing tree-based searching.', 'SubjectTree organises Section › Subjects › Tasks. The search bar walks it depth-first. A task’s checklist is a tree too: steps hold sub-steps, and progress is added up post-order, children before their parent.', `<div class="max-h-80 overflow-y-auto rounded-2xl bg-canvas p-3 text-sm">${treeView(d.tree)}</div>${checklistView(d.checklist)}`)}
      ${card(7, 'Graph', 'Representing connections, routes, relationships, or networks.', 'DependencyGraph stores classmates as vertices and study-group links as edges (adjacency list). Breadth-first search finds your groupmates, the shortest chain linking you to someone, and who you’ve never worked with.', graphView(d.graph) + isolatedView(d.isolated))}
    </div>
    <h2 class="mb-3 mt-8 text-xl font-bold">Algorithms, measured on this section’s data</h2>
    <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
      ${algoCard('Sorting compared', 'Your notification history sorted newest first by all three. Each count is how many pairs of items were compared.', sortView(d.sorting))}
      ${algoCard('Binary Search on deadlines', '“Due by Friday” on the Schedule. The timeline is already sorted by deadline, so the search halves it to find where Friday 11:59 PM falls, instead of checking every task.', binaryView(d.binary_search))}
    </div>`;
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

function card(n, name, rubric, how, body) {
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card">
      <div class="flex items-center gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-charcoal text-sm font-bold text-white">${n}</span>
        <h2 class="text-xl font-bold">${name}</h2>
      </div>
      <p class="mt-2 text-sm font-medium">“${rubric}”</p>
      <p class="mt-1 text-xs text-muted">${how}</p>
      <div class="mt-4">${body}</div>
    </section>`;
}

const none = (text) => `<p class="rounded-2xl bg-canvas p-4 text-sm text-muted">${text}</p>`;

function algoCard(name, how, body) {
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card">
      <h3 class="text-lg font-bold">${name}</h3>
      <p class="mt-1 text-xs text-muted">${how}</p>
      <div class="mt-4">${body}</div>
    </section>`;
}

const TINT = { lavender: '#E6E1FD', peach: '#FCE3CF', yellow: '#FDF3C7', mint: '#E5F6EE' };

/** The 2D week grid, 7 AM to 9 PM: each filled run of cells is one class meeting. */
function weekGridView(g) {
  const from = 7 * 60 / g.step;
  const to = 21 * 60 / g.step;
  const pct = (c) => ((Math.min(Math.max(c, from), to) - from) / (to - from)) * 100;
  const rows = g.days.map((d) => `
    <div class="flex items-center gap-2">
      <span class="w-9 shrink-0 text-[11px] font-semibold text-muted">${esc(d.day.slice(0, 3))}</span>
      <div class="relative h-7 flex-1 rounded-lg bg-canvas">
        ${d.runs.map((r) => `<span class="absolute inset-y-0 flex items-center overflow-hidden rounded-md px-1 text-[10px] font-bold" style="left:${pct(r.from)}%;width:${pct(r.to + 1) - pct(r.from)}%;background:${TINT[r.color] || TINT.lavender}" title="${esc(r.code)}">${esc(r.code)}</span>`).join('')}
      </div>
    </div>`).join('');
  return `
    <div class="mt-4 rounded-2xl border border-line p-3">
      <div class="mb-2 text-xs font-semibold">WeekGrid, 7 AM – 9 PM</div>
      <div class="space-y-1.5">${rows}</div>
      <div class="ml-11 mt-1 flex justify-between text-[10px] text-muted"><span>7 AM</span><span>12 PM</span><span>5 PM</span><span>9 PM</span></div>
      <p class="mt-2 text-xs text-muted">${g.filled} of ${g.cells} cells taken</p>
    </div>`;
}

function undoMineView(items) {
  return `<div class="mt-4">
    <div class="mb-1.5 text-xs font-semibold">Your Undo stack</div>
    ${items.length ? `<div class="space-y-1.5">${items.map((l, i) => `<div class="flex items-center gap-2 rounded-xl ${i === 0 ? 'bg-charcoal text-white' : 'bg-canvas'} px-3 py-2 text-sm">
      <span class="w-7 text-[10px] font-semibold ${i === 0 ? 'text-sun' : 'text-muted'}">${i === 0 ? 'top' : ''}</span><span class="flex-1 truncate">${esc(l)}</span></div>`).join('')}</div>`
      : none('Empty. Leave a group, cancel a request or tick a task, and its Undo lands here.')}
  </div>`;
}

function checklistView(c) {
  if (!c) return `<div class="mt-4">${none('No checklists yet. Open a task and add steps to see a second tree here.')}</div>`;
  const node = (n, depth) => `<div style="padding-left:${depth ? 14 : 0}px" class="${depth ? 'border-l border-line' : ''}">
    <div class="flex justify-between gap-2 py-0.5"><span>${depth ? '└ ' : ''}${depth ? esc(n.title) : `<b>${esc(n.title)}</b>`}</span>
      <span class="shrink-0 text-xs ${n.total && n.done_count === n.total ? 'text-success' : 'text-muted'}">${n.done_count}/${n.total}</span></div>
    ${n.children.map((x) => node(x, depth + 1)).join('')}</div>`;
  return `<div class="mt-4"><div class="mb-1.5 text-xs font-semibold">Checklist tree: ${esc(c.task)}, ${c.done} of ${c.total} done</div>
    <div class="max-h-64 overflow-y-auto rounded-2xl bg-canvas p-3 text-sm">${node(c.tree, 0)}</div></div>`;
}

function isolatedView(list) {
  return `<p class="mt-3 text-xs text-muted">${list.length
    ? `No edges yet (degree 0): ${list.map(esc).join(', ')}.`
    : 'Everyone has at least one edge: each classmate has been in a group with someone.'}</p>`;
}

function sortView(s) {
  if (s.n < 2) return none('You need at least 2 notifications to compare sorting.');
  const max = Math.max(s.bubble, s.selection, s.merge, 1);
  const bar = (name, n, best) => `
    <div>
      <div class="flex justify-between text-sm"><span class="font-semibold">${name}</span><span>${plural(n, 'comparison')}</span></div>
      <div class="mt-1 h-3 rounded-full bg-canvas"><div class="h-full rounded-full ${best ? 'bg-success' : 'bg-charcoal'}" style="width:${Math.max(2, (n / max) * 100)}%"></div></div>
    </div>`;
  const best = Math.min(s.bubble, s.selection, s.merge);
  return `<div class="space-y-3">
    ${bar('Bubble Sort', s.bubble, s.bubble === best)}
    ${bar('Selection Sort', s.selection, s.selection === best)}
    ${bar('Merge Sort', s.merge, s.merge === best)}
  </div>
  <p class="mt-3 text-xs text-muted">${plural(s.n, 'notification')} sorted. ${s.same ? 'All three give the same order.' : 'The orders differ.'} Merge Sort grows like n log n; the other two grow like n², so the gap widens as the history gets longer.</p>`;
}

function binaryView(b) {
  const day = Fmt.short(b.date);
  return `<div class="grid grid-cols-2 gap-3">
      <div class="rounded-2xl bg-mint-tint p-4"><div class="text-3xl font-extrabold">${b.steps}</div><div class="text-xs text-muted">checks by Binary Search</div></div>
      <div class="rounded-2xl bg-canvas p-4"><div class="text-3xl font-extrabold">${b.searched}</div><div class="text-xs text-muted">checks scanning one by one (worst case)</div></div>
    </div>
    <p class="mt-3 text-sm">${plural(b.found, 'task')} due by ${esc(day)}, 11:59 PM.</p>`;
}

function arrayView(a) {
  if (!a.items.length) return none('No subjects stored yet.');
  return `<div class="grid grid-cols-4 gap-1.5 sm:grid-cols-8">
    ${Array.from({ length: a.capacity }, (_, i) => {
      const s = a.items[i];
      return `<div class="rounded-xl ${s ? 'bg-lavender-tint' : 'border border-dashed border-line'} p-2 text-center">
        <div class="text-[10px] text-muted">[${i}]</div><div class="truncate text-[11px] font-semibold">${s ? esc(s.code) : '—'}</div></div>`;
    }).join('')}
  </div><p class="mt-2 text-xs text-muted">${a.items.length} of ${a.capacity} slots used</p>`;
}

function listView(items) {
  if (!items.length) return none('No active tasks — the list is empty (head = null).');
  return `<div class="flex flex-wrap items-center gap-1.5 text-xs">
    <span class="font-semibold text-muted">head →</span>
    ${items.map((t) => `<span class="rounded-xl bg-peach-tint px-2.5 py-1.5"><b>${esc(t.title)}</b><br><span class="text-muted">${t.deadline ? esc(Fmt.monthDay(t.deadline)) : 'no date'} · ${esc(t.subject)}</span></span><span class="text-muted">→</span>`).join('')}
    <span class="font-semibold text-muted">null</span>
  </div>`;
}

function hashView(h) {
  const rows = Object.entries(h.filled);
  if (!rows.length) return none('The set is empty.');
  return `<div class="space-y-1.5 text-xs">
    ${rows.map(([i, keys]) => `<div class="flex items-start gap-2"><span class="w-12 shrink-0 rounded-lg bg-charcoal py-1 text-center font-mono text-white">${i}</span>
      <span class="flex flex-wrap gap-1">${keys.map((k) => `<span class="rounded-lg bg-mint-tint px-2 py-1 font-mono">${esc(k)}</span>`).join('')}</span></div>`).join('')}
  </div><p class="mt-2 text-xs text-muted">${h.size} unique e-mails · a bucket with 2+ entries is a collision handled by chaining</p>`;
}

function stackView(items) {
  if (!items.length) return none('Empty — nothing to undo.');
  return `<div class="space-y-1.5">${items.map((t, i) => `
    <div class="flex items-center gap-2 rounded-xl ${i === 0 ? 'bg-charcoal text-white' : 'bg-canvas'} px-3 py-2 text-sm">
      <span class="text-[10px] font-semibold uppercase ${i === 0 ? 'text-sun' : 'text-muted'}">${i === 0 ? 'top' : ''}</span>
      <span class="flex-1 truncate">${esc(t.title)}</span><span class="text-xs opacity-70">${esc(t.subject)}</span></div>`).join('')}</div>`;
}

function queueView(items) {
  if (!items.length) return none('Empty — no requests waiting.');
  return `<div class="flex flex-wrap items-center gap-1.5 text-xs">
    <span class="font-semibold text-muted">front →</span>
    ${items.map((t, i) => `<span class="rounded-xl ${i === 0 ? 'bg-charcoal text-white' : 'bg-sun-tint'} px-2.5 py-1.5"><b>#${i + 1} ${esc(t.title)}</b><br><span class="opacity-70">${esc(t.by)}</span></span>`).join('')}
    <span class="font-semibold text-muted">← rear</span>
  </div>`;
}

/** A small front → rear strip of names; members (no names) see only how many are waiting. */
function namesQueue(title, size, items, emptyText, note = '') {
  const body = !size ? none(emptyText)
    : items ? `<div class="flex flex-wrap items-center gap-1.5 text-xs">
        <span class="font-semibold text-muted">front →</span>
        ${items.map((n, i) => `<span class="rounded-xl ${i === 0 ? 'bg-charcoal text-white' : 'bg-sun-tint'} px-2.5 py-1.5 font-semibold">#${i + 1} ${esc(n)}</span>`).join('')}
        <span class="font-semibold text-muted">← rear</span></div>`
      : `<p class="rounded-2xl bg-canvas p-3 text-sm">${plural(size, 'person', 'people')} waiting. Officers see the names.</p>`;
  return `<div class="mt-4"><div class="mb-1.5 text-xs font-semibold">${title}</div>${body}${note ? `<p class="mt-1.5 text-xs text-muted">${note}</p>` : ''}</div>`;
}

function joinQueueView(q) {
  return namesQueue('Waiting to join the section', q.size, q.items, 'Nobody is waiting to join.');
}

function volunteerQueueView(q) {
  if (!q.size) return namesQueue('Volunteers for a part', 0, null, 'No open part has volunteers yet.');
  return namesQueue(`Volunteers for “${esc(q.part)}” (${esc(q.activity)})`, q.size, q.items, '',
    q.my_position ? `You are #${q.my_position} in this queue.` : 'Officers pick from the front.');
}

/** Who paid the soonest due in full: a HashSet, so "did this person pay?" is one O(1) lookup. */
function paidSetView(p) {
  if (!p) return `<div class="mt-4">${none('No open dues, so the “paid in full” set is empty.')}</div>`;
  const buckets = p.buckets ? `<div class="mt-2 space-y-1.5 text-xs">${Object.entries(p.buckets).map(([i, keys]) => `<div class="flex items-start gap-2">
      <span class="w-12 shrink-0 rounded-lg bg-charcoal py-1 text-center font-mono text-white">${i}</span>
      <span class="flex flex-wrap gap-1">${keys.map((k) => `<span class="rounded-lg bg-mint-tint px-2 py-1">${esc(k)}</span>`).join('')}</span></div>`).join('')}</div>` : '';
  return `<div class="mt-4">
    <div class="mb-1.5 text-xs font-semibold">Paid “${esc(p.due)}” in full</div>
    <p class="rounded-2xl bg-canvas p-3 text-sm">${p.size} of ${plural(p.charged, 'member')} · ${p.me ? 'you’re in the set' : 'you’re not in the set yet'}</p>
    ${buckets}
  </div>`;
}

function treeView(node, depth = 0) {
  const label = depth === 0 ? `<b>${esc(node.label)}</b>` : depth === 1 ? `<span class="font-semibold">${esc(node.label)}</span> <span class="text-muted">${esc(node.data.title || '')}</span>` : esc(node.label);
  return `<div style="padding-left:${depth ? 14 : 0}px" class="${depth ? 'border-l border-line' : ''}">
    <div class="py-0.5">${depth ? '└ ' : ''}${label}</div>
    ${node.children.map((c) => treeView(c, depth + 1)).join('')}
  </div>`;
}

function graphView(g) {
  const linked = g.filter((v) => v.edges.length);
  if (!linked.length) return none('No study groups yet — no edges.');
  return `<div class="space-y-2 text-sm">${linked.map((v) => `
    <div class="rounded-xl bg-canvas p-2.5"><b>${esc(v.student)}</b>
      <div class="mt-1 flex flex-wrap gap-1">${v.edges.map((e) => `<span class="chip bg-white text-[11px]">→ ${esc(e.to)} · ${esc(e.group)}</span>`).join('')}</div></div>`).join('')}</div>`;
}
