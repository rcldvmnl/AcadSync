<?php
/**
 * [OOP + DSA] Organization activities: meetings, seminars and outreach.
 *
 *   ABSTRACTION   Activity is abstract: every activity has a title, a time, a venue and maybe a
 *                 capacity; WHO is expected and WHO may be checked in is left to each kind.
 *   INHERITANCE   Meeting, Seminar and Outreach extend Activity.
 *   POLYMORPHISM  expectsAttendanceFrom() and canCheckIn() differ per kind:
 *                   Meeting   every member is expected; anyone can be checked in
 *                   Seminar   only people with a seat (RSVP'd) are expected and may check in
 *                   Outreach  volunteers who RSVP'd are expected; walk-in helpers may still check in
 *   ENCAPSULATION the constructor rejects a bad activity (no title, ends before it starts,
 *                 capacity 0…), so an invalid Activity object can't exist. No setters.
 *   RELATIONSHIP  ActivityRoster (composition with the DSA): a HashSet of who is going, a QUEUE of
 *                 who is waiting for a seat (first come, first served) and a HashSet of check-ins.
 */

declare(strict_types=1);

abstract class Activity
{
    public const MAX_CAPACITY = 1000;

    public function __construct(
        private int $id,
        private string $title,
        private string $description,
        private string $venue,
        private string $startsAt,          // 'YYYY-MM-DD HH:MM'
        private string $endsAt,
        private ?int $capacity,
        private string $status,
        private int $createdBy,
    ) {
        $title = trim($title);
        if ($title === '' || strlen($title) > 120) {
            throw new InvalidArgumentException('Give the activity a title (up to 120 characters).');
        }
        $start = strtotime($startsAt);
        $end = strtotime($endsAt);
        if ($start === false || $end === false) {
            throw new InvalidArgumentException('Pick a date and the start and end times.');
        }
        if ($end <= $start) {
            throw new InvalidArgumentException('The end time must be after the start time.');
        }
        if ($capacity !== null && ($capacity < 1 || $capacity > self::MAX_CAPACITY)) {
            throw new InvalidArgumentException('Seats must be between 1 and ' . self::MAX_CAPACITY . ', or leave it empty for no limit.');
        }
        if (strlen($description) > 2000 || strlen($venue) > 120) {
            throw new InvalidArgumentException('The venue or description is too long.');
        }
        $this->title = $title;
    }

    /** Factory: builds the right kind from a row (or from new input, with id 0). */
    public static function fromRow(array $r): Activity
    {
        $args = [(int) ($r['id'] ?? 0), (string) ($r['title'] ?? ''), (string) ($r['description'] ?? ''), (string) ($r['venue'] ?? ''),
            (string) ($r['starts_at'] ?? ''), (string) ($r['ends_at'] ?? ''),
            isset($r['capacity']) && $r['capacity'] !== null && $r['capacity'] !== '' ? (int) $r['capacity'] : null,
            (string) ($r['status'] ?? 'scheduled'), (int) ($r['created_by'] ?? 0)];
        return match ($r['kind'] ?? '') {
            'meeting' => new Meeting(...$args),
            'seminar' => new Seminar(...$args),
            'outreach' => new Outreach(...$args),
            default => throw new InvalidArgumentException('Pick Meeting, Seminar or Outreach.'),
        };
    }

