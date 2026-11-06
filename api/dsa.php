<?php
/**
 * GET /api/dsa.php
 * A read-only snapshot of all 7 data structures for the open section, so the
 * "How it works" page can show them live during system checking.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$now = time();
$name = fn (int $id) => isset($ctx['users'][$id]) ? $ctx['users'][$id]['first_name'] . ' ' . $ctx['users'][$id]['last_name'] : "#$id";

// 1. ARRAY
$array = [];
for ($i = 0; $i < $ctx['subjects']->size(); $i++) {
    $s = $ctx['subjects']->get($i);
    $array[] = ['index' => $i, 'code' => $s['code'], 'title' => $s['title']];
}

// 2. LINKED LIST (expired tasks already dropped from the head)
$timeline = build_timeline($ctx['tasks'], $now);
$expired = 0;
foreach ($ctx['tasks'] as $t) {
    $ts = DsaUtil::deadlineTs($t);
    if ($t['status'] === 'approved' && $t['kind'] === 'new' && $ts !== null && $ts < $now) {
        $expired++;
    }
}
$list = [];
foreach ($timeline->toArray() as $t) {
    $list[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'subject' => $ctx['subjectIndex'][(int) $t['subject_id']]['code'] ?? '', 'deadline' => $t['deadline_end'] ?: $t['deadline'], 'time' => $t['deadline_time']];
}

// 3. HASHSET (members' e-mails, as stored in their buckets)
$set = new StudentHashSet(13);   // small bucket count so collisions are visible
foreach ($ctx['members'] as $m) {
    $set->addEmail($m['email']);
}

// 4. STACK / 5. QUEUE
$present = fn (array $t) => ['id' => (int) $t['id'], 'title' => $t['title'], 'subject' => $ctx['subjectIndex'][(int) $t['subject_id']]['code'] ?? '', 'by' => $name((int) $t['requested_by'])];
$stack = array_map($present, build_undo_stack($sectionId, $ctx['taskIndex'])->toArray());
$queue = array_map($present, build_queue($sectionId, $ctx['taskIndex'])->toArray());

// 6. TREE
$tree = build_tree($ctx['section'], $ctx['subjects'], $timeline)->toArray();

// 7. GRAPH
$graph = build_graph($sectionId, $ctx['members']);
$adjacency = [];
foreach ($graph->adjacencyList() as $id => $edges) {
    $links = [];
    foreach ($edges as $e) {
        $links[] = ['to' => $name($e['to']), 'group' => $e['context']];
    }
    $adjacency[] = ['student' => $name($id), 'edges' => $links];
}

// 1b. 2D ARRAY — the week grid used by the clash checker
$grid = new WeekGrid();
$slots = class_slots($sectionId, $ctx['subjectIndex']);
foreach ($slots as $s) {
    $grid->place($s['day_of_week'], $s['start_time'], $s['end_time'], $s['subject_id']);
}
$gridDays = [];
for ($d = 1; $d <= WeekGrid::DAYS; $d++) {
    $runs = [];
    foreach ($grid->runs($d) as $r) {
        $runs[] = $r + ['code' => $ctx['subjectIndex'][$r['subject']]['code'] ?? '?', 'color' => $ctx['subjectIndex'][$r['subject']]['color_tag'] ?? 'lavender'];
    }
    $gridDays[] = ['day' => DAY_NAMES[$d], 'runs' => $runs];
}

// 4b. STACK — this user's own Undo history (top first)
$mineUndo = [];
foreach (undo_stack($sectionId)->toArray() as $e) {
    $mineUndo[] = $e['label'];
}

// 6b. TREE — your biggest checklist, with progress rolled up post-order
$myGroupIds = [];
foreach (load_groups($sectionId) as $g) {
    if (in_array((int) $user['id'], $g['members'], true)) {
        $myGroupIds[$g['id']] = true;
    }
}
$byTask = [];
foreach (section_rows('subtasks', $sectionId) as $r) {
    $visible = ($r['group_id'] !== null && isset($myGroupIds[(int) $r['group_id']])) || ($r['group_id'] === null && (int) $r['owner_id'] === (int) $user['id']);
    if ($visible) {
        $byTask[(int) $r['task_id']][] = $r;
    }
}
$checklist = null;
foreach ($byTask as $tid => $rows) {
    if ($checklist === null || count($rows) > $checklist['count']) {
        $checkTree = ChecklistTree::fromRows($ctx['taskIndex'][$tid]['title'] ?? 'Task', $rows);   // not $tree: that is the subject tree
        [$done, $total] = $checkTree->rollup();
        $checklist = ['count' => count($rows), 'task' => $ctx['taskIndex'][$tid]['title'] ?? 'Task', 'done' => $done, 'total' => $total, 'tree' => $checkTree->toArray()];
    }
}

// 7b. GRAPH — classmates with no edges (never grouped with anyone)
$isolated = [];
foreach ($ctx['members'] as $m) {
    if ($graph->degree((int) $m['id']) === 0) {
        $isolated[] = $m['first_name'] . ' ' . $m['last_name'];
    }
}

// ALGORITHMS — Bubble vs Selection vs Merge Sort on the same list: your notification history, newest first
$history = [];
foreach (Store::where('notifications', 'user_id', (int) $user['id']) as $n) {
    $history[] = ['k' => (int) strtotime((string) $n['created_at']) * 1000000 + (int) $n['id']];
}
$sb = 0;
$ss = 0;
$sm = 0;
$b = Algorithms::bubbleSortBy($history, 'k', true, $sb);
$s = Algorithms::selectionSortBy($history, 'k', true, $ss);
$m = Algorithms::mergeSortBy($history, 'k', true, $sm);
$sameResult = true;
for ($i = 0; $i < count($history); $i++) {
    $sameResult = $sameResult && $b[$i]['k'] === $m[$i]['k'] && $s[$i]['k'] === $m[$i]['k'];
}

// ALGORITHMS — Binary Search on deadlines ("Due by Friday")
$friday = strtotime(date('Y-m-d', strtotime((int) date('N', $now) >= 6 ? 'friday next week' : 'friday this week', $now)) . ' 23:59:59');
$active = $timeline->toArray();
$bsSteps = 0;
$dueCount = Algorithms::countDueBy($active, $friday, $bsSteps);

respond([
    'week_grid' => ['days' => $gridDays, 'filled' => $grid->filledCells(), 'cells' => WeekGrid::DAYS * WeekGrid::CELLS, 'step' => WeekGrid::STEP],
    'undo_mine' => $mineUndo,
    'checklist' => $checklist,
    'isolated' => $isolated,
    'sorting' => ['n' => count($history), 'bubble' => $sb, 'selection' => $ss, 'merge' => $sm, 'same' => $sameResult],
    'binary_search' => ['date' => date('Y-m-d', $friday), 'searched' => count($active), 'steps' => $bsSteps, 'found' => $dueCount],
    'section' => present_section($ctx['section'], count($ctx['members'])),
    'array' => ['capacity' => $ctx['subjects']->capacity(), 'items' => $array],
    'linked_list' => ['items' => $list, 'expired_dropped' => $expired],
    'hashset' => ['buckets' => 13, 'size' => $set->size(), 'filled' => $set->buckets()],
    'stack' => $stack,
    'queue' => $queue,
    'tree' => $tree,
    'graph' => $adjacency,
]);
