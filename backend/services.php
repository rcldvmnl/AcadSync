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
        'deadline_until' => $t['deadline_until'] ?? null,
        'deadline_end' => $t['deadline_end'],
        'deadline_type' => $t['deadline_type'],
        'deadline_ts' => DsaUtil::deadlineTs($t),
        'opens_ts' => Deadlines::fromRow($t)->opensAt(),       // first day of a range (= deadline for an exact date)
        'deadline_label' => Deadlines::fromRow($t)->label(),   // [OOP] each Deadline class words itself
        'location' => $t['location'],
        'work_mode' => $t['work_mode'] ?? 'individual',
        'group_min' => isset($t['group_min']) ? (int) $t['group_min'] : null,
        'group_max' => isset($t['group_max']) ? (int) $t['group_max'] : null,
        'group_count' => isset($t['group_count']) ? (int) $t['group_count'] : null,
        // [OOP: POLYMORPHISM] the task's GroupRule subclass names its own setup and wording
        'group_mode' => ($rule = Task::fromRow($t)->rule())?->mode(),
        'group_size' => $rule?->size(),
        'group_label' => $rule?->label(),
        'group_plan' => !empty($t['group_plan']) ? json_decode((string) $t['group_plan'], true) : null,
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

/** The undo stack for the queue screen: its size, the top (latest rejection) and every item, top first. */
function present_undo(UndoStack $stack, array $ctx): array
{
    $items = [];
    foreach ($stack->toArray() as $i => $t) {
        $items[] = present_task($t, $ctx['subjectIndex'], $ctx['users']) + ['stack_position' => $i + 1];
    }
    return ['size' => $stack->size(), 'top' => $items[0] ?? null, 'items' => $items];
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

/** The section's activities as Activity objects (every kind), by id, with who was invited to invite-only ones. */
function load_activities(int $sectionId): array
{
    $invited = [];
    foreach (section_rows('activity_invites', $sectionId) as $i) {
        $invited[(int) $i['activity_id']][] = (int) $i['user_id'];
    }
    $out = [];
    foreach (section_rows('activities', $sectionId) as $r) {
        $out[(int) $r['id']] = Activity::fromRow($r + ['invited' => $invited[(int) $r['id']] ?? []], stored: true);
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

/**
 * The parts of every competition / section project: activity id => ActivityPart[] in display order.
 * [ALGORITHM: MERGE SORT] by position (stable, so equal positions keep the order they were added).
 */
function load_parts(int $sectionId, array $activities): array
{
    $byActivity = [];
    foreach (section_rows('activity_parts', $sectionId) as $r) {
        $a = $activities[(int) $r['activity_id']] ?? null;
        if ($a !== null) {
            $byActivity[$a->id()][] = ['position' => (int) $r['position'], 'part' => ActivityPart::fromRow($r, $a)];
        }
    }
    $out = [];
    $steps = 0;
    foreach ($byActivity as $activityId => $items) {
        foreach (Algorithms::mergeSortBy($items, 'position', false, $steps) as $item) {
            $out[$activityId][] = $item['part'];
        }
    }
    return $out;
}

/** One PartRoster (volunteer QUEUE, picked HashSet) per part id. */
function part_rosters(int $sectionId): array
{
    $members = [];
    foreach (section_rows('part_members', $sectionId) as $m) {
        $members[(int) $m['part_id']][] = $m;
    }
    $out = [];
    foreach (section_rows('activity_parts', $sectionId) as $p) {
        $id = (int) $p['id'];
        $out[$id] = new PartRoster($members[$id] ?? []);
    }
    return $out;
}

/**
 * [DSA: QUEUE] — people waiting to be let into the section, oldest first (front = next to review).
 * Each item is the join_requests row with the person's user row under 'user'.
 */
function build_join_queue(int $sectionId): ApprovalQueue
{
    $users = user_index();
    $queue = new ApprovalQueue();
    foreach (section_rows('join_requests', $sectionId) as $r) {
        $u = $users[(int) $r['user_id']] ?? null;
        if ($r['status'] === 'pending' && $u) {
            $queue->enqueue($r + ['user' => $u]);
        }
    }
    return $queue;
}

/** This user's pending join request for a section, or null. */
function pending_join_request(int $sectionId, int $userId): ?array
{
    foreach (section_rows('join_requests', $sectionId) as $r) {
        if ((int) $r['user_id'] === $userId && $r['status'] === 'pending') {
            return $r;
        }
    }
    return null;
}

/** Sections this user asked to join and is still waiting for. */
function pending_joins(int $userId): array
{
    $sections = [];
    foreach (Store::all('sections') as $s) {
        $sections[(int) $s['id']] = $s;
    }
    $out = [];
    foreach (Store::all('join_requests') as $r) {
        $s = $sections[(int) $r['section_id']] ?? null;
        if ($s && (int) $r['user_id'] === $userId && $r['status'] === 'pending') {
            $out[] = present_section($s, count(section_members((int) $s['id']))) + ['requested_at' => $r['created_at']];
        }
    }
    return $out;
}

/** Vice President / Secretary / Treasurer terms per user id, oldest first. */
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

/** [DSA: HASHSET] — the lowest "Group N" not in $used (deleting a group never makes two "Group 3"s). */
function free_group_name(HashSet $used): string
{
    $n = 1;
    while ($used->contains("Group $n")) {
        $n++;
    }
    return "Group $n";
}

/**
 * Makes a published group task's groups, so classmates can see and join them right away:
 *   - AssignedGroups: the groups and members the prof gave (stored in tasks.group_plan)
 *   - FixedGroupCount / FixedSizeAndCount: empty groups up to the number the prof set
 *   - FixedGroupSize: nothing; students make the groups they need
 * Never deletes: groups already there stay (an edit that raises the count only adds the missing ones).
 * [OOP: POLYMORPHISM] the rule says whether it makes empty groups; [DSA: HASHSET] names and students stay unique.
 * Returns how many groups were made.
 */
function sync_task_groups(int $sectionId, int $taskId, int $byUserId): int
{
    $taskRow = null;
    foreach (section_rows('tasks', $sectionId) as $t) {
        if ((int) $t['id'] === $taskId) {
            $taskRow = $t;
        }
    }
    $rule = $taskRow ? Task::fromRow($taskRow)->rule() : null;
    if ($rule === null || $taskRow['status'] !== 'approved') {
        return 0;
    }
    $names = new HashSet();
    $grouped = new HashSet();
    $formed = 0;
    foreach (load_groups($sectionId) as $g) {
        if ((int) $g['task_id'] === $taskId) {
            $names->add((string) $g['name']);
            foreach ($g['members'] as $id) {
                $grouped->add((string) $id);
            }
            $formed++;
        }
    }
    $members = new HashSet();
    foreach (section_members($sectionId) as $m) {
        $members->add((string) $m['id']);
    }
    $base = $rule->seats(count(section_members($sectionId)))['base'];
    $made = 0;
    $new = function (string $name) use ($sectionId, $taskRow, $taskId, $byUserId, $base, $names): int {
        $names->add($name);
        return Store::insert('study_groups', [
            'section_id' => $sectionId, 'name' => $name, 'subject_id' => (int) $taskRow['subject_id'], 'task_id' => $taskId,
            'purpose' => $taskRow['title'], 'max_members' => $base, 'created_by' => $byUserId,
        ]);
    };

    if (!$rule->studentsOrganize()) {                    // assigned by the prof: make them as listed
        if ($formed > 0) {
            return 0;                                     // already made (officers edit them from here on)
        }
        $plan = !empty($taskRow['group_plan']) ? json_decode((string) $taskRow['group_plan'], true) : [];
        foreach (is_array($plan) ? $plan : [] as $entry) {
            $name = preg_replace('/\s+/', ' ', trim((string) ($entry['name'] ?? '')));
            if ($name === '' || $names->contains($name)) {
                $name = free_group_name($names);
            }
            $gid = $new($name);
            $made++;
            foreach ((array) ($entry['member_ids'] ?? []) as $id) {
                $id = (int) $id;
                if ($members->contains((string) $id) && $grouped->add((string) $id)) {
                    Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $gid, 'user_id' => $id]);
                    notify($id, $sectionId, 'group', "You’re in “{$name}” for “{$taskRow['title']}” (assigned by your prof)", "task.html?id=$taskId");
                }
            }
        }
        return $made;
    }
    if ($rule->makesEmptyGroups()) {                     // the number of groups is known: make the empty ones
        for ($k = $formed; $k < (int) $rule->count(); $k++) {
            $new(free_group_name($names));
            $made++;
        }
    }
    return $made;
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
                // a meeting can have its own room (lecture vs lab day); otherwise the subject's room
                'room' => trim((string) ($c['room'] ?? '')) !== '' ? $c['room'] : $subject['room'],
                'slot_room' => trim((string) ($c['room'] ?? '')) !== '' ? $c['room'] : null,
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
        $room = trim((string) ($slot['room'] ?? ''));
        if (strlen($room) > 60) {
            fail('Keep each room under 60 characters.');
        }
        $out[] = ['day' => $day, 'start' => $start, 'end' => $end, 'room' => $room === '' ? null : $room];
    }
    return $out;
}

