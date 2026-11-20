<?php
/**
 * POST /api/request_task.php
 *   action=check     {subject_id, title, description, ignore_id?}  -> Linear Search for similar tasks
 *   action=submit    {subject_id, category, title, description, location,
 *                     deadline_type: exact|range|unset, deadline, deadline_end, deadline_time,
 *                     work_mode: individual|group, target_task_id?,
 *                     group work: group_mode: assigned|count|size|size_count, group_size, group_count,
 *                                 group_plan [{name, member_ids[]}] (assigned only)}
 *   action=update_request {request_id, ...same fields}   student edits their own pending request
 *   action=withdraw  {request_id}                         student cancels their own pending request
 *   action=delete    {task_id}                            admin removes a published task
 *
 * Student -> the request is ENQUEUED into the Approval Queue (status = pending).
 *            With target_task_id it is an edit ("change") request for that task.
 *            A student may have at most MAX_PENDING requests waiting at once.
 * Admin   -> published right away (Add Task) or applied directly (Edit Task).
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

const CATEGORIES = ['Reporting', 'Quiz', 'Exam', 'Activity', 'Project'];
const MAX_PENDING = 3;

require_method('POST');
$user = current_member();
$ctx = load_context($user);
$me = Person::fromRow($user);     // [OOP] Member or Officer
// An officer's tasks are published straight away; a member's request waits in the queue
$publishesDirectly = $me->can(Permission::PUBLISH_TASK);
$sectionId = (int) $ctx['section']['id'];
$uid = (int) $user['id'];
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

/** [DSA: QUEUE] — this student's requests currently waiting in the queue. */
function my_pending(int $sectionId, array $taskIndex, int $uid): array
{
    $mine = [];
    foreach (build_queue($sectionId, $taskIndex)->toArray() as $q) {
        if ((int) $q['requested_by'] === $uid) {
            $mine[] = $q;
        }
    }
    return $mine;
}

/**
 * Validates the submitted fields and returns a tasks row.
 * $base supplies defaults for fields left out (an edit request or an existing request).
 */
