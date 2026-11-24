<?php
/**
 * [OOP] Deadlines.
 *
 *   ABSTRACTION   Deadline is an interface: callers only ask "when is it due?" (timestamp)
 *                 and "how do I say it?" (label), never how the date is stored.
 *   INHERITANCE   ExactDeadline and RangeDeadline both extend the abstract DatedDeadline,
 *                 which holds what they share (the last day and the due time).
 *   POLYMORPHISM  timestamp() / label() / type() give a different answer for each kind;
 *                 no caller needs an if/else on deadline_type any more.
 *   ENCAPSULATION fields are private / protected and set once in the constructor.
 */

declare(strict_types=1);

interface Deadline
{
    /** Unix time of the deadline, or null when no date is set. */
    public function timestamp(): ?int;

    /** Human wording, e.g. "Due Wed, Nov 18 · 11:59 PM". */
    public function label(): string;

    /** exact | range | unset — the value stored in tasks.deadline_type. */
    public function type(): string;

    /** When work can first be handed in: the first day of a range, or the deadline of an exact date. */
    public function opensAt(): ?int;

    /**
     * Where the task belongs on the schedule right now. An exact date: at its deadline. A range that
     * is already open: now (it can be called or submitted today). A range that opens later: its first day.
     */
    public function attentionAt(int $now): ?int;
}

/**
 * What every dated deadline shares: the last day and an optional time (default 11:59 PM). The time may have
 * a length ($until, e.g. 9:00–11:00 AM for a 2-hour quiz): then it is due when that time ends.
 */
abstract class DatedDeadline implements Deadline
{
    protected const DEFAULT_TIME = '23:59';

    public function __construct(protected string $lastDay, protected ?string $time = null, protected ?string $until = null)
    {
    }

    public function timestamp(): ?int
    {
        $ts = strtotime($this->lastDay . ' ' . ($this->until ?: $this->time ?: self::DEFAULT_TIME));
        return $ts === false ? null : $ts;
    }

    /** An exact date opens at its deadline; RangeDeadline overrides this with its first day. */
    public function opensAt(): ?int
    {
        return $this->timestamp();
    }

    public function attentionAt(int $now): ?int
    {
        $end = $this->timestamp();
        $open = $this->opensAt();
        if ($end === null || $open === null) {
            return $end;
        }
        return max($open, min($now, $end));
    }

    protected function timeLabel(): string
    {
        if (!$this->time) {
            return ' · 11:59 PM';
        }
        $label = ' · ' . date('g:i A', (int) strtotime($this->time));
        return $this->until ? $label . ' – ' . date('g:i A', (int) strtotime($this->until)) : $label;
    }

    /** End of the due time ("HH:MM"), or null when the time has no length. */
    public function until(): ?string
    {
        return $this->until;
    }

    public function lastDay(): string
    {
        return $this->lastDay;
    }
}

/** Due on one day. */
final class ExactDeadline extends DatedDeadline
{
    public function type(): string
    {
        return 'exact';
    }

    public function label(): string
    {
        return 'Due ' . date('D, M j', (int) strtotime($this->lastDay)) . $this->timeLabel();
    }
}

/** May be submitted on any day of a range; the last day counts as the deadline. */
final class RangeDeadline extends DatedDeadline
{
    public function __construct(private string $firstDay, string $lastDay, ?string $time = null, ?string $until = null)
    {
        parent::__construct($lastDay, $time, $until);
    }

    public function type(): string
    {
        return 'range';
    }

    public function label(): string
    {
        return 'Any day ' . date('M j', (int) strtotime($this->firstDay)) . ' – ' . date('M j', (int) strtotime($this->lastDay)) . $this->timeLabel();
    }

    public function firstDay(): string
    {
        return $this->firstDay;
    }

    /** The range opens at the start of its first day. */
    public function opensAt(): ?int
    {
        $ts = strtotime($this->firstDay . ' 00:00');
        return $ts === false ? null : $ts;
    }
}

/** The professor hasn't announced the date yet. */
final class NoDeadline implements Deadline
{
    public function timestamp(): ?int
    {
        return null;
    }

    public function label(): string
    {
        return 'Date not set yet';
    }

    public function opensAt(): ?int
    {
        return null;
    }

    public function attentionAt(int $now): ?int
    {
        return null;
    }

    public function type(): string
    {
        return 'unset';
    }
}

/** Factory: builds the right Deadline object from a tasks row. */
final class Deadlines
{
    private function __construct()   // static factory only, never instantiated
    {
    }

    public static function fromRow(array $task): Deadline
    {
        $first = $task['deadline'] ?? null;
        $last = !empty($task['deadline_end']) ? $task['deadline_end'] : $first;
        if (empty($last)) {
            return new NoDeadline();
        }
        $time = !empty($task['deadline_time']) ? (string) $task['deadline_time'] : null;
        $until = $time !== null && !empty($task['deadline_until']) ? (string) $task['deadline_until'] : null;
        if (!empty($task['deadline_end']) && !empty($first) && $first !== $task['deadline_end']) {
            return new RangeDeadline((string) $first, (string) $last, $time, $until);
        }
        return new ExactDeadline((string) $last, $time, $until);
    }
}

/** A real calendar date in Y-m-d ("2026-02-31" is not one, though strtotime would roll it over). */
function is_real_date(mixed $d): bool
{
    return is_string($d) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}
