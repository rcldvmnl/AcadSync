<?php
/**
 * [OOP] The people in a section (a student organization): members and its officers.
 *
 *   Person (abstract)
 *    └─ Member                    a regular member
 *        └─ Officer (abstract)    any officer: reviews the approval queue, runs activities, sees records
 *            ├─ VicePresident     chosen by the President
 *            ├─ Secretary         chosen by the President; also takes attendance and keeps records
 *            └─ President         created the section; chooses the VP and Secretary; runs everything
 *
 *   ABSTRACTION   Person and Officer are abstract: they declare what every person / officer can be
 *                 asked (role, position, permissions, account stats) and leave the answers to subclasses.
 *   INHERITANCE   each level adds to its parent: Officer::permissions() starts from
 *                 parent::permissions() (Member's), and Secretary / President add theirs on top.
 *   POLYMORPHISM  can(), position(), roleLabel(), accountStats() answer differently per class; the
 *                 endpoints ask $person->can(Permission::REVIEW_REQUESTS) and never check a role string.
 *   ENCAPSULATION identity and status are private with read-only getters; the permission lists are
 *                 protected so only the class family decides who may do what.
 *   RELATIONSHIP  Person HAS-A MemberStatus (composition): an Alumni member loses some permissions.
 */

declare(strict_types=1);

/** Everything a person might be allowed to do in a section. */
final class Permission
{
    public const REQUEST_TASK = 'request_task';             // send a task request to the approval queue
    public const JOIN_GROUP = 'join_group';                 // ask to join a group, create one, leave one
    public const REVIEW_REQUESTS = 'review_requests';       // approve / reject / undo in the approval queue
    public const MANAGE_ACTIVITIES = 'manage_activities';   // create and cancel activities
    public const VIEW_MEMBER_RECORDS = 'view_member_records';
    public const RECORD_ATTENDANCE = 'record_attendance';   // check people in at an activity
    public const UPDATE_MEMBER_RECORDS = 'update_member_records'; // change a member's status
    public const ASSIGN_OFFICERS = 'assign_officers';       // choose the Vice President and Secretary
    public const PUBLISH_TASK = 'publish_task';             // post or edit a task directly (skips the queue)
    public const REMOVE_TASK = 'remove_task';
    public const MANAGE_SECTION = 'manage_section';         // subjects, class times, class code
    public const MANAGE_ANY_GROUP = 'manage_any_group';     // add to / delete any group, not only your own
    public const ANSWER_ANY_REQUEST = 'answer_any_request'; // accept or decline join requests for any group
    public const JOIN_DIRECTLY = 'join_directly';           // join a group without a request
    public const AUTO_GROUP = 'auto_group';                 // put everyone left into groups

    private function __construct()
    {
    }
}

abstract class Person
{
    public const VICE_PRESIDENT = 'vice_president';
    public const SECRETARY = 'secretary';

    private MemberStatus $status;

    public function __construct(
        private int $id,
        private string $firstName,
        private string $lastName,
        private string $studentId,
        private string $email,
        ?MemberStatus $status = null,
    ) {
        $this->status = $status ?? new ActiveStatus();
    }

    /** Factory: the role and position in this section decide which class the person is. */
    public static function fromRow(array $row): Person
    {
        $args = [(int) $row['id'], (string) $row['first_name'], (string) $row['last_name'], (string) $row['student_id'],
            (string) $row['email'], MemberStatus::fromString($row['status'] ?? 'active')];
        if (($row['role'] ?? 'student') === 'admin') {
            return new President(...$args);
        }
        return match ($row['position'] ?? null) {
            self::VICE_PRESIDENT => new VicePresident(...$args),
            self::SECRETARY => new Secretary(...$args),
            default => new Member(...$args),
        };
    }

