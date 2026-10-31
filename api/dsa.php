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

respond([
    'section' => present_section($ctx['section'], count($ctx['members'])),
    'array' => ['capacity' => $ctx['subjects']->capacity(), 'items' => $array],
    'linked_list' => ['items' => $list, 'expired_dropped' => $expired],
    'hashset' => ['buckets' => 13, 'size' => $set->size(), 'filled' => $set->buckets()],
    'stack' => $stack,
    'queue' => $queue,
    'tree' => $tree,
    'graph' => $adjacency,
]);
