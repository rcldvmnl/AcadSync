<?php
/**
 * GET  /api/groups.php                 -> all groups in the section (mine first) + upcoming group tasks
 * GET  /api/groups.php?task_id=12      -> groups for one group task + classmates without a group
 * POST (any member)
 *   action=create       {name, subject_id?, task_id?, purpose?, max_members?, member_ids[]}
 *   action=join         {group_id}
 *   action=leave        {group_id}
 *   action=add_members  {group_id, member_ids[]}   creator or admin
 *   action=delete       {group_id}                 creator or admin
 * POST (admin)
 *   action=auto_assign  {task_id}   put everyone without a group into groups within the size range
 *
 * Rules for groups tied to a GROUP TASK:
 *   - a student can be in only one group per task   ([DSA: HASHSET] of grouped students)
 *   - a group can't grow past the task's group_max
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = (int) $user['id'];
$isAdmin = $user['role'] === 'admin';
$groups = load_groups($sectionId);

$memberIds = new HashSet();                 // [DSA: HASHSET] — who belongs to this section
foreach ($ctx['members'] as $m) {
    $memberIds->add((string) $m['id']);
}

/** [DSA: HASHSET] — everyone already in a group for this task. */
function grouped_for_task(array $groups, int $taskId): HashSet
{
    $set = new HashSet();
    foreach ($groups as $g) {
        if ((int) $g['task_id'] === $taskId) {
            foreach ($g['members'] as $id) {
                $set->add((string) $id);
            }
        }
    }
    return $set;
}

function group_task(array $ctx, ?int $taskId): ?array
{
    if (!$taskId) {
        return null;
    }
    $t = $ctx['taskIndex'][$taskId] ?? null;
    if (!$t || $t['status'] !== 'approved' || $t['work_mode'] !== 'group') {
        fail('That task is not open for group work.', 404);
    }
    return $t;
}

function next_group_name(array $groups, int $taskId): string
{
    $n = 1;
    foreach ($groups as $g) {
        if ((int) $g['task_id'] === $taskId) {
            $n++;
        }
    }
    return "Group $n";
}

$present = fn (array $g) => present_group($g, $ctx['users'], $ctx['subjectIndex'], $ctx['taskIndex'], $uid);

/* ---------------------------------------------------------------- reads */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $taskId = param('task_id') ? (int) param('task_id') : null;

    if ($taskId) {
        $task = group_task($ctx, $taskId);
        $out = [];
        foreach ($groups as $g) {
            if ((int) $g['task_id'] === $taskId) {
                $out[] = $present($g);
            }
        }
        $grouped = grouped_for_task($groups, $taskId);
        $ungrouped = [];
        foreach (Algorithms::selectionSortByLastName($ctx['members']) as $m) {
            if (!$grouped->contains((string) $m['id'])) {
                $ungrouped[] = ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'student_id' => $m['student_id']];
            }
        }
        respond([
            'task' => present_task($task, $ctx['subjectIndex'], $ctx['users']),
            'groups' => $out,
            'ungrouped' => $ungrouped,
            'in_group' => $grouped->contains((string) $uid),
            'plan' => Algorithms::planGroupSizes(count($ungrouped), (int) $task['group_min'], (int) $task['group_max']),
            'role' => $user['role'],
        ]);
    }

    // All groups: mine first, then the rest (newest first)
    $mine = [];
    $others = [];
    $ids = array_keys($groups);
    for ($i = count($ids) - 1; $i >= 0; $i--) {
        $p = $present($groups[$ids[$i]]);
        if ($p['is_member']) {
            $mine[] = $p;
        } else {
            $others[] = $p;
        }
    }
    // Upcoming group tasks, in timeline order ([DSA: LINKED LIST])
    $upcoming = [];
    foreach (build_timeline($ctx['tasks'], time())->toArray() as $t) {
        if ($t['work_mode'] === 'group') {
            $grouped = grouped_for_task($groups, (int) $t['id']);
            $count = 0;
            foreach ($groups as $g) {
                $count += (int) $g['task_id'] === (int) $t['id'] ? 1 : 0;
            }
            $upcoming[] = present_task($t, $ctx['subjectIndex'], $ctx['users']) + [
                'groups_formed' => $count,
                'grouped_count' => $grouped->size(),
                'in_group' => $grouped->contains((string) $uid),
            ];
        }
    }
    respond(['mine' => $mine, 'others' => $others, 'upcoming' => $upcoming, 'members' => count($ctx['members']), 'role' => $user['role'], 'me' => $uid]);
}

