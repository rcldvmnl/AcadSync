<?php
/**
 * POST /api/request_task.php
 *   action=check   {subject_id, title, description, ignore_id?}  -> Linear Search for similar tasks
 *   action=submit  {subject_id, category, title, description, location,
 *                   deadline_type: exact|range|unset, deadline, deadline_end, deadline_time,
 *                   target_task_id?}
 *   action=delete  {task_id}   (admin) remove a published task from the timeline
 *
 * Student  -> the request is ENQUEUED into the Approval Queue (status = pending).
 *             With target_task_id it becomes an edit ("change") request.
 * Admin    -> published right away (Add Task) or applied directly (Edit Task).
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

const CATEGORIES = ['Reporting', 'Quiz', 'Exam', 'Activity', 'Project'];

require_method('POST');
$user = current_member();
$ctx = load_context($user);
$isAdmin = $user['role'] === 'admin';
$sectionId = (int) $ctx['section']['id'];
$uid = (int) $user['id'];

$subjectId = (int) param('subject_id', 0);
$title = (string) param('title', '');
$description = (string) param('description', '');
$targetId = param('target_task_id') ? (int) param('target_task_id') : null;

switch (param('action')) {
    case 'check':
        // [ALGORITHM: LINEAR SEARCH] — warn before submitting a duplicate
        respond(['similar' => find_similar(
            $ctx['tasks'], $subjectId, $title . ' ' . $description,
            param('ignore_id') ? (int) param('ignore_id') : $targetId,
            $ctx['subjectIndex'], $ctx['users']
        )]);

    case 'delete': {
        if (!$isAdmin) {
            fail('Only the class admin can remove tasks.', 403);
        }
        $taskId = (int) param('task_id', 0);
        // [DSA: LINKED LIST] — DELETION: unlink the node from the timeline
        $timeline = build_timeline($ctx['tasks'], time());
        $removed = $timeline->deleteById($taskId);
        $row = $removed ?? ($ctx['taskIndex'][$taskId] ?? null);
        if (!$row || $row['status'] !== 'approved') {
            fail('Task not found.', 404);
        }
        Store::update('tasks', $taskId, ['status' => 'removed', 'reviewed_by' => $uid]);
        notify_section($sectionId, $uid, 'moved', "“{$row['title']}” was removed from the schedule", 'schedule.html');
        respond(['removed' => $taskId, 'timeline_length' => $timeline->length()]);
    }

    case 'submit':
        break;

    default:
        fail('Unknown action.');
}

// --- Validation ---------------------------------------------------------------
$target = null;
if ($targetId !== null) {
    $t = $ctx['taskIndex'][$targetId] ?? null;
    if (!$t || $t['status'] !== 'approved' || $t['kind'] !== 'new') {
        fail('The task you want to change no longer exists.', 404);
    }
    $target = $t;
    $subjectId = $subjectId ?: (int) $target['subject_id'];
    $title = $title !== '' ? $title : $target['title'];

    // A student can only have one pending edit request per task
    if (!$isAdmin) {
        foreach (build_queue($sectionId, $ctx['taskIndex'])->toArray() as $q) {
            if ($q['kind'] === 'change' && (int) $q['target_task_id'] === $targetId && (int) $q['requested_by'] === $uid) {
                fail('You already asked to change this task. Wait for the admin to review it.', 409);
            }
        }
    }
}
if (!isset($ctx['subjectIndex'][$subjectId])) {
    fail('Pick a subject.');
}
if ($title === '') {
    fail('Give the task a title.');
}
$category = (string) param('category', $target['category'] ?? '');
$validCategory = false;
foreach (CATEGORIES as $c) {
    $validCategory = $validCategory || $c === $category;
}
if (!$validCategory) {
    fail('Pick a category.');
}

$type = (string) param('deadline_type', 'exact');
$deadline = param('deadline') ?: null;
$deadlineEnd = param('deadline_end') ?: null;
$time = param('deadline_time') ?: null;
$isDate = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$today = date('Y-m-d');

if ($type === 'unset') {
    $deadline = $deadlineEnd = $time = null;
} elseif ($type === 'range') {
    if (!$isDate($deadline) || !$isDate($deadlineEnd) || $deadline > $deadlineEnd) {
        fail('Pick the first and last day of the range.');
    }
    if ($deadlineEnd < $today) {
        fail('That date range is already over.');
    }
} else {
    $type = 'exact';
    $deadlineEnd = null;
    if (!$isDate($deadline)) {
        fail('Pick a deadline date.');
    }
    if ($deadline < $today) {
        fail('The deadline can’t be in the past.');
    }
}
if ($time !== null && !valid_time((string) $time)) {
    fail('Pick a valid due time.');
}

$row = [
    'section_id' => $sectionId,
    'subject_id' => $subjectId,
    'category' => $category,
    'title' => $title,
    'description' => $description !== '' ? $description : ($target['description'] ?? ''),
    'deadline' => $deadline,
    'deadline_time' => $time,
    'deadline_end' => $deadlineEnd,
    'deadline_type' => $type,
    'location' => (string) param('location', $target['location'] ?? ''),
];

// [ALGORITHM: LINEAR SEARCH] — warn about possible duplicates
$similar = $target ? [] : find_similar($ctx['tasks'], $subjectId, $title . ' ' . $row['description'], null, $ctx['subjectIndex'], $ctx['users']);
$subjectCode = $ctx['subjectIndex'][$subjectId]['code'];
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

if ($isAdmin) {
    if ($target) {
        // Admin "Edit Task" — everyone sees the change right away
        Store::update('tasks', (int) $target['id'], $row);
        notify_section($sectionId, $uid, 'moved', "“{$title}” was updated", "task.html?id={$target['id']}");
        respond(['id' => (int) $target['id'], 'status' => 'approved', 'similar' => []]);
    }
    // Admin "Add Task" — skips the queue; the Linked List places it by date
    $id = Store::insert('tasks', $row + [
        'requested_by' => $uid,
        'status' => 'approved',
        'kind' => 'new',
        'reviewed_by' => $uid,
    ]);
    notify_section($sectionId, $uid, 'schedule', "New {$category}: “{$title}” ({$subjectCode})", "task.html?id=$id");
    respond(['id' => $id, 'status' => 'approved', 'similar' => $similar], 201);
}

// [DSA: QUEUE] — student request is ENQUEUED at the rear of the approval queue
Store::begin();
$id = Store::insert('tasks', $row + [
    'requested_by' => $uid,
    'status' => 'pending',
    'kind' => $target ? 'change' : 'new',
    'target_task_id' => $target ? (int) $target['id'] : null,
]);
Store::insert('approval_queue', ['section_id' => $sectionId, 'task_id' => $id]);
Store::commit();

$verb = $target ? 'asked to change' : 'requested';
notify_admins($sectionId, 'request', "$fullName $verb “{$title}”", 'queue.html');

$ctx = load_context($user);
$position = build_queue($sectionId, $ctx['taskIndex'])->size();
respond(['id' => $id, 'status' => 'pending', 'queue_position' => $position, 'similar' => $similar], 201);
