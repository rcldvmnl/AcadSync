<?php
/**
 * People who entered the class code and wait to be let into the section (officers).
 *
 * GET  /api/join_requests.php                      -> the waiting line, oldest first
 * POST action=approve {request_id}                 -> they become a Member
 *      action=decline {request_id}
 *
 * [DSA: QUEUE] Requests are decided first-come, first-served: only the FRONT of the queue can be
 *              approved or declined, like the task approval queue.
 * [DSA: HASHSET] A student ID already in the section can't be let in twice.
 * [OOP] Permission::REVIEW_MEMBERS belongs to Officer, so the President, Vice President, Secretary
 *       and Treasurer can all review; the endpoint asks $person->can(), never a role string.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = require_permission(Permission::REVIEW_MEMBERS, 'Only officers let new members in.');
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$section = $ctx['section'];

$queue = build_join_queue($sectionId);

/** One request for the page: who, when, and whether they're in other sections already. */
$present = function (array $r, int $position) {
    $u = $r['user'];
    return [
        'id' => (int) $r['id'],
        'position' => $position,
        'user_id' => (int) $u['id'],
        'name' => trim($u['first_name'] . ' ' . $u['last_name']),
        'student_id' => $u['student_id'],
        'email' => $u['email'],
        'other_sections' => count(user_sections((int) $u['id'])),
        'created_at' => $r['created_at'],
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $items = [];
    $position = 0;
    foreach ($queue->toArray() as $r) {
        $items[] = $present($r, ++$position);
    }
    respond(['requests' => $items, 'size' => $queue->size()]);
}

require_method('POST');

$front = $queue->peek();      // [DSA: QUEUE] — the oldest request
$id = (int) param('request_id', 0);
if ($front === null) {
    fail('Nobody is waiting to join.', 404);
}
if ((int) $front['id'] !== $id) {
    fail('Decide #1 first: people are let in in the order they asked.', 409);
}
$queue->dequeue();
$who = (int) $front['user']['id'];
$name = trim($front['user']['first_name'] . ' ' . $front['user']['last_name']);

switch (param('action')) {
    case 'approve': {
        // [DSA: HASHSET] — student IDs already in the section
        $ids = new HashSet();
        foreach ($ctx['members'] as $m) {
            $ids->add((string) $m['student_id']);
        }
        Store::begin();
        if (!$ids->contains((string) $front['user']['student_id'])) {
            Store::insert('memberships', ['section_id' => $sectionId, 'user_id' => $who, 'role' => 'student']);
        }
        Store::update('join_requests', $id, ['status' => 'approved', 'reviewed_by' => (int) $user['id']]);
        if ($front['user']['active_section_id'] === null) {    // their first section opens by itself
            Store::update('users', $who, ['active_section_id' => $sectionId]);
        }
        Store::commit();
        notify($who, $sectionId, 'members', "You’re in! You’re now a member of {$section['name']}.", 'home.html');
        respond(['approved' => $name, 'size' => $queue->size()]);
    }

    case 'decline': {
        Store::update('join_requests', $id, ['status' => 'declined', 'reviewed_by' => (int) $user['id']]);
        notify($who, $sectionId, 'members', "Your request to join {$section['name']} was declined. Check the class code with your officer.", 'section.html');
        respond(['declined' => $name, 'size' => $queue->size()]);
    }

    default:
        fail('Unknown action.');
}
