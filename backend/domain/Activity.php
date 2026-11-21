<?php
/**
 * [OOP + DSA] Organization activities: meetings, seminars, outreach, competitions and section projects.
 *
 *   ABSTRACTION   Activity is abstract: every activity has a title, a time (maybe over several days),
 *                 a venue, maybe a capacity, a link to the original post and a sign-up deadline;
 *                 WHO is expected, WHO may be checked in and HOW people join is left to each kind.
 *   INHERITANCE   Meeting, Seminar, Outreach and SectionEvent extend RsvpActivity; Competition and SectionProject
 *                 extend PartsActivity; both extend Activity.
 *   POLYMORPHISM  expectsAttendanceFrom() and canCheckIn() differ per kind:
 *                   Meeting      every member is expected; anyone can be checked in
 *                   Seminar      only people with a seat (RSVP'd) are expected and may check in
 *                   Outreach     volunteers who RSVP'd are expected; walk-in helpers may still check in
 *                   SectionEvent open to everyone (a fair, a party, college week): anyone can be checked in,
 *                                but nobody is marked absent
 *                   Competition  no attendance: volunteer for a part, officers pick
 *                   SectionProject no attendance: parts with people and due dates
 *                 joinsBy(), usesSeats(), canAssignDirectly(), partsNeedDueDates() too.
 *   ENCAPSULATION the constructor rejects a bad activity (no title, ends before it starts,
 *                 capacity 0, a link that isn't http(s), a sign-up deadline after the first day…),
 *                 so an invalid Activity object can't exist. No setters.
 *   RELATIONSHIP  ActivityRoster (composition with the DSA): a HashSet of who is going, a QUEUE of
 *                 who is waiting for a seat (first come, first served) and a HashSet of check-ins.
 */

declare(strict_types=1);

abstract class Activity
{
    public const MAX_CAPACITY = 1000;

