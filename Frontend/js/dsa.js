// "How AcadSync works" — live view of the 7 data structures for the open section.

(async () => {
  await Shell.init({ active: 'account', eyebrow: (m) => `${m.section.name} · live data`, title: 'How it works' });
  const d = await API.get('dsa.php');
  $('#main').innerHTML = `
    <p class="mb-5 max-w-2xl text-sm text-muted">Each card shows one data structure built from scratch in <code class="rounded bg-white px-1.5 py-0.5 text-charcoal">backend/custom_dsa.php</code>, filled with ${esc(d.section.name)}’s real records right now.</p>
    <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
      ${card(1, 'Array', 'Storing and accessing collections of records.', 'SubjectArray holds this section’s subject records in fixed, indexed slots.', arrayView(d.array))}
      ${card(2, 'Linked List', 'Managing sequences of records through insertion and deletion.', `TaskLinkedList keeps active tasks in date order: approved tasks are inserted in place (Insertion Sort); expired or removed ones are deleted by re-pointing <i>next</i>. ${d.linked_list.expired_dropped} expired task${d.linked_list.expired_dropped === 1 ? ' was' : 's were'} dropped from the head.`, listView(d.linked_list.items))}
      ${card(3, 'HashSet', 'Preventing duplicate entries or maintaining unique records.', `Each e-mail is hashed (djb2) into one of ${d.hashset.buckets} buckets, so checking “is this e-mail taken?” is O(1). The same set keeps student IDs, section names, class codes and subject codes unique.`, hashView(d.hashset))}
      ${card(4, 'Stack', 'Implementing undo operations, history, or backtracking.', 'UndoStack holds rejected requests. Undo pops the top (most recent) one back into the queue.', stackView(d.stack))}
      ${card(5, 'Queue', 'Managing waiting lists, service requests, or tasks in order.', 'ApprovalQueue lines up student requests first-come, first-served. The admin can only decide the front.', queueView(d.queue))}
      ${card(6, 'Tree', 'Organizing hierarchical data or implementing tree-based searching.', 'SubjectTree organises Section › Subjects › Tasks. The search bar walks it depth-first.', `<div class="max-h-80 overflow-y-auto rounded-2xl bg-canvas p-3 text-sm">${treeView(d.tree)}</div>`)}
      ${card(7, 'Graph', 'Representing connections, routes, relationships, or networks.', 'DependencyGraph stores classmates as vertices and study-group links as edges (adjacency list). Group filters use breadth-first search.', graphView(d.graph))}
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
