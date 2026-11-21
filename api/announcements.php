<?php
/**
 * Section announcements. Each kind is an Announcement subclass (backend/domain/Announcement.php)
 * that checks its own input, does the real change it announces, and words its own card.
 *
 * GET  /api/announcements.php
 *      -> { coming_up, notices, earlier, earlier_total, kinds, options, can_post }
 *         coming_up: dated, from today on, soonest first · notices: undated, newest first ·
 *         earlier: dated and past, newest first (first 10). Pinned first inside each list.
 * POST action=post   {kind, title?, body?, pinned?, …the kind's fields}    officers
 *      action=pin    {id, pinned}                                           officers
 *      action=delete {id}                 whoever posted it, or the President (No class: the class comes back)
 *
 * [OOP: POLYMORPHISM] Announcement::fromInput() builds the right kind; apply(), facts(), notifyText()
 *                     answer per kind; this file never tests the kind string.
 * [ALGORITHM: MERGE SORT] the three lists, then a stable sort that brings pinned items to the top.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$me = Person::fromRow($user);
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = $me->id();
$c = announcement_context($user, $ctx);

/** The section's announcements as objects, by id. */
$items = [];
foreach (section_rows('announcements', $sectionId) as $row) {
    $items[(int) $row['id']] = Announcement::fromRow($row);
}
$canDelete = fn (Announcement $a) => $a->createdBy() === $uid || $me->can(Permission::MANAGE_SECTION);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $names = [];
    foreach ($ctx['users'] as $u) {
        $names[(int) $u['id']] = trim($u['first_name'] . ' ' . $u['last_name']);
    }
    $coming = $notices = $earlier = [];
    foreach ($items as $a) {
        $row = $a->toArray($c, $canDelete($a)) + ['by' => $names[$a->createdBy()] ?? 'Former member'];
        // [OOP: POLYMORPHISM] task notices follow the task's current deadline; an event is over when it ends
        if ($a->eventAtIn($c) === null) {
            $notices[] = $row;
        } elseif (!$a->isOverIn($c)) {
            $coming[] = $row;
        } else {
            $earlier[] = $row;
        }
    }
    $steps = 0;
    $coming = Algorithms::mergeSortBy($coming, 'event_ts', false, $steps);     // soonest first
    $notices = Algorithms::mergeSortBy($notices, 'id', true, $steps);          // newest first
    $earlier = Algorithms::mergeSortBy($earlier, 'event_ts', true, $steps);    // most recent first
    // stable: pinned on top, each part keeps its order
    $coming = Algorithms::mergeSortBy($coming, 'pin_key', true, $steps);
    $notices = Algorithms::mergeSortBy($notices, 'pin_key', true, $steps);
    $earlier = Algorithms::mergeSortBy($earlier, 'pin_key', true, $steps);

    // What the composer may offer: only choices the server would accept
    // A new activity made from an Event announcement is an RSVP kind only: a competition or project
    // needs parts, which that small form doesn't have (it can still POINT to one).
    $rsvpKinds = [];
    foreach (Activity::kinds() as $k) {
        if ($k['joins_by'] === 'rsvp') {
            $rsvpKinds[] = $k;
        }
    }
    $options = ['dues' => [], 'tasks' => [], 'activities' => [], 'subjects' => [],
        'activity_kinds' => $rsvpKinds, 'can_create_due' => $me->can(Permission::MANAGE_DUES),
        'can_create_activity' => $me->can(Permission::MANAGE_ACTIVITIES)];
    if ($me->can(Permission::POST_ANNOUNCEMENTS)) {
        foreach ($c->dues as $d) {
            $options['dues'][] = ['id' => $d->id(), 'title' => $d->title(), 'amount_label' => Due::peso($d->amount()), 'due_date' => $d->dueDate()];
        }
        $taskOption = fn (array $t) => ['id' => (int) $t['id'], 'title' => $t['title'], 'subject_code' => $ctx['subjectIndex'][(int) $t['subject_id']]['code'] ?? '',
            'deadline_label' => Deadlines::fromRow($t)->label(), 'deadline_type' => $t['deadline_type'], 'deadline' => $t['deadline'],
            'deadline_end' => $t['deadline_end'], 'deadline_time' => $t['deadline_time'],
            'sort_ts' => Deadlines::fromRow($t)->timestamp() ?? PHP_INT_MAX, 'overdue' => (Deadlines::fromRow($t)->timestamp() ?? PHP_INT_MAX) < $c->now];
        foreach (build_timeline($ctx['tasks'], $c->now)->toArray() as $t) {   // [DSA: LINKED LIST] upcoming, by deadline (Task reminders)
            $options['tasks'][] = $taskOption($t);
        }
        // Deadline change also reaches tasks whose deadline just passed ("the prof extended it"):
        // every published task, by deadline ([ALGORITHM: MERGE SORT])
        $all = [];
        foreach ($ctx['tasks'] as $t) {
            if ($t['status'] === 'approved' && ($t['kind'] ?? 'new') === 'new') {
                $all[] = $taskOption($t);
            }
        }
        $options['deadline_tasks'] = Algorithms::mergeSortBy($all, 'sort_ts', false);
        foreach ($c->activities as $a) {
            if (!$a->isCancelled() && !$a->isOver($c->now)) {
                $options['activities'][] = ['id' => $a->id(), 'title' => $a->title(), 'kind_label' => $a->kindLabel(),
                    'starts_at' => $a->toRow()['starts_at'], 'venue' => $a->toRow()['venue'] ?? ''];
            }
        }
        $bySubject = [];
        foreach ($c->slots as $s) {
            $bySubject[$s['subject_id']][] = ['dow' => $s['day_of_week'], 'start' => $s['start_time'], 'end' => $s['end_time']];
        }
        foreach ($ctx['subjectIndex'] as $id => $s) {
            if (isset($bySubject[(int) $id])) {
                $options['subjects'][] = ['id' => (int) $id, 'code' => $s['code'], 'title' => $s['title'], 'slots' => $bySubject[(int) $id]];
            }
        }
        // Classes already cancelled ("date|subjectId" / "date|*"), so the form doesn't offer them again
        $options['cancelled'] = [];
        foreach (section_rows('class_cancellations', $sectionId) as $row) {
            $options['cancelled'][] = $row['date'] . '|' . ($row['subject_id'] !== null ? (int) $row['subject_id'] : '*');
        }
    }
    respond([
        'coming_up' => $coming, 'notices' => $notices,
        'earlier' => array_slice($earlier, 0, 10), 'earlier_total' => count($earlier),
        'kinds' => $me->can(Permission::POST_ANNOUNCEMENTS) ? Announcement::kinds($c) : [],
        'options' => $options,
        'can_post' => $me->can(Permission::POST_ANNOUNCEMENTS),
        'sorted_by' => 'Merge Sort', 'steps' => $steps,
    ]);
}

