<?php
/**
 * [OOP + DSA] The parts of a competition or a section project, and who is on each part.
 *
 *   ENCAPSULATION ActivityPart checks itself in the constructor (a name, 1–100 spots or "any number",
 *                 up to 10 short roles, a due date only where the activity's kind wants one), so an
 *                 invalid part can't exist. No setters.
 *   POLYMORPHISM  the part asks its Activity: partsNeedDueDates() (SectionProject yes, Competition no)
 *                 and canAssignDirectly() (only a SectionProject lets officers assign without a volunteer).
 *   RELATIONSHIP  PartRoster (composition with the DSA), like ActivityRoster:
 *                   [DSA: QUEUE]   the volunteers in the order they came (officers see the earliest first)
 *                   [DSA: HASHSET] who is picked (no duplicates, O(1) "is this person on it?")
 */

declare(strict_types=1);

final class ActivityPart
{
    public const MAX_SPOTS = 100;
    public const MAX_ROLES = 10;

    /** @param string[] $roles */
    public function __construct(
        private int $id,
        private int $activityId,
        private string $name,
        private string $details,
        private ?int $spots,              // null = any number
        private bool $together,           // one team ("team of 4") rather than separate people ("2 spots")
        private array $roles,             // e.g. Director, Editor
        private ?string $dueDate,         // 'YYYY-MM-DD', project parts only
        private int $position,
        private ?string $doneAt = null,
        private ?int $doneBy = null,
        private ?string $result = null,
        bool $needsDueDate = false,
        ?string $lastDay = null,          // 'YYYY-MM-DD' the activity's last day
    ) {
        $name = trim($name);
        if ($name === '' || strlen($name) > 80) {
            throw new InvalidArgumentException('Give the part a name (up to 80 characters).');
        }
        $this->name = $name;
        $this->details = trim($details);
        if (strlen($this->details) > 1000) {
            throw new InvalidArgumentException('Keep the details under 1,000 characters.');
        }
        if ($spots !== null && ($spots < 1 || $spots > self::MAX_SPOTS)) {
            throw new InvalidArgumentException('Spots must be between 1 and 100, or leave it empty for any number.');
        }
        $clean = [];
        foreach ($roles as $role) {
            $role = trim((string) $role);
            if ($role === '') {
                continue;
            }
            if (strlen($role) > 40) {
                throw new InvalidArgumentException('Up to 10 roles, each up to 40 characters.');
            }
            $clean[] = $role;
        }
        if (count($clean) > self::MAX_ROLES) {
            throw new InvalidArgumentException('Up to 10 roles, each up to 40 characters.');
        }
        $this->roles = $clean;
        $due = trim((string) $dueDate);
        if ($needsDueDate) {
            if ($due === '') {
                throw new InvalidArgumentException('Give each part a due date.');
            }
            if (!is_real_date($due)) {
                throw new InvalidArgumentException('Pick a real due date.');
            }
            if ($lastDay !== null && $due > $lastDay) {
                throw new InvalidArgumentException('Pick a due date on or before the project’s last day (' . date('D, M j', (int) strtotime($lastDay)) . ').');
            }
        } elseif ($due !== '') {
            throw new InvalidArgumentException('Competition parts don’t have due dates.');
        }
        $this->dueDate = $due === '' ? null : $due;
        $result = trim((string) $result);
        if (strlen($result) > 300) {
            throw new InvalidArgumentException('Keep the result under 300 characters.');
        }
        $this->result = $result === '' ? null : $result;
    }

