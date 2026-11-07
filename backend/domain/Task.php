<?php
/**
 * [OOP] Tasks (the section's activities) and the rules for group work.
 *
 *   ABSTRACTION   Task is abstract: every task has a title, a subject, a status and a Deadline,
 *                 but whether people work alone or in groups is left to the subclasses.
 *   INHERITANCE   IndividualTask and GroupTask extend Task.
 *   POLYMORPHISM  acceptsGroups(), workMode() and workLabel() differ per subclass, so the
 *                 endpoints ask $task->acceptsGroups() instead of comparing work_mode strings.
 *   ENCAPSULATION GroupRule checks its numbers in the constructor and has no setters, so an
 *                 invalid rule (1 per group, max below min, 0 groups…) can never exist.
 *                 StudyGroup keeps its member list private behind size() / hasMember().
 *   RELATIONSHIPS Task HAS-A Deadline (composition); GroupTask HAS-A GroupRule (composition);
 *                 GroupRule works on many StudyGroups (association).
 */

declare(strict_types=1);

abstract class Task
{
    protected function __construct(
        private int $id,
        private string $title,
        private int $subjectId,
        private string $status,
        private Deadline $deadline,
    ) {
    }

    /** Factory: the stored work mode decides the class. */
    public static function fromRow(array $row): Task
    {
        $args = [(int) $row['id'], (string) $row['title'], (int) $row['subject_id'], (string) $row['status'], Deadlines::fromRow($row)];
        if (($row['work_mode'] ?? 'individual') === 'group' && $row['group_min'] !== null && $row['group_max'] !== null) {
            $count = isset($row['group_count']) && (int) $row['group_count'] > 0 ? (int) $row['group_count'] : null;
            return new GroupTask(...[...$args, new GroupRule((int) $row['group_min'], (int) $row['group_max'], $count)]);
        }
        return new IndividualTask(...$args);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function subjectId(): int
    {
        return $this->subjectId;
    }

    public function deadline(): Deadline
    {
        return $this->deadline;
    }

    /** Published and open for work (not pending, rejected or removed). */
    public function isOpen(): bool
    {
        return $this->status === 'approved';
    }

    /** The group rule, or null when the task isn't group work (GroupTask overrides this). */
    public function rule(): ?GroupRule
    {
        return null;
    }

    /** Can classmates form groups for this task? */
    abstract public function acceptsGroups(): bool;

    /** individual | group — the value stored in tasks.work_mode. */
    abstract public function workMode(): string;

    /** e.g. "Individual" or "Group · 2–3 per group · 3 groups". */
    abstract public function workLabel(): string;
}

final class IndividualTask extends Task
{
    public function acceptsGroups(): bool
    {
        return false;
    }

    public function workMode(): string
    {
        return 'individual';
    }

    public function workLabel(): string
    {
        return 'Individual';
    }
}

final class GroupTask extends Task
{
    public function __construct(int $id, string $title, int $subjectId, string $status, Deadline $deadline, private GroupRule $rule)
    {
        parent::__construct($id, $title, $subjectId, $status, $deadline);
    }

    public function acceptsGroups(): bool
    {
        return true;
    }

    public function workMode(): string
    {
        return 'group';
    }

    public function workLabel(): string
    {
        return 'Group · ' . $this->rule->label();
    }

    /** Overrides Task::rule() (narrower return type: always a rule for group work). */
    public function rule(): GroupRule
    {
        return $this->rule;
    }
}

/**
 * How groups for one task must be formed: size min–max, and optionally an exact number of groups.
 * Immutable: the numbers are checked once in the constructor and can't change afterwards.
 */
final class GroupRule
{
    public const MIN_SIZE = 2;
    public const MAX_SIZE = 20;
    public const MAX_GROUPS = 50;

    public function __construct(private int $min, private int $max, private ?int $count = null)
    {
        if ($min < self::MIN_SIZE || $max < $min || $max > self::MAX_SIZE) {
            throw new InvalidArgumentException('Group size: at least 2 per group, and the maximum can’t be below the minimum (up to 20).');
        }
        if ($count !== null && ($count < 1 || $count > self::MAX_GROUPS)) {
            throw new InvalidArgumentException('Number of groups must be between 1 and 50.');
        }
    }

