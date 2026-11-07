<?php
/**
 * Organization activities (meetings, seminars, outreach), RSVPs and attendance.
 *
 * GET  /api/activities.php                     -> upcoming + past activities, your RSVP, your attendance rate
 * GET  /api/activities.php?id=5&roster=1       -> the attendance sheet (officers who take attendance)
 * POST action=create      {kind, title, date, start, end, venue?, capacity?, description?}   officers
 *      action=cancel      {id}                                                               officers
 *      action=rsvp        {id}            a seat if there is one, otherwise the waitlist (QUEUE)
 *      action=cancel_rsvp {id}            frees your seat; the FRONT of the waitlist gets it
 *      action=check_in    {id, user_id, present: true|false}                    Secretary / President
 *
 * [OOP] Activity::fromRow() builds a Meeting, Seminar or Outreach; each decides who may be checked in
 *       (canCheckIn) and who is expected (expectsAttendanceFrom). Person::can() decides who may do what.
 * [DSA] ActivityRoster: HashSet of who is going and who came, QUEUE for the waitlist;
 *       Merge Sort orders the lists by date.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$me = Person::fromRow($user);
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = $me->id();
$now = time();
const CHECK_IN_EARLY = 3600;     // attendance opens 1 hour before the start

$activities = load_activities($sectionId);
$rosters = activity_rosters($sectionId);

/** The activity being acted on, or a 404. */
$find = function () use ($activities): Activity {
    $a = $activities[(int) param('id', 0)] ?? null;
    if ($a === null) {
        fail('Activity not found.', 404);
    }
    return $a;
};

/** One activity for the list, with seats, the waitlist and this person's own state. */
$present = function (Activity $a) use ($rosters, $uid, $now, $me): array {
    $r = $rosters[$a->id()];
    return $a->toArray($now) + [
        'going' => $r->goingCount(), 'waitlist' => $r->waitlistSize(), 'checked_in' => $r->checkedInCount(),
        'full' => !$a->hasRoom($r->goingCount()),
        'me' => [
            'going' => $r->isGoing($uid), 'waitlist_position' => $r->waitlistPosition($uid), 'checked_in' => $r->isCheckedIn($uid),
            'can_rsvp' => $me->status()->canRsvp() && !$a->isCancelled() && !$a->isOver($now),
        ],
    ];
};

/* ------------------------------------------------------------------ reads */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (param('roster')) {
        if (!$me->can(Permission::RECORD_ATTENDANCE) && !$me->can(Permission::MANAGE_ACTIVITIES)) {
            fail('Only officers can open the attendance sheet.', 403);
        }
        $a = $find();
        $r = $rosters[$a->id()];
        $people = [];
        // [ALGORITHM: SELECTION SORT] — the sheet lists members A–Z
        foreach (Algorithms::selectionSortByLastName($ctx['members']) as $m) {
            $id = (int) $m['id'];
            $p = Person::fromRow($m);
            $people[] = [
                'id' => $id, 'name' => $p->fullName(), 'student_id' => $p->studentId(), 'role_label' => $p->roleLabel(),
                'status' => $p->status()->label(),
                'has_seat' => $r->isGoing($id), 'waitlist_position' => $r->waitlistPosition($id), 'checked_in' => $r->isCheckedIn($id),
                'can_check_in' => $a->canCheckIn($r->isGoing($id)),       // [POLYMORPHISM] the kind of activity decides
            ];
        }
        respond([
            'activity' => $present($a), 'people' => $people,
            'can_record' => $me->can(Permission::RECORD_ATTENDANCE),
            'opens_at' => $a->startTs() - CHECK_IN_EARLY,
        ]);
    }

    // Upcoming (soonest first) and past (latest first), both put in order with Merge Sort
    $upcoming = [];
    $past = [];
    foreach ($activities as $a) {
        $row = $present($a);
        if ($a->isOver($now)) {
            $past[] = $row;
        } else {
            $upcoming[] = $row;
        }
    }
    $steps = 0;
    $upcoming = Algorithms::mergeSortBy($upcoming, 'start_ts', false, $steps);
    $past = Algorithms::mergeSortBy($past, 'start_ts', true, $steps);

    // Your own attendance, worked out the same way as in member records
    $mine = null;
    foreach (member_records($ctx) as $rec) {
        if ($rec->person()->id() === $uid) {
            $mine = ['attended' => $rec->attended(), 'expected' => $rec->expected(), 'rate' => $rec->rate()];
        }
    }
    respond([
        'upcoming' => $upcoming, 'past' => $past, 'my_attendance' => $mine, 'kinds' => Activity::kinds(),
        'can_manage' => $me->can(Permission::MANAGE_ACTIVITIES), 'can_record' => $me->can(Permission::RECORD_ATTENDANCE),
        'my_status' => $me->status()->toArray(),
    ]);
}

/* ------------------------------------------------------------------ writes */
require_method('POST');

