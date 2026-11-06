<?php
/**
 * GET /api/tasks.php?id=12   -> one task with subject + requester details
 * GET /api/tasks.php?mine=1  -> the logged-in user's own requests in this section
 *                              (with their current place in the approval queue)
 * GET /api/tasks.php?history=1 -> tasks the user marked as done + tasks whose deadline passed
 * POST action=done {task_id, done: true|false}  -> mark / unmark a task as done for this user
 *                              (the task is NOT removed from the schedule)
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$uid = (int) $user['id'];
$sectionId = (int) $ctx['section']['id'];
$done = done_map($uid, $sectionId);
$present = fn (array $t) => present_task($t, $ctx['subjectIndex'], $ctx['users'])
    + ['done' => isset($done[(int) $t['id']]), 'done_at' => $done[(int) $t['id']] ?? null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (param('action') !== 'done') {
        fail('Unknown action.');
    }
    $taskId = (int) param('task_id', 0);
    $task = $ctx['taskIndex'][$taskId] ?? null;
    if (!$task || $task['status'] !== 'approved') {
        fail('Task not found.', 404);
    }
    $want = filter_var(param('done', true), FILTER_VALIDATE_BOOLEAN);
    $row = null;
    foreach (section_rows('task_done', $sectionId) as $r) {
        if ((int) $r['task_id'] === $taskId && (int) $r['user_id'] === $uid) {
            $row = $r;
            break;
        }
    }
    $changed = ($want && !$row) || (!$want && $row);
    if ($want && !$row) {
        Store::insert('task_done', ['section_id' => $sectionId, 'task_id' => $taskId, 'user_id' => $uid]);
    } elseif (!$want && $row) {
        Store::delete('task_done', (int) $row['id']);
    }
    if ($changed) {
        // [DSA: STACK] — PUSH the opposite so Undo can set it back
        undo_push($sectionId, [
            'type' => 'done', 'task_id' => $taskId, 'done' => !$want,
            'label' => ($want ? 'Marked “' : 'Unmarked “') . $task['title'] . '”',
        ]);
    }
    respond(['task_id' => $taskId, 'done' => $want, 'undo' => $changed]);
}

if (param('history')) {
    // Completed (marked done) and ended (deadline passed) tasks, most recent first
    $now = time();
    $completed = [];
    $ended = [];
    foreach ($ctx['tasks'] as $t) {
        if ($t['status'] !== 'approved' || $t['kind'] !== 'new') {
            continue;
        }
        $id = (int) $t['id'];
        $ts = DsaUtil::deadlineTs($t);
        if (isset($done[$id])) {
            $completed[] = $present($t) + ['done_ts' => strtotime((string) $done[$id]) ?: null];
        } elseif ($ts !== null && $ts < $now) {
            $ended[] = $present($t);
        }
    }
    // [ALGORITHM: INSERTION SORT] — newest first
    $newestFirst = function (array $items, string $key): array {
        for ($i = 1; $i < count($items); $i++) {
            $cur = $items[$i];
            $j = $i - 1;
            while ($j >= 0 && ($items[$j][$key] ?? 0) < ($cur[$key] ?? 0)) {
                $items[$j + 1] = $items[$j];
                $j--;
            }
            $items[$j + 1] = $cur;
        }
        return $items;
    };
    respond([
        'completed' => $newestFirst($completed, 'done_ts'),
        'ended' => $newestFirst($ended, 'deadline_ts'),
    ]);
}

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
        if ((int) $t['requested_by'] === $uid && $t['status'] !== 'removed' && $t['status'] !== 'withdrawn') {
            $mine[] = present_task($t, $ctx['subjectIndex'], $ctx['users']) + ['queue_position' => $positions[(int) $t['id']] ?? null];
        }
    }
    $waiting = 0;
    foreach ($mine as $r) {
        $waiting += $r['status'] === 'pending' ? 1 : 0;
    }
    respond(['requests' => $mine, 'queue_size' => $i, 'pending' => $waiting, 'limit' => 3]);
}

$task = $ctx['taskIndex'][(int) param('id', 0)] ?? null;
if (!$task || $task['status'] === 'removed' || $task['status'] === 'withdrawn') {
    fail('Task not found.', 404);
}
$isOwner = (int) $task['requested_by'] === $uid;
if ($task['status'] !== 'approved' && $user['role'] !== 'admin' && !$isOwner) {
    fail('This task is still waiting for admin approval.', 403);
}

// [DSA: DOUBLY LINKED LIST] — previous / next task by deadline, read from the node's prev and next
$timeline = build_timeline($ctx['tasks'], time());
$near = $timeline->neighbors((int) $task['id']);
$brief = fn (?array $t) => $t ? ['id' => (int) $t['id'], 'title' => $t['title'], 'deadline_ts' => DsaUtil::deadlineTs($t)] : null;

respond([
    'task' => $present($task),
    'role' => $user['role'],
    'now' => time(),
    'nav' => $near ? [
        'prev' => $brief($near['prev']), 'next' => $brief($near['next']),
        'position' => $near['position'], 'length' => $timeline->length(),
    ] : null,
]);
