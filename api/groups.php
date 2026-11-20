<?php
/**
 * GET  /api/groups.php                 -> all groups in the section (mine first) + upcoming group tasks
 * GET  /api/groups.php?task_id=12      -> groups for one group task + classmates without a group
 * POST (any member)
 *   action=create         {name, subject_id?, task_id?, purpose?, max_members?, member_ids[]}
 *   action=join           {group_id}    an EMPTY group (you become its leader); officers: any group
 *   action=request_join   {group_id}    ask a group that has members; any member can accept
 *   action=leave          {group_id}
 *   action=add_members    {group_id, member_ids[]}   the group's leader or an officer
 *   action=rename         {group_id, name}           the group's leader or an officer
 *   action=delete         {group_id}                 the group's leader or an officer
 * POST (officers: Permission::MANAGE_ANY_GROUP)
 *   action=remove_member  {group_id, user_id}
 *   action=move_member    {group_id, user_id}   to this group, out of their group for the same task
 * POST (President)
 *   action=auto_assign    {task_id}   put everyone without a group into groups, following the task's rule
 *
 * Rules for groups tied to a GROUP TASK ([OOP] the task's GroupRule subclass decides):
 *   - a student can be in only one group per task   ([DSA: HASHSET] of grouped students)
 *   - a group holds the rule's base size, +1 while an extra seat is free (GroupRule::capacityOf)
 *   - groups the prof assigned (AssignedGroups) are changed by officers only
 *   - when the number of groups is fixed, empty groups stay (they're seats waiting to be taken)
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$me = Person::fromRow($user);               // [OOP] a Member or an Officer: permissions are asked, not role-checked
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = $me->id();
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
    // [OOP: POLYMORPHISM] — GroupTask says yes, IndividualTask says no
    if (!$t || !Task::fromRow($t)->isOpen() || !Task::fromRow($t)->acceptsGroups()) {
        fail('That task is not open for group work.', 404);
    }
    return $t;
}

/** [DSA: HASHSET] — the names already used by this task's groups. */
function group_names_for_task(array $groups, int $taskId): HashSet
{
    $names = new HashSet();
    foreach ($groups as $g) {
        if ((int) $g['task_id'] === $taskId) {
            $names->add((string) $g['name']);
        }
    }
    return $names;
}

/**
 * The lowest free "Group N" for this task. Counting the groups instead would repeat a name
 * once a group is deleted (Groups 2 and 3 left -> "Group 3" again).
 */
function next_group_name(array $groups, int $taskId): string
{
    return free_group_name(group_names_for_task($groups, $taskId));
}

/** [OOP] The task as an object (GroupTask / IndividualTask), or null. */
$taskOf = function (?int $taskId) use ($ctx): ?Task {
    $row = $taskId ? ($ctx['taskIndex'][$taskId] ?? null) : null;
    return $row ? Task::fromRow($row) : null;
};

/** [OOP] The task's groups as StudyGroup objects. */
$studyGroupsOf = function (int $taskId) use ($groups): array {
    $out = [];
    foreach ($groups as $g) {
        if ((int) $g['task_id'] === $taskId) {
            $out[] = StudyGroup::fromRow($g);
        }
    }
    return $out;
};

/**
 * Where each task's grouping stands, worked out once per request by the task's GroupRule subclass
 * (base size, extra seats left, fixed number of groups, plain-language summary — GroupRule::evaluate).
 */
$rulesFor = function (int $taskId) use ($ctx, $taskOf, $studyGroupsOf) {
    static $cache = [];
    $rule = $taskOf($taskId)?->rule();      // null for an individual task (polymorphism)
    if ($rule === null) {
        return null;
    }
    return $cache[$taskId] ??= $rule->evaluate($studyGroupsOf($taskId), count($ctx['members']));
};

/**
 * A group's normal size. A group made for a task always follows its task's CURRENT GroupRule
 * (the stored max_members is only what it was when the group was made), so editing the task
 * updates every group of that task. Study groups keep their own limit. Null = no limit.
 */
