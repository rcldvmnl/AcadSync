<?php
/**
 * Bridges raw SQLite rows and the custom data structures.
 * Each loader does a raw `SELECT *`, filters with a loop, then feeds the rows
 * into the matching structure from custom_dsa.php.
 */

declare(strict_types=1);

/** ARRAY — the section's curriculum in a fixed-capacity SubjectArray. */
function load_subjects(int $sectionId): SubjectArray
{
    $array = new SubjectArray();
    foreach (Store::all('subjects') as $row) {
        if ((int) $row['section_id'] === $sectionId) {
            $row['id'] = (int) $row['id'];
            $array->add($row);
        }
    }
    return $array;
}

/** id => subject, for quick lookups when presenting tasks. */
function subject_index(SubjectArray $subjects): array
{
    $map = [];
    for ($i = 0; $i < $subjects->size(); $i++) {
        $s = $subjects->get($i);
        $map[$s['id']] = $s;
    }
    return $map;
}

function user_index(): array
{
    $map = [];
    foreach (Store::all('users') as $u) {
        $map[(int) $u['id']] = $u;
    }
    return $map;
}

/** Every task row belonging to the section's subjects. */
function section_tasks(array $subjectIndex): array
{
    $out = [];
    foreach (Store::all('tasks') as $t) {
        if (isset($subjectIndex[(int) $t['subject_id']])) {
            $out[] = $t;
        }
    }
    return $out;
}

/** Adds subject + requester details and a timestamp the frontend can format. */
function present_task(array $t, array $subjectIndex, array $users): array
{
    $subject = $subjectIndex[(int) $t['subject_id']] ?? null;
    $by = $users[(int) $t['requested_by']] ?? null;
    return [
        'id' => (int) $t['id'],
        'subject_id' => (int) $t['subject_id'],
        'subject_code' => $subject['code'] ?? '',
        'subject_title' => $subject['title'] ?? '',
        'color' => $subject['color_tag'] ?? 'lavender',
        'category' => $t['category'],
        'title' => $t['title'],
        'description' => $t['description'],
        'deadline' => $t['deadline'],
        'deadline_time' => $t['deadline_time'],
        'deadline_end' => $t['deadline_end'],
        'deadline_type' => $t['deadline_type'],
        'deadline_ts' => DsaUtil::deadlineTs($t),
        'location' => $t['location'],
        'status' => $t['status'],
        'kind' => $t['kind'],
        'target_task_id' => $t['target_task_id'] !== null ? (int) $t['target_task_id'] : null,
        'requested_by' => (int) $t['requested_by'],
        'requested_by_name' => $by ? trim($by['first_name'] . ' ' . $by['last_name']) : 'Unknown',
        'requested_by_role' => $by['role'] ?? 'student',
        'created_at' => $t['created_at'],
        'created_ts' => strtotime((string) $t['created_at']) ?: null,
    ];
}

/**
 * LINKED LIST — approved tasks inserted one by one with Insertion Sort,
 * then expired deadlines are dropped by moving the head pointer.
 */
function build_timeline(array $tasks, int $now): TaskLinkedList
{
    $list = new TaskLinkedList();
    foreach ($tasks as $t) {
        if ($t['status'] === 'approved' && $t['kind'] === 'new') {
            $list->insertChronologically(new TaskNode($t));
        }
    }
    $list->removeExpired($now);
    return $list;
}

/** QUEUE — pending requests in arrival order (approval_queue rowid order). */
function build_queue(array $subjectIndex): ApprovalQueue
{
    $tasks = [];
    foreach (Store::all('tasks') as $t) {
        $tasks[(int) $t['id']] = $t;
    }
    $queue = new ApprovalQueue();
    foreach (Store::all('approval_queue') as $row) {
        $t = $tasks[(int) $row['task_id']] ?? null;
        if ($t && $t['status'] === 'pending' && isset($subjectIndex[(int) $t['subject_id']])) {
            $queue->enqueue(['queue_row' => (int) $row['id']] + $t);
        }
    }
    return $queue;
}

/** STACK — rejected requests; the most recent rejection ends up on top. */
function build_undo_stack(array $subjectIndex): UndoStack
{
    $tasks = [];
    foreach (Store::all('tasks') as $t) {
        $tasks[(int) $t['id']] = $t;
    }
    $stack = new UndoStack();
    foreach (Store::all('undo_stack') as $row) {
        $t = $tasks[(int) $row['task_id']] ?? null;
        if ($t && $t['status'] === 'rejected' && isset($subjectIndex[(int) $t['subject_id']])) {
            $stack->push(['stack_row' => (int) $row['id']] + $t);
        }
    }
    return $stack;
}

