<?php
/**
 * [OOP] Tasks (the section's activities) and the rules for group work.
 *
 *   ABSTRACTION   Task is abstract: every task has a title, a subject, a status and a Deadline,
 *                 but whether people work alone or in groups is left to the subclasses.
 *                 GroupRule is abstract too: each way a prof can set up group work is a subclass
 *                 that answers seats(), explain() and studentsOrganize() in its own way.
 *   INHERITANCE   IndividualTask and GroupTask extend Task.
 *                 AssignedGroups, FixedGroupCount, FixedGroupSize and FixedSizeAndCount extend GroupRule.
 *   POLYMORPHISM  acceptsGroups(), workMode() and workLabel() differ per Task subclass; seats(),
 *                 explain(), label() and studentsOrganize() differ per GroupRule subclass, so the
 *                 endpoints never compare work_mode or group_mode strings.
 *   ENCAPSULATION every GroupRule checks its numbers in the constructor and has no setters, so an
 *                 invalid rule (1 per group, 0 groups…) can never exist.
 *                 StudyGroup keeps its member list private behind size() / hasMember().
 *   RELATIONSHIPS Task HAS-A Deadline (composition); GroupTask HAS-A GroupRule (composition);
 *                 GroupRule works on many StudyGroups (association).
 *
 * The four group setups (a prof says one of these):
 *   1. AssignedGroups      "Here are the groups and their members"  -> made at posting, students can't change them
 *   2. FixedGroupCount     "Make 5 groups"                          -> 33 students: 3 groups of 7, 2 groups of 6
 *   3. FixedGroupSize      "4 members per group"                    -> 33 students: 8 groups of 4, the 1 left can
 *                                                                      join a group as its 5th or start a group
 *   4. FixedSizeAndCount   "8 groups of 4"                          -> 33 students: 32 seats, 1 group may take a 5th
 * In 1, 2 and 4 the number of groups is known, so every group is made when the task is posted
 * (empty seats for 2 and 4) and classmates simply join one.
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
        $rule = ($row['work_mode'] ?? 'individual') === 'group' ? GroupRule::fromRow($row) : null;
        return $rule ? new GroupTask(...[...$args, $rule]) : new IndividualTask(...$args);
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

    /** e.g. "Individual" or "Group · 5 groups". */
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
 * How the groups of one task are formed. Each subclass is one way a prof sets it up.
 * The shared work (where the task stands, how many a group may hold, auto-grouping) is done here
 * from the three numbers a subclass gives in seats(): the normal group size (base), how many
 * groups may take ONE extra member (extras), and the fixed number of groups (count, or null).
 */
abstract class GroupRule
{
    public const MIN_SIZE = 2;
    public const MAX_SIZE = 20;
    public const MAX_GROUPS = 50;

    public const ASSIGNED = 'assigned';
    public const BY_COUNT = 'count';
    public const BY_SIZE = 'size';
    public const BY_SIZE_AND_COUNT = 'size_count';

    /**
     * Factory from a tasks row (group_mode, group_max = members per group, group_count).
     * Tasks saved before group_mode existed are read the way they were meant: a count and a strict
     * size -> both; a count only -> number of groups; a size only -> members per group.
     */
    public static function fromRow(array $row): ?GroupRule
    {
        $size = isset($row['group_max']) && (int) $row['group_max'] > 0 ? (int) $row['group_max'] : null;
        $count = isset($row['group_count']) && (int) $row['group_count'] > 0 ? (int) $row['group_count'] : null;
        $mode = $row['group_mode'] ?? null;
        if ($mode === null || $mode === '') {
            $strict = isset($row['group_min']) && (int) $row['group_min'] === $size;
            $mode = $count !== null ? ($size !== null && $strict ? self::BY_SIZE_AND_COUNT : self::BY_COUNT) : self::BY_SIZE;
        }
        try {
            return self::create((string) $mode, $size, $count);
        } catch (InvalidArgumentException) {
            return null;    // an unreadable old row is treated as individual work instead of crashing
        }
    }