    /** Every kind with its label and attendance rule, for the "New activity" form. */
    public static function kinds(): array
    {
        $out = [];
        foreach (['meeting', 'seminar', 'outreach'] as $kind) {
            $a = self::fromRow(['kind' => $kind, 'title' => $kind, 'starts_at' => '2000-01-01 08:00', 'ends_at' => '2000-01-01 09:00']);
            $out[] = ['kind' => $a->kind(), 'label' => $a->kindLabel(), 'rule' => $a->attendanceRule()];
        }
        return $out;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function startTs(): int
    {
        return (int) strtotime($this->startsAt);
    }

    public function endTs(): int
    {
        return (int) strtotime($this->endsAt);
    }

    public function capacity(): ?int
    {
        return $this->capacity;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOver(int $now): bool
    {
        return $this->endTs() < $now;
    }

    /** Is there a free seat when $going people already have one? (No capacity = always.) */
    public function hasRoom(int $going): bool
    {
        return $this->capacity === null || $going < $this->capacity;
    }

    /** meeting | seminar | outreach — stored in activities.kind. */
    abstract public function kind(): string;

    abstract public function kindLabel(): string;

    /** Is this member expected to attend (so an absence lowers their attendance rate)? */
    abstract public function expectsAttendanceFrom(bool $hasSeat): bool;

    /** May this member be checked in? */
    abstract public function canCheckIn(bool $hasSeat): bool;

    /** The rule above in words, for the screen. */
    abstract public function attendanceRule(): string;

    /** The columns to store for a new activity. */
    public function toRow(): array
    {
        return [
            'kind' => $this->kind(), 'title' => $this->title, 'description' => $this->description, 'venue' => $this->venue,
            'starts_at' => $this->startsAt, 'ends_at' => $this->endsAt, 'capacity' => $this->capacity, 'created_by' => $this->createdBy,
        ];
    }

    public function toArray(int $now): array
    {
        return $this->toRow() + [
            'id' => $this->id, 'status' => $this->status, 'kind_label' => $this->kindLabel(), 'rule' => $this->attendanceRule(),
            'start_ts' => $this->startTs(), 'end_ts' => $this->endTs(), 'is_over' => $this->isOver($now), 'cancelled' => $this->isCancelled(),
        ];
    }
}

final class Meeting extends Activity
{
    public function kind(): string
    {
        return 'meeting';
    }

    public function kindLabel(): string
    {
        return 'Meeting';
    }

    public function expectsAttendanceFrom(bool $hasSeat): bool
    {
        return true;                       // every active member should be there
    }

    public function canCheckIn(bool $hasSeat): bool
    {
        return true;
    }

    public function attendanceRule(): string
    {
        return 'Every member is expected. Attendance counts for everyone.';
    }
}

final class Seminar extends Activity
{
    public function kind(): string
    {
        return 'seminar';
    }

    public function kindLabel(): string
    {
        return 'Seminar';
    }

    public function expectsAttendanceFrom(bool $hasSeat): bool
    {
        return $hasSeat;                   // only those who reserved a seat
    }

    public function canCheckIn(bool $hasSeat): bool
    {
        return $hasSeat;                   // seats are limited: no seat, no entry
    }

    public function attendanceRule(): string
    {
        return 'Seats are reserved by RSVP. Only people with a seat can be checked in.';
    }
}

final class Outreach extends Activity
{
    public function kind(): string
    {
        return 'outreach';
    }

    public function kindLabel(): string
    {
        return 'Outreach';
    }

    public function expectsAttendanceFrom(bool $hasSeat): bool
    {
        return $hasSeat;                   // volunteers who signed up
    }

    public function canCheckIn(bool $hasSeat): bool
    {
        return true;                       // walk-in helpers are welcome too
    }

    public function attendanceRule(): string
    {
        return 'Volunteers RSVP. Walk-in helpers can still be checked in.';
    }
}

/**
 * Who is going, who is waiting, who came — for one activity.
 * [DSA: HASHSET] going and checked-in (no duplicates, O(1) checks);
 * [DSA: QUEUE] the waitlist, in the order people asked (the front gets the next free seat).
 */
final class ActivityRoster
{
    private HashSet $going;
    private HashSet $checkedIn;
    private ApprovalQueue $waitlist;
    private int $goingCount = 0;

    /** @param array $rsvps activity_rsvps rows of this activity, in stored (arrival) order */
    public function __construct(array $rsvps, array $attendanceRows)
    {
        $this->going = new HashSet();
        $this->checkedIn = new HashSet();
        $this->waitlist = new ApprovalQueue();
        foreach ($rsvps as $r) {
            if ($r['status'] === 'going') {
                $this->going->add((string) $r['user_id']);
                $this->goingCount++;
            } else {
                $this->waitlist->enqueue(['user_id' => (int) $r['user_id'], 'rsvp_id' => (int) $r['id']]);   // ENQUEUE at the rear
            }
        }
        foreach ($attendanceRows as $a) {
            $this->checkedIn->add((string) $a['user_id']);
        }
    }

    public function isGoing(int $userId): bool
    {
        return $this->going->contains((string) $userId);
    }

    public function isCheckedIn(int $userId): bool
    {
        return $this->checkedIn->contains((string) $userId);
    }

    public function goingCount(): int
    {
        return $this->goingCount;
    }

    public function checkedInCount(): int
    {
        return $this->checkedIn->size();
    }

    public function waitlistSize(): int
    {
        return $this->waitlist->size();
    }

    /** 1-based place in the waitlist, or 0 when not waiting. */
    public function waitlistPosition(int $userId): int
    {
        $i = 0;
        foreach ($this->waitlist->toArray() as $w) {
            $i++;
            if ($w['user_id'] === $userId) {
                return $i;
            }
        }
        return 0;
    }

    /** The person at the FRONT of the waitlist (they get the next free seat), without removing them. */
    public function nextInLine(): ?array
    {
        return $this->waitlist->peek();
    }

    /** @return int[] waitlisted user ids, front first */
    public function waitlistIds(): array
    {
        $ids = [];
        foreach ($this->waitlist->toArray() as $w) {
            $ids[] = $w['user_id'];
        }
        return $ids;
    }
}
