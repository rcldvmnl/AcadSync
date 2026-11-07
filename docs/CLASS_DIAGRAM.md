# AcadSync class diagram

The classes below are the ones in `backend/domain/` and `backend/Env.php` (the OOP model) and
`backend/custom_dsa.php` (the data structures), with their real names, fields, methods and visibility.
GitHub draws these diagrams automatically; they can also be pasted into <https://mermaid.live> to
export a PNG for the documentation.

Visibility: `+` public, `-` private, `#` protected. `*` marks an abstract method, `$` a static one.

## 1. People, offices and membership status

```mermaid
classDiagram
    direction TB
    class Person {
        <<abstract>>
        -int id
        -string firstName
        -string lastName
        -string studentId
        -string email
        -MemberStatus status
        +__construct(id, firstName, lastName, studentId, email, status)
        +fromRow(row)$ Person
        +id() int
        +fullName() string
        +status() MemberStatus
        +role()* string
        +position()* string
        +roleLabel()* string
        +isOfficer()* bool
        #permissions()* array
        +accountStats(ctx)* array
        +can(permission) bool
        +allPermissions() array
        +canManageGroup(createdBy) bool
    }
    class Member {
        +role() string
        +position() string
        +roleLabel() string
        +isOfficer() bool
        #permissions() array
        +accountStats(ctx) array
    }
    class Officer {
        <<abstract>>
        +isOfficer() bool
        #permissions() array
    }
    class VicePresident {
        +position() string
        +roleLabel() string
    }
    class Secretary {
        +position() string
        +roleLabel() string
        #permissions() array
    }
    class President {
        +role() string
        +position() string
        +roleLabel() string
        #permissions() array
        +accountStats(ctx) array
    }
    class Permission {
        <<final>>
        +REVIEW_REQUESTS$
        +MANAGE_ACTIVITIES$
        +RECORD_ATTENDANCE$
        +UPDATE_MEMBER_RECORDS$
        +ASSIGN_OFFICERS$
        +MANAGE_SECTION$
    }
    Person <|-- Member
    Member <|-- Officer
    Officer <|-- VicePresident
    Officer <|-- Secretary
    Officer <|-- President
    Person ..> Permission : checks

    class MemberStatus {
        <<abstract>>
        +key()* string
        +label()* string
        +meaning()* string
        +canRsvp()* bool
        +countsInAttendance()* bool
        +canHoldOffice()* bool
        +allows(permission) bool
        +fromString(key)$ MemberStatus
        +all()$ array
    }
    class ActiveStatus
    class InactiveStatus
    class AlumniStatus {
        +allows(permission) bool
    }
    MemberStatus <|-- ActiveStatus
    MemberStatus <|-- InactiveStatus
    MemberStatus <|-- AlumniStatus
    Person *-- "1" MemberStatus : has
```

## 2. Activities, attendance and member records

```mermaid
classDiagram
    direction TB
    class Activity {
        <<abstract>>
        -int id
        -string title
        -string description
        -string venue
        -string startsAt
        -string endsAt
        -int capacity
        -string status
        -int createdBy
        +__construct(id, title, …, capacity, status, createdBy)
        +fromRow(row)$ Activity
        +kinds()$ array
        +startTs() int
        +isOver(now) bool
        +hasRoom(going) bool
        +kind()* string
        +kindLabel()* string
        +expectsAttendanceFrom(hasSeat)* bool
        +canCheckIn(hasSeat)* bool
        +attendanceRule()* string
        +toRow() array
    }
    class Meeting {
        +expectsAttendanceFrom(hasSeat) bool
        +canCheckIn(hasSeat) bool
    }
    class Seminar {
        +expectsAttendanceFrom(hasSeat) bool
        +canCheckIn(hasSeat) bool
    }
    class Outreach {
        +expectsAttendanceFrom(hasSeat) bool
        +canCheckIn(hasSeat) bool
    }
    Activity <|-- Meeting
    Activity <|-- Seminar
    Activity <|-- Outreach

    class ActivityRoster {
        <<final>>
        -HashSet going
        -HashSet checkedIn
        -ApprovalQueue waitlist
        +isGoing(userId) bool
        +isCheckedIn(userId) bool
        +waitlistPosition(userId) int
        +nextInLine() array
    }
    ActivityRoster *-- HashSet
    ActivityRoster *-- ApprovalQueue : waitlist

    class MemberRecord {
        <<final>>
        -Person person
        -int attended
        -int expected
        -array terms
        -array history
        +fromActivities(person, joinedAt, activities, rosters, groups, terms)$ MemberRecord
        +rate() int
        +rankKey() int
        +toArray(withHistory) array
    }
    MemberRecord o-- "1" Person
    MemberRecord ..> Activity : asks who is expected
    MemberRecord ..> ActivityRoster : reads
```

## 3. Tasks, deadlines and group work

```mermaid
classDiagram
    direction TB
    class Task {
        <<abstract>>
        -int id
        -string title
        -int subjectId
        -string status
        -Deadline deadline
        +fromRow(row)$ Task
        +deadline() Deadline
        +isOpen() bool
        +rule() GroupRule
        +acceptsGroups()* bool
        +workMode()* string
        +workLabel()* string
    }
    class IndividualTask
    class GroupTask {
        -GroupRule rule
        +rule() GroupRule
    }
    Task <|-- IndividualTask
    Task <|-- GroupTask

    class Deadline {
        <<interface>>
        +timestamp() int
        +label() string
        +type() string
    }
    class DatedDeadline {
        <<abstract>>
        #string lastDay
        #string time
        +timestamp() int
    }
    class ExactDeadline
    class RangeDeadline {
        -string firstDay
    }
    class NoDeadline
    class Deadlines {
        <<final>>
        +fromRow(row)$ Deadline
    }
    Deadline <|.. DatedDeadline
    Deadline <|.. NoDeadline
    DatedDeadline <|-- ExactDeadline
    DatedDeadline <|-- RangeDeadline
    Task *-- "1" Deadline : has
    Deadlines ..> Deadline : creates

    class GroupRule {
        <<final>>
        -int min
        -int max
        -int count
        +__construct(min, max, count)
        +evaluate(groups, memberCount) array
        +capacityOf(group, extraSlots) int
    }
    class StudyGroup {
        <<final>>
        -int id
        -string name
        -int[] members
        +size() int
        +hasMember(userId) bool
    }
    GroupTask *-- "1" GroupRule : has
    GroupRule ..> "*" StudyGroup : evaluates
```

