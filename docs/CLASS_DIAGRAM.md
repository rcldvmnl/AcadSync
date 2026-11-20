# AcadSync class diagram

The classes below are every class in `backend/domain/` and `backend/Env.php` (the OOP model) and
`backend/custom_dsa.php` (the data structures and algorithms), with their real names, fields, methods
and visibility. Long method lists are shortened to the methods the app relies on.
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
    class Treasurer {
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
        +REQUEST_TASK$
        +JOIN_GROUP$
        +REVIEW_REQUESTS$
        +REVIEW_MEMBERS$
        +POST_ANNOUNCEMENTS$
        +MANAGE_ACTIVITIES$
        +RECORD_ATTENDANCE$
        +UPDATE_MEMBER_RECORDS$
        +MANAGE_DUES$
        +ASSIGN_OFFICERS$
        +MANAGE_SECTION$
    }
    Person <|-- Member
    Member <|-- Officer
    Officer <|-- VicePresident
    Officer <|-- Secretary
    Officer <|-- Treasurer
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
        +owesDues()* bool
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

## 2. Activities, attendance, competition / project parts and member records

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
        -string link
        -string signupDeadline
        -string result
        +__construct(id, title, …, capacity, status, createdBy, link, signupDeadline, result)
        +fromRow(row)$ Activity
        +kinds()$ array
        -cleanLink(link)$ string
        +startTs() int
        +isOver(now) bool
        +hasRoom(going) bool
        +linkHost() string
        +signupClosesAt() int
        +signupOpen(now) bool
        +isInviteOnly() bool
        +isFor(userId) bool
        +invitedIds() array
        +kind()* string
        +kindLabel()* string
        +expectsAttendanceFrom(hasSeat)* bool
        +canCheckIn(hasSeat)* bool
        +attendanceRule()* string
        +joinsBy()* string
        +usesSeats()* bool
        +canAssignDirectly()* bool
        +partsNeedDueDates()* bool
        +toRow() array
    }
    class RsvpActivity {
        <<abstract>>
        +joinsBy() string
        +usesSeats() bool
    }
    class PartsActivity {
        <<abstract>>
        +joinsBy() string
        +usesSeats() bool
        +expectsAttendanceFrom(hasSeat) bool
        +canCheckIn(hasSeat) bool
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
    class SectionEvent {
        +expectsAttendanceFrom(hasSeat) bool
        +canCheckIn(hasSeat) bool
    }
    class Competition {
        +canAssignDirectly() bool
        +partsNeedDueDates() bool
    }
    class SectionProject {
        +canAssignDirectly() bool
        +partsNeedDueDates() bool
    }
    Activity <|-- RsvpActivity
    Activity <|-- PartsActivity
    RsvpActivity <|-- Meeting
    RsvpActivity <|-- Seminar
    RsvpActivity <|-- Outreach
    RsvpActivity <|-- SectionEvent
    PartsActivity <|-- Competition
    PartsActivity <|-- SectionProject

    class ActivityPart {
        <<final>>
        -int id
        -int activityId
        -string name
        -string details
        -int spots
        -bool together
        -string[] roles
        -string dueDate
        -string doneAt
        -string result
        +fromRow(row, activity)$ ActivityPart
        +openSpots(picked) int
        +isFull(picked) bool
        +whyCantResize(newSpots, picked) string
        +spotsLabel() string
        +isDone() bool
        +toArray(today) array
    }
    class PartRoster {
        <<final>>
        -ApprovalQueue volunteers
        -HashSet picked
        +statusOf(userId) string
        +roleOf(userId) string
        +pickedCount() int
        +volunteers() array
        +whyCantVolunteer(activity, userId, canRsvp, now) string
        +whyCantPick(part, activity, userId, direct) string
    }
    PartsActivity "1" *-- "1..*" ActivityPart : parts
    ActivityPart ..> Activity : partsNeedDueDates()
    ActivityPart "1" -- "1" PartRoster : who is on it
    PartRoster *-- ApprovalQueue : volunteers
    PartRoster *-- HashSet : picked
    PartRoster ..> Activity : signupOpen(), canAssignDirectly()

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
        +opensAt() int
        +attentionAt(now) int
    }
    class DatedDeadline {
        <<abstract>>
        #string lastDay
        #string time
        +timestamp() int
        +opensAt() int
        +attentionAt(now) int
    }
    class ExactDeadline
    class RangeDeadline {
        -string firstDay
        +opensAt() int
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
        <<abstract>>
        +fromRow(row)$ GroupRule
        +create(mode, size, count)$ GroupRule
        +mode()* string
        +label()* string
        +seats(classSize)* array
        +explain(classSize)* string
        +size() int
        +count() int
        +studentsOrganize() bool
        +makesEmptyGroups() bool
        +sizes(classSize) array
        +evaluate(groups, classSize) array
        +capacityOf(group, state) int
        +autoPlan(sizes, left, classSize) array
        +toColumns() array
    }
    class AssignedGroups {
        -int groups
        +studentsOrganize() bool
    }
    class FixedGroupCount {
        -int groups
        +makesEmptyGroups() bool
    }
    class FixedGroupSize {
        -int members
    }
    class FixedSizeAndCount {
        -int members
        -int groups
        +makesEmptyGroups() bool
    }
    GroupRule <|-- AssignedGroups
    GroupRule <|-- FixedGroupCount
    GroupRule <|-- FixedGroupSize
    GroupRule <|-- FixedSizeAndCount
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

