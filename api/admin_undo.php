<?php
/**
 * POST /api/admin_undo.php
 * POPs the most recently rejected request off the UndoStack and ENQUEUEs it
 * back into the Approval Queue (it joins the rear of the line).
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

require_method('POST');
$admin = require_admin();
$ctx = load_context($admin);

$stack = build_undo_stack($ctx['subjectIndex']);
$task = $stack->pop();                  // POP (LIFO)
if ($task === null) {
    fail('Nothing to undo.', 409);
}

Store::begin();
Store::delete('undo_stack', (int) $task['stack_row']);
Store::update('tasks', (int) $task['id'], ['status' => 'pending', 'reviewed_by' => null]);
Store::insert('approval_queue', ['task_id' => (int) $task['id']]);   // ENQUEUE (rear)
Store::commit();

$queue = build_queue($ctx['subjectIndex']);
$next = $stack->peek();
respond([
    'restored' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
    'queue_size' => $queue->size(),
    'undo' => [
        'size' => $stack->size(),
        'top' => $next ? present_task($next, $ctx['subjectIndex'], $ctx['users']) : null,
    ],
]);
