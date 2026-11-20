<?php
/**
 * GET /api/schedule.php[?week=YYYY-MM-DD]
 * Returns the active task timeline (Linked List + Insertion Sort, expired
 * tasks dropped), the urgency view (Bubble Sort), the weekly class grid,
 * subjects with due counts, dashboard stats and the subject Tree.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$now = time();

// [DSA: LINKED LIST] — chronological timeline of approved tasks, expired ones dropped
$timeline = build_timeline($ctx['tasks'], $now);
$active = $timeline->toArray();

// [ALGORITHM: BUBBLE SORT] — the "Urgent" toggle
$byUrgency = Algorithms::bubbleSortByUrgency($active, $now);

// Tasks this user marked as done stay on the schedule, flagged with `done`
$done = done_map((int) $user['id'], (int) $ctx['section']['id']);
// Group tasks: which group (if any) this user is in, so cards can say "Your group" / "Needs a group"
$myGroupForTask = [];
foreach (load_groups((int) $ctx['section']['id']) as $g) {
    if ($g['task_id'] !== null && Algorithms::linearContains($g['members'], (int) $user['id'])) {   // [ALGORITHM: LINEAR SEARCH]
        $myGroupForTask[(int) $g['task_id']] = $g['name'];
    }
}
$present = fn (array $t) => present_task($t, $ctx['subjectIndex'], $ctx['users'])
    + ['done' => isset($done[(int) $t['id']]), 'done_at' => $done[(int) $t['id']] ?? null,
       'my_group' => $myGroupForTask[(int) $t['id']] ?? null];

// Week grid (Mon–Sat) for the requested week
$anchor = strtotime((string) param('week', date('Y-m-d', $now))) ?: $now;
$monday = strtotime('monday this week', $anchor);
$sectionId = (int) $ctx['section']['id'];
$slots = class_slots($sectionId, $ctx['subjectIndex']);
// "No class" announcements cancel a class (or the whole day) on one date only
$cancelled = class_cancellations($sectionId);   // [DSA: HASHSET] "date|subjectId" / "date|*"
$noteFor = [];                                  // the same keys => the announcement's title, for the label
$titles = [];
foreach (section_rows('announcements', $sectionId) as $a) {
    $titles[(int) $a['id']] = $a['title'];
}
foreach (section_rows('class_cancellations', $sectionId) as $row) {
    $noteFor[$row['date'] . '|' . ($row['subject_id'] !== null ? (int) $row['subject_id'] : '*')] = $titles[(int) $row['announcement_id']] ?? 'No class';
}
$days = [];
for ($d = 0; $d < 7; $d++) {   // the whole week, Monday (1) to Sunday (7)
    $ts = strtotime("+$d day", $monday);
    $date = date('Y-m-d', $ts);
    $classes = [];
    foreach (classes_on_day($slots, $d + 1) as $class) {
        $key = $cancelled->contains("$date|*") ? "$date|*" : "$date|{$class['subject_id']}";
        $off = $cancelled->contains($key);
        $classes[] = $class + ['cancelled' => $off, 'cancel_note' => $off ? ($noteFor[$key] ?? 'No class') : null];
    }
    $days[] = ['date' => $date, 'dow' => $d + 1, 'classes' => $classes];
}

// Dashboard numbers
$endOfToday = strtotime('tomorrow', $now) - 1;
$endOfWeek = strtotime('sunday this week 23:59:59', $now);
$dueToday = 0;
$dueWeek = 0;
$subjectsDueToday = [];
$dueBySubject = [];
$nextDue = null;   // earliest deadline the user hasn't finished yet
foreach ($active as $t) {
    if ($nextDue === null && !isset($done[(int) $t['id']])) {
        $nextDue = DsaUtil::deadlineTs($t);
    }
    // [OOP: POLYMORPHISM] — an open date range counts from today, not from its last day
    $ts = Deadlines::fromRow($t)->attentionAt($now);
    if ($ts === null || isset($done[(int) $t['id']])) {   // finished work doesn't count as "due"
        continue;
    }
    if ($ts <= $endOfToday) {
        $dueToday++;
        $subjectsDueToday[(int) $t['subject_id']] = true;
    }
    if ($ts <= $endOfWeek) {
        $dueWeek++;
    }
    if ($ts <= $now + 7 * 86400) {
        $dueBySubject[(int) $t['subject_id']] = ($dueBySubject[(int) $t['subject_id']] ?? 0) + 1;
    }
}

// [DSA: QUEUE] — pending requests, oldest first
$queue = build_queue($sectionId, $ctx['taskIndex']);
$mine = 0;
$oldest = null;
foreach ($queue->toArray() as $q) {
    if ((int) $q['requested_by'] === (int) $user['id']) {
        $mine++;
    }
    $oldest ??= $q['created_at'];
}

$subjects = [];
foreach ($ctx['subjects']->toArray() as $s) {
    $subjects[] = $s + ['due' => $dueBySubject[$s['id']] ?? 0];
}

$members = $ctx['members'];

// [ALGORITHM: BINARY SEARCH] — "Due by Friday": the timeline is already sorted by deadline,
// so halving it finds where Friday 11:59 PM falls; everything before that index is due by then.
// ?before=YYYY-MM-DD picks another day. Default: this Friday (next Friday on a weekend).
$beforeParam = (string) param('before', '');
$dueByDay = preg_match('/^\d{4}-\d{2}-\d{2}$/', $beforeParam) && strtotime($beforeParam)
    ? strtotime($beforeParam)
    : strtotime((int) date('N', $now) >= 6 ? 'friday next week' : 'friday this week', $now);
$limit = strtotime(date('Y-m-d', $dueByDay) . ' 23:59:59');
$steps = 0;
$k = Algorithms::countDueBy($active, $limit, $steps);
$dueByIds = [];
for ($i = 0; $i < $k; $i++) {
    $dueByIds[] = (int) $active[$i]['id'];
}

respond([
    'now' => $now,
    'role' => $user['role'],
    'user' => public_user($user),
    'section' => present_section($ctx['section'], count($members)),
    'timeline' => array_map($present, $active),
    'by_urgency' => array_map($present, $byUrgency),
    'week' => ['monday' => date('Y-m-d', $monday), 'days' => $days,
        // every cancellation ("date|subjectId" / "date|*"), so "Next class" can skip one next week too
        'cancelled' => array_keys($noteFor)],
    'due_by' => ['date' => date('Y-m-d', $limit), 'ids' => $dueByIds, 'steps' => $steps, 'searched' => count($active)],
    'subjects' => $subjects,
    'stats' => [
        'due_today' => $dueToday,
        'due_week' => $dueWeek,
        'subjects_due_today' => count($subjectsDueToday),
        'queue_size' => $queue->size(),
        // [DSA: QUEUE] — people waiting to be let in (only officers see the number)
        'join_requests' => Person::fromRow($user)->can(Permission::REVIEW_MEMBERS) ? build_join_queue($sectionId)->size() : 0,
        'my_pending' => $mine,
        'oldest_request' => $oldest,
        'next_due_ts' => $nextDue,
        'done' => count($done),
    ],
    // [DSA: TREE] — Section -> Subjects -> Tasks
    'tree' => build_tree($ctx['section'], $ctx['subjects'], $timeline)->toArray(),
]);