## 4. Undo (OOP objects kept in a DSA stack) and settings

```mermaid
classDiagram
    direction LR
    class UndoAction {
        <<abstract>>
        -string label
        +label() string
        #kind()* string
        #payload()* array
        +undo(context)* void
        +toArray() array
        +fromArray(a)$ UndoAction
    }
    class ToggleDoneAction
    class RejoinGroupAction
    class RequestAgainAction
    class UndoContext {
        <<final>>
        +int sectionId «readonly»
        +int userId «readonly»
        +groupedFor(taskId) bool
    }
    UndoAction <|-- ToggleDoneAction
    UndoAction <|-- RejoinGroupAction
    UndoAction <|-- RequestAgainAction
    UndoAction ..> UndoContext : uses
    UndoStack o-- "*" UndoAction : holds

    class Env {
        <<final>>
        -array values$
        +load(file)$ void
        +get(key, default)$ string
        +bool(key, default)$ bool
        +path(key, default)$ string
    }
```

## 5. Data structures (`backend/custom_dsa.php`)

```mermaid
classDiagram
    direction LR
    class FixedArray {
        #array slots
        #int size
        #int capacity
        +add(value) bool
        +get(index) mixed
    }
    class SubjectArray
    FixedArray <|-- SubjectArray
    class WeekGrid {
        -array cells
        +occupants(day, start, end) array
        +place(day, start, end, subjectId) void
    }
    class TaskLinkedList {
        +TaskNode head
        +insertChronologically(node) void
        +removeExpired(now) array
        +deleteById(taskId) array
        +neighbors(taskId) array
    }
    class TaskNode {
        +TaskNode next
        +TaskNode prev
    }
    TaskLinkedList o-- "*" TaskNode
    class HashSet {
        -array buckets
        +add(value) bool
        +contains(value) bool
    }
    class StudentHashSet
    HashSet <|-- StudentHashSet
    class UndoStack {
        +push(value) void
        +pop() mixed
        +peek() mixed
    }
    class ApprovalQueue {
        +enqueue(value) void
        +dequeue() mixed
        +peek() mixed
    }
    class SubjectTree {
        +search(query) array
    }
    class ChecklistTree {
        +rollup() array
        +subtreeIds(node) array
    }
    class DependencyGraph {
        +addEdge(a, b, context, groupId) void
        +teamMembers(start, context, groupId) array
        +shortestPath(a, b) array
    }
```

## Where each pillar is used

| Pillar | Where in the code | What it does in AcadSync |
| --- | --- | --- |
| **Encapsulation** | Private fields + getters in every domain class; `Activity` and `GroupRule` check their values in the constructor and have no setters; `Env` keeps settings in a private array; `UndoContext` uses `readonly` | An invalid activity or group rule can't exist; nothing outside a class can change its data |
| **Inheritance** | `President`/`VicePresident`/`Secretary` → `Officer` → `Member` → `Person`; `Meeting`/`Seminar`/`Outreach` → `Activity`; `GroupTask`/`IndividualTask` → `Task`; the three `MemberStatus` and three `UndoAction` subclasses; `ExactDeadline`/`RangeDeadline` → `DatedDeadline`; `SubjectArray` → `FixedArray`, `StudentHashSet` → `HashSet` | `Officer::permissions()` adds to `parent::permissions()`; `Secretary` and `President` add to `Officer`'s; officers inherit a member's account stats |
| **Polymorphism** | `$person->can()` / `roleLabel()` / `accountStats()`; `$activity->canCheckIn()` / `expectsAttendanceFrom()`; `$status->canRsvp()` / `allows()`; `$task->acceptsGroups()` / `rule()`; `$deadline->timestamp()`; `$action->undo()` | The endpoints never test `role === 'admin'`, the activity kind, `work_mode` or `deadline_type`: each object answers for itself |
| **Abstraction** | `interface Deadline`; `abstract class Person`, `Officer`, `MemberStatus`, `Activity`, `Task`, `DatedDeadline`, `UndoAction` | Callers use what an object can do, not how it does it |

## Relationships

| Relationship | Kind | Meaning |
| --- | --- | --- |
| `Person` ◆— `MemberStatus` | composition | every person has exactly one status; it can hold back permissions (Alumni) |
| `Task` ◆— `Deadline`, `GroupTask` ◆— `GroupRule` | composition | a task owns its deadline and its group rule |
| `ActivityRoster` ◆— `HashSet`, `ApprovalQueue` | composition | going / checked-in sets and the waitlist queue of one activity |
| `MemberRecord` ◇— `Person`, uses `Activity` | aggregation / dependency | a record is built from a person and asks each activity who was expected |
| `UndoStack` ◇— `UndoAction` | aggregation | the DSA stack holds OOP objects that know how to reverse themselves |
| `GroupRule` → `StudyGroup` | association | the rule evaluates a task's groups |
