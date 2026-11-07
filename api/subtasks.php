<?php
/**
 * A task's checklist, stored as a TREE (items with sub-items).
 *
 * GET  /api/subtasks.php?task_id=12
 *      -> {scope: group|personal, group, members, tree, done, total}
 * POST action=add     {task_id, title, parent_id?}      parent_id = put it under that item
 *      action=toggle  {task_id, id}                     ticks an item and EVERYTHING under it
 *      action=assign  {task_id, id, assignee_id|null}   group checklists only
 *      action=rename  {task_id, id, title}
 *      action=delete  {task_id, id}                     removes the item and its whole branch
 *
 * Whose checklist: on a group task, your group's (everyone in it edits the same list);
 * otherwise your own. [DSA: TREE] — progress is rolled up post-order (children before
 * their parent), so "Chapter 1" shows 1 of 2 done when one of its two sub-items is ticked.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

const MAX_DEPTH = 3;          // item › sub-item › sub-sub-item
const MAX_ITEMS = 60;

$user = current_member();
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = (int) $user['id'];

$task = $ctx['taskIndex'][(int) param('task_id', 0)] ?? null;
if (!$task || $task['status'] !== 'approved' || $task['kind'] !== 'new') {
    fail('Task not found.', 404);
}
$taskId = (int) $task['id'];

// Whose list: your group's for this task, or your own
$group = null;
if (Task::fromRow($task)->acceptsGroups()) {   // [OOP: POLYMORPHISM] GroupTask vs IndividualTask
    foreach (load_groups($sectionId) as $g) {
        if ((int) $g['task_id'] === $taskId && in_array($uid, $g['members'], true)) {
            $group = $g;
        }
    }
}
$rows = [];
foreach (section_rows('subtasks', $sectionId) as $r) {
    $mine = $group ? (int) $r['group_id'] === $group['id'] : ($r['group_id'] === null && (int) $r['owner_id'] === $uid);
    if ((int) $r['task_id'] === $taskId && $mine) {
        $rows[] = $r;
    }
}
$tree = ChecklistTree::fromRows($task['title'], $rows);

$members = [];
if ($group) {
    foreach ($group['members'] as $id) {
        $u = $ctx['users'][$id] ?? null;
        if ($u) {
            $members[] = ['id' => $id, 'name' => $u['first_name'] . ' ' . $u['last_name']];
        }
    }
}

/** The checklist as JSON, with progress rolled up post-order. */
$payload = function () use (&$tree, $group, $members) {   // by reference: $tree is rebuilt after changes
    [$done, $total] = $tree->rollup();
    return [
        'scope' => $group ? 'group' : 'personal',
        'group' => $group ? ['id' => $group['id'], 'name' => $group['name']] : null,
        'members' => $members,
        'done' => $done,
        'total' => $total,
        'tree' => $tree->toArray()['children'],
        'algorithm' => 'Tree, progress rolled up with a post-order traversal',
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond($payload());
}

require_method('POST');
$item = null;
if (param('id')) {
    $item = $tree->find((int) param('id'));
    if ($item === null) {
        fail('That checklist item is gone. Refresh the page.', 404);
    }
}
$clean = function (string $title): string {
    $title = preg_replace('/\s+/', ' ', trim($title));
    if ($title === '' || strlen($title) > 120) {
        fail('Write the item in 1–120 characters.');
    }
    return $title;
};

switch (param('action')) {
    case 'add': {
        if (count($rows) >= MAX_ITEMS) {
            fail('This checklist is full (' . MAX_ITEMS . ' items).', 409);
        }
        $parentId = param('parent_id') ? (int) param('parent_id') : null;
        if ($parentId !== null) {
            if ($tree->find($parentId) === null) {
                fail('The item you’re adding under is gone. Refresh the page.', 404);
            }
            if ($tree->depthOf($parentId) >= MAX_DEPTH) {
                fail('Items can only go ' . MAX_DEPTH . ' levels deep.');
            }
        }
        $id = Store::insert('subtasks', [
            'section_id' => $sectionId, 'task_id' => $taskId,
            'group_id' => $group ? $group['id'] : null, 'owner_id' => $group ? null : $uid,
            'parent_id' => $parentId, 'title' => $clean((string) param('title', '')), 'created_by' => $uid,
        ]);
        if ($parentId !== null) {
            Store::update('subtasks', $parentId, ['done' => 0]);   // a new open sub-item re-opens its parent
        }
        $rows[] = Store::find('subtasks', $id);
        $tree = ChecklistTree::fromRows($task['title'], $rows);
        respond(['id' => $id] + $payload(), 201);
    }

    case 'toggle': {
        // Ticking a parent ticks its whole branch (post-order walk of the subtree)
        $tree->rollup();
        $to = $item->data['done'] ? 0 : 1;
        Store::begin();
        foreach ($tree->subtreeIds($item) as $id) {
            Store::update('subtasks', $id, ['done' => $to]);
        }
        Store::commit();
        break;
    }

    case 'assign': {
        if (!$group) {
            fail('Only a group checklist can assign items.');
        }
        $who = param('assignee_id') ? (int) param('assignee_id') : null;
        if ($who !== null && !in_array($who, $group['members'], true)) {
            fail('Pick someone in your group.');
        }
        Store::update('subtasks', (int) $item->data['id'], ['assignee_id' => $who]);
        break;
    }

    case 'rename': {
        Store::update('subtasks', (int) $item->data['id'], ['title' => $clean((string) param('title', ''))]);
        break;
    }

    case 'delete': {
        Store::begin();
        foreach ($tree->subtreeIds($item) as $id) {   // children first, then the item
            Store::delete('subtasks', $id);
        }
        Store::commit();
        break;
    }

    default:
        fail('Unknown action.');
}

// Reload and answer with the fresh tree
$rows = [];
foreach (section_rows('subtasks', $sectionId) as $r) {
    $mine = $group ? (int) $r['group_id'] === $group['id'] : ($r['group_id'] === null && (int) $r['owner_id'] === $uid);
    if ((int) $r['task_id'] === $taskId && $mine) {
        $rows[] = $r;
    }
}
$tree = ChecklistTree::fromRows($task['title'], $rows);
respond($payload());
