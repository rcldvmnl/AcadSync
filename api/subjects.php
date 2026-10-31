<?php
/**
 * GET  /api/subjects.php            -> curriculum (Array) with weekly meetings
 * GET  /api/subjects.php?id=3       -> subject details + its active tasks
 * POST (admin)
 *   action=add          {code, title, instructor, room, color, slots:[{day, start, end}]}
 *   action=update       {id, code, title, instructor, room, color, slots:[...]}   (replaces meetings)
 *   action=add_slot     {subject_id, day_of_week, start_time, end_time}
 *   action=remove_slot  {slot_id}
 *
 * Every meeting has its own day AND time, e.g. Monday 13:00–14:00 and
 * Tuesday 14:00–17:00 for the same subject.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

const COLORS = ['lavender', 'peach', 'yellow', 'mint'];

function slots_of(array $slots, int $subjectId): array
{
    $out = [];
    foreach ($slots as $slot) {
        if ($slot['subject_id'] === $subjectId) {
            $out[] = $slot;
        }
    }
    return $out;
}

function pick_color(string $color, int $fallbackIndex): string
{
    foreach (COLORS as $c) {
        if ($c === $color) {
            return $c;
        }
    }
    return COLORS[$fallbackIndex % 4];
}

function insert_slots(int $sectionId, int $subjectId, array $slots): void
{
    foreach ($slots as $s) {
        Store::insert('class_schedules', [
            'section_id' => $sectionId, 'subject_id' => $subjectId,
            'day_of_week' => $s['day'], 'day_name' => DAY_NAMES[$s['day']],
            'start_time' => $s['start'], 'end_time' => $s['end'],
        ]);
    }
}

/** "Mon 1:00 PM" summaries for notifications. */
function slot_summary(array $slots): string
{
    $parts = [];
    foreach ($slots as $s) {
        $parts[] = substr(DAY_NAMES[$s['day']], 0, 3) . ' ' . date('g:i A', strtotime($s['start']));
    }
    return implode(', ', $parts);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = current_member();
    $ctx = load_context($user);
    $sectionId = (int) $ctx['section']['id'];
    $slots = class_slots($sectionId, $ctx['subjectIndex']);
    $id = param('id');

    if ($id === null) {
        $out = [];
        // [DSA: ARRAY] — read every subject record back by index
        for ($i = 0; $i < $ctx['subjects']->size(); $i++) {
            $s = $ctx['subjects']->get($i);
            $out[] = $s + ['index' => $i, 'slots' => slots_of($slots, $s['id'])];
        }
        respond(['subjects' => $out, 'capacity' => $ctx['subjects']->capacity(), 'role' => $user['role']]);
    }

    $subject = $ctx['subjectIndex'][(int) $id] ?? null;
    if (!$subject) {
        fail('Subject not found.', 404);
    }
    $tasks = [];
    foreach (build_timeline($ctx['tasks'], time())->toArray() as $t) {
        if ((int) $t['subject_id'] === $subject['id']) {
            $tasks[] = present_task($t, $ctx['subjectIndex'], $ctx['users']);
        }
    }
    $pending = 0;
    foreach (build_queue($sectionId, $ctx['taskIndex'])->toArray() as $q) {
        if ((int) $q['subject_id'] === $subject['id']) {
            $pending++;
        }
    }
    respond(['subject' => $subject, 'slots' => slots_of($slots, $subject['id']), 'tasks' => $tasks, 'pending' => $pending, 'role' => $user['role']]);
}

require_method('POST');
$admin = require_admin();
$ctx = load_context($admin);
$sectionId = (int) $ctx['section']['id'];

switch (param('action')) {
    case 'add':
    case 'update': {
        $editing = param('action') === 'update';
        $id = (int) param('id', 0);
        if ($editing && !isset($ctx['subjectIndex'][$id])) {
            fail('Subject not found.', 404);
        }
        $code = strtoupper(preg_replace('/\s+/', ' ', (string) param('code', '')));
        $title = (string) param('title', '');
        if ($code === '' || $title === '') {
            fail('Subject code and name are required.');
        }

        // [DSA: HASHSET] — subject codes must be unique inside the section
        $codes = new HashSet();
        foreach ($ctx['subjects']->toArray() as $s) {
            if (!$editing || $s['id'] !== $id) {
                $codes->add($s['code']);
            }
        }
        if (!$codes->add($code)) {
            fail("$code is already in this section’s subjects.", 409);
        }
        // [DSA: ARRAY] — the curriculum array has a fixed capacity
        if (!$editing && $ctx['subjects']->size() >= $ctx['subjects']->capacity()) {
            fail('This section already has the maximum of ' . $ctx['subjects']->capacity() . ' subjects.', 409);
        }

        $slots = clean_slots((array) param('slots', []));
        $row = [
            'code' => $code,
            'title' => $title,
            'instructor' => (string) param('instructor', ''),
            'room' => (string) param('room', ''),
            'color_tag' => pick_color((string) param('color', ''), $ctx['subjects']->size()),
        ];

        Store::begin();
        if ($editing) {
            Store::update('subjects', $id, $row);
            foreach (section_rows('class_schedules', $sectionId) as $c) {
                if ((int) $c['subject_id'] === $id) {
                    Store::delete('class_schedules', (int) $c['id']);
                }
            }
        } else {
            $id = Store::insert('subjects', $row + ['section_id' => $sectionId]);
        }
        insert_slots($sectionId, $id, $slots);
        Store::commit();

        $when = $slots ? ' · ' . slot_summary($slots) : '';
        notify_section($sectionId, (int) $admin['id'], 'schedule', ($editing ? "$code schedule updated" : "$code added") . $when, "subject.html?id=$id");
        respond(['id' => $id], $editing ? 200 : 201);
    }

    case 'add_slot': {
        $subjectId = (int) param('subject_id', 0);
        if (!isset($ctx['subjectIndex'][$subjectId])) {
            fail('Subject not found.', 404);
        }
        $slots = clean_slots([['day' => param('day_of_week'), 'start' => param('start_time'), 'end' => param('end_time')]]);
        insert_slots($sectionId, $subjectId, $slots);
        respond([], 201);
    }

    case 'remove_slot': {
        $slot = Store::find('class_schedules', (int) param('slot_id', 0));
        if (!$slot || (int) $slot['section_id'] !== $sectionId) {
            fail('Class not found.', 404);
        }
        Store::delete('class_schedules', (int) $slot['id']);
        respond();
    }

    default:
        fail('Unknown action.');
}