/* ---------------------------------------------------------------- writes */
require_method('POST');

$group = null;
if (param('group_id')) {
    $group = $groups[(int) param('group_id')] ?? null;
    if (!$group) {
        fail('Group not found.', 404);
    }
}
$canManage = fn (array $g) => $isAdmin || (int) $g['created_by'] === $uid;
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

switch (param('action')) {
    case 'create': {
        $taskId = param('task_id') ? (int) param('task_id') : null;
        $task = group_task($ctx, $taskId);
        $name = preg_replace('/\s+/', ' ', (string) param('name', ''));
        if ($name === '') {
            $name = $task ? next_group_name($groups, $taskId) : '';
        }
        if ($name === '' || strlen($name) > 40) {
            fail('Give the group a name (up to 40 characters).');
        }
        $subjectId = $task ? (int) $task['subject_id'] : (param('subject_id') ? (int) param('subject_id') : null);
        if ($subjectId !== null && !isset($ctx['subjectIndex'][$subjectId])) {
            fail('Pick a subject from this section.');
        }
        $max = $task ? (int) $task['group_max'] : (param('max_members') ? (int) param('max_members') : null);
        if ($max !== null && ($max < 2 || $max > 50)) {
            fail('Group size limit must be between 2 and 50.');
        }

        // [DSA: HASHSET] — the member list must have no duplicates, only classmates, and you
        $picked = new HashSet();
        $ids = [];
        foreach (array_merge([$uid], (array) param('member_ids', [])) as $id) {
            $id = (int) $id;
            if ($memberIds->contains((string) $id) && $picked->add((string) $id)) {
                $ids[] = $id;
            }
        }
        if ($task) {
            $grouped = grouped_for_task($groups, $taskId);
            foreach ($ids as $id) {
                if ($grouped->contains((string) $id)) {
                    $who = $ctx['users'][$id];
                    fail("{$who['first_name']} {$who['last_name']} is already in a group for this task.", 409);
                }
            }
        }
        if ($max !== null && count($ids) > $max) {
            fail("This group can have at most $max members.");
        }

        Store::begin();
        $gid = Store::insert('study_groups', [
            'section_id' => $sectionId, 'name' => $name, 'subject_id' => $subjectId, 'task_id' => $taskId,
            'purpose' => (string) param('purpose', ''), 'max_members' => $max, 'created_by' => $uid,
        ]);
        foreach ($ids as $id) {
            Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $gid, 'user_id' => $id]);
        }
        Store::commit();
        foreach ($ids as $id) {
            if ($id !== $uid) {
                notify($id, $sectionId, 'group', "$fullName added you to “{$name}”" . ($task ? " for {$task['title']}" : ''), $task ? "task.html?id={$task['id']}" : 'classmates.html#groups');
            }
        }
        respond(['id' => $gid], 201);
    }

    case 'join': {
        if (!$group) {
            fail('Pick a group.');
        }
        foreach ($group['members'] as $id) {
            if ($id === $uid) {
                fail('You’re already in this group.', 409);
            }
        }
        if ($group['task_id'] && grouped_for_task($groups, (int) $group['task_id'])->contains((string) $uid)) {
            fail('You’re already in a group for this task. Leave it first.', 409);
        }
        if ($group['max_members'] !== null && count($group['members']) >= (int) $group['max_members']) {
            fail('This group is full.', 409);
        }
        Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $group['id'], 'user_id' => $uid]);
        notify((int) $group['created_by'], $sectionId, 'group', "$fullName joined “{$group['name']}”", 'classmates.html#groups');
        respond();
    }

    case 'leave': {
        if (!$group) {
            fail('Pick a group.');
        }
        $row = null;
        foreach (section_rows('group_members', $sectionId) as $m) {
            if ((int) $m['group_id'] === $group['id'] && (int) $m['user_id'] === $uid) {
                $row = $m;
            }
        }
        if (!$row) {
            fail('You’re not in this group.', 404);
        }
        Store::delete('group_members', (int) $row['id']);
        // An empty group is removed automatically
        if (count($group['members']) === 1) {
            Store::delete('study_groups', $group['id']);
        }
        respond();
    }

    case 'add_members': {
        if (!$group || !$canManage($group)) {
            fail('Only the group’s creator or the class admin can add members.', 403);
        }
        $in = new HashSet();
        foreach ($group['members'] as $id) {
            $in->add((string) $id);
        }
        $grouped = $group['task_id'] ? grouped_for_task($groups, (int) $group['task_id']) : new HashSet();
        $added = [];
        foreach ((array) param('member_ids', []) as $id) {
            $id = (int) $id;
            if ($memberIds->contains((string) $id) && !$grouped->contains((string) $id) && $in->add((string) $id)) {
                $added[] = $id;
            }
        }
        if ($group['max_members'] !== null && count($group['members']) + count($added) > (int) $group['max_members']) {
            fail("This group can have at most {$group['max_members']} members.");
        }
        foreach ($added as $id) {
            Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $group['id'], 'user_id' => $id]);
            notify($id, $sectionId, 'group', "$fullName added you to “{$group['name']}”", 'classmates.html#groups');
        }
        respond(['added' => count($added)]);
    }

    case 'delete': {
        if (!$group || !$canManage($group)) {
            fail('Only the group’s creator or the class admin can delete it.', 403);
        }
        Store::begin();
        foreach (section_rows('group_members', $sectionId) as $m) {
            if ((int) $m['group_id'] === $group['id']) {
                Store::delete('group_members', (int) $m['id']);
            }
        }
        Store::delete('study_groups', $group['id']);
        Store::commit();
        respond();
    }

    case 'auto_assign': {
        if (!$isAdmin) {
            fail('Only the class admin can auto-group.', 403);
        }
        $taskId = (int) param('task_id', 0);
        $task = group_task($ctx, $taskId);
        $min = (int) $task['group_min'];
        $max = (int) $task['group_max'];

        // [DSA: QUEUE] — waiting line of students without a group (shuffled for fairness)
        $grouped = grouped_for_task($groups, $taskId);
        $waiting = new ApprovalQueue();
        foreach (Algorithms::shuffle($ctx['members']) as $m) {
            if (!$grouped->contains((string) $m['id'])) {
                $waiting->enqueue((int) $m['id']);
            }
        }
        if ($waiting->isEmpty()) {
            fail('Everyone already has a group.', 409);
        }

        // First top up existing groups that are below the minimum
        Store::begin();
        $filled = 0;
        foreach ($groups as $g) {
            if ((int) $g['task_id'] === $taskId) {
                $size = count($g['members']);
                while ($size < $min && !$waiting->isEmpty()) {
                    Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $g['id'], 'user_id' => $waiting->dequeue()]);
                    $size++;
                    $filled++;
                }
            }
        }
        // Then split everyone left into new groups sized within [min, max]
        $sizes = Algorithms::planGroupSizes($waiting->size(), $min, $max);
        $made = 0;
        foreach ($sizes as $size) {
            $gid = Store::insert('study_groups', [
                'section_id' => $sectionId, 'name' => next_group_name(load_groups($sectionId), $taskId), 'subject_id' => (int) $task['subject_id'],
                'task_id' => $taskId, 'purpose' => $task['title'], 'max_members' => $max, 'created_by' => $uid,
            ]);
            for ($i = 0; $i < $size; $i++) {
                $id = $waiting->dequeue();      // DEQUEUE students in order
                Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $gid, 'user_id' => $id]);
                notify($id, $sectionId, 'group', "You were placed in a group for “{$task['title']}”", "task.html?id=$taskId");
            }
            $made++;
        }
        Store::commit();
        respond(['groups_created' => $made, 'sizes' => $sizes, 'topped_up' => $filled]);
    }

    default:
        fail('Unknown action.');
}