    /** Builds the rule for one setup. Throws InvalidArgumentException when the numbers don't make sense. */
    public static function create(string $mode, ?int $size, ?int $count): GroupRule
    {
        return match ($mode) {
            self::ASSIGNED => new AssignedGroups((int) $count),
            self::BY_COUNT => new FixedGroupCount((int) $count),
            self::BY_SIZE => new FixedGroupSize((int) $size),
            self::BY_SIZE_AND_COUNT => new FixedSizeAndCount((int) $size, (int) $count),
            default => throw new InvalidArgumentException('Pick how the groups are formed.'),
        };
    }

    protected static function checkCount(int $count): void
    {
        if ($count < 1 || $count > self::MAX_GROUPS) {
            throw new InvalidArgumentException('Number of groups must be between 1 and ' . self::MAX_GROUPS . '.');
        }
    }

    protected static function checkSize(int $size): void
    {
        if ($size < self::MIN_SIZE || $size > self::MAX_SIZE) {
            throw new InvalidArgumentException('Members per group must be between ' . self::MIN_SIZE . ' and ' . self::MAX_SIZE . '.');
        }
    }

    /** assigned | count | size | size_count — the value stored in tasks.group_mode. */
    abstract public function mode(): string;

    /** Short wording without the class size, e.g. "5 groups" or "4 per group". */
    abstract public function label(): string;

    /**
     * The seats for a class of $classSize: ['base' => normal size or null (no limit),
     * 'extras' => how many groups may take one extra member, 'count' => fixed number of groups or null,
     * 'unplaceable' => classmates who won't fit even then].
     */
    abstract public function seats(int $classSize): array;

    /** One plain sentence for the page, e.g. "33 classmates: 3 groups of 7 and 2 groups of 6." */
    abstract public function explain(int $classSize): string;

    /** Members per group set by the prof (null when the prof only gave a number of groups). */
    public function size(): ?int
    {
        return null;
    }

    /** Number of groups set by the prof (null when only a size was given). */
    public function count(): ?int
    {
        return null;
    }

    /** Do classmates form, join and leave the groups themselves? (Not when the prof assigned them.) */
    public function studentsOrganize(): bool
    {
        return true;
    }

    /** Are all groups made, empty, when the task is posted? (Whenever the number of groups is known.) */
    public function makesEmptyGroups(): bool
    {
        return false;
    }

    /** The planned sizes for a class of $classSize, biggest first (for previews). */
    public function sizes(int $classSize): array
    {
        $s = $this->seats($classSize);
        if ($s['base'] === null || $s['count'] === null) {
            $out = [];
            for ($i = 0; $s['base'] !== null && $i < intdiv($classSize, $s['base']); $i++) {
                $out[] = $s['base'] + ($i < $s['extras'] ? 1 : 0);
            }
            return $out;
        }
        $out = [];
        for ($i = 0; $i < $s['count']; $i++) {
            $out[] = $s['base'] + ($i < $s['extras'] ? 1 : 0);
        }
        return $out;
    }

    /**
     * Where the task's grouping stands, given its groups so far and how many classmates there are.
     * @param StudyGroup[] $groups the task's groups
     */
    public function evaluate(array $groups, int $classSize): array
    {
        $s = $this->seats($classSize);
        $base = $s['base'];
        $formed = 0;
        $grouped = 0;
        $open = 0;
        $over = 0;                                   // groups already holding base + 1
        foreach ($groups as $g) {
            $formed++;
            $grouped += $g->size();
            if ($base !== null) {
                $open += max(0, $base - $g->size());
                $over += $g->size() > $base ? 1 : 0;
            }
        }
        $left = max(0, $classSize - $grouped);
        return [
            'mode' => $this->mode(), 'label' => $this->label(), 'summary' => $this->explain($classSize),
            'base' => $base, 'size' => $this->size(), 'count' => $s['count'], 'formed' => $formed,
            'groups_left' => $s['count'] !== null ? max(0, $s['count'] - $formed) : null,
            'ungrouped' => $left, 'open_spots' => $open,
            'extras' => $s['extras'], 'extras_left' => max(0, $s['extras'] - $over), 'over' => $over,
            'students_organize' => $this->studentsOrganize(),
            'can_create' => $this->studentsOrganize() && $left > 0 && ($s['count'] === null || $formed < $s['count']),
            'unplaceable' => $s['unplaceable'],
        ];
    }

