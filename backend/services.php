<?php
/**
 * Bridges raw SQLite rows and the custom data structures.
 * Each loader does a raw `SELECT *` (Store::all), keeps the rows of ONE section
 * with a simple loop, then feeds them into the matching structure from
 * custom_dsa.php. Search for "[DSA:" to find every structure in use.
 */

declare(strict_types=1);

const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

/** Raw rows of a table that belong to one section. */
function section_rows(string $table, int $sectionId): array
{
    $out = [];
    foreach (Store::all($table) as $row) {
        if ((int) $row['section_id'] === $sectionId) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * [DSA: ARRAY] — storing and accessing a collection of records.
 * The section's subject records are stored in a fixed-capacity SubjectArray
 * and read back by index everywhere subjects are listed.
 */
function load_subjects(int $sectionId): SubjectArray
{
    $array = new SubjectArray();
    foreach (section_rows('subjects', $sectionId) as $row) {
        $row['id'] = (int) $row['id'];
        $array->add($row);
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

/** Adds subject + requester details and a timestamp the frontend can format. */
function present_task(array $t, array $subjectIndex, array $users): array
{
    $subject = $subjectIndex[(int) $t['subject_id']] ?? null;
    $by = $users[(int) $t['requested_by']] ?? null;
    return [
        'id' => (int) $t['id'],
        'section_id' => (int) $t['section_id'],
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
        'deadline_label' => Deadlines::fromRow($t)->label(),   // [OOP] each Deadline class words itself
        'location' => $t['location'],
        'work_mode' => $t['work_mode'] ?? 'individual',
        'group_min' => isset($t['group_min']) ? (int) $t['group_min'] : null,
        'group_max' => isset($t['group_max']) ? (int) $t['group_max'] : null,
        'group_count' => isset($t['group_count']) ? (int) $t['group_count'] : null,
        'status' => $t['status'],
        'kind' => $t['kind'],
        'target_task_id' => $t['target_task_id'] !== null ? (int) $t['target_task_id'] : null,
        'requested_by' => (int) $t['requested_by'],
        'requested_by_name' => $by ? trim($by['first_name'] . ' ' . $by['last_name']) : 'Unknown',
        'requested_by_role' => $t['requested_by_role'] ?? null,
        'created_at' => $t['created_at'],
        'created_ts' => strtotime((string) $t['created_at']) ?: null,
    ];
}

/**
 * [DSA: LINKED LIST] — managing a sequence of records through insertion and deletion.
 * INSERTION: each approved task is spliced in by deadline (Insertion Sort).
 * DELETION:  expired tasks are removed from the head (head = head->next).
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

/** task_id => done_at for every task this user has marked as done in the section. */
function done_map(int $userId, int $sectionId): array
{
    $map = [];
    foreach (section_rows('task_done', $sectionId) as $row) {
        if ((int) $row['user_id'] === $userId) {
            $map[(int) $row['task_id']] = $row['done_at'];
        }
    }
    return $map;
}

/** id => task row for the section. */
function task_index(array $tasks): array
{
    $map = [];
    foreach ($tasks as $t) {
        $map[(int) $t['id']] = $t;
    }
    return $map;
}

/**
 * [DSA: QUEUE] — managing a waiting list of service requests in order.
 * Pending requests are enqueued in arrival order (approval_queue row order).
 */
function build_queue(int $sectionId, array $taskIndex): ApprovalQueue
{
    $queue = new ApprovalQueue();
    foreach (section_rows('approval_queue', $sectionId) as $row) {
        $t = $taskIndex[(int) $row['task_id']] ?? null;
        if ($t && $t['status'] === 'pending') {
            $queue->enqueue(['queue_row' => (int) $row['id']] + $t);
        }
    }
    return $queue;
}

/**
 * [DSA: STACK] — undo history. Rejected requests are pushed in the order they
 * were rejected, so the most recent rejection ends up on top.
 */
function build_undo_stack(int $sectionId, array $taskIndex): UndoStack
{
    $stack = new UndoStack();
    foreach (section_rows('undo_stack', $sectionId) as $row) {
        $t = $taskIndex[(int) $row['task_id']] ?? null;
        if ($t && $t['status'] === 'rejected') {
            $stack->push(['stack_row' => (int) $row['id']] + $t);
        }
    }
    return $stack;
}

/**
 * [DSA: TREE] — hierarchical data: Section (root) -> Subjects -> active Tasks.
 */
function build_tree(array $section, SubjectArray $subjects, TaskLinkedList $timeline): SubjectTree
{
    $tree = new SubjectTree($section['name']);
    for ($i = 0; $i < $subjects->size(); $i++) {
        $tree->addSubject($subjects->get($i));
    }
    foreach ($timeline->toArray() as $t) {
        $tree->addTask((int) $t['subject_id'], [
            'id' => (int) $t['id'], 'title' => $t['title'], 'category' => $t['category'], 'deadline' => $t['deadline'],
        ]);
    }
    return $tree;
}

/** Users who belong to a section, each with their role in it. */
function section_members(int $sectionId): array
{
    $users = user_index();
    $out = [];
    foreach (section_rows('memberships', $sectionId) as $m) {
        $u = $users[(int) $m['user_id']] ?? null;
        if ($u) {
            $u['role'] = $m['role'];
            $u['position'] = $m['position'] ?? null;
            $u['status'] = $m['status'] ?? 'active';
            $u['joined_at'] = $m['joined_at'];
            $u['section_id'] = $sectionId;
            $u['membership_id'] = (int) $m['id'];
            $out[] = $u;
        }
    }
    return $out;
}

/** Every section a user belongs to (for the section switcher). */
function user_sections(int $userId): array
{
    $sections = [];
    foreach (Store::all('sections') as $s) {
        $sections[(int) $s['id']] = $s;
    }
    $counts = [];
    foreach (Store::all('memberships') as $m) {
        $counts[(int) $m['section_id']] = ($counts[(int) $m['section_id']] ?? 0) + 1;
    }
    $out = [];
    foreach (Store::all('memberships') as $m) {
        if ((int) $m['user_id'] === $userId && isset($sections[(int) $m['section_id']])) {
            $s = $sections[(int) $m['section_id']];
            $out[] = present_section($s, $counts[(int) $s['id']] ?? 0) + ['role' => $m['role'], 'joined_at' => $m['joined_at']];
        }
    }
    return $out;
}

/**
 * The section's groups, each with its member ids: [groupId => row + ['members' => [ids]]].
 */
function load_groups(int $sectionId): array
{
    $groups = [];
    foreach (section_rows('study_groups', $sectionId) as $g) {
        $g['id'] = (int) $g['id'];
        $g['members'] = [];
        $groups[$g['id']] = $g;
    }
    foreach (section_rows('group_members', $sectionId) as $m) {
        if (isset($groups[(int) $m['group_id']])) {
            $groups[(int) $m['group_id']]['members'][] = (int) $m['user_id'];
        }
    }
    return $groups;
}

/**
 * [DSA: STACK] — each student's own Undo history, kept in their login session per section.
 * The session stores the stack's items bottom-to-top; every call rebuilds the UndoStack,
 * pushes or pops, and saves it back. Only the last UNDO_LIMIT actions are kept.
 */
const UNDO_LIMIT = 10;

/** [DSA: STACK of OOP objects] — the session's stored arrays rebuilt into UndoAction objects. */
function undo_stack(int $sectionId): UndoStack
{
    $stack = new UndoStack();
    foreach ($_SESSION['undo'][$sectionId] ?? [] as $entry) {   // bottom first, so the newest ends on top
        $action = UndoAction::fromArray($entry);
        if ($action !== null) {
            $stack->push($action);
        }
    }
    return $stack;
}

function undo_save(int $sectionId, UndoStack $stack): void
{
    $topFirst = $stack->toArray();
    $bottomFirst = [];
    for ($i = count($topFirst) - 1; $i >= 0; $i--) {
        $bottomFirst[] = $topFirst[$i]->toArray();
    }
    $_SESSION['undo'][$sectionId] = $bottomFirst;
}

/** PUSH an action (it knows how to reverse itself), e.g. new RejoinGroupAction('Left “Group 2”', ...). */
function undo_push(int $sectionId, UndoAction $action): void
{
    $stack = undo_stack($sectionId);
    $stack->push($action);
    if ($stack->size() > UNDO_LIMIT) {        // drop the oldest (bottom) item
        $keep = $stack->toArray();
        $stack = new UndoStack();
        for ($i = UNDO_LIMIT - 1; $i >= 0; $i--) {
            $stack->push($keep[$i]);
        }
    }
    undo_save($sectionId, $stack);
}

/** POP the most recent action (LIFO), or null when there is nothing to undo. */
function undo_pop(int $sectionId): ?UndoAction
{
    $stack = undo_stack($sectionId);
    $top = $stack->pop();
    undo_save($sectionId, $stack);
    return $top;
}

/* ------------------------------------------------------------------ activities and member records */

/** The section's activities as Activity objects (Meeting / Seminar / Outreach), by id. */
function load_activities(int $sectionId): array
{
    $out = [];
    foreach (section_rows('activities', $sectionId) as $r) {
        $out[(int) $r['id']] = Activity::fromRow($r);
    }
    return $out;
}

/** One ActivityRoster (going HashSet, waitlist QUEUE, checked-in HashSet) per activity id. */
function activity_rosters(int $sectionId): array
{
    $rsvps = [];
    foreach (section_rows('activity_rsvps', $sectionId) as $r) {
        $rsvps[(int) $r['activity_id']][] = $r;
    }
    $came = [];
    foreach (section_rows('attendance', $sectionId) as $a) {
        $came[(int) $a['activity_id']][] = $a;
    }
    $out = [];
    foreach (section_rows('activities', $sectionId) as $r) {
        $id = (int) $r['id'];
        $out[$id] = new ActivityRoster($rsvps[$id] ?? [], $came[$id] ?? []);
    }
    return $out;
}

/** Vice President / Secretary terms per user id, oldest first. */
function officer_terms(int $sectionId): array
{
    $out = [];
    foreach (section_rows('officer_terms', $sectionId) as $t) {
        $out[(int) $t['user_id']][] = ['position' => $t['position'], 'started_at' => $t['started_at'], 'ended_at' => $t['ended_at']];
    }
    return $out;
}

/** Closes whatever office this member holds (when they're replaced, made inactive or leave). */
function end_officer_term(int $sectionId, int $userId): void
{
    foreach (section_rows('officer_terms', $sectionId) as $t) {
        if ((int) $t['user_id'] === $userId && $t['ended_at'] === null) {
            Store::update('officer_terms', (int) $t['id'], ['ended_at' => date('Y-m-d H:i:s')]);
        }
    }
}

/** A MemberRecord for every member of the section (attendance from finished activities). */
function member_records(array $ctx): array
{
    $sectionId = (int) $ctx['section']['id'];
    $now = time();
    $finished = [];
    foreach (load_activities($sectionId) as $a) {
        if (!$a->isCancelled() && $a->isOver($now)) {
            $finished[] = $a;
        }
    }
    $rosters = activity_rosters($sectionId);
    $terms = officer_terms($sectionId);
    $groupCount = [];
    foreach (load_groups($sectionId) as $g) {
        foreach ($g['members'] as $id) {
            $groupCount[$id] = ($groupCount[$id] ?? 0) + 1;
        }
    }
    $out = [];
    foreach ($ctx['members'] as $m) {
        $id = (int) $m['id'];
        $out[] = MemberRecord::fromActivities(Person::fromRow($m), (string) $m['joined_at'], $finished, $rosters, $groupCount[$id] ?? 0, $terms[$id] ?? []);
    }
    return $out;
}

/**
 * Deletes a group with its member rows and its join requests (answered ones too).
 * Foreign keys are on and group_requests/group_members point at study_groups without a
 * cascade, so the group row can only go once nothing refers to it any more.
 */
function delete_group(int $sectionId, int $groupId): void
{
    foreach (section_rows('group_requests', $sectionId) as $r) {
        if ((int) $r['group_id'] === $groupId) {
            Store::delete('group_requests', (int) $r['id']);
        }
    }
    foreach (section_rows('group_members', $sectionId) as $m) {
        if ((int) $m['group_id'] === $groupId) {
            Store::delete('group_members', (int) $m['id']);
        }
    }
    Store::delete('study_groups', $groupId);
}

/**
 * [DSA: GRAPH] — connections between classmates. Every two members of the same
 * group are joined by an undirected edge labelled with the group's name.
 */
function build_graph(int $sectionId, array $members, ?array $groups = null): DependencyGraph
{
    $graph = new DependencyGraph();
    foreach ($members as $m) {
        $graph->addVertex((int) $m['id']);
    }
    foreach ($groups ?? load_groups($sectionId) as $g) {
        $ids = $g['members'];
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $graph->addEdge($ids[$i], $ids[$j], $g['name'], (int) $g['id']);
            }
        }
    }
    return $graph;
}

/** Group row ready for JSON, with member names and its subject/task. */
function present_group(array $g, array $users, array $subjectIndex, array $taskIndex, int $viewerId): array
{
    $members = [];
    foreach ($g['members'] as $id) {
        $u = $users[$id] ?? null;
        if ($u) {
            $members[] = ['id' => $id, 'name' => $u['first_name'] . ' ' . $u['last_name'], 'student_id' => $u['student_id']];
        }
    }
    $task = $g['task_id'] ? ($taskIndex[(int) $g['task_id']] ?? null) : null;
    $subject = $g['subject_id'] ? ($subjectIndex[(int) $g['subject_id']] ?? null) : null;
    $mine = false;
    foreach ($g['members'] as $id) {
        $mine = $mine || $id === $viewerId;
    }
    return [
        'id' => $g['id'],
        'name' => $g['name'],
        'purpose' => $g['purpose'],
        'max_members' => $g['max_members'] !== null ? (int) $g['max_members'] : null,
        'min_members' => $task && $task['group_min'] !== null ? (int) $task['group_min'] : null,
        'created_by' => (int) $g['created_by'],
        'created_at' => $g['created_at'],
        'subject' => $subject ? ['id' => $subject['id'], 'code' => $subject['code'], 'title' => $subject['title'], 'color' => $subject['color_tag']] : null,
        'task' => $task ? ['id' => (int) $task['id'], 'title' => $task['title'], 'deadline_ts' => DsaUtil::deadlineTs($task), 'status' => $task['status']] : null,
        'members' => $members,
        'is_member' => $mine,
    ];
}

/** Weekly class meetings for the section, with subject info attached. */
function class_slots(int $sectionId, array $subjectIndex): array
{
    $out = [];
    foreach (section_rows('class_schedules', $sectionId) as $c) {
        $subject = $subjectIndex[(int) $c['subject_id']] ?? null;
        if ($subject) {
            $out[] = [
                'id' => (int) $c['id'],
                'subject_id' => (int) $c['subject_id'],
                'day_of_week' => (int) $c['day_of_week'],
                'day_name' => $c['day_name'],
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

/** Everything the app needs about the user's open section. */
function load_context(array $user): array
{
    $sectionId = (int) $user['section_id'];
    $section = Store::find('sections', $sectionId);
    $subjects = load_subjects($sectionId);
    $members = section_members($sectionId);
    $roles = [];
    foreach ($members as $m) {
        $roles[(int) $m['id']] = $m['role'];
    }
    $tasks = [];
    foreach (section_rows('tasks', $sectionId) as $t) {
        $t['requested_by_role'] = $roles[(int) $t['requested_by']] ?? 'student';
        $tasks[] = $t;
    }
    return [
        'section' => $section,
        'subjects' => $subjects,
        'subjectIndex' => subject_index($subjects),
        'users' => user_index(),
        'members' => $members,
        'tasks' => $tasks,
        'taskIndex' => task_index($tasks),
    ];
}

function present_section(array $section, int $memberCount): array
{
    return [
        'id' => (int) $section['id'],
        'name' => $section['name'],
        'class_code' => $section['class_code'],
        'program' => $section['program'],
        'year_level' => $section['year_level'],
        'semester' => $section['semester'],
        'school_year' => $section['school_year'],
        'members' => $memberCount,
    ];
}

/**
 * [DSA: HASHSET] helpers — one set per uniqueness rule.
 */
function hashset_of(string $table, string $field, ?int $sectionId = null): HashSet
{
    $set = new HashSet();
    foreach ($sectionId === null ? Store::all($table) : section_rows($table, $sectionId) as $row) {
        $set->add((string) $row[$field]);
    }
    return $set;
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

/** "13:00" -> minutes since midnight. */
function to_minutes(string $hhmm): int
{
    return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
}

function valid_time(string $t): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
}

/**
 * Validates a list of class meetings [{day, start, end}, ...] where every
 * day may have its own time. Returns the cleaned list or throws a message.
 */
function clean_slots(array $slots): array
{
    $out = [];
    foreach ($slots as $slot) {
        $day = (int) ($slot['day'] ?? 0);
        $start = (string) ($slot['start'] ?? '');
        $end = (string) ($slot['end'] ?? '');
        if ($day < 1 || $day > 6) {
            fail('Class days must be Monday to Saturday.');
        }
        if (!valid_time($start) || !valid_time($end) || to_minutes($start) >= to_minutes($end)) {
            fail('On ' . DAY_NAMES[$day] . ', the start time must be before the end time.');
        }
        $out[] = ['day' => $day, 'start' => $start, 'end' => $end];
    }
    return $out;
}