## 4. Dues and payments (`backend/domain/Payment.php`)

```mermaid
classDiagram
    direction TB
    class Payment {
        <<abstract>>
        -int id
        -int dueId
        -int userId
        -int amount
        -string reference
        -int receivedBy
        -string paidAt
        +__construct(id, dueId, userId, amount, reference, receivedBy, paidAt)
        +create(method, dueId, userId, amount, reference, receivedBy)$ Payment
        +fromRow(row)$ Payment
        +methods()$ array
        +method()* string
        +methodLabel()* string
        #cleanReference(reference)* string
        +receiptLine()* string
        +referenceIsUnique()* bool
        +amount() int
        +toRow(sectionId) array
    }
    class CashPayment {
        #cleanReference(reference) string
        +receiptLine() string
        +referenceIsUnique() bool
    }
    class GcashPayment {
        #cleanReference(reference) string
        +receiptLine() string
        +referenceIsUnique() bool
    }
    class Due {
        <<final>>
        -int id
        -string title
        -int amount
        -string dueDate
        -string status
        -int createdBy
        +__construct(id, title, amount, dueDate, status, createdBy)
        +fromRow(row)$ Due
        +centavos(pesos)$ int
        +peso(centavos)$ string
        +isOpen() bool
        +balance(paid) int
        +standing(paid, now) string
    }
    Payment <|-- CashPayment
    Payment <|-- GcashPayment
    Payment "*" --> "1" Due : pays
    Payment ..> Person : paid by
    Due ..> MemberStatus : owesDues()
```

## 4b. Typed announcements (`backend/domain/Announcement.php`)

Each kind does the real change it announces in `apply()`, inside the same transaction as the post.

```mermaid
classDiagram
    direction TB
    class Announcement {
        <<abstract>>
        +KINDS$
        +KIND$
        +LABEL$
        +ICON$
        -int id
        -string title
        -string body
        -bool pinned
        -int createdBy
        #int refId
        #string eventAt
        #array details
        +fromRow(row)$ Announcement
        +fromInput(kind, input, context)$ Announcement
        +kinds(context)$ array
        +canPost(context)$ bool
        #build(input, context)$ Announcement
        +defaultTitle()* string
        +facts(context)* array
        +apply(context, announcementId)* void
        +onRemove(context) void
        +requiredPermission() string
        +eventAt() int
        +notifyText(context) string
        +toRow(sectionId) array
        +toArray(context, canDelete) array
    }
    class GeneralNotice
    class PaymentNotice {
        +requiredPermission() string
        +apply(context, id) void
    }
    class NoClassNotice {
        +apply(context, id) void
        +onRemove(context) void
    }
    class DeadlineChangeNotice {
        +canPost(context)$ bool
        +requiredPermission() string
        +apply(context, id) void
    }
    class TaskNotice
    class EventNotice {
        +requiredPermission() string
        +apply(context, id) void
    }
    class AnnouncementContext {
        <<final>>
        +int sectionId «readonly»
        +int userId «readonly»
        +Person me «readonly»
        +array tasks «readonly»
        +array slots «readonly»
        +Due[] dues «readonly»
        +Activity[] activities «readonly»
        +HashSet cancelled «readonly»
        +int now «readonly»
        +array going «readonly»
        +array openSpots «readonly»
        +today() string
    }
    Announcement <|-- GeneralNotice
    Announcement <|-- PaymentNotice
    Announcement <|-- NoClassNotice
    Announcement <|-- DeadlineChangeNotice
    Announcement <|-- TaskNotice
    Announcement <|-- EventNotice
    Announcement ..> AnnouncementContext : uses
    PaymentNotice ..> Due : points at / makes
    EventNotice ..> Activity : points at / makes
    DeadlineChangeNotice ..> Task : moves the deadline of
    NoClassNotice ..> HashSet : cancelled classes
```