/** TREE — BSINFO-2A -> subjects -> active tasks. */
function build_tree(array $section, SubjectArray $subjects, TaskLinkedList $timeline): SubjectTree
{
    $tree = new SubjectTree($section['name']);
    for ($i = 0; $i < $subjects->size(); $i++) {
        $tree->addSubject($subjects->get($i));
    }
    foreach ($timeline->toArray() as $t) {
        $tree->addTask((int) $t['subject_id'], ['id' => (int) $t['id'], 'title' => $t['title'], 'deadline' => $t['deadline']]);
    }
    return $tree;
}

function section_members(int $sectionId): array
{
    $out = [];
    foreach (Store::all('users') as $u) {
        if ((int) $u['section_id'] === $sectionId) {
            $out[] = $u;
        }
    }
    return $out;
}

/** GRAPH — study-group edges between members of the section. */
function build_graph(array $members): DependencyGraph
{
    $graph = new DependencyGraph();
    $inSection = [];
    foreach ($members as $m) {
        $inSection[(int) $m['id']] = true;
        $graph->addVertex((int) $m['id']);
    }
    foreach (Store::all('study_groups') as $e) {
        $a = (int) $e['student_a_id'];
        $b = (int) $e['student_b_id'];
        if (isset($inSection[$a], $inSection[$b])) {
            $graph->addEdge($a, $b, $e['project_context']);
        }
    }
    return $graph;
}

/** Weekly class slots for the section, with subject info attached. */
function class_slots(array $subjectIndex): array
{
    $out = [];
    foreach (Store::all('class_schedules') as $c) {
        $subject = $subjectIndex[(int) $c['subject_id']] ?? null;
        if ($subject) {
            $out[] = [
                'id' => (int) $c['id'],
                'subject_id' => (int) $c['subject_id'],
                'day_of_week' => (int) $c['day_of_week'],
                'start_time' => $c['start_time'],
                'end_time' => $c['end_time'],
                'code' => $subject['code'],
                'title' => $subject['title'],
                'color' => $subject['color_tag'],
                'room' => $subject['room'],
                'instructor' => $subject['instructor'],
            ];
        }
    }
    return $out;
}

/** Classes on one weekday, earliest first (Insertion Sort on start time). */
function classes_on_day(array $slots, int $dow): array
{
    $day = [];
    foreach ($slots as $s) {
        if ($s['day_of_week'] === $dow) {
            // insertion sort by start_time ("08:00" < "13:00" as text)
            $i = count($day) - 1;
            $day[] = $s;
            while ($i >= 0 && DsaUtil::compareText($day[$i]['start_time'], $s['start_time']) > 0) {
                $day[$i + 1] = $day[$i];
                $i--;
            }
            $day[$i + 1] = $s;
        }
    }
    return $day;
}

/** Everything the app needs about the user's section. */
function load_context(array $user): array
{
    $section = Store::find('sections', (int) $user['section_id']);
    $subjects = load_subjects((int) $section['id']);
    $subjectIndex = subject_index($subjects);
    return [
        'section' => $section,
        'subjects' => $subjects,
        'subjectIndex' => $subjectIndex,
        'users' => user_index(),
        'tasks' => section_tasks($subjectIndex),
    ];
}

function present_section(array $section, int $memberCount): array
{
    return [
        'id' => (int) $section['id'],
        'name' => $section['name'],
        'class_code' => $section['class_code'],
        'program' => $section['program'],
        'semester' => $section['semester'],
        'members' => $memberCount,
    ];
}

/**
 * LINEAR SEARCH wrapper: approved tasks in the same subject whose text
 * overlaps with the given title/description.
 */
function find_similar(array $tasks, int $subjectId, string $text, ?int $ignoreId, array $subjectIndex, array $users): array
{
    $approved = [];
    foreach ($tasks as $t) {
        if ($t['status'] === 'approved' && $t['kind'] === 'new') {
            $approved[] = $t;
        }
    }
    $out = [];
    foreach (Algorithms::linearSearchTasks($approved, $text, $subjectId, $ignoreId) as $m) {
        $out[] = ['task' => present_task($m['task'], $subjectIndex, $users), 'keywords' => $m['keywords']];
    }
    return $out;
}
