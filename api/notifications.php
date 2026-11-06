<?php
/**
 * GET  /api/notifications.php          -> newest first + unread count
 * POST /api/notifications.php action=read_all | read {id}
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_user();
$uid = (int) $user['id'];

$mine = Store::where('notifications', 'user_id', $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = param('id') ? (int) param('id') : null;
    foreach ($mine as $n) {
        if (!(int) $n['is_read'] && ($id === null || (int) $n['id'] === $id)) {
            Store::update('notifications', (int) $n['id'], ['is_read' => 1]);
        }
    }
    respond();
}

// [ALGORITHM: MERGE SORT] — newest first by time (ties: the later row first).
// Divide-and-conquer keeps a long history fast: O(n log n) comparisons.
$keyed = [];
foreach ($mine as $n) {
    $keyed[] = $n + ['sort_key' => (int) strtotime((string) $n['created_at']) * 1000000 + (int) $n['id']];
}
$steps = 0;
$sorted = Algorithms::mergeSortBy($keyed, 'sort_key', true, $steps);

$out = [];
$unread = 0;
foreach ($sorted as $n) {
    $out[] = [
        'id' => (int) $n['id'],
        'type' => $n['type'],
        'message' => $n['message'],
        'link' => $n['link'],
        'is_read' => (bool) (int) $n['is_read'],
        'created_at' => $n['created_at'],
        'ts' => strtotime($n['created_at']),
    ];
    if (!(int) $n['is_read']) {
        $unread++;
    }
}
respond(['notifications' => $out, 'unread' => $unread, 'sorted_by' => 'Merge Sort', 'steps' => $steps]);