$maxOf = function (array $g) use ($taskOf, $rulesFor): ?int {
    $taskId = $g['task_id'] ? (int) $g['task_id'] : null;
    if ($taskOf($taskId)?->rule() !== null) {
        return $rulesFor($taskId)['base'];
    }
    return StudyGroup::fromRow($g)->ownLimit();
};

/** How many members a group can hold right now (GroupRule::capacityOf: base, +1 while an extra seat is free). */
$capacity = function (array $g) use ($taskOf, $rulesFor, $maxOf): ?int {
    $group = StudyGroup::fromRow($g);
    $rule = $taskOf($group->taskId())?->rule();
    if ($rule === null) {
        return $maxOf($g);                   // a study group: its own limit (or none)
    }
    return $rule->capacityOf($group, $rulesFor($group->taskId()));   // [OOP: POLYMORPHISM]
};
$isFull = fn (array $g) => $capacity($g) !== null && count($g['members']) >= $capacity($g);

/** Can classmates organize this group themselves? False for groups the prof assigned. */
$studentsOrganize = function (array $g) use ($taskOf): bool {
    return $taskOf($g['task_id'] ? (int) $g['task_id'] : null)?->rule()?->studentsOrganize() ?? true;
};

/**
 * One plain status line for a group, the same on Task Details and in Classmates › Groups:
 * "Empty · join to start it", "Needs 2 more", "1 spot left", "Full", "Full · room for 1 extra", "Full · 1 extra".
 */
$stateOf = function (array $g) use ($maxOf, $capacity): array {
    $n = count($g['members']);
    $base = $maxOf($g);
    $cap = $capacity($g);
    if ($n === 0) {
        return ['state' => 'empty', 'state_label' => 'Empty · join to start it'];
    }
    if ($base === null) {
        return ['state' => 'open', 'state_label' => $n . ' member' . ($n === 1 ? '' : 's')];
    }
    if ($n > $base) {
        return ['state' => 'extra', 'state_label' => 'Full · ' . ($n - $base) . ' extra'];   // normally 1; more only if an officer added them
    }
    if ($n === $base) {
        return $cap > $base ? ['state' => 'extra_seat', 'state_label' => 'Full · room for 1 extra'] : ['state' => 'full', 'state_label' => 'Full'];
    }
    $short = $base - $n;
    return ['state' => 'open', 'state_label' => $short === 1 ? '1 spot left' : "$short spots left"];
};

/** Pending join requests, grouped by group id. */
$pendingByGroup = [];
foreach (section_rows('group_requests', $sectionId) as $r) {
    if ($r['status'] === 'pending' && isset($groups[(int) $r['group_id']])) {
        $pendingByGroup[(int) $r['group_id']][] = $r;
    }
}

/**
 * Group + join requests. Members (and the admin) see who is asking to join;
 * everyone sees whether THEY have a request waiting (my_request).
 */
$present = function (array $g) use ($ctx, $uid, $me, $groups, $pendingByGroup, $capacity, $maxOf, $stateOf, $studentsOrganize) {
    $p = present_group($g, $ctx['users'], $ctx['subjectIndex'], $ctx['taskIndex'], $uid);
    $p['max_members'] = $maxOf($g);   // the task's current limit, not the one saved at creation
    $grouped = $g['task_id'] ? grouped_for_task($groups, (int) $g['task_id']) : new HashSet();
    $requests = [];
    $mine = null;
    foreach ($pendingByGroup[$g['id']] ?? [] as $r) {
        $who = (int) $r['user_id'];
        if ($who === $uid) {
            $mine = (int) $r['id'];
        }
        if ($grouped->contains((string) $who)) {
            continue;   // already found a group for this task
        }
        $u = $ctx['users'][$who] ?? null;
        if ($u) {
            $requests[] = ['id' => (int) $r['id'], 'user_id' => $who, 'name' => $u['first_name'] . ' ' . $u['last_name'], 'student_id' => $u['student_id'], 'created_at' => $r['created_at']];
        }
    }
    $organize = $studentsOrganize($g);
    $officer = $me->can(Permission::MANAGE_ANY_GROUP);
    $inTask = $g['task_id'] && $grouped->contains((string) $uid);
    $cap = $capacity($g);
    $full = $cap !== null && count($g['members']) >= $cap;
    return $p + ['requests' => ($p['is_member'] || $me->can(Permission::ANSWER_ANY_REQUEST)) ? $requests : [], 'request_count' => count($requests), 'my_request' => $mine,
        'capacity' => $cap, 'target' => $maxOf($g), 'is_full' => $full] + $stateOf($g) + [
        'assigned' => !$organize,                                    // made by the prof: officers change it
        'can_manage' => $officer || ($organize && (int) $g['created_by'] === $uid),
        'can_move' => $officer,                                      // move / remove any member
        'can_join' => !$p['is_member'] && !$inTask && $organize && $me->can(Permission::JOIN_GROUP)
            && count($g['members']) === 0,                           // an empty group: take it, no one to ask
        'can_request' => !$p['is_member'] && !$inTask && $organize && $me->can(Permission::JOIN_GROUP)
            && count($g['members']) > 0 && !$mine && !$full,
        'can_leave' => $p['is_member'] && ($organize || $officer),
    ];
};