/**
 * Checks a task deadline (used by the task form and by the "Deadline change" announcement).
 * $in: deadline_type (exact | range | unset), deadline, deadline_end, deadline_time. $today: Y-m-d.
 * Returns the four tasks columns, tidied. Throws InvalidArgumentException with the message to show.
 */
function validate_deadline(array $in, string $today): array
{
    $type = (string) ($in['deadline_type'] ?? 'exact');
    $deadline = ($in['deadline'] ?? null) ?: null;
    $end = ($in['deadline_end'] ?? null) ?: null;
    $time = ($in['deadline_time'] ?? null) ?: null;
    $until = ($in['deadline_until'] ?? null) ?: null;   // end of the due time (1 hour, 2 hours, … or a picked end)
    $isDate = fn ($d) => is_real_date($d);   // "2026-02-31" is refused (strtotime would roll it to March)
    if ($type === 'unset') {
        return ['deadline_type' => 'unset', 'deadline' => null, 'deadline_end' => null, 'deadline_time' => null, 'deadline_until' => null];
    }
    if ($type === 'range') {
        if (!$isDate($deadline) || !$isDate($end) || $deadline > $end) {
            throw new InvalidArgumentException('Pick the first and last day of the range.');
        }
        if ($end < $today) {
            throw new InvalidArgumentException('That date range is already over.');
        }
    } else {
        $type = 'exact';
        $end = null;
        if (!$isDate($deadline)) {
            throw new InvalidArgumentException('Pick a deadline date.');
        }
        if ($deadline < $today) {
            throw new InvalidArgumentException('The deadline can’t be in the past.');
        }
    }
    if ($time !== null && !valid_time((string) $time)) {
        throw new InvalidArgumentException('Pick a valid due time.');
    }
    if ($until !== null) {
        if ($time === null || !valid_time((string) $until)) {
            throw new InvalidArgumentException('Pick a valid end time.');
        }
        if ((string) $until <= (string) $time) {      // "HH:MM" strings compare in time order
            throw new InvalidArgumentException('The end time must be after the start, on the same day.');
        }
    }
    return ['deadline_type' => $type, 'deadline' => $deadline, 'deadline_end' => $end, 'deadline_time' => $time, 'deadline_until' => $until];
}

