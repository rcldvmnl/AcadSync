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

// Rows are stored oldest-first, so walking backwards gives newest-first.
$out = [];
$unread = 0;
for ($i = count($mine) - 1; $i >= 0; $i--) {
    $n = $mine[$i];
    $out[] = [
        'id' => (int) $n['id'],
        'type' => $n['type'],
        'message' => $n['message'],
        'link' => $n['link'],
        'is_read' => (bool) (int) $n['is_read'],
        'created_at' => $n['created_at'],
        'ts' => strtotime($n['created_at'] . ' UTC'),
    ];
    if (!(int) $n['is_read']) {
        $unread++;
    }
}
respond(['notifications' => $out, 'unread' => $unread]);