/**
 * [DSA: GRAPH] — suggested groupmates. For each classmate still without a group:
 *   never: no edge between you (you've never been in a group together) -> listed first
 *   via:   the shortest chain of classmates linking you (BFS), e.g. you -> Juan -> Ana
 * Returns [userId => ['never' => bool, 'via' => [names between you and them]]].
 */
function suggestions(array $ctx, array $groups, int $uid, array $people): array
{
    $graph = build_graph((int) $ctx['section']['id'], $ctx['members'], $groups);
    $name = fn (int $id) => isset($ctx['users'][$id]) ? $ctx['users'][$id]['first_name'] : '?';
    $out = [];
    foreach ($people as $p) {
        $id = (int) $p['id'];
        if ($id === $uid) {
            continue;
        }
        $never = !$graph->isLinked($uid, $id);
        $via = [];
        if ($never) {
            $path = $graph->shortestPath($uid, $id);
            for ($i = 1; $path !== null && $i < count($path) - 1; $i++) {
                $via[] = $name($path[$i]);
            }
        }
        $out[$id] = ['never' => $never, 'via' => $via];
    }
    return $out;
}

/** The viewer's group for a task, or null. */
function my_group_for_task(array $groups, int $taskId, int $uid): ?array
{
    foreach ($groups as $g) {
        if ((int) $g['task_id'] === $taskId && Algorithms::linearContains($g['members'], $uid)) {   // [ALGORITHM: LINEAR SEARCH]
            return $g;
        }
    }
    return null;
}

