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
$sectionId = (int) $ctx['section']['id'];

// [DSA: QUEUE] — waiting list of requests, front = oldest
$queue = build_queue($sectionId, $ctx['taskIndex']);
// [DSA: STACK] — rejected requests, top = most recent
$stack = build_undo_stack($sectionId, $ctx['taskIndex']);

$items = [];
$position = 0;
foreach ($queue->toArray() as $q) {
    $position++;
    $item = present_task($q, $ctx['subjectIndex'], $ctx['users']);
    $item['position'] = $position;
    // [ALGORITHM: LINEAR SEARCH] — compare with existing tasks of the same subject
    $item['similar'] = $q['kind'] === 'new'
        ? find_similar($ctx['tasks'], (int) $q['subject_id'], $q['title'] . ' ' . $q['description'], null, $ctx['subjectIndex'], $ctx['users'])
        : [];
    $original = $ctx['taskIndex'][(int) $q['target_task_id']] ?? null;
    if ($q['kind'] === 'change' && $original) {
        $item['original'] = present_task($original, $ctx['subjectIndex'], $ctx['users']);
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
