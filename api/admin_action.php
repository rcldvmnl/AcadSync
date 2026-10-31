<?php
/**
 * POST /api/admin_action.php {task_id, action: approve|reject}
 *
 * The queue is strictly FIFO: only the request at the FRONT can be decided.
 *   approve -> dequeue, mark approved, and Insertion-Sort it into the timeline
 *              (for edit requests the changes are copied onto the original task)
 *   reject  -> dequeue and PUSH onto the UndoStack
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

require_method('POST');
$admin = require_admin();
$ctx = load_context($admin);

$queue = build_queue($ctx['subjectIndex']);
$front = $queue->peek();
if ($front === null) {
    fail('The queue is empty.', 409);
}
if ((int) $front['id'] !== (int) param('task_id', 0)) {
    fail('Requests are reviewed in order — decide #1 first.', 409, ['front_id' => (int) $front['id']]);
}

$task = $queue->dequeue();              // DEQUEUE (FIFO)
$action = param('action');
$requester = (int) $task['requested_by'];

Store::begin();
Store::delete('approval_queue', (int) $task['queue_row']);

if ($action === 'approve') {
    Store::update('tasks', (int) $task['id'], ['status' => 'approved', 'reviewed_by' => (int) $admin['id']]);
    $linkId = (int) $task['id'];

    if ($task['kind'] === 'change' && $task['target_task_id']) {
        // Copy the requested changes onto the original task
        Store::update('tasks', (int) $task['target_task_id'], [
            'category' => $task['category'],
            'title' => $task['title'],
            'description' => $task['description'],
            'deadline' => $task['deadline'],
            'deadline_time' => $task['deadline_time'],
            'deadline_end' => $task['deadline_end'],
            'deadline_type' => $task['deadline_type'],
            'location' => $task['location'],
        ]);
        $linkId = (int) $task['target_task_id'];
    }
    Store::commit();

    // Rebuild the timeline: the approved task is placed with Insertion Sort
    $ctx = load_context($admin);
    $timeline = build_timeline($ctx['tasks'], time());
    $position = 0;
    $i = 0;
    foreach ($timeline->toArray() as $t) {
        $i++;
        if ((int) $t['id'] === $linkId) {
            $position = $i;
        }
    }

    notify($requester, 'approved', "Your request “{$task['title']}” was approved", "task.html?id=$linkId");
    respond(['action' => 'approved', 'task_id' => $linkId, 'timeline_position' => $position, 'queue_size' => $queue->size()]);
}

if ($action === 'reject') {
    Store::update('tasks', (int) $task['id'], ['status' => 'rejected', 'reviewed_by' => (int) $admin['id']]);
    Store::insert('undo_stack', ['task_id' => (int) $task['id'], 'admin_id' => (int) $admin['id']]);   // PUSH (LIFO)
    Store::commit();

    notify($requester, 'rejected', "Your request “{$task['title']}” was not approved", null);
    $stack = build_undo_stack($ctx['subjectIndex']);
    respond([
        'action' => 'rejected',
        'task' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
        'undo_size' => $stack->size(),
        'queue_size' => $queue->size(),
    ]);
}

Store::pdo()->rollBack();
fail('Action must be approve or reject.');