function task_fields(array $ctx, ?array $base): array
{
    $subjectId = (int) param('subject_id', $base['subject_id'] ?? 0);
    if (!isset($ctx['subjectIndex'][$subjectId])) {
        fail('Pick a subject.');
    }
    $title = (string) param('title', $base['title'] ?? '');
    if ($title === '' || strlen($title) > 120) {
        fail('Give the task a title (up to 120 characters).');
    }

    // Category: one of the standard ones, or "Other" with a custom label
    $category = preg_replace('/\s+/', ' ', (string) param('category', $base['category'] ?? ''));
    $known = false;
    foreach (CATEGORIES as $c) {
        $known = $known || $c === $category;
    }
    if (!$known && (strlen($category) < 2 || strlen($category) > 30 || DsaUtil::lower($category) === 'other')) {
        fail('Pick a category, or choose Other and type what kind of task it is (2–30 characters).');
    }

    // Deadline (shared with announcements' "Deadline change": validate_deadline in services.php)
    try {
        $when = validate_deadline([
            'deadline_type' => param('deadline_type', $base['deadline_type'] ?? 'exact'),
            'deadline' => param('deadline', $base['deadline'] ?? null),
            'deadline_end' => param('deadline_end', $base['deadline_end'] ?? null),
            'deadline_time' => param('deadline_time', $base['deadline_time'] ?? null),
        ], date('Y-m-d'));
    } catch (InvalidArgumentException $e) {
        fail($e->getMessage());
    }
    ['deadline_type' => $type, 'deadline' => $deadline, 'deadline_end' => $deadlineEnd, 'deadline_time' => $time] = $when;

    // Individual or group work, and for group work how the prof set the groups up
    $mode = (string) param('work_mode', $base['work_mode'] ?? 'individual');
    $group = ['group_mode' => null, 'group_min' => null, 'group_max' => null, 'group_count' => null, 'group_plan' => null];
    if ($mode === 'group') {
        $baseRule = $base ? GroupRule::fromRow($base) : null;
        $groupMode = (string) param('group_mode', $baseRule?->mode() ?? '');
        $size = (int) param('group_size', $baseRule?->size() ?? 0) ?: null;
        $count = (int) param('group_count', $baseRule?->count() ?? 0) ?: null;
        $plan = null;
        if ($groupMode === GroupRule::ASSIGNED) {
            $plan = assigned_plan($ctx, input()['group_plan'] ?? null, $base);
            $count = count($plan);
        }
        // [OOP: ENCAPSULATION + POLYMORPHISM] — GroupRule::create builds the right subclass and
        // checks its own numbers; an invalid setup can't be built
        try {
            $rule = GroupRule::create($groupMode, $size, $count);
        } catch (InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        $group = $rule->toColumns() + ['group_plan' => $plan !== null ? json_encode($plan) : null];
    } else {
        $mode = 'individual';
    }

    $description = (string) param('description', $base['description'] ?? '');
    if (strlen($description) > 2000) {
        fail('Description is too long (2000 characters max).');
    }

    return [
        'subject_id' => $subjectId,
        'category' => $category,
        'title' => $title,
        'description' => $description,
        'deadline' => $deadline,
        'deadline_time' => $time,
        'deadline_end' => $deadlineEnd,
        'deadline_type' => $type,
        'location' => (string) param('location', $base['location'] ?? ''),
        'work_mode' => $mode,
    ] + $group;
}

/**
 * The groups the prof assigned, checked: 1–50 groups, each named once and with at least one member,
 * members from this section only and each in one group. An edit without a new list keeps the old one.
 * [DSA: HASHSET] — names and students are kept unique.
 */
function assigned_plan(array $ctx, mixed $raw, ?array $base): array
{
    if ($raw === null && $base && !empty($base['group_plan'])) {
        return json_decode((string) $base['group_plan'], true) ?: [];
    }
    if ($raw === null && $base && ($base['group_mode'] ?? null) === GroupRule::ASSIGNED) {
        return array_fill(0, max(1, (int) $base['group_count']), ['name' => '', 'member_ids' => []]);   // already made: officers edit the groups
    }
    if (!is_array($raw) || !$raw) {
        fail('Add the groups your prof assigned, with their members.');
    }
    if (count($raw) > GroupRule::MAX_GROUPS) {
        fail('At most ' . GroupRule::MAX_GROUPS . ' groups.');
    }
    $members = new HashSet();
    foreach ($ctx['members'] as $m) {
        $members->add((string) $m['id']);
    }
    $names = new HashSet();
    $placed = new HashSet();
    $plan = [];
    foreach ($raw as $i => $entry) {
        $name = preg_replace('/\s+/', ' ', trim((string) ($entry['name'] ?? ''))) ?: 'Group ' . ($i + 1);
        if (strlen($name) > 40) {
            fail('Group names can have at most 40 characters.');
        }
        if (!$names->add($name)) {
            fail("Two groups are called “{$name}”. Give each group its own name.");
        }
        $ids = [];
        foreach ((array) ($entry['member_ids'] ?? []) as $id) {
            $id = (int) $id;
            if (!$members->contains((string) $id)) {
                fail('Pick members from this section only.');
            }
            if (!$placed->add((string) $id)) {
                $u = $ctx['users'][$id];
                fail("{$u['first_name']} {$u['last_name']} is in two groups. Each classmate goes in one group.");
            }
            $ids[] = $id;
        }
        if (!$ids) {
            fail("“{$name}” has no members yet. Add its members, or remove the group.");
        }
        $plan[] = ['name' => $name, 'member_ids' => $ids];
    }
    return $plan;
}

/** The student's own pending request, or a 404. */
function own_pending(array $ctx, int $uid): array
{
    $r = $ctx['taskIndex'][(int) param('request_id', 0)] ?? null;
    if (!$r || (int) $r['requested_by'] !== $uid) {
        fail('Request not found.', 404);
    }
    if ($r['status'] !== 'pending') {
        fail('The admin already reviewed this request, so it can’t be changed.', 409);
    }
    return $r;
}

switch (param('action')) {
    case 'check':
        // [ALGORITHM: LINEAR SEARCH] — warn before submitting a duplicate
        respond(['similar' => find_similar(
            $ctx['tasks'], (int) param('subject_id', 0), param('title', '') . ' ' . param('description', ''),
            param('ignore_id') ? (int) param('ignore_id') : null,
            $ctx['subjectIndex'], $ctx['users']
        ), 'pending' => count(my_pending($sectionId, $ctx['taskIndex'], $uid)), 'limit' => MAX_PENDING]);

    case 'delete': {
        if (!$me->can(Permission::REMOVE_TASK)) {
            fail('Only the class admin can remove tasks.', 403);
        }
        $taskId = (int) param('task_id', 0);
        // [DSA: LINKED LIST] — DELETION: unlink the node from the timeline
        $timeline = build_timeline($ctx['tasks'], time());
        $removed = $timeline->deleteById($taskId);
        $row = $removed ?? ($ctx['taskIndex'][$taskId] ?? null);
        if (!$row || $row['status'] !== 'approved') {
            fail('Task not found.', 404);
        }
        Store::update('tasks', $taskId, ['status' => 'removed', 'reviewed_by' => $uid]);
        notify_section($sectionId, $uid, 'moved', "“{$row['title']}” was removed from the schedule", 'schedule.html');
        respond(['removed' => $taskId, 'timeline_length' => $timeline->length()]);
    }

    case 'update_request': {
        // Editing keeps the request in the same place in the queue
        $r = own_pending($ctx, $uid);
        $row = task_fields($ctx, $r);
        Store::update('tasks', (int) $r['id'], $row);
        respond(['id' => (int) $r['id'], 'status' => 'pending']);
    }

    case 'withdraw': {
        $r = own_pending($ctx, $uid);
        Store::begin();
        foreach (section_rows('approval_queue', $sectionId) as $q) {
            if ((int) $q['task_id'] === (int) $r['id']) {
                Store::delete('approval_queue', (int) $q['id']);   // leaves the queue
            }
        }
        Store::update('tasks', (int) $r['id'], ['status' => 'withdrawn']);
        Store::commit();
        respond(['withdrawn' => (int) $r['id']]);
    }

    case 'submit':
        break;

    default:
        fail('Unknown action.');
}

// --- submit ------------------------------------------------------------------
$targetId = param('target_task_id') ? (int) param('target_task_id') : null;
$target = null;
if ($targetId !== null) {
    $t = $ctx['taskIndex'][$targetId] ?? null;
    if (!$t || $t['status'] !== 'approved' || $t['kind'] !== 'new') {
        fail('The task you want to change no longer exists.', 404);
    }
    $target = $t;
}

if (!$publishesDirectly) {
    if (!$me->can(Permission::REQUEST_TASK)) {   // MemberStatus: an Alumni member can't send new requests
        fail('Alumni members can’t send task requests.', 403);
    }
    // Anti-spam: at most MAX_PENDING requests waiting per student
    $pending = my_pending($sectionId, $ctx['taskIndex'], $uid);
    if (count($pending) >= MAX_PENDING) {
        fail('You already have ' . MAX_PENDING . ' requests waiting. Edit or withdraw one, or wait for the admin to review them.', 429, ['pending' => count($pending), 'limit' => MAX_PENDING]);
    }
    if ($target) {
        foreach ($pending as $q) {
            if ($q['kind'] === 'change' && (int) $q['target_task_id'] === $targetId) {
                fail('You already asked to change this task. Edit that request instead.', 409);
            }
        }
    }
}

$row = task_fields($ctx, $target) + ['section_id' => $sectionId];
$subjectCode = $ctx['subjectIndex'][$row['subject_id']]['code'];

// [ALGORITHM: LINEAR SEARCH] — warn about possible duplicates
$similar = $target ? [] : find_similar($ctx['tasks'], $row['subject_id'], $row['title'] . ' ' . $row['description'], null, $ctx['subjectIndex'], $ctx['users']);

if ($publishesDirectly) {
    if ($target) {
        // Admin "Edit Task" — everyone sees the change right away
        Store::update('tasks', (int) $target['id'], $row);
        $made = sync_task_groups($sectionId, (int) $target['id'], $uid);   // e.g. raised from 5 to 6 groups
        notify_section($sectionId, $uid, 'moved', "“{$row['title']}” was updated", "task.html?id={$target['id']}");
        respond(['id' => (int) $target['id'], 'status' => 'approved', 'similar' => [], 'groups_made' => $made]);
    }
    // Admin "Add Task" — skips the queue; the Linked List places it by date
    Store::begin();
    $id = Store::insert('tasks', $row + ['requested_by' => $uid, 'status' => 'approved', 'kind' => 'new', 'reviewed_by' => $uid]);
    $made = sync_task_groups($sectionId, $id, $uid);   // every group appears right away when their number is known
    Store::commit();
    notify_section($sectionId, $uid, 'schedule', "New {$row['category']}: “{$row['title']}” ({$subjectCode})", "task.html?id=$id");
    respond(['id' => $id, 'status' => 'approved', 'similar' => $similar, 'groups_made' => $made], 201);
}

// [DSA: QUEUE] — student request is ENQUEUED at the rear of the approval queue
Store::begin();
$id = Store::insert('tasks', $row + [
    'requested_by' => $uid,
    'status' => 'pending',
    'kind' => $target ? 'change' : 'new',
    'target_task_id' => $target ? (int) $target['id'] : null,
]);
Store::insert('approval_queue', ['section_id' => $sectionId, 'task_id' => $id]);
Store::commit();

$verb = $target ? 'asked to change' : 'requested';
notify_admins($sectionId, 'request', "$fullName $verb “{$row['title']}”", 'queue.html');

$ctx = load_context($user);
respond([
    'id' => $id,
    'status' => 'pending',
    'queue_position' => build_queue($sectionId, $ctx['taskIndex'])->size(),
    'pending' => count(my_pending($sectionId, $ctx['taskIndex'], $uid)),
    'limit' => MAX_PENDING,
    'similar' => $similar,
], 201);
