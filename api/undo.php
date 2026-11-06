<?php
/**
 * GET  /api/undo.php  -> what Undo would reverse next: {size, top: label}
 * POST /api/undo.php  -> POP the latest action off the student's UndoStack and reverse it
 *
 * [DSA: STACK] — every reversible action PUSHES its opposite (see undo_push in services.php):
 *   leave a group        -> rejoin it (re-made if it was deleted because it became empty)
 *   cancel a join request -> send the request again
 *   mark a task done/not  -> set it back
 * Undo always reverses the MOST RECENT action first (LIFO).
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stack = undo_stack($sectionId);
    respond(['size' => $stack->size(), 'top' => $stack->peek()['label'] ?? null]);
}

require_method('POST');
$entry = undo_pop($sectionId);
if ($entry === null) {
    fail('Nothing to undo.', 409);
}
$groups = load_groups($sectionId);
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

/** Is the user already in a group for this task (one group per task)? */
$groupedFor = function (?int $taskId) use ($groups, $uid): bool {
    if (!$taskId) {
        return false;
    }
    foreach ($groups as $g) {
        if ((int) $g['task_id'] === $taskId && in_array($uid, $g['members'], true)) {
            return true;
        }
    }
    return false;
};

switch ($entry['type']) {
    case 'done': {
        $taskId = (int) $entry['task_id'];
        $task = $ctx['taskIndex'][$taskId] ?? null;
        if (!$task || $task['status'] !== 'approved') {
            fail('That task was removed, so this can’t be undone.', 409);
        }
        $row = null;
        foreach (section_rows('task_done', $sectionId) as $r) {
            if ((int) $r['task_id'] === $taskId && (int) $r['user_id'] === $uid) {
                $row = $r;
            }
        }
        if ($entry['done'] && !$row) {
            Store::insert('task_done', ['section_id' => $sectionId, 'task_id' => $taskId, 'user_id' => $uid]);
        } elseif (!$entry['done'] && $row) {
            Store::delete('task_done', (int) $row['id']);
        }
        break;
    }

    case 'rejoin': {
        $snap = $entry['group'];
        $taskId = $snap['task_id'] !== null ? (int) $snap['task_id'] : null;
        if ($groupedFor($taskId)) {
            fail('You’re already in another group for that task, so you can’t rejoin.', 409);
        }
        $g = $groups[(int) $snap['id']] ?? null;
        if ($g) {
            // Still there: take your seat back if there is one
            $task = $taskId ? ($ctx['taskIndex'][$taskId] ?? null) : null;
            $max = $task && $task['group_max'] !== null ? (int) $task['group_max'] : ($g['max_members'] !== null ? (int) $g['max_members'] : null);
            if ($max !== null && count($g['members']) >= $max) {
                fail("“{$g['name']}” is full now, so you can’t rejoin.", 409);
            }
            Store::begin();
            Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $g['id'], 'user_id' => $uid]);
            if ((int) $snap['created_by'] === $uid) {
                Store::update('study_groups', $g['id'], ['created_by' => $uid]);   // it was yours before you left
            }
            Store::commit();
        } else {
            // It was deleted when you left (you were the last one): make it again
            if ($taskId) {
                $task = $ctx['taskIndex'][$taskId] ?? null;
                if (!$task || $task['status'] !== 'approved') {
                    fail('That task was removed, so the group can’t come back.', 409);
                }
                $formed = 0;
                foreach ($groups as $x) {
                    $formed += (int) $x['task_id'] === $taskId ? 1 : 0;
                }
                if (!empty($task['group_count']) && $formed >= (int) $task['group_count']) {
                    fail("All {$task['group_count']} groups for “{$task['title']}” are made now. Ask to join one instead.", 409);
                }
            }
            Store::begin();
            $gid = Store::insert('study_groups', [
                'section_id' => $sectionId, 'name' => $snap['name'], 'subject_id' => $snap['subject_id'], 'task_id' => $taskId,
                'purpose' => $snap['purpose'], 'max_members' => $snap['max_members'], 'created_by' => $uid,
            ]);
            Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $gid, 'user_id' => $uid]);
            Store::commit();
        }
        break;
    }

    case 'request': {
        $g = $groups[(int) $entry['group_id']] ?? null;
        if (!$g) {
            fail('That group no longer exists.', 409);
        }
        if (in_array($uid, $g['members'], true) || $groupedFor($g['task_id'] ? (int) $g['task_id'] : null)) {
            fail('You already have a group for that task.', 409);
        }
        foreach (section_rows('group_requests', $sectionId) as $r) {
            if ((int) $r['group_id'] === $g['id'] && (int) $r['user_id'] === $uid && $r['status'] === 'pending') {
                respond(['undone' => $entry['label'], 'size' => undo_stack($sectionId)->size()]);   // already waiting
            }
        }
        Store::insert('group_requests', ['section_id' => $sectionId, 'group_id' => $g['id'], 'user_id' => $uid]);
        foreach ($g['members'] as $id) {
            notify($id, $sectionId, 'group', "$fullName asked to join “{$g['name']}”", 'classmates.html#groups');
        }
        break;
    }

    default:
        fail('That action can’t be undone.', 409);
}

respond(['undone' => $entry['label'], 'size' => undo_stack($sectionId)->size()]);