    public function id(): int
    {
        return $this->id;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function studentId(): string
    {
        return $this->studentId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function status(): MemberStatus
    {
        return $this->status;
    }

    /** admin | student — the value stored in memberships.role. */
    abstract public function role(): string;

    /** president | vice_president | secretary, or null for a regular member. */
    abstract public function position(): ?string;

    /** What the app calls this person's role, e.g. "Vice President". */
    abstract public function roleLabel(): string;

    abstract public function isOfficer(): bool;

    /** The permissions this kind of person has. */
    abstract protected function permissions(): array;

    /** The numbers shown on this person's account card. */
    abstract public function accountStats(array $ctx): array;

    /**
     * May this person do $permission? Their class must grant it (linear search over its list)
     * AND their status must allow it (an Alumni member can't request tasks or join groups).
     */
    final public function can(string $permission): bool
    {
        if (!$this->status->allows($permission)) {
            return false;
        }
        foreach ($this->permissions() as $p) {
            if ($p === $permission) {
                return true;
            }
        }
        return false;
    }

    /** Every permission this person has right now (sent to the browser to show the right buttons). */
    final public function allPermissions(): array
    {
        $out = [];
        foreach ($this->permissions() as $p) {
            if ($this->can($p)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** May this person add to / delete a group created by $createdBy? Their own, or any for the President. */
    public function canManageGroup(int $createdBy): bool
    {
        return $createdBy === $this->id || $this->can(Permission::MANAGE_ANY_GROUP);
    }
}

/** A regular member of the section. */
class Member extends Person
{
    public function role(): string
    {
        return 'student';
    }

    public function position(): ?string
    {
        return null;
    }

    public function roleLabel(): string
    {
        return 'Member';
    }

    public function isOfficer(): bool
    {
        return false;
    }

    protected function permissions(): array
    {
        return [Permission::REQUEST_TASK, Permission::JOIN_GROUP];
    }

    /** Requests sent, how many got approved, and how many groups they are in. */
    public function accountStats(array $ctx): array
    {
        $submitted = 0;
        $approved = 0;
        foreach ($ctx['tasks'] as $t) {
            if ((int) $t['requested_by'] === $this->id()) {
                $submitted++;
                $approved += $t['status'] === 'approved' ? 1 : 0;
            }
        }
        // Every group this member is in (by id, so two groups both called "Group 2" count as two,
        // and a group they're alone in still counts)
        $groups = 0;
        foreach (load_groups((int) $ctx['section']['id']) as $g) {
            $groups += in_array($this->id(), $g['members'], true) ? 1 : 0;
        }
        return ['submitted' => $submitted, 'approved' => $approved, 'groups' => $groups];
    }
}

/** Any officer. Officers are members (they keep a member's account stats) with more rights. */
abstract class Officer extends Member
{
    public function isOfficer(): bool
    {
        return true;
    }

    /** What every officer may do on top of a member: review the queue, run activities, read records. */
    protected function permissions(): array
    {
        return [...parent::permissions(),
            Permission::REVIEW_REQUESTS, Permission::MANAGE_ACTIVITIES, Permission::VIEW_MEMBER_RECORDS,
        ];
    }
}

final class VicePresident extends Officer
{
    public function position(): ?string
    {
        return self::VICE_PRESIDENT;
    }

    public function roleLabel(): string
    {
        return 'Vice President';
    }
}

/** Keeps the organization's records: takes attendance and updates member status. */
final class Secretary extends Officer
{
    public function position(): ?string
    {
        return self::SECRETARY;
    }

    public function roleLabel(): string
    {
        return 'Secretary';
    }

    protected function permissions(): array
    {
        return [...parent::permissions(), Permission::RECORD_ATTENDANCE, Permission::UPDATE_MEMBER_RECORDS];
    }
}

/** Created the section (its admin). Chooses the Vice President and Secretary and runs everything. */
final class President extends Officer
{
    public function role(): string
    {
        return 'admin';
    }

    public function position(): ?string
    {
        return 'president';
    }

    public function roleLabel(): string
    {
        return 'President';
    }

    protected function permissions(): array
    {
        return [...parent::permissions(),
            Permission::RECORD_ATTENDANCE, Permission::UPDATE_MEMBER_RECORDS, Permission::ASSIGN_OFFICERS,
            Permission::PUBLISH_TASK, Permission::REMOVE_TASK, Permission::MANAGE_SECTION,
            Permission::MANAGE_ANY_GROUP, Permission::ANSWER_ANY_REQUEST, Permission::JOIN_DIRECTLY, Permission::AUTO_GROUP,
        ];
    }

    /** Subjects in the section, tasks they published, and requests from others they reviewed. */
    public function accountStats(array $ctx): array
    {
        $published = 0;
        $reviewed = 0;
        foreach ($ctx['tasks'] as $t) {
            if ((int) $t['reviewed_by'] === $this->id() && (int) $t['requested_by'] !== $this->id()) {
                $reviewed++;
            }
            if ((int) $t['requested_by'] === $this->id() && $t['status'] === 'approved') {
                $published++;
            }
        }
        return ['subjects' => $ctx['subjects']->size(), 'published' => $published, 'reviewed' => $reviewed];
    }
}
