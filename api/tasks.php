<?php
/**
 * GET /api/tasks.php?id=12   -> one task with subject + requester details
 * GET /api/tasks.php?mine=1  -> the logged-in user's own requests in this section
 *                              (with their current place in the approval queue)
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$uid = (int) $user['id'];

if (param('mine')) {
    // [DSA: QUEUE] — find each pending request's position from the front
    $positions = [];
    $i = 0;
    foreach (build_queue((int) $ctx['section']['id'], $ctx['taskIndex'])->toArray() as $q) {
        $positions[(int) $q['id']] = ++$i;
    }
    $mine = [];
    // newest first: walk the rows backwards
    for ($j = count($ctx['tasks']) - 1; $j >= 0; $j--) {
        $t = $ctx['tasks'][$j];
        if ((int) $t['requested_by'] === $uid && $t['status'] !== 'removed') {
            $mine[] = present_task($t, $ctx['subjectIndex'], $ctx['users']) + ['queue_position' => $positions[(int) $t['id']] ?? null];
        }
    }
    respond(['requests' => $mine, 'queue_size' => $i]);
}

$task = $ctx['taskIndex'][(int) param('id', 0)] ?? null;
if (!$task || $task['status'] === 'removed') {
    fail('Task not found.', 404);
}
$isOwner = (int) $task['requested_by'] === $uid;
if ($task['status'] !== 'approved' && $user['role'] !== 'admin' && !$isOwner) {
    fail('This task is still waiting for admin approval.', 403);
}

respond([
    'task' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
    'role' => $user['role'],
    'now' => time(),
]);