switch (param('action')) {
    case 'create': {
        if (!$me->can(Permission::MANAGE_ACTIVITIES)) {
            fail('Only officers can add activities.', 403);
        }
        $date = (string) param('date', '');
        $start = (string) param('start', '');
        $end = (string) param('end', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !valid_time($start) || !valid_time($end)) {
            fail('Pick a date and the start and end times.');
        }
        if (strtotime("$date $start") < $now - 300) {
            fail('That start time has already passed. Pick a later time or another day.');
        }
        $capacity = param('capacity');
        try {
            // [OOP: ENCAPSULATION] the Activity constructor rejects anything invalid
            $activity = Activity::fromRow([
                'kind' => (string) param('kind', ''), 'title' => (string) param('title', ''), 'description' => (string) param('description', ''),
                'venue' => (string) param('venue', ''), 'starts_at' => "$date $start", 'ends_at' => "$date $end",
                'capacity' => $capacity === null || $capacity === '' ? null : (int) $capacity, 'created_by' => $uid,
            ]);
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        $id = Store::insert('activities', $activity->toRow() + ['section_id' => $sectionId]);
        notify_section($sectionId, $uid, 'schedule', "New {$activity->kindLabel()}: “{$activity->title()}” on " . date('D, M j · g:i A', $activity->startTs()), 'activities.html');
        respond(['id' => $id], 201);
    }

    case 'cancel': {
        if (!$me->can(Permission::MANAGE_ACTIVITIES)) {
            fail('Only officers can cancel activities.', 403);
        }
        $a = $find();
        if ($a->isCancelled()) {
            respond();
        }
        Store::update('activities', $a->id(), ['status' => 'cancelled']);
        $r = $rosters[$a->id()];
        foreach ($ctx['members'] as $m) {
            $id = (int) $m['id'];
            if ($id !== $uid && ($r->isGoing($id) || $r->waitlistPosition($id))) {
                notify($id, $sectionId, 'moved', "“{$a->title()}” was cancelled", 'activities.html');
            }
        }
        respond();
    }

    case 'rsvp': {
        $a = $find();
        $r = $rosters[$a->id()];
        if (!$me->status()->canRsvp()) {            // [POLYMORPHISM] the member's status decides
            fail('Alumni members can’t RSVP.', 403);
        }
        if ($a->isCancelled() || $a->isOver($now)) {
            fail('This activity is no longer open for RSVPs.', 409);
        }
        if ($r->isGoing($uid) || $r->waitlistPosition($uid)) {
            fail('You already RSVP’d.', 409);
        }
        // A seat if there is one; otherwise ENQUEUE at the rear of the waitlist
        $status = $a->hasRoom($r->goingCount()) ? 'going' : 'waitlist';
        Store::insert('activity_rsvps', ['section_id' => $sectionId, 'activity_id' => $a->id(), 'user_id' => $uid, 'status' => $status]);
        respond(['status' => $status, 'position' => $status === 'waitlist' ? $r->waitlistSize() + 1 : 0]);
    }

    case 'cancel_rsvp': {
        $a = $find();
        $r = $rosters[$a->id()];
        $row = null;
        foreach (section_rows('activity_rsvps', $sectionId) as $x) {
            if ((int) $x['activity_id'] === $a->id() && (int) $x['user_id'] === $uid) {
                $row = $x;
            }
        }
        if ($row === null) {
            fail('You haven’t RSVP’d to this.', 404);
        }
        Store::begin();
        Store::delete('activity_rsvps', (int) $row['id']);
        $promoted = null;
        // A seat opened up: DEQUEUE the front of the waitlist into it (first come, first served)
        if ($row['status'] === 'going' && !$a->isOver($now) && !$a->isCancelled() && ($next = $r->nextInLine()) !== null) {
            Store::update('activity_rsvps', $next['rsvp_id'], ['status' => 'going']);
            $promoted = $next['user_id'];
        }
        Store::commit();
        if ($promoted !== null) {
            notify($promoted, $sectionId, 'approved', "A seat opened up: you’re going to “{$a->title()}”", 'activities.html');
        }
        respond(['promoted' => $promoted !== null]);
    }

    case 'check_in': {
        if (!$me->can(Permission::RECORD_ATTENDANCE)) {
            fail('Only the Secretary or the President takes attendance.', 403);
        }
        $a = $find();
        $r = $rosters[$a->id()];
        $who = (int) param('user_id', 0);
        $member = null;
        foreach ($ctx['members'] as $m) {
            if ((int) $m['id'] === $who) {
                $member = $m;
            }
        }
        if ($member === null) {
            fail('Pick someone in this section.', 404);
        }
        if ($a->isCancelled()) {
            fail('This activity was cancelled.', 409);
        }
        if ($now < $a->startTs() - CHECK_IN_EARLY) {
            fail('Attendance opens 1 hour before the activity starts.', 409);
        }
        $isPresent = filter_var(param('present', true), FILTER_VALIDATE_BOOLEAN);
        $name = $member['first_name'] . ' ' . $member['last_name'];
        if ($isPresent) {
            if (!$a->canCheckIn($r->isGoing($who))) {   // [POLYMORPHISM] e.g. a Seminar needs a reserved seat
                fail("$name has no seat for this {$a->kindLabel()}. Seats are reserved by RSVP.", 409);
            }
            if (!$r->isCheckedIn($who)) {               // [DSA: HASHSET] once each
                Store::insert('attendance', ['section_id' => $sectionId, 'activity_id' => $a->id(), 'user_id' => $who, 'checked_in_by' => $uid]);
            }
        } else {
            foreach (section_rows('attendance', $sectionId) as $x) {
                if ((int) $x['activity_id'] === $a->id() && (int) $x['user_id'] === $who) {
                    Store::delete('attendance', (int) $x['id']);
                }
            }
        }
        $fresh = activity_rosters($sectionId)[$a->id()];
        respond(['user_id' => $who, 'present' => $isPresent, 'checked_in' => $fresh->checkedInCount()]);
    }

    default:
        fail('Unknown action.');
}
