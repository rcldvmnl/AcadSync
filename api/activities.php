<?php
/**
 * Organization activities (meetings, seminars, outreach, competitions, section projects), RSVPs,
 * attendance, and the parts people volunteer for.
 *
 * GET  /api/activities.php                     -> upcoming + past activities (with their parts), your RSVP,
 *                                                 old_cancelled (cancelled over 3 days ago, kept apart),
 *                                                 your attendance rate, needs_people, my_signups, month, latest_results
 * GET  /api/activities.php?mine=1              -> only my_signups (for Home)
 * GET  /api/activities.php?id=5&roster=1       -> the attendance sheet (officers who take attendance)
 * POST action=create      {kind, title, date, end_date?, start, end, venue?, capacity?, description?,
 *                          audience?: everyone|invited, invited?: [user ids], link?, signup_deadline?, parts?: [{name, details?, spots?, together?, roles?, due_date?}]}  officers
 *      action=edit        {id, title?, description?, venue?, date?, end_date?, start?, end?, link?, signup_deadline?}  officers
 *      action=cancel      {id}                                                               officers
 *      action=delete      {id}            only a cancelled one: removes it with its RSVPs, parts, invites  officers
 *      action=set_result  {id, part_id?, result}               after the activity ends     officers
 *      action=add_part    {id, name, details?, spots?, together?, roles?, due_date?}         officers
 *      action=edit_part   {part_id, …same fields}             never below the people picked officers
 *      action=remove_part {part_id}                           only when nobody is picked    officers
 *      action=pick        {part_id, user_id, role?}           a volunteer → picked          officers
 *      action=assign      {part_id, user_id, role?}           projects only: no volunteering needed  officers
 *      action=unpick      {part_id, user_id}                  back to volunteer (or off)    officers
 *      action=set_role    {part_id, user_id, role}                                          officers
 *      action=volunteer   {part_id, note?, role?}             active members
 *      action=withdraw    {part_id}                           while still a volunteer
 *      action=step_down   {part_id}                           the picked person leaves; the creator is told
 *      action=mark_done   {part_id, done}                     project parts: the people on it, or officers
 *      action=rsvp        {id}            a seat if there is one, otherwise the waitlist (QUEUE)
 *      action=cancel_rsvp {id}            frees your seat; the FRONT of the waitlist gets it
 *      action=check_in    {id, user_id, present: true|false}                    Secretary / President
 *
 * [OOP] Activity::fromRow() builds a Meeting, Seminar, Outreach, Competition or SectionProject; each decides
 *       who may be checked in (canCheckIn), who is expected (expectsAttendanceFrom), how people join (joinsBy)
 *       and whether officers may assign directly (canAssignDirectly). ActivityPart checks itself;
 *       PartRoster decides who may volunteer / be picked. Person::can() decides who may do what.
 * [DSA] ActivityRoster: HashSet of who is going and who came, QUEUE for the waitlist;
 *       PartRoster: QUEUE of volunteers in arrival order, HashSet of who is picked;
 *       HashSet: one notification per person on cancel, the month's days; Merge Sort orders every list.
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
const HIDE_CANCELLED_AFTER = 3 * 86400;   // a cancelled activity stops showing 3 days after it would have ended

$activities = load_activities($sectionId);
$rosters = activity_rosters($sectionId);
$parts = load_parts($sectionId, $activities);          // activity id => ActivityPart[] (position order)
$partRosters = part_rosters($sectionId);               // part id => PartRoster
$canManage = $me->can(Permission::MANAGE_ACTIVITIES);
$today = date('Y-m-d', $now);

/** A member's display name by user id. */
$nameOf = function (int $id) use ($ctx): string {
    $u = $ctx['users'][$id] ?? null;
    return $u ? trim($u['first_name'] . ' ' . $u['last_name']) : 'Former member';
};

/** The activity being acted on, or a 404. */
$find = function () use ($activities): Activity {
    $a = $activities[(int) param('id', 0)] ?? null;
    if ($a === null) {
        fail('Activity not found.', 404);
    }
    return $a;
};

/** The part being acted on with its activity and roster, or a 404. */
$findPart = function () use ($parts, $activities, $partRosters): array {
    $id = (int) param('part_id', 0);
    foreach ($parts as $activityId => $list) {
        foreach ($list as $p) {
            if ($p->id() === $id) {
                return [$p, $activities[$activityId], $partRosters[$id] ?? new PartRoster([])];
            }
        }
    }
    fail('Part not found.', 404);
};

/**
 * The 'invited' list from input, checked against this section's members ([DSA: HASHSET]); fails on anyone else.
 * Returns null when the input doesn't say who can join, [] for everyone.
 * $keep: people already invited (edit), who may stay on the list even if they left the section since.
 */
$invitedInput = function (array $in, array $keep = []) use ($ctx): ?array {
    if (!array_key_exists('audience', $in)) {
        return null;
    }
    if (($in['audience'] ?? 'everyone') !== 'invited') {
        return [];
    }
    $members = new HashSet();
    foreach ($ctx['members'] as $m) {
        $members->add((string) (int) $m['id']);
    }
    foreach ($keep as $id) {
        $members->add((string) (int) $id);
    }
    $ids = [];
    foreach ((array) ($in['invited'] ?? []) as $id) {
        if (!$members->contains((string) (int) $id)) {
            fail('Pick people from this section.');
        }
        $ids[] = (int) $id;
    }
    return $ids;
};

