<?php
/**
 * GET /api/tasks.php?id=12 -> one task with subject + requester details.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$id = (int) param('id', 0);

$task = null;
foreach ($ctx['tasks'] as $t) {
    if ((int) $t['id'] === $id) {
        $task = $t;
    }
}
if (!$task) {
    fail('Task not found.', 404);
}
$isOwner = (int) $task['requested_by'] === (int) $user['id'];
if ($task['status'] !== 'approved' && $user['role'] !== 'admin' && !$isOwner) {
    fail('This task is still waiting for admin approval.', 403);
}

respond([
    'task' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
    'role' => $user['role'],
    'now' => time(),
]);
