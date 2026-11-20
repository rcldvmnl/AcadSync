<?php
/**
 * POST /api/admin_undo.php
 * POPs the most recently rejected request off the UndoStack and ENQUEUEs it
 * back into the Approval Queue (it joins the rear of the line).
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

require_method('POST');
$admin = require_permission(Permission::REVIEW_REQUESTS, 'Only officers (President, Vice President, Secretary, Treasurer) can review requests.');
$ctx = load_context($admin);
$sectionId = (int) $ctx['section']['id'];

// [DSA: STACK] — undo = POP the latest rejection (LIFO)
$stack = build_undo_stack($sectionId, $ctx['taskIndex']);
$task = $stack->pop();
if ($task === null) {
    fail('Nothing to undo.', 409);
}

Store::begin();
Store::delete('undo_stack', (int) $task['stack_row']);
Store::update('tasks', (int) $task['id'], ['status' => 'pending', 'reviewed_by' => null]);
// [DSA: QUEUE] — ENQUEUE it again at the rear of the waiting list
Store::insert('approval_queue', ['section_id' => $sectionId, 'task_id' => (int) $task['id']]);
Store::commit();

$ctx = load_context($admin);
$queue = build_queue($sectionId, $ctx['taskIndex']);
$next = $stack->peek();
respond([
    'restored' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
    'queue_size' => $queue->size(),
    'undo' => [
        'size' => $stack->size(),
        'top' => $next ? present_task($next, $ctx['subjectIndex'], $ctx['users']) : null,
    ],
]);