/** Replaces an activity's invite rows with $ids (inside the caller's transaction). */
$saveInvites = function (int $activityId, array $ids) use ($sectionId): void {
    foreach (section_rows('activity_invites', $sectionId) as $row) {
        if ((int) $row['activity_id'] === $activityId) {
            Store::delete('activity_invites', (int) $row['id']);
        }
    }
    $seen = new HashSet();                               // [DSA: HASHSET] once each
    foreach ($ids as $id) {
        if ($seen->add((string) $id)) {
            Store::insert('activity_invites', ['section_id' => $sectionId, 'activity_id' => $activityId, 'user_id' => $id]);
        }
    }
};

/** Stops a change to a cancelled activity's parts (the page hides those buttons; this covers a direct POST). */
$refuseCancelled = function (Activity $a): void {
    if ($a->isCancelled()) {
        fail('This activity was cancelled.', 409);
    }
};

/** A person in this section (by user_id), or a 404. */
$findMember = function () use ($ctx): array {
    $who = (int) param('user_id', 0);
    foreach ($ctx['members'] as $m) {
        if ((int) $m['id'] === $who) {
            return $m;
        }
    }
    fail('Pick someone in this section.', 404);
};

/** The part_members row of one person on one part, re-read from the table (used inside transactions). */
$memberRow = function (int $partId, int $userId) use ($sectionId): ?array {
    foreach (section_rows('part_members', $sectionId) as $m) {
        if ((int) $m['part_id'] === $partId && (int) $m['user_id'] === $userId) {
            return $m;
        }
    }
    return null;
};

/**
 * Starts a transaction that holds the write lock first (a no-op write by primary key), then re-reads this
 * activity's RSVPs. Two people pressing RSVP at once then go one after the other, so the last seat is taken once.
 */
$lockedRoster = function (Activity $a) use ($sectionId): ActivityRoster {
    Store::begin();
    Store::update('activities', $a->id(), ['id' => $a->id()]);   // takes SQLite's write lock before reading
    $rows = [];
    foreach (section_rows('activity_rsvps', $sectionId) as $x) {
        if ((int) $x['activity_id'] === $a->id()) {
            $rows[] = $x;
        }
    }
    return new ActivityRoster($rows, []);
};

/** A fresh PartRoster for one part, re-read inside the transaction so two officers can't overfill it. */
$freshRoster = function (int $partId) use ($sectionId): PartRoster {
    $rows = [];
    foreach (section_rows('part_members', $sectionId) as $m) {
        if ((int) $m['part_id'] === $partId) {
            $rows[] = $m;
        }
    }
    return new PartRoster($rows);
};

/** A role from input: empty → null; otherwise one of the part's roles ([ALGORITHM: LINEAR SEARCH]). */
$roleInput = function (ActivityPart $p): ?string {
    $role = trim((string) param('role', ''));
    if ($role === '') {
        return null;
    }
    if (!Algorithms::linearContains($p->roles(), $role)) {
        fail('Pick one of this part’s roles.');
    }
    return $role;
};

/** One part for the list: who is picked, how many volunteered, this person's own state. */
$presentPart = function (ActivityPart $p) use ($partRosters, $uid, $canManage, $today, $nameOf): array {
    $r = $partRosters[$p->id()] ?? new PartRoster([]);
    $picked = [];
    foreach ($r->picked() as $row) {
        $picked[] = ['id' => (int) $row['user_id'], 'name' => $nameOf((int) $row['user_id']), 'role' => $row['role'] ?? null,
            'assigned' => (int) ($row['volunteered'] ?? 1) === 0];
    }
    $mine = null;
    foreach ([...$r->picked(), ...$r->volunteers()] as $row) {
        if ((int) $row['user_id'] === $uid) {
            $mine = $row;
        }
    }
    $out = $p->toArray($today) + [
        'picked' => $picked, 'picked_count' => $r->pickedCount(), 'volunteer_count' => $r->volunteerCount(),
        'open_spots' => $p->openSpots($r->pickedCount()), 'full' => $p->isFull($r->pickedCount()),
        'me' => ['status' => $r->statusOf($uid), 'role' => $r->roleOf($uid), 'note' => $mine['note'] ?? null],
    ];
    if ($canManage) {   // spec: only officers see the volunteers' names and notes, earliest first (QUEUE order)
        $out['volunteers'] = [];
        foreach ($r->volunteers() as $row) {
            $out['volunteers'][] = ['id' => (int) $row['user_id'], 'name' => $nameOf((int) $row['user_id']),
                'note' => $row['note'] ?? null, 'role' => $row['role'] ?? null, 'created_at' => $row['created_at'] ?? null];
        }
    }
    return $out;
};

