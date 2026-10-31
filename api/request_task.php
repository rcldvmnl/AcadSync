<?php
/**
 * POST /api/request_task.php
 *   action=check   {subject_id, title, description, ignore_id?}  -> Linear Search for similar tasks
 *   action=submit  {subject_id, category, title, description, location,
 *                   deadline_type: exact|range|unset, deadline, deadline_end, deadline_time,
 *                   target_task_id?}
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

$subjectId = (int) param('subject_id', 0);
$title = (string) param('title', '');
$description = (string) param('description', '');
$targetId = param('target_task_id') ? (int) param('target_task_id') : null;

if (param('action') === 'check') {
    respond(['similar' => find_similar(
        $ctx['tasks'], $subjectId, $title . ' ' . $description,
        param('ignore_id') ? (int) param('ignore_id') : $targetId,
        $ctx['subjectIndex'], $ctx['users']
    )]);
}

if (param('action') !== 'submit') {
    fail('Unknown action.');
}

// --- Validation ---------------------------------------------------------------
$target = null;
if ($targetId !== null) {
    foreach ($ctx['tasks'] as $t) {
        if ((int) $t['id'] === $targetId && $t['status'] === 'approved') {
            $target = $t;
        }
    }
    if (!$target) {
        fail('The task you want to change no longer exists.', 404);
    }
    $subjectId = $subjectId ?: (int) $target['subject_id'];
    $title = $title !== '' ? $title : $target['title'];
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

if ($type === 'unset') {
    $deadline = $deadlineEnd = $time = null;
} elseif ($type === 'range') {
    if (!$isDate($deadline) || !$isDate($deadlineEnd) || $deadline > $deadlineEnd) {
        fail('Pick the first and last day of the range.');
    }
} else {
    $type = 'exact';
    $deadlineEnd = null;
    if (!$isDate($deadline)) {
        fail('Pick a deadline date.');
    }
}
if ($time !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $time)) {
    fail('Pick a valid due time.');
}

$row = [
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

// LINEAR SEARCH — warn about possible duplicates
$similar = $target ? [] : find_similar($ctx['tasks'], $subjectId, $title . ' ' . $row['description'], null, $ctx['subjectIndex'], $ctx['users']);
$subjectCode = $ctx['subjectIndex'][$subjectId]['code'];
$sectionId = (int) $ctx['section']['id'];
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

if ($isAdmin) {
    if ($target) {
        // Admin "Edit Task" — everyone sees the change right away
        Store::update('tasks', (int) $target['id'], $row);
        foreach (section_members($sectionId) as $m) {
            if ((int) $m['id'] !== (int) $user['id']) {
                notify((int) $m['id'], 'moved', "“{$title}” was updated", "task.html?id={$target['id']}");
            }
        }
        respond(['id' => (int) $target['id'], 'status' => 'approved', 'similar' => []]);
    }
    // Admin "Add Task" — skips the queue
    $id = Store::insert('tasks', $row + [
        'requested_by' => (int) $user['id'],
        'status' => 'approved',
        'kind' => 'new',
        'reviewed_by' => (int) $user['id'],
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    foreach (section_members($sectionId) as $m) {
        if ((int) $m['id'] !== (int) $user['id']) {
            notify((int) $m['id'], 'schedule', "New {$category}: “{$title}” ({$subjectCode})", "task.html?id=$id");
        }
    }
    respond(['id' => $id, 'status' => 'approved', 'similar' => $similar], 201);
}

// Student request -> ENQUEUE into the approval queue
Store::begin();
$id = Store::insert('tasks', $row + [
    'requested_by' => (int) $user['id'],
    'status' => 'pending',
    'kind' => $target ? 'change' : 'new',
    'target_task_id' => $target ? (int) $target['id'] : null,
    'created_at' => date('Y-m-d H:i:s'),
]);
Store::insert('approval_queue', ['task_id' => $id]);
Store::commit();

$verb = $target ? 'asked to change' : 'requested';
notify_admins($sectionId, 'request', "$fullName $verb “{$title}”", 'queue.html');

$position = build_queue($ctx['subjectIndex'])->size();
respond(['id' => $id, 'status' => 'pending', 'queue_position' => $position, 'similar' => $similar], 201);
