<?php
/**
 * [OOP + DSA] Actions a student can undo. Each one is an object that knows how to reverse itself;
 * the UndoStack (custom_dsa.php) holds these objects, newest on top.
 *
 *   ABSTRACTION   UndoAction is abstract: undo() is declared here and written by each subclass.
 *   INHERITANCE   ToggleDoneAction, RejoinGroupAction and RequestAgainAction extend UndoAction.
 *   POLYMORPHISM  api/undo.php just calls $action->undo($context): the object popped off the stack
 *                 decides what happens, so there is no switch on the action type.
 *   ENCAPSULATION each action keeps its own data private; UndoContext's properties are readonly.
 */

declare(strict_types=1);

/** What an undo needs to know about the request it runs in. Readonly: set once, never changed. */
final class UndoContext
{
    public function __construct(
        public readonly int $sectionId,
        public readonly int $userId,
        public readonly string $fullName,
        public readonly array $taskIndex,
        public readonly array $groups,      // load_groups() rows
    ) {
    }

    /** Is the user already in a group for this task (one group per task)? */
    public function groupedFor(?int $taskId): bool
    {
        if (!$taskId) {
            return false;
        }
        foreach ($this->groups as $g) {
            if ((int) $g['task_id'] === $taskId && Algorithms::linearContains($g['members'], $this->userId)) {   // [ALGORITHM: LINEAR SEARCH]
                return true;
            }
        }
        return false;
    }
}

/** Thrown when an action can't be undone any more; the message is shown to the user. */
final class UndoNotPossible extends RuntimeException
{
}

abstract class UndoAction
{
    public function __construct(private string $label)
    {
    }

    /** What the user did, e.g. "Left “Group 2”". */
    public function label(): string
    {
        return $this->label;
    }

    /** Short name stored in the session, used to rebuild the right subclass. */
    abstract protected function kind(): string;

    /** The subclass's own data, for the session. */
    abstract protected function payload(): array;

    /** Reverse the action. Throws UndoNotPossible with a reason when it can't be done. */
    abstract public function undo(UndoContext $c): void;

    /** Flat array for the session (an object can't safely live in $_SESSION across code changes). */
    final public function toArray(): array
    {
        return ['type' => $this->kind(), 'label' => $this->label] + $this->payload();
    }

    /** Factory: rebuilds the right subclass from its stored array. */
    public static function fromArray(array $a): ?UndoAction
    {
        $label = (string) ($a['label'] ?? '');
        return match ($a['type'] ?? '') {
            'done' => new ToggleDoneAction($label, (int) $a['task_id'], (bool) $a['done']),
            'rejoin' => new RejoinGroupAction($label, (array) $a['group']),
            'request' => new RequestAgainAction($label, (int) $a['group_id']),
            default => null,
        };
    }
}

/** Undo "mark as done" / "mark as not done": set the task back. */
final class ToggleDoneAction extends UndoAction
{
    public function __construct(string $label, private int $taskId, private bool $setDoneTo)
    {
        parent::__construct($label);
    }

    protected function kind(): string
    {
        return 'done';
    }

    protected function payload(): array
    {
        return ['task_id' => $this->taskId, 'done' => $this->setDoneTo];
    }

    public function undo(UndoContext $c): void
    {
        $task = $c->taskIndex[$this->taskId] ?? null;
        if (!$task || $task['status'] !== 'approved') {
            throw new UndoNotPossible('That task was removed, so this can’t be undone.');
        }
        $row = null;
        foreach (section_rows('task_done', $c->sectionId) as $r) {
            if ((int) $r['task_id'] === $this->taskId && (int) $r['user_id'] === $c->userId) {
                $row = $r;
            }
        }
        if ($this->setDoneTo && !$row) {
            Store::insert('task_done', ['section_id' => $c->sectionId, 'task_id' => $this->taskId, 'user_id' => $c->userId]);
        } elseif (!$this->setDoneTo && $row) {
            Store::delete('task_done', (int) $row['id']);
        }
    }
}