/** One activity for the list, with seats, the waitlist and this person's own state (or its parts). */
$present = function (Activity $a) use ($rosters, $uid, $now, $me, $parts, $presentPart, $nameOf, $canManage): array {
    $r = $rosters[$a->id()];
    $row = $a->toArray($now) + [
        'going' => $r->goingCount(), 'waitlist' => $r->waitlistSize(), 'checked_in' => $r->checkedInCount(),
        'full' => !$a->hasRoom($r->goingCount()), 'created_by_name' => $nameOf($a->createdBy()),
        'me' => [
            'going' => $r->isGoing($uid), 'waitlist_position' => $r->waitlistPosition($uid), 'checked_in' => $r->isCheckedIn($uid),
            // [OOP: POLYMORPHISM] only the RSVP kinds take RSVPs
            'can_rsvp' => $a->usesSeats() && $a->isFor($uid) && $me->status()->canRsvp() && !$a->isCancelled() && !$a->isOver($now),
            'can_volunteer' => !$a->usesSeats() && $a->isFor($uid) && $me->status()->canRsvp() && $a->signupOpen($now) && !$a->isOver($now),
            'invited' => $a->isInviteOnly() && $a->isFor($uid),        // the poster chose me
        ],
    ];
    if ($a->isInviteOnly() && $canManage) {               // officers see who was chosen
        $row['invited'] = [];
        foreach ($a->invitedIds() as $id) {
            $row['invited'][] = ['id' => $id, 'name' => $nameOf($id)];
        }
    }
    if ($a->joinsBy() === 'parts') {
        $row['parts'] = [];
        $done = 0;
        $open = 0;
        foreach ($parts[$a->id()] ?? [] as $p) {
            $pr = $presentPart($p);
            $row['parts'][] = $pr;
            $done += $pr['done'] ? 1 : 0;
            $open += $pr['open_spots'] ?? 0;
        }
        $row['open_spots_total'] = $open;
        if ($a->partsNeedDueDates()) {
            $row['progress'] = ['done' => $done, 'total' => count($row['parts'])];
        }
    }
    return $row;
};

/** Every part this person volunteered for or is on, on activities that are still on. Due date first, then start. */
$mySignups = function () use ($activities, $parts, $partRosters, $uid, $now, $today): array {
    $out = [];
    foreach ($activities as $a) {
        if ($a->isCancelled() || $a->isOver($now)) {
            continue;
        }
        foreach ($parts[$a->id()] ?? [] as $p) {
            $r = $partRosters[$p->id()] ?? new PartRoster([]);
            $status = $r->statusOf($uid);
            if ($status === 'none') {
                continue;
            }
            $arr = $p->toArray($today);
            $out[] = ['activity_id' => $a->id(), 'activity_title' => $a->title(), 'kind' => $a->kind(), 'kind_label' => $a->kindLabel(),
                'part_id' => $p->id(), 'part_name' => $p->name(), 'status' => $status, 'role' => $r->roleOf($uid),
                'due_date' => $p->dueDate(), 'overdue' => $arr['overdue'], 'done' => $p->isDone(), 'start_ts' => $a->startTs(),
                'due_ts' => $p->dueDate() !== null ? (int) strtotime($p->dueDate() . ' 23:59') : PHP_INT_MAX];
        }
    }
    $steps = 0;
    $out = Algorithms::mergeSortBy($out, 'start_ts', false, $steps);   // [ALGORITHM: MERGE SORT] stable: start…
    return Algorithms::mergeSortBy($out, 'due_ts', false, $steps);     // …then due date first
};

