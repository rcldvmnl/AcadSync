<?php
/**
 * Section announcements.
 *
 * GET  /api/announcements.php                      -> pinned first, then newest first
 * POST action=post   {title, body?, pinned?}       -> officers; everyone else gets a notification
 *      action=pin    {id, pinned}                  -> officers
 *      action=delete {id}                          -> whoever posted it, or the President
 *
 * [ALGORITHM: MERGE SORT] twice: newest first, then a stable sort by "pinned" brings pinned posts
 *                         to the top while each group stays newest first.
 * [OOP] Permission::POST_ANNOUNCEMENTS belongs to Officer; the endpoint asks $person->can().
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$me = Person::fromRow($user);
$sectionId = (int) $user['section_id'];
$uid = $me->id();
$users = user_index();

/** The section's announcements by id. */
$rows = [];
foreach (section_rows('announcements', $sectionId) as $a) {
    $rows[(int) $a['id']] = $a;
}

$canDelete = fn (array $a) => (int) $a['created_by'] === $uid || $me->can(Permission::MANAGE_SECTION);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $items = [];
    foreach ($rows as $a) {
        $by = $users[(int) $a['created_by']] ?? null;
        $items[] = [
            'id' => (int) $a['id'],
            'title' => $a['title'],
            'body' => $a['body'],
            'pinned' => (bool) (int) $a['pinned'],
            'pin_key' => (int) $a['pinned'],
            'created_at' => $a['created_at'],
            'by' => $by ? trim($by['first_name'] . ' ' . $by['last_name']) : 'Former member',
            'can_delete' => $canDelete($a),
        ];
    }
    $steps = 0;
    $items = Algorithms::mergeSortBy($items, 'id', true, $steps);        // newest first (ids grow over time)
    $items = Algorithms::mergeSortBy($items, 'pin_key', true, $steps);   // stable: pinned on top, still newest first
    respond([
        'announcements' => $items,
        'can_post' => $me->can(Permission::POST_ANNOUNCEMENTS),
        'sorted_by' => 'Merge Sort', 'steps' => $steps,
    ]);
}

require_method('POST');

$a = null;
if (param('id')) {
    $a = $rows[(int) param('id')] ?? null;
    if ($a === null) {
        fail('That announcement was removed.', 404);
    }
}

switch (param('action')) {
    case 'post': {
        if (!$me->can(Permission::POST_ANNOUNCEMENTS)) {
            fail('Only officers post announcements.', 403);
        }
        $title = preg_replace('/\s+/', ' ', (string) param('title', ''));
        $body = trim((string) param('body', ''));
        if ($title === '' || strlen($title) > 80) {
            fail('Give the announcement a title (up to 80 characters).');
        }
        if (strlen($body) > 1000) {
            fail('Keep the message under 1,000 characters.');
        }
        $pinned = filter_var(param('pinned', false), FILTER_VALIDATE_BOOLEAN);
        $id = Store::insert('announcements', [
            'section_id' => $sectionId, 'title' => $title, 'body' => $body, 'pinned' => $pinned ? 1 : 0, 'created_by' => $uid,
        ]);
        notify_section($sectionId, $uid, 'announcement', "{$me->roleLabel()} {$me->firstName()}: $title", 'home.html#announcements');
        respond(['id' => $id], 201);
    }

    case 'pin': {
        if (!$me->can(Permission::POST_ANNOUNCEMENTS)) {
            fail('Only officers pin announcements.', 403);
        }
        if ($a === null) {
            fail('Pick an announcement.');
        }
        $pinned = filter_var(param('pinned', false), FILTER_VALIDATE_BOOLEAN);
        Store::update('announcements', (int) $a['id'], ['pinned' => $pinned ? 1 : 0]);
        respond(['pinned' => $pinned]);
    }

    case 'delete': {
        if ($a === null || !$canDelete($a)) {
            fail('Only whoever posted it, or the President, can remove it.', 403);
        }
        Store::delete('announcements', (int) $a['id']);
        respond();
    }

    default:
        fail('Unknown action.');
}