/** Undo "leave a group": take the seat back, or make the group again if it was deleted when you left. */
final class RejoinGroupAction extends UndoAction
{
    /** @param array $group snapshot: id, name, task_id, subject_id, purpose, max_members, created_by */
    public function __construct(string $label, private array $group)
    {
        parent::__construct($label);
    }

    protected function kind(): string
    {
        return 'rejoin';
    }

    protected function payload(): array
    {
        return ['group' => $this->group];
    }

    public function undo(UndoContext $c): void
    {
        $snap = $this->group;
        $taskId = $snap['task_id'] !== null ? (int) $snap['task_id'] : null;
        if ($c->groupedFor($taskId)) {
            throw new UndoNotPossible('You’re already in another group for that task, so you can’t rejoin.');
        }
        $g = $c->groups[(int) $snap['id']] ?? null;
        $taskRow = $taskId ? ($c->taskIndex[$taskId] ?? null) : null;
        $task = $taskRow ? Task::fromRow($taskRow) : null;

        if ($g) {
            // Still there: take your seat back if there is one
            $max = $task?->rule()?->max() ?? ($g['max_members'] !== null ? (int) $g['max_members'] : null);
            if ($max !== null && count($g['members']) >= $max) {
                throw new UndoNotPossible("“{$g['name']}” is full now, so you can’t rejoin.");
            }
            Store::begin();
            Store::insert('group_members', ['section_id' => $c->sectionId, 'group_id' => $g['id'], 'user_id' => $c->userId]);
            if ((int) $snap['created_by'] === $c->userId) {
                Store::update('study_groups', (int) $g['id'], ['created_by' => $c->userId]);   // it was yours before you left
            }
            Store::commit();
            return;
        }

        // It was deleted when you left (you were the last one): make it again
        if ($taskId !== null) {
            if (!$task || !$task->isOpen()) {
                throw new UndoNotPossible('That task was removed, so the group can’t come back.');
            }
            $formed = 0;
            foreach ($c->groups as $x) {
                $formed += (int) $x['task_id'] === $taskId ? 1 : 0;
            }
            $limit = $task->rule()?->count();
            if ($limit !== null && $formed >= $limit) {
                throw new UndoNotPossible("All $limit groups for “{$task->title()}” are made now. Ask to join one instead.");
            }
        }
        Store::begin();
        $gid = Store::insert('study_groups', [
            'section_id' => $c->sectionId, 'name' => $snap['name'], 'subject_id' => $snap['subject_id'], 'task_id' => $taskId,
            'purpose' => $snap['purpose'], 'max_members' => $snap['max_members'], 'created_by' => $c->userId,
        ]);
        Store::insert('group_members', ['section_id' => $c->sectionId, 'group_id' => $gid, 'user_id' => $c->userId]);
        Store::commit();
    }
}

/** Undo "cancel my join request": send the request again. */
final class RequestAgainAction extends UndoAction
{
    public function __construct(string $label, private int $groupId)
    {
        parent::__construct($label);
    }

    protected function kind(): string
    {
        return 'request';
    }

    protected function payload(): array
    {
        return ['group_id' => $this->groupId];
    }

    public function undo(UndoContext $c): void
    {
        $g = $c->groups[$this->groupId] ?? null;
        if (!$g) {
            throw new UndoNotPossible('That group no longer exists.');
        }
        if (Algorithms::linearContains($g['members'], $c->userId) || $c->groupedFor($g['task_id'] ? (int) $g['task_id'] : null)) {   // [ALGORITHM: LINEAR SEARCH]
            throw new UndoNotPossible('You already have a group for that task.');
        }
        foreach (section_rows('group_requests', $c->sectionId) as $r) {
            if ((int) $r['group_id'] === $g['id'] && (int) $r['user_id'] === $c->userId && $r['status'] === 'pending') {
                return;   // already waiting
            }
        }
        Store::insert('group_requests', ['section_id' => $c->sectionId, 'group_id' => $g['id'], 'user_id' => $c->userId]);
        foreach ($g['members'] as $id) {
            notify($id, $c->sectionId, 'group', "{$c->fullName} asked to join “{$g['name']}”", 'classmates.html#groups');
        }
    }
}