/* ------------------------------------------------------------------ reads */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (param('mine')) {
        respond(['my_signups' => $mySignups()]);
    }
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
            if (!$a->isFor($id)) {                         // invite-only: the sheet lists the people chosen
                continue;
            }
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
    $oldCancelled = [];
    foreach ($activities as $a) {
        $row = $present($a);
        // Auto-hide: a cancelled activity leaves the lists a few days after its date (nothing is deleted).
        // It is sent apart, so "Show cancelled" can still list it and officers can remove it.
        if ($a->isCancelled() && $a->endTs() < $now - HIDE_CANCELLED_AFTER) {
            $oldCancelled[] = $row;
        } elseif ($a->isOver($now)) {
            $past[] = $row;
        } else {
            $upcoming[] = $row;
        }
    }
    $steps = 0;
    $upcoming = Algorithms::mergeSortBy($upcoming, 'start_ts', false, $steps);
    $past = Algorithms::mergeSortBy($past, 'start_ts', true, $steps);
    $oldCancelled = Algorithms::mergeSortBy($oldCancelled, 'start_ts', true, $steps);   // [ALGORITHM: MERGE SORT] latest first

    // Your own attendance, worked out the same way as in member records
    $mine = null;
    foreach (member_records($ctx) as $rec) {
        if ($rec->person()->id() === $uid) {
            $mine = ['attended' => $rec->attended(), 'expected' => $rec->expected(), 'rate' => $rec->rate()];
        }
    }
    // Needs people: parts with open spots on activities still taking sign-ups, soonest deadline first
    $needs = [];
    foreach ($activities as $a) {
        if ($a->joinsBy() !== 'parts' || !$a->isFor($uid) || !$a->signupOpen($now) || $a->isOver($now)) {
            continue;
        }
        foreach ($parts[$a->id()] ?? [] as $p) {
            $r = $partRosters[$p->id()] ?? new PartRoster([]);
            $open = $p->openSpots($r->pickedCount());
            if ($open !== null && $open > 0 && !$p->isDone()) {
                $needs[] = ['activity_id' => $a->id(), 'activity_title' => $a->title(), 'kind_label' => $a->kindLabel(),
                    'part_id' => $p->id(), 'part_name' => $p->name(), 'open_spots' => $open, 'closes_at' => $a->signupClosesAt(),
                    'my_status' => $r->statusOf($uid)];
            }
        }
    }
    $needs = Algorithms::mergeSortBy($needs, 'closes_at', false, $steps);   // [ALGORITHM: MERGE SORT]

    // This month: [DSA: HASHSET] each day covered by a (not cancelled) activity once; the first activity that day
    $first = date('Y-m-01', $now);
    $last = date('Y-m-t', $now);
    $seen = new HashSet();
    $days = [];
    $firstOn = [];
    $byStart = Algorithms::mergeSortBy(array_map(fn (Activity $a) => ['ts' => $a->startTs(), 'a' => $a], array_values($activities)), 'ts', false, $steps);
    foreach ($byStart as $item) {
        $a = $item['a'];
        if ($a->isCancelled()) {
            continue;
        }
        $endDay = date('Y-m-d', $a->endTs());
        for ($day = $a->startDate(); $day <= $endDay && $day <= $last; $day = date('Y-m-d', (int) strtotime("$day +1 day"))) {
            if ($day >= $first && $seen->add($day)) {
                $days[] = ['d' => $day];
                $firstOn[$day] = $a->id();
            }
        }
    }
    $dayList = [];
    foreach (Algorithms::mergeSortBy($days, 'd', false, $steps) as $x) {
        $dayList[] = $x['d'];
    }

    // Latest results: finished activities with a result (or part results), newest first, at most 3
    $results = [];
    foreach ($activities as $a) {
        if (!$a->isOver($now) || $a->isCancelled()) {
            continue;
        }
        $partResults = [];
        foreach ($parts[$a->id()] ?? [] as $p) {
            if ($p->result() !== null) {
                $partResults[] = ['name' => $p->name(), 'result' => $p->result()];
            }
        }
        if ($a->result() !== null || $partResults) {
            $results[] = ['activity_id' => $a->id(), 'title' => $a->title(), 'kind_label' => $a->kindLabel(), 'result' => $a->result(),
                'parts' => $partResults, 'end_ts' => $a->endTs()];
        }
    }
    $results = Algorithms::mergeSortBy($results, 'end_ts', true, $steps);
    $latest = [];
    foreach ($results as $i => $x) {
        if ($i < 3) {
            $latest[] = $x;
        }
    }

    // Members of the section, for "Assign someone" (officers only)
    $people = [];
    if ($canManage) {
        foreach (Algorithms::selectionSortByLastName($ctx['members']) as $m) {   // [ALGORITHM: SELECTION SORT] A–Z
            if (Person::fromRow($m)->status()->canRsvp()) {
                $people[] = ['id' => (int) $m['id'], 'name' => trim($m['first_name'] . ' ' . $m['last_name'])];
            }
        }
    }

    respond([
        'upcoming' => $upcoming, 'past' => $past, 'my_attendance' => $mine, 'kinds' => Activity::kinds(),
        'can_manage' => $canManage, 'can_record' => $me->can(Permission::RECORD_ATTENDANCE),
        'my_status' => $me->status()->toArray(), 'me_id' => $uid,
        'needs_people' => $needs, 'my_signups' => $mySignups(),
        'month' => ['first' => $first, 'days' => $dayList, 'first_activity' => $firstOn, 'today' => $today],
        'latest_results' => $latest, 'people' => $people, 'old_cancelled' => $oldCancelled,
    ]);
}

/* ------------------------------------------------------------------ writes */
require_method('POST');