## 5. Undo (OOP objects kept in a DSA stack) and settings

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
    class UndoNotPossible {
        <<exception>>
    }
    RuntimeException <|-- UndoNotPossible
    UndoAction ..> UndoNotPossible : throws

    class Env {
        <<final>>
        -array values$
        +load(file)$ void
        +get(key, default)$ string
        +bool(key, default)$ bool
        +path(key, default)$ string
    }
```

## 6. Data structures and algorithms (`backend/custom_dsa.php`)

Every node class is drawn next to the structure that links its nodes.

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
        -StackNode top
        -int size
        +push(value) void
        +pop() mixed
        +peek() mixed
        +removeWhere(match) mixed
    }
    class StackNode {
        +mixed value
        +StackNode below
    }
    UndoStack o-- "*" StackNode
    class ApprovalQueue {
        -QueueNode front
        -QueueNode rear
        -int size
        +enqueue(value) void
        +dequeue() mixed
        +peek() mixed
    }
    class QueueNode {
        +mixed value
        +QueueNode next
    }
    ApprovalQueue o-- "*" QueueNode
    class TreeNode {
        +string label
        +array data
        +TreeNode[] children
        +addChild(child) TreeNode
    }
    class SubjectTree {
        +TreeNode root
        +search(query) array
    }
    class ChecklistTree {
        +TreeNode root
        +rollup() array
        +subtreeIds(node) array
    }
    SubjectTree *-- "1" TreeNode : root
    ChecklistTree *-- "1" TreeNode : root
    TreeNode o-- "*" TreeNode : children
    class DependencyGraph {
        -array adjacency
        +addEdge(a, b, context, groupId) void
        +teamMembers(start, context, groupId) array
        +shortestPath(a, b) array
    }
    DependencyGraph ..> ApprovalQueue : BFS uses
    class Algorithms {
        <<final>>
        +bubbleSortByUrgency(tasks, now)$ array
        +selectionSortByLastName(students)$ array
        +selectionSortByStudentId(students)$ array
        +mergeSortBy(items, key, desc)$ array
        +binarySearchByStudentId(sorted, id)$ array
        +countDueBy(sorted, limitTs)$ int
        +linearSearch(items, target)$ int
        +linearContains(items, target)$ bool
        +linearSearchTasks(tasks, text)$ array
        +linearSearchByName(students, query)$ array
        +planGroupSizes(n, min, max, count)$ array
        +shuffle(items)$ array
    }
    class DsaUtil {
        <<final>>
        +lower(s)$ string
        +compareText(a, b)$ int
        +contains(haystack, needle)$ bool
        +digitsToInt(s)$ int
        +words(text, min)$ array
        +deadlineTs(task)$ int
    }
    Algorithms ..> DsaUtil : uses
```

## Where each pillar is used