require_method('POST');

$a = null;
if (param('id')) {
    $a = $items[(int) param('id')] ?? null;
    if ($a === null) {
        fail('That announcement was removed.', 404);
    }
}

switch (param('action')) {
    case 'post': {
        if (!$me->can(Permission::POST_ANNOUNCEMENTS)) {
            fail('Only officers post announcements.', 403);
        }
        try {
            // Who may make this choice is checked first (403), then its details (400)
            $new = Announcement::fromInput((string) param('kind', 'general'), input(), $c);
        } catch (AnnouncementNotAllowed $e) {
            fail($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        // Belt and braces: the built object says what it needs too
        $needs = $new->requiredPermission();
        if ($needs !== null && !$me->can($needs)) {
            fail(Announcement::deniedMessage($needs), 403);
        }
        // The announcement and its real change happen together, or not at all
        Store::begin();
        try {
            $id = Store::insert('announcements', $new->toRow($sectionId));
            $new->apply($c, $id);
        } catch (PDOException $e) {
            // two officers cancelling the same class at the same moment: the unique index stops the second
            Store::rollbackIfOpen();
            if (!is_unique_violation($e)) {                 // anything else is a real error
                throw $e;
            }
            fail('That class is already cancelled on that day.', 409);
        }
        Store::commit();
        // [OOP: POLYMORPHISM] a payment only reaches members who are charged dues
        foreach ($ctx['members'] as $m) {
            if ((int) $m['id'] !== $uid && $new->notifies($m, $c)) {
                notify((int) $m['id'], $sectionId, $new->notifyType(), $new->notifyText($c), $new->notifyLink());
            }
        }
        respond(['id' => $id, 'kind' => $new->kind()], 201);
    }

    case 'pin': {
        if (!$me->can(Permission::POST_ANNOUNCEMENTS)) {
            fail('Only officers pin announcements.', 403);
        }
        if ($a === null) {
            fail('Pick an announcement.');
        }
        $pinned = filter_var(param('pinned', false), FILTER_VALIDATE_BOOLEAN);
        Store::update('announcements', $a->id(), ['pinned' => $pinned ? 1 : 0]);
        respond(['pinned' => $pinned]);
    }

    case 'delete': {
        if ($a === null || !$canDelete($a)) {
            fail('Only whoever posted it, or the President, can remove it.', 403);
        }
        Store::begin();
        $a->onRemove($c);      // [OOP: POLYMORPHISM] — No class brings the class back; others keep their change
        Store::delete('announcements', $a->id());
        Store::commit();
        respond();
    }

    default:
        fail('Unknown action.');
}