switch (param('action')) {
    case 'create': {
        if (!$me->can(Permission::MANAGE_ACTIVITIES)) {
            fail('Only officers can add activities.', 403);
        }
        $newParts = [];
        try {
            // [OOP: ENCAPSULATION] the Activity constructor rejects anything invalid (shared helper:
            // announcements' "Event" builds new activities the same way)
            $in = input();
            $in['invited'] = $invitedInput($in) ?? [];      // only people from this section
            $activity = validate_new_activity($in, $uid, $now);   // [OOP: POLYMORPHISM] parts kinds drop any seat limit
            if ($activity->joinsBy() === 'parts') {
                $list = is_array($in['parts'] ?? null) ? array_values($in['parts']) : [];
                if (!$list) {
                    throw new InvalidArgumentException('Add at least one part.');
                }
                if (count($list) > 30) {
                    throw new InvalidArgumentException('Up to 30 parts.');
                }
                foreach ($list as $i => $p) {
                    // [OOP: ENCAPSULATION] every part checks itself against this activity's kind
                    $newParts[] = ActivityPart::fromRow((is_array($p) ? $p : []) + ['position' => $i], $activity);
                }
            }
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        Store::begin();                                     // the activity and its parts, all or nothing
        $id = Store::insert('activities', $activity->toRow() + ['section_id' => $sectionId]);
        foreach ($newParts as $p) {
            Store::insert('activity_parts', ['activity_id' => $id] + $p->toRow() + ['section_id' => $sectionId]);
        }
        if ($activity->isInviteOnly()) {
            $saveInvites($id, $activity->invitedIds());
        }
        Store::commit();
        $message = $activity->joinsBy() === 'parts'
            ? "New {$activity->kindLabel()}: “{$activity->title()}” · " . ($activity->signupDeadline() !== null
                ? 'sign up by ' . date('D, M j', $activity->signupClosesAt()) : 'starts ' . date('D, M j', $activity->startTs()))
            : "New {$activity->kindLabel()}: “{$activity->title()}” on " . date('D, M j · g:i A', $activity->startTs());
        if ($activity->isInviteOnly()) {                   // only the people the poster chose are told
            foreach ($activity->invitedIds() as $who) {
                if ($who !== $uid) {
                    notify($who, $sectionId, 'schedule', $message . ' · you’re invited', "activities.html#a$id");
                }
            }
        } else {
            notify_section($sectionId, $uid, 'schedule', $message, "activities.html#a$id");
        }
        respond(['id' => $id], 201);
    }

    case 'edit': {
        if (!$canManage) {
            fail('Only officers can edit activities.', 403);
        }
        $a = $find();
        if ($a->isCancelled()) {
            fail('This activity was cancelled.', 409);
        }
        if ($a->isOver($now)) {                             // its attendance is already counted
            fail('This activity has already ended.', 409);
        }
        $row = $a->toRow();
        $in = input();
        $date = array_key_exists('date', $in) ? trim((string) $in['date']) : $a->startDate();
        $oldEndDate = substr($row['ends_at'], 0, 10);
        if (array_key_exists('end_date', $in) && trim((string) $in['end_date']) !== '') {
            $endDate = trim((string) $in['end_date']);
        } elseif (array_key_exists('date', $in) && is_real_date($date)) {
            // Only the first day moved: keep the same number of days
            $days = (int) round((strtotime($oldEndDate) - strtotime($a->startDate())) / 86400);
            $endDate = date('Y-m-d', (int) strtotime("$date +$days day"));
        } else {
            $endDate = $oldEndDate;
        }
        $start = array_key_exists('start', $in) ? trim((string) $in['start']) : substr($row['starts_at'], 11, 5);
        $end = array_key_exists('end', $in) ? trim((string) $in['end']) : substr($row['ends_at'], 11, 5);
        if (!is_real_date($date) || !is_real_date($endDate) || !valid_time($start) || !valid_time($end)) {
            fail('Pick a date and the start and end times.');
        }
        foreach (['title', 'description', 'venue', 'link', 'signup_deadline'] as $key) {
            if (array_key_exists($key, $in)) {
                $row[$key] = $in[$key] === null ? null : trim((string) $in[$key]);
            }
        }
        if ("$date $start" !== $row['starts_at'] && strtotime("$date $start") < $now - 300) {
            // same rule as a new activity: moving it into the past would count it as missed by everyone
            fail('That start time has already passed. Pick a later time or another day.');
        }
        $row['starts_at'] = "$date $start";
        $row['ends_at'] = "$endDate $end";
        $row['invited'] = $a->invitedIds();
        $newInvites = $invitedInput($in, $a->invitedIds()); // null = who can join stays as it was
        if ($newInvites !== null) {
            $row['audience'] = $in['audience'] === 'invited' ? 'invited' : 'everyone';
            $row['invited'] = $newInvites;
        }
        try {
            // [OOP: ENCAPSULATION] rebuilt through the constructor, so an edit can't make it invalid
            $edited = Activity::fromRow($row + ['id' => $a->id(), 'status' => 'scheduled']);
            foreach ($parts[$a->id()] ?? [] as $p) {
                ActivityPart::fromRow($p->toRow() + ['id' => $p->id()], $edited);   // e.g. a due date after the new last day
            }
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        $cols = $edited->toRow();
        unset($cols['kind'], $cols['created_by'], $cols['result']);
        $droppedOut = new HashSet();                       // [DSA: HASHSET] each person told once
        $removed = [];
        $promoted = [];
        Store::begin();
        Store::update('activities', $a->id(), $cols);
        if ($newInvites !== null) {
            $saveInvites($a->id(), $edited->invitedIds());
        }
        if ($newInvites !== null && $edited->isInviteOnly()) {
            // People who aren't invited any more lose their seat, their waitlist place and their parts…
            $kept = [];
            foreach (section_rows('activity_rsvps', $sectionId) as $x) {
                if ((int) $x['activity_id'] !== $a->id()) {
                    continue;
                }
                if ($edited->isFor((int) $x['user_id'])) {
                    $kept[] = $x;
                } else {
                    Store::delete('activity_rsvps', (int) $x['id']);
                    if ($droppedOut->add((string) (int) $x['user_id'])) {
                        $removed[] = (int) $x['user_id'];
                    }
                }
            }
            $partIds = new HashSet();
            foreach ($parts[$a->id()] ?? [] as $p) {
                $partIds->add((string) $p->id());
            }
            foreach (section_rows('part_members', $sectionId) as $m) {
                if ($partIds->contains((string) (int) $m['part_id']) && !$edited->isFor((int) $m['user_id'])) {
                    Store::delete('part_members', (int) $m['id']);
                    if ($droppedOut->add((string) (int) $m['user_id'])) {
                        $removed[] = (int) $m['user_id'];
                    }
                }
            }
            // …and the seats they freed go to the invited people waiting, front of the QUEUE first
            foreach ((new ActivityRoster($kept, []))->seatsToFill($edited) as $w) {
                Store::update('activity_rsvps', $w['rsvp_id'], ['status' => 'going']);
                $promoted[] = $w['user_id'];
            }
        }
        Store::commit();
        foreach ($removed as $who) {
            if ($who !== $uid) {
                notify($who, $sectionId, 'moved', "“{$edited->title()}” is now only for the people invited, so you’re no longer on it", 'activities.html');
            }
        }
        foreach ($promoted as $who) {
            notify($who, $sectionId, 'approved', "A seat opened up: you’re going to “{$edited->title()}”", "activities.html#a{$a->id()}");
        }
        if ($edited->isInviteOnly()) {                     // tell the people who are newly invited
            foreach ($edited->invitedIds() as $who) {
                if ($who !== $uid && !($a->isInviteOnly() && $a->isFor($who))) {
                    notify($who, $sectionId, 'schedule', "You’re invited to “{$edited->title()}”", "activities.html#a{$a->id()}");
                }
            }
        }
        respond(['id' => $a->id()]);
    }

    case 'set_result': {
        if (!$canManage) {
            fail('Only officers can enter results.', 403);
        }
        $a = $find();
        if (!$a->isOver($now)) {
            fail('Add the result after the activity ends.', 409);
        }
        $result = trim((string) param('result', ''));
        $pid = (int) param('part_id', 0);
        try {
            if ($pid) {
                $part = null;
                foreach ($parts[$a->id()] ?? [] as $p) {
                    if ($p->id() === $pid) {
                        $part = $p;
                    }
                }
                if ($part === null) {
                    fail('Part not found.', 404);
                }
                ActivityPart::fromRow($part->toRow() + ['result' => $result], $a);   // checks the length
                Store::update('activity_parts', $pid, ['result' => $result === '' ? null : $result]);
            } else {
                Activity::fromRow(['result' => $result] + $a->toRow());   // checks the length
                Store::update('activities', $a->id(), ['result' => $result === '' ? null : $result]);
            }
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        respond();
    }

    case 'add_part': {
        if (!$canManage) {
            fail('Only officers can change the parts.', 403);
        }
        $a = $find();
        if ($a->joinsBy() !== 'parts') {
            fail('This activity has no parts.', 409);
        }
        if ($a->isCancelled()) {
            fail('This activity was cancelled.', 409);
        }
        $position = 0;
        foreach ($parts[$a->id()] ?? [] as $p) {
            $position = max($position, $p->position() + 1);
        }
        try {
            $part = ActivityPart::fromRow(input() + ['position' => $position], $a);
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        $id = Store::insert('activity_parts', ['activity_id' => $a->id(), 'position' => $position] + $part->toRow() + ['section_id' => $sectionId]);
        respond(['part_id' => $id], 201);
    }

    case 'edit_part': {
        if (!$canManage) {
            fail('Only officers can change the parts.', 403);
        }
        [$p, $a, $r] = $findPart();
        $refuseCancelled($a);
        $row = $p->toRow();
        $in = input();
        foreach (['name', 'details', 'spots', 'together', 'roles', 'due_date'] as $key) {
            if (array_key_exists($key, $in)) {
                $row[$key] = $in[$key];
            }
        }
        try {
            $edited = ActivityPart::fromRow($row + ['id' => $p->id()], $a);
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        if (($why = $edited->whyCantResize($edited->spots(), $r->pickedCount())) !== null) {
            fail($why, 409);
        }
        $cols = $edited->toRow();
        unset($cols['activity_id'], $cols['position']);
        Store::begin();
        Store::update('activity_parts', $p->id(), $cols);
        // A role that was taken out is cleared from whoever had it ([ALGORITHM: LINEAR SEARCH] over the part's roles)
        foreach ([...$r->picked(), ...$r->volunteers()] as $m) {
            if (($m['role'] ?? null) !== null && !Algorithms::linearContains($edited->roles(), $m['role'])) {
                Store::update('part_members', (int) $m['id'], ['role' => null]);
            }
        }
        Store::commit();
        respond();
    }

    case 'remove_part': {
        if (!$canManage) {
            fail('Only officers can change the parts.', 403);
        }
        [$p, $a, $r] = $findPart();
        if (($why = $p->whyCantResize(0, $r->pickedCount())) !== null) {   // removing = shrinking to 0
            fail($why, 409);
        }
        if (count($parts[$a->id()] ?? []) <= 1) {
            fail('A competition or project needs at least one part.', 409);
        }
        Store::begin();
        foreach ($r->volunteers() as $row) {
            Store::delete('part_members', (int) $row['id']);
        }
        Store::delete('activity_parts', $p->id());
        Store::commit();
        foreach ($r->volunteers() as $row) {
            notify((int) $row['user_id'], $sectionId, 'moved', "“{$p->name()}” was taken out of “{$a->title()}”", "activities.html#a{$a->id()}");
        }
        respond();
    }

    case 'volunteer': {
        [$p, $a, $r] = $findPart();
        // [OOP: POLYMORPHISM] the member's status decides; PartRoster knows who is already on it
        if (!$a->isFor($uid)) {
            fail('This activity is only for the people the poster invited.', 403);
        }
        if (($why = $r->whyCantVolunteer($a, $uid, $me->status()->canRsvp(), $now)) !== null) {
            fail($why, 409);
        }
        $note = trim((string) param('note', ''));
        if (strlen($note) > 300) {
            fail('Keep your note under 300 characters.');
        }
        $role = $roleInput($p);
        try {
            Store::insert('part_members', ['section_id' => $sectionId, 'part_id' => $p->id(), 'user_id' => $uid, 'status' => 'volunteer',
                'volunteered' => 1, 'role' => $role, 'note' => $note === '' ? null : $note]);
        } catch (PDOException $e) {
            if (!is_unique_violation($e)) {                 // a real error, not a double tap
                throw $e;
            }
            fail('You already volunteered for this part.', 409);   // UNIQUE(part_id, user_id)
        }
        respond(['status' => 'volunteer'], 201);   // spec: no notification, officers read the list
    }

    case 'withdraw': {
        [$p, $a, $r] = $findPart();
        $status = $r->statusOf($uid);
        if ($status === 'picked') {
            fail('You’re picked for this part. Use Step down instead.', 409);
        }
        $row = $memberRow($p->id(), $uid);
        if ($status !== 'volunteer' || $row === null) {
            fail('You haven’t volunteered for this part.', 404);
        }
        Store::delete('part_members', (int) $row['id']);
        respond();
    }

    case 'pick':
    case 'assign': {
        if (!$canManage) {
            fail('Only officers can pick people.', 403);
        }
        [$p, $a, ] = $findPart();
        $direct = param('action') === 'assign';
        if ($direct && !$a->canAssignDirectly()) {          // [OOP: POLYMORPHISM] only a SectionProject
            fail('In a competition, pick from the volunteers.');
        }
        if ($a->isCancelled()) {
            fail('This activity was cancelled.', 409);
        }
        $member = $findMember();
        $who = (int) $member['id'];
        if (!$a->isFor($who)) {
            fail('Pick someone who was invited.', 409);
        }
        if (!Person::fromRow($member)->status()->canRsvp()) {
            fail('Alumni members can’t be on a part.', 409);
        }
        $role = $roleInput($p);
        Store::begin();
        // Re-read this part's people inside the transaction: two officers picking the last spot can't both get it
        $fresh = $freshRoster($p->id());
        if (($why = $fresh->whyCantPick($p, $a, $who, $direct)) !== null) {
            Store::rollbackIfOpen();
            fail($why, 409);
        }
        $row = $memberRow($p->id(), $who);
        $stamp = date('Y-m-d H:i:s', $now);
        if ($row !== null) {
            Store::update('part_members', (int) $row['id'], ['status' => 'picked', 'role' => $role ?? $row['role'], 'picked_by' => $uid, 'picked_at' => $stamp]);
            $role = $role ?? $row['role'];
        } else {
            Store::insert('part_members', ['section_id' => $sectionId, 'part_id' => $p->id(), 'user_id' => $who, 'status' => 'picked',
                'volunteered' => 0, 'role' => $role, 'picked_by' => $uid, 'picked_at' => $stamp]);
        }
        Store::commit();
        if ($who !== $uid) {
            $as = $role !== null && $role !== '' ? " as $role" : '';
            $message = $direct || $a->partsNeedDueDates()
                ? "You’re on “{$p->name()}” for {$a->title()}$as" . ($p->dueDate() !== null ? ' · due ' . date('D, M j', (int) strtotime($p->dueDate())) : '')
                : "You were picked for “{$p->name()}” ({$a->title()})$as";
            notify($who, $sectionId, 'approved', $message, "activities.html#a{$a->id()}");
        }
        respond(['status' => 'picked']);
    }

    case 'unpick': {
        if (!$canManage) {
            fail('Only officers can un-pick people.', 403);
        }
        [$p, $a, ] = $findPart();
        $refuseCancelled($a);
        $who = (int) param('user_id', 0);
        $row = $memberRow($p->id(), $who);
        if ($row === null || $row['status'] !== 'picked') {
            fail('That person isn’t picked for this part.', 409);
        }
        if ((int) $row['volunteered'] === 1) {
            Store::update('part_members', (int) $row['id'], ['status' => 'volunteer', 'picked_by' => null, 'picked_at' => null]);
        } else {
            Store::delete('part_members', (int) $row['id']);
        }
        if ($who !== $uid) {
            notify($who, $sectionId, 'moved', "You’re no longer on “{$p->name()}” ({$a->title()})", "activities.html#a{$a->id()}");
        }
        respond();
    }

    case 'set_role': {
        if (!$canManage) {
            fail('Only officers can give roles.', 403);
        }
        [$p, $a, ] = $findPart();
        $refuseCancelled($a);
        $row = $memberRow($p->id(), (int) param('user_id', 0));
        if ($row === null) {
            fail('That person isn’t on this part.', 404);
        }
        Store::update('part_members', (int) $row['id'], ['role' => $roleInput($p)]);
        respond();
    }

    case 'step_down': {
        [$p, $a, $r] = $findPart();
        $row = $memberRow($p->id(), $uid);
        if ($r->statusOf($uid) !== 'picked' || $row === null) {
            fail('You’re not picked for this part.', 409);
        }
        Store::delete('part_members', (int) $row['id']);
        if ($a->createdBy() !== $uid) {
            notify($a->createdBy(), $sectionId, 'moved', $nameOf($uid) . " stepped down from “{$p->name()}” ({$a->title()})", "activities.html#a{$a->id()}");
        }
        respond();
    }

    case 'mark_done': {
        [$p, $a, $r] = $findPart();
        $refuseCancelled($a);
        if (!$a->partsNeedDueDates()) {                     // [OOP: POLYMORPHISM] only project parts are "done"
            fail('Only project parts are marked done.', 409);
        }
        if ($r->statusOf($uid) !== 'picked' && !$canManage) {
            fail('Only the people on this part can mark it done.', 403);
        }
        $done = filter_var(param('done', true), FILTER_VALIDATE_BOOLEAN);
        Store::update('activity_parts', $p->id(), $done
            ? ['done_at' => date('Y-m-d H:i:s', $now), 'done_by' => $uid]
            : ['done_at' => null, 'done_by' => null]);
        if ($done && !$p->isDone() && $a->createdBy() !== $uid) {
            notify($a->createdBy(), $sectionId, 'approved', "“{$p->name()}” is done ({$a->title()}) · " . $nameOf($uid), "activities.html#a{$a->id()}");
        }
        respond(['done' => $done]);
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
        $told = new HashSet();                              // [DSA: HASHSET] one notification per person
        $told->add((string) $uid);
        foreach ($ctx['members'] as $m) {
            $id = (int) $m['id'];
            $onAPart = false;
            foreach ($parts[$a->id()] ?? [] as $p) {
                $onAPart = $onAPart || ($partRosters[$p->id()] ?? new PartRoster([]))->statusOf($id) !== 'none';
            }
            if (($r->isGoing($id) || $r->waitlistPosition($id) || $onAPart) && $told->add((string) $id)) {
                notify($id, $sectionId, 'moved', "“{$a->title()}” was cancelled", 'activities.html');
            }
        }
        respond();
    }

    case 'delete': {
        // Remove a CANCELLED activity for good, with everything that hangs off it (foreign keys are on,
        // so the rows that point at it go first). Announcements that pointed at it then read "No longer available".
        if (!$canManage) {
            fail('Only officers can remove activities.', 403);
        }
        $a = $find();
        if (!$a->isCancelled()) {
            fail('Cancel the activity first, then remove it.', 409);
        }
        $id = $a->id();
        $partIds = new HashSet();                           // [DSA: HASHSET] this activity's parts
        foreach (section_rows('activity_parts', $sectionId) as $p) {
            if ((int) $p['activity_id'] === $id) {
                $partIds->add((string) (int) $p['id']);
            }
        }
        Store::begin();
        foreach (section_rows('part_members', $sectionId) as $m) {
            if ($partIds->contains((string) (int) $m['part_id'])) {
                Store::delete('part_members', (int) $m['id']);
            }
        }
        foreach (['activity_parts', 'activity_rsvps', 'attendance', 'activity_invites'] as $table) {
            foreach (section_rows($table, $sectionId) as $row) {
                if ((int) $row['activity_id'] === $id) {
                    Store::delete($table, (int) $row['id']);
                }
            }
        }
        Store::delete('activities', $id);
        Store::commit();
        respond(['removed' => $id]);
    }

    case 'rsvp': {
        $a = $find();
        $r = $rosters[$a->id()];
        if (!$a->usesSeats()) {                             // [OOP: POLYMORPHISM] competitions and projects
            fail('Volunteer for a part instead.', 409);
        }
        if (!$a->isFor($uid)) {                     // invite-only: the poster chose who may join
            fail('This activity is only for the people the poster invited.', 403);
        }
        if (!$me->status()->canRsvp()) {            // [POLYMORPHISM] the member's status decides
            fail('Alumni members can’t RSVP.', 403);
        }
        if ($a->isCancelled() || $a->isOver($now)) {
            fail('This activity is no longer open for RSVPs.', 409);
        }
        $r = $lockedRoster($a);                     // re-read under the lock: nobody else takes the seat meanwhile
        if ($r->isGoing($uid) || $r->waitlistPosition($uid)) {
            Store::rollbackIfOpen();
            fail('You already RSVP’d.', 409);
        }
        // A seat if there is one; otherwise ENQUEUE at the rear of the waitlist
        $status = $a->hasRoom($r->goingCount()) ? 'going' : 'waitlist';
        Store::insert('activity_rsvps', ['section_id' => $sectionId, 'activity_id' => $a->id(), 'user_id' => $uid, 'status' => $status]);
        Store::commit();
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
        $r = $lockedRoster($a);                             // fresh waitlist under the lock (two cancels at once)
        Store::delete('activity_rsvps', (int) $row['id']);
        $promoted = null;
        // A seat opened up: the first INVITED person in the waitlist QUEUE gets it (first come, first served).
        // Anyone waiting who may no longer join (the invite list changed) is dropped instead.
        if ($row['status'] === 'going' && !$a->isOver($now) && !$a->isCancelled()) {
            foreach ($r->waitingNotFor($a) as $w) {
                Store::delete('activity_rsvps', $w['rsvp_id']);
            }
            if (($next = $r->nextInLineFor($a)) !== null) {
                Store::update('activity_rsvps', $next['rsvp_id'], ['status' => 'going']);
                $promoted = $next['user_id'];
            }
        }
        Store::commit();
        if ($promoted !== null) {
            notify($promoted, $sectionId, 'approved', "A seat opened up: you’re going to “{$a->title()}”", "activities.html#a{$a->id()}");
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
        if (!$a->usesSeats()) {
            fail('Competitions and projects don’t take attendance.', 409);
        }
        if ($now < $a->startTs() - CHECK_IN_EARLY) {
            fail('Attendance opens 1 hour before the activity starts.', 409);
        }
        $isPresent = filter_var(param('present', true), FILTER_VALIDATE_BOOLEAN);
        $name = $member['first_name'] . ' ' . $member['last_name'];
        if ($isPresent) {
            if (!$a->isFor($who)) {
                fail("$name isn’t invited to this activity.", 409);
            }
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