    private ?HashSet $invited = null;   // [OOP: ENCAPSULATION] who may join (null = everyone)
    private array $invitedIds = [];

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
        private ?string $link = null,            // the original post (http/https only)
        private ?string $signupDeadline = null,  // 'YYYY-MM-DD': volunteering closes at the end of that day
        private ?string $result = null,          // e.g. "2nd place in Cosplay", entered after it ends
        ?array $invited = null,                  // null = everyone; otherwise the user ids the poster chose
        bool $stored = false,                    // read back from the database: an empty invite list is allowed
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
        $this->link = self::cleanLink($link);
        $deadline = trim((string) $signupDeadline);
        if ($deadline !== '') {
            if (!is_real_date($deadline)) {
                throw new InvalidArgumentException('Pick a real date for the sign-up deadline.');
            }
            if ($deadline > substr($startsAt, 0, 10)) {
                throw new InvalidArgumentException('The sign-up deadline must be on or before the first day.');
            }
        }
        $this->signupDeadline = $deadline === '' ? null : $deadline;
        $result = trim((string) $result);
        if (strlen($result) > 300) {
            throw new InvalidArgumentException('Keep the result under 300 characters.');
        }
        $this->result = $result === '' ? null : $result;
        if ($invited !== null) {
            // [DSA: HASHSET] who may join, once each
            $this->invited = new HashSet();
            foreach ($invited as $id) {
                if ((int) $id > 0 && $this->invited->add((string) (int) $id)) {
                    $this->invitedIds[] = (int) $id;
                }
            }
            // New input must name someone. A stored row whose invites are all gone still loads (nobody can
            // join until an officer picks people), so one bad row can't break every page that lists activities.
            if (!$this->invitedIds && !$stored) {
                throw new InvalidArgumentException('Pick at least one person to invite.');
            }
        }
    }

    /**
     * [OOP: ENCAPSULATION] Only a plain web link is kept: http(s), at most 500 characters.
     * "javascript:", "data:", "ftp:" and links without a scheme are refused, so a stored link can't run script.
     */
    private static function cleanLink(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        if (strlen($link) > 500 || filter_var($link, FILTER_VALIDATE_URL) === false || ($scheme !== 'http' && $scheme !== 'https')) {
            throw new InvalidArgumentException('Paste a link that starts with http:// or https://.');
        }
        return $link;
    }

    /** Factory: builds the right kind from a row (or from new input, with id 0). $stored: a row read from the database. */
    public static function fromRow(array $r, bool $stored = false): Activity
    {
        $args = [(int) ($r['id'] ?? 0), (string) ($r['title'] ?? ''), (string) ($r['description'] ?? ''), (string) ($r['venue'] ?? ''),
            (string) ($r['starts_at'] ?? ''), (string) ($r['ends_at'] ?? ''),
            isset($r['capacity']) && $r['capacity'] !== null && $r['capacity'] !== '' ? (int) $r['capacity'] : null,
            (string) ($r['status'] ?? 'scheduled'), (int) ($r['created_by'] ?? 0),
            isset($r['link']) ? (string) $r['link'] : null,
            isset($r['signup_deadline']) ? (string) $r['signup_deadline'] : null,
            isset($r['result']) ? (string) $r['result'] : null,
            ($r['audience'] ?? 'everyone') === 'invited' ? array_values((array) ($r['invited'] ?? [])) : null, $stored];
        return match ($r['kind'] ?? '') {
            'meeting' => new Meeting(...$args),
            'seminar' => new Seminar(...$args),
            'outreach' => new Outreach(...$args),
            'event' => new SectionEvent(...$args),
            'competition' => new Competition(...$args),
            'project' => new SectionProject(...$args),
            default => throw new InvalidArgumentException('Pick a kind of activity.'),
        };
    }

    /** Every kind with its label, attendance rule and how people join, for the "New activity" form. */
    public static function kinds(): array
    {
        $out = [];
        foreach (['meeting', 'seminar', 'outreach', 'event', 'competition', 'project'] as $kind) {
            $a = self::fromRow(['kind' => $kind, 'title' => $kind, 'starts_at' => '2000-01-01 08:00', 'ends_at' => '2000-01-01 09:00']);
            $out[] = ['kind' => $a->kind(), 'label' => $a->kindLabel(), 'rule' => $a->attendanceRule(), 'joins_by' => $a->joinsBy()];
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

    public function createdBy(): int
    {
        return $this->createdBy;
    }

    /** 'YYYY-MM-DD' of the first day. */
    public function startDate(): string
    {
        return substr($this->startsAt, 0, 10);
    }

    public function link(): ?string
    {
        return $this->link;
    }

    /** The site the link points to, without "www." (shown instead of the whole link). */
    public function linkHost(): ?string
    {
        if ($this->link === null) {
            return null;
        }
        $host = strtolower((string) parse_url($this->link, PHP_URL_HOST));
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** Did the poster choose who may join? */
    public function isInviteOnly(): bool
    {
        return $this->invited !== null;
    }

    /** May this person RSVP, volunteer and be expected? Everyone, unless the poster chose the people. */
    public function isFor(int $userId): bool
    {
        return $this->invited === null || $this->invited->contains((string) $userId);
    }

    /** @return int[] the people invited (empty when it's for everyone) */
    public function invitedIds(): array
    {
        return $this->invitedIds;
    }

    public function signupDeadline(): ?string
    {
        return $this->signupDeadline;
    }

    public function result(): ?string
    {
        return $this->result;
    }

    /** When volunteering closes: the end of the sign-up deadline day, or the kind's own default when there is none. */
    public function signupClosesAt(): int
    {
        return $this->signupDeadline !== null ? (int) strtotime($this->signupDeadline . ' 23:59:59') : $this->defaultSignupClose();
    }

    /** [OOP: POLYMORPHISM] Without a deadline, sign-ups close when it starts (a SectionProject: on its last day). */
    protected function defaultSignupClose(): int
    {
        return $this->startTs();
    }

    public function signupOpen(int $now): bool
    {
        return !$this->isCancelled() && $now <= $this->signupClosesAt();
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

    /** How people take part: 'rsvp' (a seat, check-in) or 'parts' (volunteer for a part, officers pick). */
    abstract public function joinsBy(): string;

    /** Only the RSVP kinds use seats, the waitlist and check-in. */
    abstract public function usesSeats(): bool;

    /** May officers put someone on a part without them volunteering first? */
    abstract public function canAssignDirectly(): bool;

    /** Does every part need its own due date? */
    abstract public function partsNeedDueDates(): bool;

    /** The columns to store for a new activity. */
    public function toRow(): array
    {
        return [
            'kind' => $this->kind(), 'title' => $this->title, 'description' => $this->description, 'venue' => $this->venue,
            'starts_at' => $this->startsAt, 'ends_at' => $this->endsAt, 'capacity' => $this->capacity, 'created_by' => $this->createdBy,
            'link' => $this->link, 'signup_deadline' => $this->signupDeadline, 'result' => $this->result,
            'audience' => $this->invited === null ? 'everyone' : 'invited',
        ];
    }

    public function toArray(int $now): array
    {
        return $this->toRow() + [
            'id' => $this->id, 'status' => $this->status, 'kind_label' => $this->kindLabel(), 'rule' => $this->attendanceRule(),
            'start_ts' => $this->startTs(), 'end_ts' => $this->endTs(), 'is_over' => $this->isOver($now), 'cancelled' => $this->isCancelled(),
            'link_host' => $this->linkHost(), 'signup_open' => $this->signupOpen($now), 'signup_closes_at' => $this->signupClosesAt(),
            'joins_by' => $this->joinsBy(), 'uses_seats' => $this->usesSeats(),
            'invite_only' => $this->isInviteOnly(), 'invited_count' => count($this->invitedIds),
            'multi_day' => $this->startDate() !== substr($this->endsAt, 0, 10),
        ];
    }
}

/** Meeting, Seminar and Outreach: people RSVP for a seat and are checked in. */
abstract class RsvpActivity extends Activity
{
    public function joinsBy(): string
    {
        return 'rsvp';
    }

    public function usesSeats(): bool
    {
        return true;
    }

    public function canAssignDirectly(): bool
    {
        return false;
    }

    public function partsNeedDueDates(): bool
    {
        return false;
    }
}

/**
 * Competition and SectionProject: made of parts (ActivityPart). People volunteer for a part and
 * officers pick. Neither touches attendance, so they never lower anyone's attendance rate.
 */
abstract class PartsActivity extends Activity
{
    /** [OOP: INHERITANCE] the same checks as every activity, but parts, not seats: a seat limit in the input is dropped. */
    public function __construct(mixed ...$args)
    {
        if (array_key_exists('capacity', $args)) {   // named arguments
            $args['capacity'] = null;
        } else {                                     // positional (Activity::fromRow): capacity is the 7th
            $args[6] = null;
        }
        parent::__construct(...$args);
    }

    public function joinsBy(): string
    {
        return 'parts';
    }

    public function usesSeats(): bool
    {
        return false;
    }

    public function expectsAttendanceFrom(bool $hasSeat): bool
    {
        return false;
    }

    public function canCheckIn(bool $hasSeat): bool
    {
        return false;
    }
}

/** Volunteer for a part; officers pick who represents the section (no direct assignment). */
final class Competition extends PartsActivity
{
    public function kind(): string
    {
        return 'competition';
    }

    public function kindLabel(): string
    {
        return 'Competition';
    }

    public function attendanceRule(): string
    {
        return 'Volunteer for a part. Officers pick who represents the section.';
    }

    public function canAssignDirectly(): bool
    {
        return false;
    }

    public function partsNeedDueDates(): bool
    {
        return false;
    }
}

/** Work split into parts, each with its people and a due date; officers may assign people directly. */
final class SectionProject extends PartsActivity
{
    public function kind(): string
    {
        return 'project';
    }

    public function kindLabel(): string
    {
        return 'Section project';
    }

    public function attendanceRule(): string
    {
        return 'Everyone helps. Each part has its people and a due date.';
    }

    /** Work goes on while the project runs, so people can still join a part until its last day. */
    protected function defaultSignupClose(): int
    {
        return $this->endTs();
    }

    public function canAssignDirectly(): bool
    {
        return true;
    }

    public function partsNeedDueDates(): bool
    {
        return true;
    }
}

final class Meeting extends RsvpActivity
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

final class Seminar extends RsvpActivity
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

final class Outreach extends RsvpActivity
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

/** A section event open to everyone: RSVP if you're coming (seats optional); it never lowers anyone's attendance. */
final class SectionEvent extends RsvpActivity
{
    public function kind(): string
    {
        return 'event';
    }

    public function kindLabel(): string
    {
        return 'Event';
    }

    public function expectsAttendanceFrom(bool $hasSeat): bool
    {
        return false;                      // optional: coming counts, staying home doesn't
    }

    public function canCheckIn(bool $hasSeat): bool
    {
        return true;                       // walk-ins welcome
    }

    public function attendanceRule(): string
    {
        return 'Open to everyone. RSVP if you’re coming; it never counts against anyone.';
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

    /**
     * The first person waiting who may still join $a (invite-only: someone the poster chose), or null.
     * [DSA: QUEUE] walked from the front, so the order people asked in is kept.
     */
    public function nextInLineFor(Activity $a): ?array
    {
        foreach ($this->waitlist->toArray() as $w) {
            if ($a->isFor($w['user_id'])) {
                return $w;
            }
        }
        return null;
    }

    /**
     * Who gets the seats that are free now (e.g. after people who aren't invited any more were taken off):
     * [DSA: QUEUE] the invited people waiting, front first, while $a still has room.
     */
    public function seatsToFill(Activity $a): array
    {
        $out = [];
        $going = $this->goingCount;
        foreach ($this->waitlist->toArray() as $w) {
            if ($a->isFor($w['user_id']) && $a->hasRoom($going)) {
                $out[] = $w;
                $going++;
            }
        }
        return $out;
    }

    /** @return array[] the people waiting who may no longer join $a (they are dropped from the waitlist) */
    public function waitingNotFor(Activity $a): array
    {
        $out = [];
        foreach ($this->waitlist->toArray() as $w) {
            if (!$a->isFor($w['user_id'])) {
                $out[] = $w;
            }
        }
        return $out;
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
