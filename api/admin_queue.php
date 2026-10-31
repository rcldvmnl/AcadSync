<?php
/**
 * GET /api/admin_queue.php
 * Pending requests from the FIFO ApprovalQueue (front first), each with a
 * Linear Search duplicate check, plus the top of the UndoStack.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$admin = require_admin();
$ctx = load_context($admin);

$queue = build_queue($ctx['subjectIndex']);
$stack = build_undo_stack($ctx['subjectIndex']);

$taskById = [];
foreach ($ctx['tasks'] as $t) {
    $taskById[(int) $t['id']] = $t;
}

$items = [];
$position = 0;
foreach ($queue->toArray() as $q) {
    $position++;
    $item = present_task($q, $ctx['subjectIndex'], $ctx['users']);
    $item['position'] = $position;
    $item['similar'] = $q['kind'] === 'new'
        ? find_similar($ctx['tasks'], (int) $q['subject_id'], $q['title'] . ' ' . $q['description'], null, $ctx['subjectIndex'], $ctx['users'])
        : [];
    if ($q['kind'] === 'change' && isset($taskById[(int) $q['target_task_id']])) {
        $item['original'] = present_task($taskById[(int) $q['target_task_id']], $ctx['subjectIndex'], $ctx['users']);
    }
    $items[] = $item;
}

$top = $stack->peek();
respond([
    'queue' => $items,
    'size' => $queue->size(),
    'undo' => [
        'size' => $stack->size(),
        'top' => $top ? present_task($top, $ctx['subjectIndex'], $ctx['users']) : null,
    ],
]);
