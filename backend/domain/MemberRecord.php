<?php
/**
 * [OOP] One member's record in the organization: who they are, their standing, the offices they
 * held, and their attendance worked out from every finished activity.
 *
 *   ENCAPSULATION  fields are private; the attendance numbers are computed once in fromActivities()
 *                  and can only be read (attended(), expected(), rate()).
 *   RELATIONSHIPS  MemberRecord HAS-A Person (which HAS-A MemberStatus) and USES Activity objects:
 *                  it asks each activity polymorphically whether this member was expected
 *                  (Meeting: everyone; Seminar / Outreach: only those with a seat).
 */

declare(strict_types=1);

final class MemberRecord
{
    /** @param array $history  one entry per activity that counts for this member */
    private function __construct(
        private Person $person,
        private string $joinedAt,
        private int $attended,
        private int $expected,
        private int $groups,
        private array $terms,
        private array $history,
    ) {
    }

    /**
     * @param Activity[]       $activities finished, not cancelled
     * @param ActivityRoster[] $rosters    by activity id
     */
    public static function fromActivities(Person $person, string $joinedAt, array $activities, array $rosters, int $groups, array $terms): self
    {
        $attended = 0;
        $expected = 0;
        $history = [];
        $joined = (int) strtotime($joinedAt);
        foreach ($activities as $a) {
            $roster = $rosters[$a->id()];
            $hasSeat = $roster->isGoing($person->id());
            $came = $roster->isCheckedIn($person->id());
            // [POLYMORPHISM] the activity decides who is expected; the member's status decides if absences count
            // (an invite-only activity only expects the people the poster chose)
            $isExpected = $came || ($joined <= $a->startTs() && $a->isFor($person->id()) && $person->status()->countsInAttendance() && $a->expectsAttendanceFrom($hasSeat));
            if (!$isExpected) {
                continue;
            }
            $expected++;
            $attended += $came ? 1 : 0;
            $history[] = ['activity_id' => $a->id(), 'title' => $a->title(), 'kind' => $a->kind(), 'kind_label' => $a->kindLabel(),
                'start_ts' => $a->startTs(), 'came' => $came, 'had_seat' => $hasSeat];
        }
        return new self($person, $joinedAt, $attended, $expected, $groups, $terms, $history);
    }

    public function person(): Person
    {
        return $this->person;
    }

    public function attended(): int
    {
        return $this->attended;
    }

    public function expected(): int
    {
        return $this->expected;
    }

    /** Attendance rate 0–100, or null when nothing was expected of them yet. */
    public function rate(): ?int
    {
        return $this->expected ? (int) round($this->attended * 100 / $this->expected) : null;
    }

    /** Sort key for ranking: rate first, then how many they attended (no rate sorts last). */
    public function rankKey(): int
    {
        return ($this->rate() ?? -1) * 10000 + $this->attended;
    }

    public function toArray(bool $withHistory = false): array
    {
        $p = $this->person;
        $out = [
            'id' => $p->id(), 'name' => $p->fullName(), 'first_name' => $p->firstName(), 'student_id' => $p->studentId(), 'email' => $p->email(),
            'role_label' => $p->roleLabel(), 'position' => $p->position(), 'is_officer' => $p->isOfficer(),
            'status' => $p->status()->toArray(), 'joined_at' => $this->joinedAt,
            'attended' => $this->attended, 'expected' => $this->expected, 'rate' => $this->rate(),
            'groups' => $this->groups, 'terms' => $this->terms, 'rank_key' => $this->rankKey(),
        ];
        if ($withHistory) {
            $out['history'] = $this->history;
        }
        return $out;
    }
}