| Pillar | Where in the code | What it does in AcadSync |
| --- | --- | --- |
| **Encapsulation** | Private fields + getters in every domain class; `Activity` (title, times, a link that must be http(s), a sign-up deadline on or before the first day), `ActivityPart`, `GroupRule`, `Due` and `Payment` check their values in the constructor and have no setters; money is whole centavos in private fields; `Env` keeps settings in a private array; `UndoContext` uses `readonly` | An invalid activity, group rule, due or payment can't exist; nothing outside a class can change its data |
| **Inheritance** | `President`/`VicePresident`/`Secretary`/`Treasurer` → `Officer` → `Member` → `Person`; `Meeting`/`Seminar`/`Outreach`/`SectionEvent` → `RsvpActivity` → `Activity`; `Competition`/`SectionProject` → `PartsActivity` → `Activity`; `GroupTask`/`IndividualTask` → `Task`; `AssignedGroups`/`FixedGroupCount`/`FixedGroupSize`/`FixedSizeAndCount` → `GroupRule`; `CashPayment`/`GcashPayment` → `Payment`; the six notices → `Announcement`; the three `MemberStatus` and three `UndoAction` subclasses; `ExactDeadline`/`RangeDeadline` → `DatedDeadline`; `SubjectArray` → `FixedArray`, `StudentHashSet` → `HashSet`; `UndoNotPossible` → `RuntimeException` | `Officer::permissions()` adds to `parent::permissions()`; `Secretary`, `Treasurer` and `President` add to `Officer`'s; officers inherit a member's account stats |
| **Polymorphism** | `$person->can()` / `roleLabel()` / `accountStats()`; `$activity->canCheckIn()` / `expectsAttendanceFrom()` / `joinsBy()` / `usesSeats()` / `canAssignDirectly()` / `partsNeedDueDates()` (competitions and projects never touch attendance); `$status->canRsvp()` / `allows()` / `owesDues()`; `$task->acceptsGroups()` / `rule()`; `$rule->seats()` / `explain()` / `studentsOrganize()` (one class per way a prof sets up groups); `$deadline->timestamp()` / `attentionAt()` (an open date range counts as today); `$action->undo()`; `$payment->receiptLine()` / `referenceIsUnique()`; `$announcement->apply()` / `facts()` / `onRemove()` | The endpoints never test `role === 'admin'`, the activity kind, `work_mode`, `deadline_type`, the payment method or the announcement kind: each object answers for itself |
| **Abstraction** | `interface Deadline`; `abstract class Person`, `Officer`, `MemberStatus`, `Activity`, `RsvpActivity`, `PartsActivity`, `Task`, `GroupRule`, `DatedDeadline`, `UndoAction`, `Payment`, `Announcement` | Callers use what an object can do, not how it does it |

## Relationships

| Relationship | Kind | Meaning |
| --- | --- | --- |
| `Person` ◆— `MemberStatus` | composition | every person has exactly one status; it can hold back permissions (Alumni) |
| `Task` ◆— `Deadline`, `GroupTask` ◆— `GroupRule` | composition | a task owns its deadline and its group rule |
| `ActivityRoster` ◆— `HashSet`, `ApprovalQueue` | composition | going / checked-in sets and the waitlist queue of one activity |
| `PartsActivity` ◆— `ActivityPart` | composition | a competition or project owns its parts (at least one) |
| `PartRoster` ◆— `ApprovalQueue`, `HashSet` | composition | a part's volunteers in arrival order and who is picked |
| `ActivityPart` / `PartRoster` → `Activity` | dependency | the part asks its activity for the due-date rule; the roster asks whether sign-ups are open and whether officers may assign directly |
| `MemberRecord` ◇— `Person`, uses `Activity` | aggregation / dependency | a record is built from a person and asks each activity who was expected |
| `UndoStack` ◇— `UndoAction` | aggregation | the DSA stack holds OOP objects that know how to reverse themselves |
| `GroupRule` → `StudyGroup` | association | the rule evaluates a task's groups (seats left, +1 extra seats, who may join) |
| `Payment` → `Due` | association | many payments (part-payments too) settle one due |
| `ApprovalQueue` ◆— `QueueNode`, `UndoStack` ◆— `StackNode`, `TreeNode` ◇— `TreeNode` | composition / aggregation | each structure links its own node objects |
| `Algorithms` → `DsaUtil` | dependency | the sorts and searches use the hand-written text helpers |