    /**
     * How many members a group may hold right now: the base size, +1 when the group is full and an
     * extra seat is still free; a group already above the base keeps its size. Null = no limit.
     */
    public function capacityOf(StudyGroup $group, array $state): ?int
    {
        $base = $state['base'];
        if ($base === null) {
            return null;
        }
        if ($group->size() > $base) {
            return $group->size();
        }
        return $base + ($group->size() >= $base && $state['extras_left'] > 0 ? 1 : 0);
    }

    /**
     * Auto-group plan for the classmates still without a group ($left), given the sizes of the task's
     * groups. 1. fill every group up to the base size; 2. make the missing groups (fixed count) or new
     * full groups (size only); 3. give the extra seats to full groups; 4. size only: whoever is still
     * left forms one smaller group. Returns add[i] (people added to existing group i), new (sizes of
     * new groups), left (still without a group), joins_existing.
     */
    public function autoPlan(array $sizes, int $left, int $classSize): array
    {
        $s = $this->seats($classSize);
        $n = count($sizes);
        $add = array_fill(0, $n, 0);
        $base = $s['base'];
        if ($base === null || !$this->studentsOrganize()) {
            return ['add' => $add, 'new' => [], 'left' => $left, 'joins_existing' => 0];
        }
        $extrasLeft = $s['extras'];
        foreach ($sizes as $size) {
            $extrasLeft -= $size > $base ? 1 : 0;
        }
        foreach ($sizes as $i => $size) {                       // 1. top up to the base size
            while ($sizes[$i] < $base && $left > 0) {
                $sizes[$i]++;
                $add[$i]++;
                $left--;
            }
        }
        $new = [];
        if ($s['count'] !== null) {                             // 2a. the missing groups of a fixed count
            for ($k = $n; $k < $s['count'] && $left > 0; $k++) {
                $take = min($base, $left);
                $new[] = $take;
                $left -= $take;
            }
        } else {                                                // 2b. as many full groups as possible
            while ($left >= $base) {
                $new[] = $base;
                $left -= $base;
            }
        }
        $all = array_merge($sizes, $new);
        // 3. the extra seats (+1). With no fixed number of groups they're only used when they take
        //    EVERYONE left; otherwise the leftovers stay together as one smaller group (4 + 3, not 5 + 2).
        $useExtras = $s['count'] !== null || $left <= $extrasLeft;
        foreach ($all as $i => $size) {
            if ($useExtras && $left > 0 && $extrasLeft > 0 && $size === $base) {
                $all[$i]++;
                $extrasLeft--;
                $left--;
                if ($i < $n) {
                    $add[$i]++;
                }
            }
        }
        if ($left > 0 && $s['count'] === null) {                // 4. a smaller group for the rest
            $all[] = $left;
            $left = 0;
        }
        $joins = 0;
        foreach ($add as $a) {
            $joins += $a;
        }
        return ['add' => $add, 'new' => array_slice($all, $n), 'left' => $left, 'joins_existing' => $joins];
    }

    /** The tasks columns for this rule. */
    public function toColumns(): array
    {
        return ['group_mode' => $this->mode(), 'group_min' => $this->size(), 'group_max' => $this->size(), 'group_count' => $this->count()];
    }

    protected static function plural(int $n, string $one, ?string $many = null): string
    {
        return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
    }

    /** 3 -> "3rd", 5 -> "5th", 11 -> "11th". */
    protected static function ordinal(int $n): string
    {
        $suffix = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : match ($n % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
        return $n . $suffix;
    }
}

/** 1. The prof gave the groups and their members. Students can't change them; officers can. */
final class AssignedGroups extends GroupRule
{
    public function __construct(private int $groups)
    {
        self::checkCount($groups);
    }

    public function mode(): string
    {
        return self::ASSIGNED;
    }

    public function label(): string
    {
        return 'Assigned · ' . self::plural($this->groups, 'group');
    }

    public function count(): ?int
    {
        return $this->groups;
    }

    public function studentsOrganize(): bool
    {
        return false;
    }

    public function seats(int $classSize): array
    {
        return ['base' => null, 'extras' => 0, 'count' => $this->groups, 'unplaceable' => 0];
    }

    public function explain(int $classSize): string
    {
        return 'Your prof assigned the ' . self::plural($this->groups, 'group') . ' and their members. Only officers can move people.';
    }
}

/** 2. The prof only said how many groups. The class is spread evenly; the remainder makes some groups 1 bigger. */
final class FixedGroupCount extends GroupRule
{
    public function __construct(private int $groups)
    {
        self::checkCount($groups);
    }