    /** Builds a part from a row (or from new input, with id 0). [OOP: POLYMORPHISM] the activity's kind decides the due-date rule. */
    public static function fromRow(array $r, Activity $a): ActivityPart
    {
        $roles = $r['roles'] ?? [];
        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }
        $spots = $r['spots'] ?? null;
        return new self(
            (int) ($r['id'] ?? 0), (int) ($r['activity_id'] ?? $a->id()), (string) ($r['name'] ?? ''), (string) ($r['details'] ?? ''),
            $spots === null || $spots === '' ? null : (int) $spots,
            !empty($r['together']), is_array($roles) ? $roles : [],
            isset($r['due_date']) ? (string) $r['due_date'] : null, (int) ($r['position'] ?? 0),
            isset($r['done_at']) && $r['done_at'] !== '' ? (string) $r['done_at'] : null,
            isset($r['done_by']) ? (int) $r['done_by'] : null,
            isset($r['result']) ? (string) $r['result'] : null,
            $a->partsNeedDueDates(), date('Y-m-d', $a->endTs()),
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function activityId(): int
    {
        return $this->activityId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function spots(): ?int
    {
        return $this->spots;
    }

    /** @return string[] */
    public function roles(): array
    {
        return $this->roles;
    }

    public function dueDate(): ?string
    {
        return $this->dueDate;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function result(): ?string
    {
        return $this->result;
    }

    public function isDone(): bool
    {
        return $this->doneAt !== null;
    }

    /** Spots still open when $picked people are on it; null = any number. */
    public function openSpots(int $picked): ?int
    {
        return $this->spots === null ? null : max(0, $this->spots - $picked);
    }

    public function isFull(int $picked): bool
    {
        return $this->spots !== null && $picked >= $this->spots;
    }

    /** Why the part can't become $newSpots (0 = removed) with $picked people on it, or null when it can. */
    public function whyCantResize(?int $newSpots, int $picked): ?string
    {
        if ($newSpots !== null && $newSpots < $picked) {
            return "That part already has $picked picked. Un-pick people first.";
        }
        return null;
    }

    public function spotsLabel(): string
    {
        if ($this->spots === null) {
            return 'Any number';
        }
        if ($this->together) {
            return "team of {$this->spots}";
        }
        return $this->spots === 1 ? '1 spot' : "{$this->spots} spots";
    }

    /** The columns to store. */
    public function toRow(): array
    {
        return [
            'activity_id' => $this->activityId, 'name' => $this->name, 'details' => $this->details, 'spots' => $this->spots,
            'together' => $this->together ? 1 : 0, 'roles' => json_encode($this->roles), 'due_date' => $this->dueDate,
            'position' => $this->position,
        ];
    }

    public function toArray(string $today): array
    {
        return [
            'id' => $this->id, 'activity_id' => $this->activityId, 'name' => $this->name, 'details' => $this->details,
            'spots' => $this->spots, 'together' => $this->together, 'roles' => $this->roles, 'due_date' => $this->dueDate,
            'position' => $this->position, 'done' => $this->isDone(), 'done_at' => $this->doneAt, 'done_by' => $this->doneBy,
            'result' => $this->result, 'spots_label' => $this->spotsLabel(),
            'overdue' => !$this->isDone() && $this->dueDate !== null && $this->dueDate < $today,
        ];
    }
}

/**
 * Who volunteered for one part and who was picked.
 * [DSA: QUEUE] volunteers in arrival order (the earliest is shown first);
 * [DSA: HASHSET] picked and volunteered user ids (no duplicates, O(1) checks).
 */
final class PartRoster
{
    private ApprovalQueue $volunteers;
    private HashSet $picked;
    private HashSet $waiting;
    private array $pickedRows = [];
    private array $roles = [];        // user id => role

    /** @param array $memberRows part_members rows of ONE part, in stored (arrival) order */
    public function __construct(array $memberRows)
    {
        $this->volunteers = new ApprovalQueue();
        $this->picked = new HashSet();
        $this->waiting = new HashSet();
        foreach ($memberRows as $row) {
            $uid = (int) $row['user_id'];
            $row['user_id'] = $uid;
            $this->roles[$uid] = isset($row['role']) && $row['role'] !== '' ? (string) $row['role'] : null;
            if (($row['status'] ?? 'volunteer') === 'picked') {
                $this->picked->add((string) $uid);
                $this->pickedRows[] = $row;
            } else {
                $this->waiting->add((string) $uid);
                $this->volunteers->enqueue($row);          // ENQUEUE at the rear
            }
        }
    }

    /** 'none' | 'volunteer' | 'picked' */
    public function statusOf(int $userId): string
    {
        if ($this->picked->contains((string) $userId)) {
            return 'picked';
        }
        return $this->waiting->contains((string) $userId) ? 'volunteer' : 'none';
    }

    public function roleOf(int $userId): ?string
    {
        return $this->roles[$userId] ?? null;
    }

    public function pickedCount(): int
    {
        return $this->picked->size();
    }

    /** Volunteers not picked yet. */
    public function volunteerCount(): int
    {
        return $this->volunteers->size();
    }

    /** Volunteer rows, earliest first (front of the queue first). */
    public function volunteers(): array
    {
        return $this->volunteers->toArray();
    }

    public function picked(): array
    {
        return $this->pickedRows;
    }

    /** Why this person can't volunteer for the part, or null when they can. */
    public function whyCantVolunteer(Activity $a, int $userId, bool $canRsvp, int $now): ?string
    {
        if (!$canRsvp) {                                   // [OOP: POLYMORPHISM] MemberStatus::canRsvp()
            return 'Alumni members can’t volunteer.';
        }
        $status = $this->statusOf($userId);
        if ($status === 'picked') {
            return 'You’re already picked for this part.';
        }
        if ($status === 'volunteer') {
            return 'You already volunteered for this part.';
        }
        if (!$a->signupOpen($now)) {
            return $a->signupDeadline() !== null
                ? "Sign-ups for “{$a->title()}” closed on " . date('D, M j', (int) strtotime($a->signupDeadline())) . '.'
                : "Sign-ups for “{$a->title()}” are closed.";
        }
        return null;
    }

    /**
     * Why an officer can't put this person on the part, or null when they can.
     * $direct: assigning someone who didn't volunteer (only kinds that canAssignDirectly()).
     */
    public function whyCantPick(ActivityPart $p, Activity $a, int $userId, bool $direct): ?string
    {
        $status = $this->statusOf($userId);
        if ($status === 'picked') {
            return 'Already picked.';
        }
        if ($status !== 'volunteer' && !($direct && $a->canAssignDirectly())) {
            return 'Pick someone who volunteered.';
        }
        if ($p->isFull($this->pickedCount())) {
            return 'This part is full. Un-pick someone first.';
        }
        return null;
    }
}
