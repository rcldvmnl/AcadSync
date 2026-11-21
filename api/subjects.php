<?php
/**
 * GET  /api/subjects.php            -> curriculum (Array) with weekly meetings
 * GET  /api/subjects.php?id=3       -> subject details + its active tasks
 * POST (admin)
 *   action=add          {code, title, instructor, room, color, design?, slots:[{day, start, end, room?}]}   room? = that meeting's own room
 *   action=update       {id, code, title, instructor, room, color, design?, slots:[...]}   (replaces meetings)
 *                       design? = the card's drawing (DESIGNS); missing or 'auto' = picked from the subject's name
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

// The drawing on a subject's card (Frontend/js/ui.js SUBJECT_DESIGNS); 'auto' or anything else = picked from its name
const DESIGNS = ['science', 'algorithm', 'database', 'chart', 'code', 'chip', 'book', 'sport', 'notebook'];

function pick_color(string $color, int $fallbackIndex): string
{
    foreach (COLORS as $c) {
        if ($c === $color) {
            return $c;
        }
    }
    return COLORS[$fallbackIndex % 4];
}

/** A chosen design, or null for "Auto". [ALGORITHM: LINEAR SEARCH] over the short list of designs. */
function pick_design(string $design): ?string
{
    return Algorithms::linearContains(DESIGNS, $design) ? $design : null;
}

function insert_slots(int $sectionId, int $subjectId, array $slots): void
{
    foreach ($slots as $s) {
        Store::insert('class_schedules', [
            'section_id' => $sectionId, 'subject_id' => $subjectId,
            'day_of_week' => $s['day'], 'day_name' => DAY_NAMES[$s['day']],
            'start_time' => $s['start'], 'end_time' => $s['end'], 'room' => $s['room'] ?? null,
        ]);
    }
}

/**
 * [DSA: 2D ARRAY] — clash check. Every other subject's meetings are written into a
 * WeekGrid (6 days × 288 five-minute cells); each new meeting then reads only the cells
 * it would cover. New meetings are written in too, so two new ones can't overlap either.
 * Returns [{day, start, end, code, title, their_start, their_end}] — empty when nothing clashes.
 */
function find_clashes(array $existing, array $newSlots, array $subjectIndex, ?int $ignoreSubjectId): array
{
    $grid = new WeekGrid();
    foreach ($existing as $s) {
        if ($ignoreSubjectId === null || $s['subject_id'] !== $ignoreSubjectId) {
            $grid->place($s['day_of_week'], $s['start_time'], $s['end_time'], $s['subject_id']);
        }
    }
    $clashes = [];
    foreach ($newSlots as $n) {
        foreach ($grid->occupants($n['day'], $n['start'], $n['end']) as $other) {
            $theirs = null;   // which of their meetings it hits, to name its time
            foreach ($existing as $s) {
                if ($s['subject_id'] === $other && $s['day_of_week'] === $n['day']
                    && to_minutes($s['start_time']) < to_minutes($n['end']) && to_minutes($n['start']) < to_minutes($s['end_time'])) {
                    $theirs = $s;
                }
            }
            $subject = $other > 0 ? ($subjectIndex[$other] ?? null) : null;
            $clashes[] = [
                'day' => $n['day'], 'day_name' => DAY_NAMES[$n['day']], 'start' => $n['start'], 'end' => $n['end'],
                'code' => $subject['code'] ?? 'This subject', 'title' => $subject['title'] ?? 'another meeting you just added',
                'their_start' => $theirs['start_time'] ?? null, 'their_end' => $theirs['end_time'] ?? null,
            ];
        }
        $grid->place($n['day'], $n['start'], $n['end'], -1);   // -1 = the subject being saved
    }
    return $clashes;
}

/** Stops the save with the clash list, unless the admin chose "Save anyway" (allow_clash). */
function guard_clashes(array $clashes): void
{
    if ($clashes && !param('allow_clash')) {
        $c = $clashes[0];
        $more = count($clashes) > 1 ? ' (and ' . (count($clashes) - 1) . ' more)' : '';
        fail("This overlaps {$c['code']} on {$c['day_name']}{$more}.", 409, ['clashes' => $clashes]);
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
    case 'check_clash': {
        // Live check while the admin edits times (nothing is saved)
        $id = param('id') ? (int) param('id') : null;
        $slots = clean_slots((array) param('slots', []));
        respond(['clashes' => find_clashes(class_slots($sectionId, $ctx['subjectIndex']), $slots, $ctx['subjectIndex'], $id)]);
    }

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
        guard_clashes(find_clashes(class_slots($sectionId, $ctx['subjectIndex']), $slots, $ctx['subjectIndex'], $editing ? $id : null));
        $row = [
            'code' => $code,
            'title' => $title,
            'instructor' => (string) param('instructor', ''),
            'room' => (string) param('room', ''),
            'color_tag' => pick_color((string) param('color', ''), $ctx['subjects']->size()),
            'design' => pick_design((string) param('design', '')),
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
        // Here the subject's own other meetings count too, so the grid ignores no one
        guard_clashes(find_clashes(class_slots($sectionId, $ctx['subjectIndex']), $slots, $ctx['subjectIndex'], null));
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
