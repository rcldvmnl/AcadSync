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
$admin = require_permission(Permission::REVIEW_REQUESTS, 'Only officers (President, Vice President, Secretary, Treasurer) can review requests.');
$ctx = load_context($admin);
$sectionId = (int) $ctx['section']['id'];
$adminId = (int) $admin['id'];

// [DSA: QUEUE] — only the front of the waiting list may be decided
$queue = build_queue($sectionId, $ctx['taskIndex']);
$front = $queue->peek();
if ($front === null) {
    fail('The queue is empty.', 409);
}
if ((int) $front['id'] !== (int) param('task_id', 0)) {
    fail('Requests are reviewed in order — decide #1 first.', 409, ['front_id' => (int) $front['id']]);
}

$action = param('action');
if ($action !== 'approve' && $action !== 'reject') {
    fail('Action must be approve or reject.');
}

$task = $queue->dequeue();              // DEQUEUE (FIFO)
$requester = (int) $task['requested_by'];

Store::begin();
Store::delete('approval_queue', (int) $task['queue_row']);

if ($action === 'approve') {
    $linkId = (int) $task['id'];
    if ($task['kind'] === 'change') {
        $original = $ctx['taskIndex'][(int) $task['target_task_id']] ?? null;
        if (!$original || $original['status'] !== 'approved') {
            Store::update('tasks', (int) $task['id'], ['status' => 'rejected', 'reviewed_by' => $adminId]);
            Store::commit();
            fail('The original task was removed, so this edit request was closed.', 409);
        }
        // Copy the requested changes onto the original task
        Store::update('tasks', (int) $original['id'], [
            'subject_id' => $task['subject_id'],
            'work_mode' => $task['work_mode'],
            'group_min' => $task['group_min'],
            'group_max' => $task['group_max'],
            'group_count' => $task['group_count'] ?? null,
            'group_mode' => $task['group_mode'] ?? null,
            'group_plan' => $task['group_plan'] ?? null,
            'category' => $task['category'],
            'title' => $task['title'],
            'description' => $task['description'],
            'deadline' => $task['deadline'],
            'deadline_time' => $task['deadline_time'],
            'deadline_end' => $task['deadline_end'],
            'deadline_type' => $task['deadline_type'],
            'location' => $task['location'],
        ]);
        $linkId = (int) $original['id'];
    }
    Store::update('tasks', (int) $task['id'], ['status' => 'approved', 'reviewed_by' => $adminId]);
    // A fixed number of groups (or the prof's own list) appears as groups right away
    sync_task_groups($sectionId, $linkId, $adminId);
    Store::commit();

    // [DSA: LINKED LIST] — rebuild the timeline; Insertion Sort places the task by date
    $ctx = load_context($admin);
    $timeline = build_timeline($ctx['tasks'], time());

    notify($requester, $sectionId, 'approved', "Your request “{$task['title']}” was approved", "task.html?id=$linkId");
    respond([
        'action' => 'approved',
        'task_id' => $linkId,
        'timeline_position' => $timeline->positionOf($linkId),
        'queue_size' => $queue->size(),
    ]);
}

Store::update('tasks', (int) $task['id'], ['status' => 'rejected', 'reviewed_by' => $adminId]);
// [DSA: STACK] — PUSH the rejection so it can be undone (LIFO)
Store::insert('undo_stack', ['section_id' => $sectionId, 'task_id' => (int) $task['id'], 'admin_id' => $adminId]);
Store::commit();

notify($requester, $sectionId, 'rejected', "Your request “{$task['title']}” was not approved", 'account.html#requests');
$ctx = load_context($admin);
respond([
    'action' => 'rejected',
    'task' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
    'undo_size' => build_undo_stack($sectionId, $ctx['taskIndex'])->size(),
    'queue_size' => $queue->size(),
]);
