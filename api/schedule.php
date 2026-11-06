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
$present = fn (array $t) => present_task($t, $ctx['subjectIndex'], $ctx['users'])
    + ['done' => isset($done[(int) $t['id']]), 'done_at' => $done[(int) $t['id']] ?? null];

// Week grid (Mon–Sat) for the requested week
$anchor = strtotime((string) param('week', date('Y-m-d', $now))) ?: $now;
$monday = strtotime('monday this week', $anchor);
$sectionId = (int) $ctx['section']['id'];
$slots = class_slots($sectionId, $ctx['subjectIndex']);
$days = [];
for ($d = 0; $d < 7; $d++) {   // the whole week, Monday (1) to Sunday (7)
    $ts = strtotime("+$d day", $monday);
    $days[] = ['date' => date('Y-m-d', $ts), 'dow' => $d + 1, 'classes' => classes_on_day($slots, $d + 1)];
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
    $ts = DsaUtil::deadlineTs($t);
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

respond([
    'now' => $now,
    'role' => $user['role'],
    'user' => public_user($user),
    'section' => present_section($ctx['section'], count($members)),
    'timeline' => array_map($present, $active),
    'by_urgency' => array_map($present, $byUrgency),
    'week' => ['monday' => date('Y-m-d', $monday), 'days' => $days],
    'subjects' => $subjects,
    'stats' => [
        'due_today' => $dueToday,
        'due_week' => $dueWeek,
        'subjects_due_today' => count($subjectsDueToday),
        'queue_size' => $queue->size(),
        'my_pending' => $mine,
        'oldest_request' => $oldest,
        'next_due_ts' => $nextDue,
        'done' => count($done),
    ],
    // [DSA: TREE] — Section -> Subjects -> Tasks
    'tree' => build_tree($ctx['section'], $ctx['subjects'], $timeline)->toArray(),
]);
