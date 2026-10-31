<?php
/**
 * GET  /api/subjects.php            -> curriculum (Array) with weekly slots
 * GET  /api/subjects.php?id=3       -> subject details + its active tasks
 * POST action=add          {code, title, instructor, room, color, days[], start, end}   (admin)
 * POST action=add_slot     {subject_id, day_of_week, start_time, end_time}            (admin)
 * POST action=remove_slot  {slot_id}                                                  (admin)
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

const COLORS = ['lavender', 'peach', 'yellow', 'mint'];

function valid_time(string $t): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = current_member();
    $ctx = load_context($user);
    $slots = class_slots($ctx['subjectIndex']);
    $id = param('id');

    if ($id === null) {
        $out = [];
        foreach ($ctx['subjects']->toArray() as $s) {
            $mine = [];
            foreach ($slots as $slot) {
                if ($slot['subject_id'] === $s['id']) {
                    $mine[] = $slot;
                }
            }
            $out[] = $s + ['slots' => $mine];
        }
        respond(['subjects' => $out, 'capacity' => $ctx['subjects']->capacity()]);
    }

    $subject = $ctx['subjectIndex'][(int) $id] ?? null;
    if (!$subject) {
        fail('Subject not found.', 404);
    }
    $mySlots = [];
    foreach ($slots as $slot) {
        if ($slot['subject_id'] === $subject['id']) {
            $mySlots[] = $slot;
        }
    }
    $tasks = [];
    foreach (build_timeline($ctx['tasks'], time())->toArray() as $t) {
        if ((int) $t['subject_id'] === $subject['id']) {
            $tasks[] = present_task($t, $ctx['subjectIndex'], $ctx['users']);
        }
    }
    $pending = 0;
    foreach (build_queue($ctx['subjectIndex'])->toArray() as $q) {
        if ((int) $q['subject_id'] === $subject['id']) {
            $pending++;
        }
    }
    respond(['subject' => $subject, 'slots' => $mySlots, 'tasks' => $tasks, 'pending' => $pending, 'role' => $user['role']]);
}

require_method('POST');
$admin = require_admin();
$ctx = load_context($admin);

switch (param('action')) {
    case 'add': {
        $code = strtoupper((string) param('code', ''));
        $title = (string) param('title', '');
        if ($code === '' || $title === '') {
            fail('Subject code and name are required.');
        }
        foreach ($ctx['subjects']->toArray() as $s) {
            if (DsaUtil::compareText($s['code'], $code) === 0) {
                fail("$code is already in the curriculum.", 409);
            }
        }
        if ($ctx['subjects']->size() >= $ctx['subjects']->capacity()) {
            fail('The curriculum array is full.', 409);
        }
        $color = (string) param('color', '');
        $isValidColor = false;
        foreach (COLORS as $c) {
            $isValidColor = $isValidColor || $c === $color;
        }
        if (!$isValidColor) {
            $color = COLORS[$ctx['subjects']->size() % 4];
        }

        $days = (array) param('days', []);
        $start = (string) param('start', '');
        $end = (string) param('end', '');
        if ($days && (!valid_time($start) || !valid_time($end) || $start >= $end)) {
            fail('Pick a start time that is before the end time.');
        }

        Store::begin();
        $id = Store::insert('subjects', [
            'section_id' => (int) $ctx['section']['id'],
            'code' => $code,
            'title' => $title,
            'color_tag' => $color,
            'instructor' => (string) param('instructor', ''),
            'room' => (string) param('room', ''),
        ]);
        $names = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $dayNames = [];
        foreach ($days as $d) {
            $d = (int) $d;
            if ($d >= 1 && $d <= 6) {
                Store::insert('class_schedules', ['subject_id' => $id, 'day_of_week' => $d, 'start_time' => $start, 'end_time' => $end]);
                $dayNames[] = $names[$d];
            }
        }
        Store::commit();

        $when = $dayNames ? ' on ' . implode(' & ', $dayNames) . ' · ' . date('g:i A', strtotime($start)) : '';
        foreach (section_members((int) $ctx['section']['id']) as $m) {
            notify((int) $m['id'], 'schedule', "$code added$when", "subject.html?id=$id");
        }
        respond(['id' => $id], 201);
    }

    case 'add_slot': {
        $subjectId = (int) param('subject_id', 0);
        $dow = (int) param('day_of_week', 0);
        $start = (string) param('start_time', '');
        $end = (string) param('end_time', '');
        if (!isset($ctx['subjectIndex'][$subjectId])) {
            fail('Subject not found.', 404);
        }
        if ($dow < 1 || $dow > 6) {
            fail('Pick a day from Monday to Saturday.');
        }
        if (!valid_time($start) || !valid_time($end) || $start >= $end) {
            fail('Pick a start time that is before the end time.');
        }
        $id = Store::insert('class_schedules', ['subject_id' => $subjectId, 'day_of_week' => $dow, 'start_time' => $start, 'end_time' => $end]);
        respond(['id' => $id], 201);
    }

    case 'remove_slot': {
        $slot = Store::find('class_schedules', (int) param('slot_id', 0));
        if (!$slot || !isset($ctx['subjectIndex'][(int) $slot['subject_id']])) {
            fail('Class not found.', 404);
        }
        Store::delete('class_schedules', (int) $slot['id']);
        respond();
    }

    default:
        fail('Unknown action.');
}