    public function min(): int
    {
        return $this->min;
    }

    public function max(): int
    {
        return $this->max;
    }

    public function count(): ?int
    {
        return $this->count;
    }

    /** "2–3 per group · 3 groups" */
    public function label(): string
    {
        $size = $this->min === $this->max ? (string) $this->min : "{$this->min}–{$this->max}";
        return "$size per group" . ($this->count ? " · {$this->count} group" . ($this->count === 1 ? '' : 's') : '');
    }

    /**
     * Where a task's grouping stands, given its groups so far and how many classmates there are.
     *   can_create:  false once the exact number of groups exists
     *   extra_slots: how many FULL groups may still take one extra member (+1), for the classmates
     *                who don't fit in count × max seats — e.g. 10 students, exactly 3 groups of 3:
     *                9 seats, 1 excess, so exactly one full group may have 4
     *   unplaceable: classmates who won't fit even with +1 in every group (the numbers are too small)
     *
     * @param StudyGroup[] $groups the task's groups
     */
    public function evaluate(array $groups, int $memberCount): array
    {
        $formed = 0;
        $grouped = 0;
        $open = 0;
        $over = 0;                                   // groups already holding max + 1
        foreach ($groups as $g) {
            $formed++;
            $grouped += $g->size();
            $open += max(0, $this->max - $g->size());
            $over += $g->size() > $this->max ? 1 : 0;
        }
        $left = max(0, $memberCount - $grouped);
        if ($this->count !== null) {
            $groupsLeft = max(0, $this->count - $formed);
            $excess = max(0, $memberCount - $this->count * $this->max);
            $extraSlots = $left > 0 ? max(0, min($excess, $this->count) - $over) : 0;
            $unplaceable = max(0, $excess - $this->count);
        } else {
            $groupsLeft = null;
            // No fixed count: leftovers too few for another group may join a full group as its +1
            $extraSlots = ($left > 0 && $left < $this->min && $left > $open) ? $left - $open : 0;
            $unplaceable = 0;
        }
        return [
            'min' => $this->min, 'max' => $this->max, 'count' => $this->count, 'formed' => $formed,
            'groups_left' => $groupsLeft, 'ungrouped' => $left, 'open_spots' => $open,
            'can_create' => $this->count === null || $formed < $this->count,
            'overflow' => $extraSlots > 0,
            'extra_slots' => $extraSlots,
            'over' => $over,
            'unplaceable' => $unplaceable,
        ];
    }

    /**
     * How many members this group may hold right now: the max, +1 only if the group is already
     * full and the task still has an extra slot; a group already above max keeps its size.
     */
    public function capacityOf(StudyGroup $group, int $extraSlots): int
    {
        if ($group->size() > $this->max) {
            return $group->size();
        }
        return $this->max + ($group->size() >= $this->max && $extraSlots > 0 ? 1 : 0);
    }
}

/** A group of classmates, for one task or for studying. */
final class StudyGroup
{
    /** @param int[] $members */
    public function __construct(
        private int $id,
        private string $name,
        private ?int $taskId,
        private int $createdBy,
        private ?int $ownLimit,
        private array $members,
    ) {
    }

    /** From a load_groups() row (a study_groups row plus its member ids). */
    public static function fromRow(array $g): StudyGroup
    {
        $members = [];
        foreach ($g['members'] as $id) {
            $members[] = (int) $id;
        }
        return new StudyGroup((int) $g['id'], (string) $g['name'], $g['task_id'] !== null ? (int) $g['task_id'] : null,
            (int) $g['created_by'], $g['max_members'] !== null ? (int) $g['max_members'] : null, $members);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function taskId(): ?int
    {
        return $this->taskId;
    }

    public function createdBy(): int
    {
        return $this->createdBy;
    }

    /** The size limit saved on a study group (groups for a task follow the task's GroupRule instead). */
    public function ownLimit(): ?int
    {
        return $this->ownLimit;
    }

    public function size(): int
    {
        return count($this->members);
    }

    public function hasMember(int $userId): bool
    {
        foreach ($this->members as $id) {
            if ($id === $userId) {
                return true;
            }
        }
        return false;
    }
}