/**
 * Builds a new activity from form input (used by Activities and by the "Event" announcement).
 * $in: kind, title, description, venue, date (Y-m-d), end_date (Y-m-d, optional: several days),
 * start, end (HH:MM), capacity ('' = no limit), link, signup_deadline (Y-m-d),
 * audience ('everyone' | 'invited') and invited (user ids; the caller checks they are in the section).
 * Throws InvalidArgumentException; [OOP: ENCAPSULATION] the Activity constructor checks the rest.
 */
function validate_new_activity(array $in, int $createdBy, int $now): Activity
{
    $date = trim((string) ($in['date'] ?? ''));
    $endDate = trim((string) ($in['end_date'] ?? ''));
    $endDate = $endDate === '' ? $date : $endDate;
    $start = trim((string) ($in['start'] ?? ''));
    $end = trim((string) ($in['end'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !valid_time($start) || !valid_time($end)) {
        throw new InvalidArgumentException('Pick a date and the start and end times.');
    }
    if (!is_real_date($date) || !is_real_date($endDate)) {
        throw new InvalidArgumentException('Pick a real date.');
    }
    if (strtotime("$date $start") < $now - 300) {
        throw new InvalidArgumentException('That start time has already passed. Pick a later time or another day.');
    }
    $capacity = $in['capacity'] ?? null;
    return Activity::fromRow([
        'kind' => trim((string) ($in['kind'] ?? '')), 'title' => trim((string) ($in['title'] ?? '')),
        'description' => trim((string) ($in['description'] ?? '')), 'venue' => trim((string) ($in['venue'] ?? '')),
        'starts_at' => "$date $start", 'ends_at' => "$endDate $end",
        'capacity' => $capacity === null || $capacity === '' ? null : (int) $capacity, 'created_by' => $createdBy,
        'link' => isset($in['link']) ? (string) $in['link'] : null,
        'signup_deadline' => isset($in['signup_deadline']) ? (string) $in['signup_deadline'] : null,
        // who can join: 'everyone' (default) or 'invited' with the user ids the poster chose
        'audience' => ($in['audience'] ?? 'everyone') === 'invited' ? 'invited' : 'everyone',
        'invited' => is_array($in['invited'] ?? null) ? $in['invited'] : [],
    ]);
}

/**
 * Was this insert stopped by a UNIQUE index (a double tap, or two people at the same moment)?
 * Anything else (a locked database, a foreign key) is a real error and must not be reported as "already done".
 */
function is_unique_violation(PDOException $e): bool
{
    return (int) ($e->errorInfo[1] ?? 0) === 19 && str_contains((string) ($e->errorInfo[2] ?? $e->getMessage()), 'UNIQUE');
}

/**
 * One CSV line (RFC 4180: commas, quotes and line breaks are quoted; ends with CRLF).
 * A cell that starts with = + - @ or a tab/return gets a leading ' so a spreadsheet shows it as text
 * instead of running it as a formula (CSV injection).
 */
function csv_line(array $cells): string
{
    $out = [];
    foreach ($cells as $cell) {
        $cell = (string) $cell;
        if ($cell !== '' && str_contains("=+-@\t\r", $cell[0])) {
            $cell = "'" . $cell;
        }
        $out[] = preg_match('/[",\r\n]/', $cell) ? '"' . str_replace('"', '""', $cell) . '"' : $cell;
    }
    return implode(',', $out) . "\r\n";
}

/** [DSA: HASHSET] — classes cancelled by "No class" announcements: "Y-m-d|subjectId", or "Y-m-d|*" for a whole day. */
function class_cancellations(int $sectionId): HashSet
{
    $set = new HashSet();
    foreach (section_rows('class_cancellations', $sectionId) as $row) {
        $set->add($row['date'] . '|' . ($row['subject_id'] !== null ? (int) $row['subject_id'] : '*'));
    }
    return $set;
}

/** [OOP] What every Announcement kind needs to check its input and word its card, for the open section. */
function announcement_context(array $user, array $ctx): AnnouncementContext
{
    $sectionId = (int) $ctx['section']['id'];
    $dues = [];
    foreach (section_rows('dues', $sectionId) as $row) {
        $due = Due::fromRow($row);
        if ($due->isOpen()) {
            $dues[$due->id()] = $due;
        }
    }
    $going = [];
    foreach (section_rows('activity_rsvps', $sectionId) as $r) {
        if ($r['status'] === 'going') {
            $going[(int) $r['activity_id']] = ($going[(int) $r['activity_id']] ?? 0) + 1;
        }
    }
    // Competitions / projects: spots still open across their parts ("any number" parts don't count)
    $activities = load_activities($sectionId);
    $rosters = part_rosters($sectionId);
    $openSpots = [];
    foreach (load_parts($sectionId, $activities) as $activityId => $list) {
        $open = 0;
        foreach ($list as $p) {
            $open += $p->openSpots(($rosters[$p->id()] ?? new PartRoster([]))->pickedCount()) ?? 0;
        }
        $openSpots[$activityId] = ['open' => $open, 'parts' => count($list)];
    }
    return new AnnouncementContext(
        sectionId: $sectionId, userId: (int) $user['id'], me: Person::fromRow($user),
        tasks: $ctx['taskIndex'], subjects: $ctx['subjectIndex'], slots: class_slots($sectionId, $ctx['subjectIndex']),
        dues: $dues, activities: $activities, cancelled: class_cancellations($sectionId), now: time(), going: $going,
        openSpots: $openSpots,
    );
}