    public function mode(): string
    {
        return self::BY_COUNT;
    }

    public function label(): string
    {
        return self::plural($this->groups, 'group');
    }

    public function count(): ?int
    {
        return $this->groups;
    }

    public function makesEmptyGroups(): bool
    {
        return true;
    }

    /** 33 classmates, 5 groups: base 6, and 33 % 5 = 3 groups may have 7. */
    public function seats(int $classSize): array
    {
        $base = max(1, intdiv($classSize, $this->groups));
        $extras = $classSize >= $this->groups ? $classSize % $this->groups : 0;
        return ['base' => $base, 'extras' => $extras, 'count' => $this->groups, 'unplaceable' => 0];
    }

    public function explain(int $classSize): string
    {
        $s = $this->seats($classSize);
        $plain = $this->groups - $s['extras'];
        $parts = [];
        if ($s['extras']) {
            $parts[] = self::plural($s['extras'], 'group') . ' of ' . ($s['base'] + 1);
        }
        if ($plain) {
            $parts[] = self::plural($plain, 'group') . ' of ' . $s['base'];
        }
        return "Your prof set {$this->label()}. With $classSize classmates that’s " . implode(' and ', $parts) . '.';
    }
}

/** 3. The prof only said how many per group. Leftovers join a group as its +1, or make a smaller group. */
final class FixedGroupSize extends GroupRule
{
    public function __construct(private int $members)
    {
        self::checkSize($members);
    }

    public function mode(): string
    {
        return self::BY_SIZE;
    }

    public function label(): string
    {
        return "{$this->members} per group";
    }

    public function size(): ?int
    {
        return $this->members;
    }

    /** 33 classmates, 4 per group: 8 full groups and 1 left, so 1 group may take a 5th. */
    public function seats(int $classSize): array
    {
        $full = intdiv($classSize, $this->members);
        return ['base' => $this->members, 'extras' => min($full, $classSize % $this->members), 'count' => null, 'unplaceable' => 0];
    }

    public function explain(int $classSize): string
    {
        $full = intdiv($classSize, $this->members);
        $left = $classSize % $this->members;
        $text = "{$this->members} members per group. With $classSize classmates that’s " . self::plural($full, 'full group');
        if ($left === 0) {
            return "$text.";
        }
        return "$text, and " . self::plural($left, 'classmate') . ' left: '
            . ($left === 1 ? 'they can join a group as its ' : 'each can join a group as its ')
            . self::ordinal($this->members + 1) . ' member, or they make a smaller group.';
    }
}

/** 4. The prof gave both. All groups are made; if the class is bigger than the seats, some groups take +1. */
final class FixedSizeAndCount extends GroupRule
{
    public function __construct(private int $members, private int $groups)
    {
        self::checkSize($members);
        self::checkCount($groups);
    }

    public function mode(): string
    {
        return self::BY_SIZE_AND_COUNT;
    }

    public function label(): string
    {
        return self::plural($this->groups, 'group') . " of {$this->members}";
    }

    public function size(): ?int
    {
        return $this->members;
    }

    public function count(): ?int
    {
        return $this->groups;
    }

    public function makesEmptyGroups(): bool
    {
        return true;
    }

    /** 33 classmates, 8 groups of 4: 32 seats, 1 more than that, so 1 group may take a 5th. */
    public function seats(int $classSize): array
    {
        $over = max(0, $classSize - $this->members * $this->groups);
        return ['base' => $this->members, 'extras' => min($this->groups, $over), 'count' => $this->groups,
            'unplaceable' => max(0, $over - $this->groups)];
    }

    public function explain(int $classSize): string
    {
        $seats = $this->members * $this->groups;
        $text = "Your prof set {$this->label()}: $seats seats for $classSize classmates.";
        $s = $this->seats($classSize);
        if ($s['extras']) {
            $text .= ' So ' . self::plural($s['extras'], 'group') . ' can take 1 extra member (' . ($this->members + 1) . ').';
        } elseif ($classSize < $seats) {
            $text .= ' Some groups will have fewer than ' . $this->members . '.';
        }
        if ($s['unplaceable']) {
            $text .= ' ' . self::plural($s['unplaceable'], 'classmate') . ' still won’t fit. Ask an officer.';
        }
        return $text;
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
