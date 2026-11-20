<?php
/**
 * [OOP] A member's standing in the organization: Active, Inactive (on leave) or Alumni.
 *
 *   ABSTRACTION   MemberStatus is abstract: it says what a status decides (may this member RSVP,
 *                 do they count in attendance, may they hold office) without the details.
 *   INHERITANCE   ActiveStatus, InactiveStatus and AlumniStatus extend it.
 *   POLYMORPHISM  Person::can() asks $status->allows($permission), Activities ask canRsvp(),
 *                 member records ask countsInAttendance(), dues ask owesDues():
 *                 each status answers in its own way.
 */

declare(strict_types=1);

abstract class MemberStatus
{
    /** active | inactive | alumni — the value stored in memberships.status. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** One line explaining what this status means, shown in member records. */
    abstract public function meaning(): string;

    /** May they RSVP to activities? */
    abstract public function canRsvp(): bool;

    /** Are they expected at activities (do absences lower their attendance rate)? */
    abstract public function countsInAttendance(): bool;

    /** May they hold an office (Vice President, Secretary, Treasurer)? */
    abstract public function canHoldOffice(): bool;

    /** Are they charged the organization's dues? */
    abstract public function owesDues(): bool;

    /** Does this status allow a member permission? Active allows all; subclasses may hold some back. */
    public function allows(string $permission): bool
    {
        return true;
    }

    public static function fromString(?string $key): MemberStatus
    {
        return match ($key) {
            'inactive' => new InactiveStatus(),
            'alumni' => new AlumniStatus(),
            default => new ActiveStatus(),
        };
    }

    /** @return MemberStatus[] every status, in the order the app lists them */
    public static function all(): array
    {
        return [new ActiveStatus(), new InactiveStatus(), new AlumniStatus()];
    }

    public function toArray(): array
    {
        return ['key' => $this->key(), 'label' => $this->label(), 'meaning' => $this->meaning()];
    }
}

final class ActiveStatus extends MemberStatus
{
    public function key(): string
    {
        return 'active';
    }

    public function label(): string
    {
        return 'Active';
    }

    public function meaning(): string
    {
        return 'Takes part in everything; attendance counts.';
    }

    public function canRsvp(): bool
    {
        return true;
    }

    public function countsInAttendance(): bool
    {
        return true;
    }

    public function canHoldOffice(): bool
    {
        return true;
    }

    public function owesDues(): bool
    {
        return true;
    }
}

/** On leave (e.g. a semester off): may still join events, but absences don't count against them. */
final class InactiveStatus extends MemberStatus
{
    public function key(): string
    {
        return 'inactive';
    }

    public function label(): string
    {
        return 'Inactive';
    }

    public function meaning(): string
    {
        return 'On leave: can still join events, absences don’t count.';
    }

    public function canRsvp(): bool
    {
        return true;
    }

    public function countsInAttendance(): bool
    {
        return false;
    }

    public function canHoldOffice(): bool
    {
        return false;
    }

    /** Dues are not charged while on leave. */
    public function owesDues(): bool
    {
        return false;
    }
}

/** Former member: kept in the records, read-only. */
final class AlumniStatus extends MemberStatus
{
    public function key(): string
    {
        return 'alumni';
    }

    public function label(): string
    {
        return 'Alumni';
    }

    public function meaning(): string
    {
        return 'Former member: kept in the records, can’t RSVP, request tasks or join groups.';
    }

    public function canRsvp(): bool
    {
        return false;
    }

    public function countsInAttendance(): bool
    {
        return false;
    }

    public function canHoldOffice(): bool
    {
        return false;
    }

    public function owesDues(): bool
    {
        return false;
    }

    /** Alumni keep read access but can't start new work. */
    public function allows(string $permission): bool
    {
        return $permission !== Permission::REQUEST_TASK && $permission !== Permission::JOIN_GROUP;
    }
}