/* ---------------------------------------------------------------- reads */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $taskId = param('task_id') ? (int) param('task_id') : null;

    if ($taskId) {
        $task = group_task($ctx, $taskId);
        $out = [];
        $sizes = [];          // member count of each of this task's groups, for GroupRule::autoPlan()
        foreach ($groups as $g) {
            if ((int) $g['task_id'] === $taskId) {
                $out[] = $present($g);
                $sizes[] = count($g['members']);
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
            'suggest' => suggestions($ctx, $groups, $uid, $ungrouped),
            'in_group' => $grouped->contains((string) $uid),
            'members' => count($ctx['members']),
            'rules' => $rulesFor($taskId),
            'plan' => $taskOf($taskId)->rule()->autoPlan($sizes, count($ungrouped), count($ctx['members'])),
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
        if (Task::fromRow($t)->acceptsGroups()) {   // [OOP: POLYMORPHISM]
            $grouped = grouped_for_task($groups, (int) $t['id']);
            $count = 0;
            foreach ($groups as $g) {
                $count += (int) $g['task_id'] === (int) $t['id'] ? 1 : 0;
            }
            $mineG = my_group_for_task($groups, (int) $t['id'], $uid);
            $asked = 0;     // my pending requests to groups of this task
            $waiting = 0;   // requests waiting for MY group to answer
            foreach ($groups as $g) {
                if ((int) $g['task_id'] !== (int) $t['id']) {
                    continue;
                }
                $pg = $present($g);
                $asked += $pg['my_request'] ? 1 : 0;
                $waiting += $pg['is_member'] ? $pg['request_count'] : 0;
            }
            $upcoming[] = present_task($t, $ctx['subjectIndex'], $ctx['users']) + [
                'groups_formed' => $count,
                'grouped_count' => $grouped->size(),
                'in_group' => $grouped->contains((string) $uid),
                'my_group' => $mineG ? ['id' => $mineG['id'], 'name' => $mineG['name'], 'size' => count($mineG['members'])] : null,
                'my_requests' => $asked,
                'requests_waiting' => $waiting,
                'can_create' => $rulesFor((int) $t['id'])['can_create'],
                'rules' => $rulesFor((int) $t['id']),               // [OOP] the rule's own numbers and summary
                'extras_left' => $rulesFor((int) $t['id'])['extras_left'],
                'groups_left' => $rulesFor((int) $t['id'])['groups_left'],
                'unplaceable' => $rulesFor((int) $t['id'])['unplaceable'],
            ];
        }
    }
    // [DSA: GRAPH] — classmates with no edge at all (degree 0) have never been in a group with anyone
    $isolated = [];
    if ($me->can(Permission::MANAGE_SECTION)) {
        $graph = build_graph($sectionId, $ctx['members'], $groups);
        foreach (Algorithms::selectionSortByLastName($ctx['members']) as $m) {
            if ($graph->degree((int) $m['id']) === 0) {
                $isolated[] = ['id' => (int) $m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name']];
            }
        }
    }
    respond(['mine' => $mine, 'others' => $others, 'upcoming' => $upcoming, 'members' => count($ctx['members']), 'role' => $user['role'], 'me' => $uid, 'isolated' => $isolated]);
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
// Own group, or any for an officer; groups the prof assigned are for officers only
$canManage = fn (array $g) => $me->can(Permission::MANAGE_ANY_GROUP) || ($studentsOrganize($g) && $me->canManageGroup((int) $g['created_by']));
/** Keep an emptied group when the prof fixed the number of groups (it's a seat waiting to be taken). */
$keepsWhenEmpty = fn (array $g) => ($taskOf($g['task_id'] ? (int) $g['task_id'] : null)?->rule()?->count()) !== null;
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

switch (param('action')) {
    case 'create': {
        if (!$me->can(Permission::JOIN_GROUP)) {   // e.g. an Alumni member (their status holds this back)
            fail('Alumni members can’t make new groups.', 403);
        }
        $taskId = param('task_id') ? (int) param('task_id') : null;
        $task = group_task($ctx, $taskId);
        $officer = $me->can(Permission::MANAGE_ANY_GROUP);
        if ($task && !$rulesFor($taskId)['students_organize'] && !$officer) {
            fail('Your prof assigned these groups. Ask an officer if something needs to change.', 403);
        }
        if ($task && !$rulesFor($taskId)['can_create'] && !$officer) {
            $n = $rulesFor($taskId)['count'];
            fail($n !== null
                ? "Your prof set $n group" . ($n === 1 ? '' : 's') . ' and they’re all made. Join one of them instead.'
                : 'Everyone already has a group.', 409);
        }
        $name = preg_replace('/\s+/', ' ', (string) param('name', ''));
        if ($name === '') {
            $name = $task ? next_group_name($groups, $taskId) : '';
        }
        if ($name === '' || strlen($name) > 40) {
            fail('Give the group a name (up to 40 characters).');
        }
        if ($task && group_names_for_task($groups, $taskId)->contains($name)) {
            fail("There’s already a group called “{$name}” for this task. Pick another name.", 409);
        }
        $subjectId = $task ? (int) $task['subject_id'] : (param('subject_id') ? (int) param('subject_id') : null);
        if ($subjectId !== null && !isset($ctx['subjectIndex'][$subjectId])) {
            fail('Pick a subject from this section.');
        }
        // A task's groups follow its rule: the base size, +1 when an extra seat is free
        $max = $task ? $rulesFor($taskId)['base'] : (param('max_members') ? (int) param('max_members') : null);
        if (!$task && $max !== null && ($max < 2 || $max > 50)) {
            fail('Group size limit must be between 2 and 50.');
        }
        $room = $task && $max !== null ? $max + ($rulesFor($taskId)['extras_left'] > 0 ? 1 : 0) : $max;

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
        if ($room !== null && count($ids) > $room && !$officer) {
            fail("This group can have at most $room members.");
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

    case 'request_join': {
        if (!$group) {
            fail('Pick a group.');
        }
        if (!$me->can(Permission::JOIN_GROUP)) {
            fail('Alumni members can’t join groups.', 403);
        }
        if (!$studentsOrganize($group)) {
            fail('Your prof assigned these groups. Ask an officer to move you.', 403);
        }
        if (Algorithms::linearContains($group['members'], $uid)) {   // [ALGORITHM: LINEAR SEARCH]
            fail('You’re already in this group.', 409);
        }
        if (!$group['members']) {
            fail('This group is empty, so there’s no one to ask. Join it directly.', 409);
        }
        if ($group['task_id'] && grouped_for_task($groups, (int) $group['task_id'])->contains((string) $uid)) {
            fail('You’re already in a group for this task. Leave it first.', 409);
        }
        if ($isFull($group)) {
            fail('This group is full.', 409);
        }
        foreach ($pendingByGroup[$group['id']] ?? [] as $r) {
            if ((int) $r['user_id'] === $uid) {
                fail('You already asked to join this group.', 409);
            }
        }
        $rid = Store::insert('group_requests', ['section_id' => $sectionId, 'group_id' => $group['id'], 'user_id' => $uid]);
        $link = $group['task_id'] ? "classmates.html#groups&task={$group['task_id']}" : 'classmates.html#groups';
        foreach ($group['members'] as $id) {
            notify($id, $sectionId, 'group', "$fullName asked to join “{$group['name']}”", $link);
        }
        respond(['request_id' => $rid], 201);
    }

    case 'cancel_request': {
        if (!$group) {
            fail('Pick a group.');
        }
        foreach ($pendingByGroup[$group['id']] ?? [] as $r) {
            if ((int) $r['user_id'] === $uid) {
                Store::update('group_requests', (int) $r['id'], ['status' => 'cancelled']);
                // [DSA: STACK] — PUSH the opposite: Undo sends the request again
                undo_push($sectionId, new RequestAgainAction("Cancelled your request to “{$group['name']}”", (int) $group['id']));
                respond(['undo' => true]);
            }
        }
        fail('You have no request for this group.', 404);
    }

    case 'respond_request': {
        // Any member of the group (or the class admin) can answer
        $req = null;
        foreach (section_rows('group_requests', $sectionId) as $r) {
            if ((int) $r['id'] === (int) param('request_id', 0)) {
                $req = $r;
            }
        }
        if (!$req || $req['status'] !== 'pending') {
            fail('That request was already answered.', 404);
        }
        $g = $groups[(int) $req['group_id']] ?? null;
        if (!$g) {
            fail('Group not found.', 404);
        }
        if (!$me->can(Permission::ANSWER_ANY_REQUEST) && !Algorithms::linearContains($g['members'], $uid)) {   // [ALGORITHM: LINEAR SEARCH]
            fail('Only members of this group can answer join requests.', 403);
        }
        $who = (int) $req['user_id'];
        $accept = filter_var(param('accept', false), FILTER_VALIDATE_BOOLEAN);
        // They may have left the section after asking: close the request instead of adding a non-member
        if (!$memberIds->contains((string) $who)) {
            Store::update('group_requests', (int) $req['id'], ['status' => 'cancelled']);
            fail('They’re no longer in this section, so the request was closed.', 409);
        }
        $link = $g['task_id'] ? "task.html?id={$g['task_id']}" : 'classmates.html#groups';
        if (!$accept) {
            Store::update('group_requests', (int) $req['id'], ['status' => 'declined']);
            notify($who, $sectionId, 'group', "Your request to join “{$g['name']}” was declined", $link);
            respond(['accepted' => false]);
        }
        if ($isFull($g)) {
            fail('Your group is already full.', 409);
        }
        if ($g['task_id'] && grouped_for_task($groups, (int) $g['task_id'])->contains((string) $who)) {
            Store::update('group_requests', (int) $req['id'], ['status' => 'cancelled']);
            fail('They already joined another group for this task.', 409);
        }
        Store::begin();
        Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $g['id'], 'user_id' => $who]);
        Store::update('group_requests', (int) $req['id'], ['status' => 'accepted']);
        // Their other requests for the same task are no longer needed
        foreach ($pendingByGroup as $gid => $rows) {
            foreach ($rows as $r) {
                $sameTask = $g['task_id'] && (int) ($groups[$gid]['task_id'] ?? 0) === (int) $g['task_id'];
                if ((int) $r['user_id'] === $who && (int) $r['id'] !== (int) $req['id'] && $sameTask) {
                    Store::update('group_requests', (int) $r['id'], ['status' => 'cancelled']);
                }
            }
        }
        Store::commit();
        notify($who, $sectionId, 'group', "You’re in! “{$g['name']}” accepted your request", $link);
        respond(['accepted' => true]);
    }

    case 'join': {
        // Anyone may take an EMPTY group (there's no one to ask) and becomes its leader;
        // a group with members is joined by request, except for the President (JOIN_DIRECTLY)
        if (!$group) {
            fail('Pick a group.');
        }
        $empty = !$group['members'];
        if (!$empty && !$me->can(Permission::JOIN_DIRECTLY)) {
            fail('Send a join request — the group’s members will accept you.', 403);
        }
        if (!$me->can(Permission::JOIN_GROUP)) {
            fail('Alumni members can’t join groups.', 403);
        }
        if (!$studentsOrganize($group) && !$me->can(Permission::MANAGE_ANY_GROUP)) {
            fail('Your prof assigned these groups. Ask an officer to move you.', 403);
        }
        foreach ($group['members'] as $id) {
            if ($id === $uid) {
                fail('You’re already in this group.', 409);
            }
        }
        if ($group['task_id'] && grouped_for_task($groups, (int) $group['task_id'])->contains((string) $uid)) {
            fail('You’re already in a group for this task. Leave it first.', 409);
        }
        if ($isFull($group)) {
            fail('This group is full.', 409);
        }
        Store::begin();
        Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $group['id'], 'user_id' => $uid]);
        // [DSA: QUEUE] — your join requests to other groups of this task are no longer needed
        foreach ($pendingByGroup as $gid => $rows) {
            foreach ($rows as $r) {
                if ((int) $r['user_id'] === $uid && $group['task_id'] && (int) ($groups[$gid]['task_id'] ?? 0) === (int) $group['task_id']) {
                    Store::update('group_requests', (int) $r['id'], ['status' => 'cancelled']);
                }
            }
        }
        if ($empty) {
            Store::update('study_groups', $group['id'], ['created_by' => $uid]);   // first in: you lead it
        }
        Store::commit();
        if (!$empty && (int) $group['created_by'] !== $uid) {
            notify((int) $group['created_by'], $sectionId, 'group', "$fullName joined “{$group['name']}”", 'classmates.html#groups');
        }
        respond(['leader' => $empty]);
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
        if (!$studentsOrganize($group) && !$me->can(Permission::MANAGE_ANY_GROUP)) {
            fail('Your prof assigned this group. Ask an officer to move you.', 403);
        }
        // [DSA: STACK] — PUSH the opposite: Undo puts you back (or re-makes the group if it gets deleted)
        undo_push($sectionId, new RejoinGroupAction("Left “{$group['name']}”", [
            'id' => $group['id'], 'name' => $group['name'], 'task_id' => $group['task_id'] !== null ? (int) $group['task_id'] : null,
            'subject_id' => $group['subject_id'] !== null ? (int) $group['subject_id'] : null, 'purpose' => $group['purpose'],
            'max_members' => $group['max_members'] !== null ? (int) $group['max_members'] : null, 'created_by' => (int) $group['created_by'],
        ]));
        Store::begin();
        Store::delete('group_members', (int) $row['id']);
        if (count($group['members']) === 1 && !$keepsWhenEmpty($group)) {
            delete_group($sectionId, $group['id']);       // an empty group is removed automatically…
        } elseif (count($group['members']) === 1) {
            // …unless the prof fixed the number of groups: it stays as an empty group to join
        } elseif ((int) $group['created_by'] === $uid) {
            // Hand the group to someone still in it, so it can still be managed
            foreach ($group['members'] as $id) {
                if ($id !== $uid) {
                    Store::update('study_groups', $group['id'], ['created_by' => $id]);
                    break;
                }
            }
        }
        Store::commit();
        respond(['undo' => true]);
    }

    case 'add_members': {
        if (!$group || !$canManage($group)) {
            fail('Only the group’s leader or an officer can add members.', 403);
        }
        $officer = $me->can(Permission::MANAGE_ANY_GROUP);
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
        $cap = $capacity($group);
        if (!$officer && $cap !== null && count($group['members']) + count($added) > $cap) {   // officers may override
            fail("This group can have at most $cap members right now.");
        }
        foreach ($added as $id) {
            Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $group['id'], 'user_id' => $id]);
            notify($id, $sectionId, 'group', "$fullName added you to “{$group['name']}”", $group['task_id'] ? "task.html?id={$group['task_id']}" : 'classmates.html#groups');
        }
        respond(['added' => count($added)]);
    }

    case 'remove_member':
    case 'move_member': {
        // Officers fix groups: take someone out, or move them to another group of the same task
        if (!$me->can(Permission::MANAGE_ANY_GROUP)) {
            fail('Only officers can move people between groups.', 403);
        }
        if (!$group) {
            fail('Pick a group.');
        }
        $who = (int) param('user_id', 0);
        if (!$memberIds->contains((string) $who)) {
            fail('Pick someone in this section.', 404);
        }
        $moving = param('action') === 'move_member';
        // Where they are now: this group (remove), or their group for the same task (move)
        $from = null;
        foreach ($groups as $g) {
            $same = $moving ? ($group['task_id'] && (int) $g['task_id'] === (int) $group['task_id']) : $g['id'] === $group['id'];
            if ($same && Algorithms::linearContains($g['members'], $who)) {   // [ALGORITHM: LINEAR SEARCH]
                $from = $g;
            }
        }
        if (!$moving && $from === null) {
            fail('They’re not in this group.', 404);
        }
        if ($moving && $from !== null && $from['id'] === $group['id']) {
            respond(['moved' => false]);   // already there
        }
        $name = trim($ctx['users'][$who]['first_name'] . ' ' . $ctx['users'][$who]['last_name']);
        Store::begin();
        if ($from !== null) {
            foreach (section_rows('group_members', $sectionId) as $m) {
                if ((int) $m['group_id'] === $from['id'] && (int) $m['user_id'] === $who) {
                    Store::delete('group_members', (int) $m['id']);
                }
            }
            $rest = [];
            foreach ($from['members'] as $id) {
                if ($id !== $who) {
                    $rest[] = $id;
                }
            }
            if (!$rest && !$keepsWhenEmpty($from)) {
                delete_group($sectionId, $from['id']);
            } elseif ($rest && (int) $from['created_by'] === $who) {
                Store::update('study_groups', $from['id'], ['created_by' => $rest[0]]);   // the next member leads it
            }
        }
        if ($moving) {
            Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $group['id'], 'user_id' => $who]);
            if (!$group['members']) {
                Store::update('study_groups', $group['id'], ['created_by' => $who]);
            }
        }
        Store::commit();
        $link = $group['task_id'] ? "task.html?id={$group['task_id']}" : 'classmates.html#groups';
        if ($who !== $uid) {
            notify($who, $sectionId, 'group', $moving
                ? "$fullName moved you to “{$group['name']}”" . ($from ? " (from “{$from['name']}”)" : '')
                : "$fullName took you out of “{$group['name']}”", $link);
        }
        respond(['moved' => $moving, 'removed' => !$moving, 'name' => $name]);
    }

    case 'rename': {
        if (!$group || !$canManage($group)) {
            fail('Only the group’s creator or the President can rename it.', 403);
        }
        $name = preg_replace('/\s+/', ' ', (string) param('name', ''));
        if ($name === '' || strlen($name) > 40) {
            fail('Give the group a name (up to 40 characters).');
        }
        if ($name === $group['name']) {
            respond(['name' => $name]);
        }
        // [DSA: HASHSET] — names used by the OTHER groups of this task ("group 3" -> "Group 3" is allowed)
        if ($group['task_id']) {
            $others = [];
            foreach ($groups as $g) {
                if ($g['id'] !== $group['id']) {
                    $others[] = $g;
                }
            }
            if (group_names_for_task($others, (int) $group['task_id'])->contains($name)) {
                fail("There’s already a group called “{$name}” for this task. Pick another name.", 409);
            }
        }
        Store::update('study_groups', $group['id'], ['name' => $name]);
        $link = $group['task_id'] ? "task.html?id={$group['task_id']}" : 'classmates.html#groups';
        foreach ($group['members'] as $id) {
            if ($id !== $uid) {
                notify($id, $sectionId, 'group', "$fullName renamed “{$group['name']}” to “{$name}”", $link);
            }
        }
        respond(['name' => $name]);
    }

    case 'delete': {
        if (!$group || !$canManage($group)) {
            fail('Only the group’s creator or the class admin can delete it.', 403);
        }
        Store::begin();
        delete_group($sectionId, $group['id']);
        Store::commit();
        respond();
    }

    case 'auto_assign': {
        if (!$me->can(Permission::AUTO_GROUP)) {
            fail('Only the class admin can auto-group.', 403);
        }
        $taskId = (int) param('task_id', 0);
        $task = group_task($ctx, $taskId);
        $rule = $taskOf($taskId)->rule();
        if (!$rule->studentsOrganize()) {
            fail('Your prof assigned these groups. Add the people left to a group one by one.', 409);
        }
        $max = $rulesFor($taskId)['base'];

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

        // Plan first (same plan the admin previewed), then place students in queue order
        $taskGroups = [];
        foreach ($groups as $g) {
            if ((int) $g['task_id'] === $taskId) {
                $taskGroups[] = $g;
            }
        }
        // [OOP: POLYMORPHISM] — the task's rule plans it (fill to the base size, extra seats, a smaller group…)
        $plan = $rule->autoPlan(array_map(fn ($g) => count($g['members']), $taskGroups), $waiting->size(), count($ctx['members']));
        Store::begin();
        $filled = 0;
        foreach ($taskGroups as $i => $g) {
            for ($n = 0; $n < $plan['add'][$i] && !$waiting->isEmpty(); $n++) {
                $id = $waiting->dequeue();
                Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $g['id'], 'user_id' => $id]);
                notify($id, $sectionId, 'group', "You were placed in “{$g['name']}” for “{$task['title']}”", "task.html?id=$taskId");
                $filled++;
            }
        }
        $made = 0;
        foreach ($plan['new'] as $size) {
            $gid = Store::insert('study_groups', [
                'section_id' => $sectionId, 'name' => next_group_name(load_groups($sectionId), $taskId), 'subject_id' => (int) $task['subject_id'],
                'task_id' => $taskId, 'purpose' => $task['title'], 'max_members' => $max, 'created_by' => $uid,
            ]);
            for ($i = 0; $i < $size && !$waiting->isEmpty(); $i++) {
                $id = $waiting->dequeue();      // DEQUEUE students in order
                Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $gid, 'user_id' => $id]);
                notify($id, $sectionId, 'group', "You were placed in a group for “{$task['title']}”", "task.html?id=$taskId");
            }
            $made++;
        }
        Store::commit();
        respond(['groups_created' => $made, 'sizes' => $plan['new'], 'topped_up' => $filled, 'left' => $plan['left']]);
    }

    default:
        fail('Unknown action.');
}
