<?php
/**
 * POST /api/admin_undo.php              {}            -> undo the most recent rejection (POP the top)
 * POST /api/admin_undo.php              {task_id: 7}  -> undo that one rejection only
 * The request goes back into the Approval Queue (ENQUEUE at the rear of the line).
 *
 * [DSA: STACK] Undoing one from the middle uses only stack operations (UndoStack::removeWhere):
 * the rejections above it are POPped onto a holding stack and PUSHed back afterwards, so the
 * others stay in the same order.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

require_method('POST');
$admin = require_permission(Permission::REVIEW_REQUESTS, 'Only officers (President, Vice President, Secretary, Treasurer) can review requests.');
$ctx = load_context($admin);
$sectionId = (int) $ctx['section']['id'];

$stack = build_undo_stack($sectionId, $ctx['taskIndex']);
$wanted = (int) param('task_id', 0);
$task = $wanted
    ? $stack->removeWhere(fn (array $t) => (int) $t['id'] === $wanted)   // only this section's rejections are on the stack
    : $stack->pop();                                                      // LIFO: the latest rejection
if ($task === null) {
    fail($wanted ? 'That request isn’t in the undo stack any more.' : 'Nothing to undo.', 409);
}

Store::begin();
Store::delete('undo_stack', (int) $task['stack_row']);
Store::update('tasks', (int) $task['id'], ['status' => 'pending', 'reviewed_by' => null]);
// [DSA: QUEUE] — ENQUEUE it again at the rear of the waiting list
Store::insert('approval_queue', ['section_id' => $sectionId, 'task_id' => (int) $task['id']]);
Store::commit();

$ctx = load_context($admin);
$queue = build_queue($sectionId, $ctx['taskIndex']);
respond([
    'restored' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
    'queue_size' => $queue->size(),
    'undo' => present_undo(build_undo_stack($sectionId, $ctx['taskIndex']), $ctx),
]);
