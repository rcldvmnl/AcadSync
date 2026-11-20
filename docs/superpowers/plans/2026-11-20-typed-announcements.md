# Typed Announcements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Announcements get a type (Notice, Payment, No class, Deadline change, Task, Event) that does the real change, reads clearly, and puts upcoming dated items first.

**Architecture:** `abstract class Announcement` with one final subclass per type in `backend/domain/Announcement.php`; each validates its input, performs `apply()` inside the same transaction as the insert, words its card (`facts()`), and gives its sort date (`eventAt()`). `api/announcements.php` only calls these methods. "No class" writes `class_cancellations`, which `schedule.php` reads through a HashSet to cross classes out on that date.

**Tech Stack:** PHP 8.1+ (XAMPP), SQLite via PDO, vanilla JS + Tailwind CDN, the project's own `tests/dsa_test.php` runner.

**Spec:** `docs/superpowers/specs/2026-11-20-typed-announcements-design.md`

## Global Constraints

- Reads are `Store::all` / `section_rows` only; no `ORDER BY`/`WHERE`/`JOIN`; no `in_array`, `array_search`, `array_filter`, `usort`, `sort`. `grep -rnE "\b(in_array|array_search|array_filter|usort|sort)\(" api backend` must print nothing.
- Sorting with `Algorithms::mergeSortBy`; uniqueness/lookups with `HashSet`. Tag uses `// [DSA: …]`, `// [ALGORITHM: …]`, `// [OOP: …]`.
- Permission checks only through `Person::can(Permission::X)` / `Shell.can('x')`; never `role === 'admin'`.
- New columns via `Store::addMissingColumns`, new tables via `CREATE TABLE IF NOT EXISTS`; do **not** change `SCHEMA_VERSION`.
- Copy is plain sentence case; design: white rounded cards on `#F6F3EE`, pastel tints, charcoal buttons; works at 360 px with no sideways scroll.
- Never test against `data/acadsync.db`: use a scratch copy (see PROJECT_CONTEXT.md "Testing approach that worked").
- **Do not commit.** The owner commits (PROJECT_CONTEXT.md: "Commit only when asked"). Each task ends with lint + tests instead.
- After each task: `C:\xampp\php\php.exe tests/dsa_test.php` ends with `0 failed`; `php -l` on edited PHP; `node --check` on edited JS.

## Review Focus

1. **No class for a subject that doesn't meet that weekday** (e.g. ITEC 204 on a Saturday) → refused: "ITEC 204 has no class on Saturdays." Test in Task 2.
2. **The same No class posted twice** (same date + subject, or the whole day already cancelled) → second one refused: "That class is already cancelled on Fri, Nov 27." Test in Task 2.
3. **Deadline change to a past date, or for a task that was removed/rejected** → refused and nothing changes (task row untouched, no announcement). Tests in Task 2 (validation) and Task 3 (transaction).
4. **Existing due/task/activity id from another section, cancelled or finished** → refused: "Pick an open due from this section." / "Pick an upcoming task." / "Pick an upcoming activity." Test in Task 2.
5. **Old rows and dangling references** (announcement with no `kind`; Payment notice whose due was later cancelled; Event whose activity was cancelled) → still listed, read as Notice / show the fact "No longer available", no PHP warnings. Test in Task 3.

---

### Task 1: Shared validation helpers (deadline, activity)

**Files:**
- Modify: `backend/services.php` (add two functions at the end)
- Modify: `api/request_task.php:73-101` (deadline block of `task_fields()` → call helper)
- Modify: `api/activities.php:115-142` (`create` → call helper)
- Test: `tests/dsa_test.php` (new section "Shared validation"); add `require_once __DIR__ . '/../backend/domain/Activity.php';` and `require_once __DIR__ . '/../backend/services.php';` after the existing requires (services.php only defines functions at include time).

**Interfaces:**
- Produces: `validate_deadline(array $in, string $today): array` → `['deadline_type' => 'exact'|'range'|'unset', 'deadline' => ?string, 'deadline_end' => ?string, 'deadline_time' => ?string]`. Reads keys `deadline_type`, `deadline`, `deadline_end`, `deadline_time`. Throws `InvalidArgumentException` with exactly today's messages: "Pick the first and last day of the range.", "That date range is already over.", "Pick a deadline date.", "The deadline can’t be in the past.", "Pick a valid due time."
- Produces: `validate_new_activity(array $in, int $createdBy, int $now): Activity` reading `kind,title,description,venue,date,start,end,capacity`. Throws `InvalidArgumentException`: "Pick a date and the start and end times.", "That start time has already passed. Pick a later time or another day.", plus whatever `Activity::fromRow` throws.

- [ ] **Step 1: Write failing tests** in `tests/dsa_test.php`, section `Shared validation`:

```php
$ok = validate_deadline(['deadline_type' => 'range', 'deadline' => '2026-11-20', 'deadline_end' => '2026-11-23', 'deadline_time' => '23:59'], '2026-11-20');
check('deadline: range kept', [$ok['deadline_type'], $ok['deadline_end']], ['range', '2026-11-23']);
check('deadline: unset clears dates', validate_deadline(['deadline_type' => 'unset', 'deadline' => '2026-11-25'], '2026-11-20')['deadline'], null);
check('deadline: past exact refused', $refused(fn () => validate_deadline(['deadline_type' => 'exact', 'deadline' => '2026-11-19'], '2026-11-20')), 'The deadline can’t be in the past.');
check('deadline: bad time refused', $refused(fn () => validate_deadline(['deadline_type' => 'exact', 'deadline' => '2026-11-25', 'deadline_time' => '25:00'], '2026-11-20')), 'Pick a valid due time.');
$a = validate_new_activity(['kind' => 'seminar', 'title' => 'Intro to Figma', 'venue' => 'AVR', 'date' => '2026-11-28', 'start' => '13:00', 'end' => '15:00', 'capacity' => '30'], 1, strtotime('2026-11-20 10:00'));
check('activity: built', [$a->kind(), $a->capacity()], ['seminar', 30]);
check('activity: past start refused', $refused(fn () => validate_new_activity(['kind' => 'meeting', 'title' => 'X', 'date' => '2026-11-19', 'start' => '13:00', 'end' => '14:00'], 1, strtotime('2026-11-20 10:00'))), 'That start time has already passed. Pick a later time or another day.');
```
Define once at the top of the file: `$refused = function (callable $f) { try { $f(); return 'built'; } catch (InvalidArgumentException $e) { return $e->getMessage(); } };`
(Use the real accessor names in `Activity.php`; if it has no `capacity()` getter, assert on `toRow()['capacity']`.)

- [ ] **Step 2: Run** `C:\xampp\php\php.exe tests/dsa_test.php` → FAIL ("Call to undefined function validate_deadline").
- [ ] **Step 3: Implement both helpers** in `services.php` by moving the existing checks (no behaviour change); replace the blocks in `request_task.php` and `activities.php` with `try { … } catch (InvalidArgumentException $e) { fail($e->getMessage()); }`. `task_fields()` passes `['deadline_type' => param('deadline_type', $base['deadline_type'] ?? 'exact'), …]` so edit requests keep their defaults.
- [ ] **Step 4: Run tests** → all pass, `0 failed`. Lint the three PHP files.
- [ ] **Step 5: Scratch check** — in a scratch copy, post one exact, one range and one past task through `request_task.php`, and create + past-create an activity through `activities.php`: same responses as before the change.

---

### Task 2: Announcement domain classes + schema

**Files:**
- Create: `backend/domain/Announcement.php`
- Modify: `backend/bootstrap.php` (require it after `Payment.php`)
- Modify: `backend/schema.sql` (add `class_cancellations` table; add the four columns to the `announcements` CREATE)
- Modify: `backend/db.php` (`addMissingColumns`: `'announcements' => ['kind' => "TEXT NOT NULL DEFAULT 'general'", 'ref_id' => 'INTEGER', 'event_at' => 'TEXT', 'details' => 'TEXT']`; add `'class_cancellations'` to the `table()` allow-list)
- Test: `tests/dsa_test.php` (section "Announcements"; require `Person.php`, `MemberStatus.php`, `Payment.php`, `Announcement.php`)

**Interfaces:**
- Consumes: `validate_deadline`, `validate_new_activity` (Task 1); `Due`, `Due::centavos`, `Due::peso` (Payment.php); `Activity` (Activity.php); `Task::fromRow`, `Deadlines::fromRow`; `Person`, `Permission`; `HashSet`.
- Produces:
  - `final class AnnouncementContext` (readonly props): `int $sectionId, int $userId, Person $me, array $tasks /* taskIndex: id => row */, array $subjects /* subjectIndex */, array $slots /* class_slots() rows */, array $dues /* id => Due (open only) */, array $activities /* id => Activity */, HashSet $cancelled /* keys "Y-m-d|subjectId" and "Y-m-d|*" */, int $now`.
  - `abstract class Announcement`, constructor `(int $id, string $title, string $body, bool $pinned, int $createdBy, string $createdAt, ?int $refId, ?string $eventAt, array $details)`.
  - Statics: `fromRow(array $row): Announcement` (unknown/missing `kind` → `GeneralNotice`); `fromInput(string $kind, array $in, AnnouncementContext $c): Announcement` (throws `InvalidArgumentException`); `kinds(AnnouncementContext $c): array` → list of `['kind','label','icon']` the user may post.
  - Abstract: `kind(): string`, `kindLabel(): string`, `icon(): string`, `defaultTitle(): string`, `facts(AnnouncementContext $c): array` → `['lines' => [string…], 'action' => ['label' => string, 'href' => string] | null]`, `apply(AnnouncementContext $c, int $announcementId): void` (called after the row is inserted, inside the same transaction; No class needs the id).
  - Concrete with defaults: `requiredPermission(): ?string` (null), `onRemove(AnnouncementContext $c): void` (nothing), `eventAt(): ?int` (strtotime of `event_at`), `title(): string` (`defaultTitle()` when empty), `notifyText(AnnouncementContext $c): string`, `notifyLink(): string`, `notifyType(): string` (`'announcement'`), `toRow(int $sectionId): array`, `toArray(AnnouncementContext $c, bool $canDelete): array` → `kind, kind_label, icon, title, body, lines, action, event_at, event_ts, pinned, by_id, created_at, can_delete, id`.
  - Subclasses (final), `kind()` value / `kindLabel()` / `icon()`:
    - `GeneralNotice` `general` / Notice / `bell`
    - `PaymentNotice` `payment` / Payment / `clipboard` — input `due_id` **or** `new_due: {title, amount, due_date}`; `requiredPermission()` = `Permission::MANAGE_DUES` only when creating; `apply()` inserts the due when new and sets `refId`; `event_at` = due date `23:59`; facts: "₱150.00 · due Mon, Nov 30", action "View dues" → `dues.html`.
    - `NoClassNotice` `no_class` / No class / `x` — input `date` (Y-m-d, today or later, else "Pick today or a later day.") and `subject_id` (null = whole day); refuse when the subject has no slot on that weekday ("{CODE} has no class on {Saturdays}.") or when no class at all that weekday ("There are no classes on {Saturdays}."), or when `$c->cancelled` already has `"date|subject"` or `"date|*"` ("That class is already cancelled on {Fri, Nov 27}."); `apply()` inserts into `class_cancellations` with the given announcement id; `onRemove()` deletes its cancellation rows; `event_at` = date `00:00`; facts: "ITEC 204 · 1:00–2:30 PM" or "All classes that day", action "Open schedule" → `schedule.html`; `notifyType()` `schedule`.
    - `DeadlineChangeNotice` `deadline_change` / Deadline change / `clock` — input `task_id` + deadline fields; task must be in `$c->tasks`, `status === 'approved'`, `kind === 'new'` ("Pick an upcoming task."); deadline via `validate_deadline(...)`; `requiredPermission()` = `Permission::PUBLISH_TASK`; `details` = `{from: <old Deadline label>, to: <new label>}`; `apply()` `Store::update('tasks', id, newDeadlineFields)`; `event_at` = new deadline; facts: "Reporting — Ch. 3", "{from} → {to}"; action "Open task"; `notifyType()` `moved`.
    - `TaskNotice` `task` / Task / `notebook` — input `task_id`, same "upcoming" rule; facts: title, subject code, deadline label; action "Open task"; `event_at` = deadline (null when unset).
    - `EventNotice` `event` / Event / `flag` — input `activity_id` (must exist, not cancelled, not over: "Pick an upcoming activity.") **or** `new_activity` (via `validate_new_activity`, `requiredPermission()` = `MANAGE_ACTIVITIES`); facts: "Sat, Nov 28 · 1:00–3:00 PM · AVR Hall", "{n} seats left" when capped; action "RSVP" → `activities.html`.
  - Default titles: Payment "Payment: {due title}", No class "No class on {Friday}" / "No {CODE} class on {Friday}", Deadline change "Deadline moved: {task}", Task "{task} is coming up", Event "{Kind}: {title}".
- Schema: `class_cancellations (id INTEGER PRIMARY KEY AUTOINCREMENT, section_id INTEGER NOT NULL, announcement_id INTEGER NOT NULL, date TEXT NOT NULL, subject_id INTEGER, created_by INTEGER NOT NULL, created_at DATETIME DEFAULT (datetime('now','localtime')), FOREIGN KEY(section_id) REFERENCES sections(id))`.

- [ ] **Step 1: Write failing tests** (section `Announcements`), building an `AnnouncementContext` from arrays — a President `Person::fromRow([...'role' => 'admin'])`, a Secretary, subjects `[4 => ['id' => 4, 'code' => 'ITEC 204', …]]`, slots `[['subject_id' => 4, 'day_of_week' => 5, 'start_time' => '13:00', 'end_time' => '14:30', 'code' => 'ITEC 204', …]]` (Friday), one approved task, one open `Due`, an empty `HashSet`, `now` = 2026-11-20 10:00 (a Friday):
  - `fromRow([... no kind ...]) instanceof GeneralNotice` → true
  - `fromInput('no_class', ['date' => '2026-11-27', 'subject_id' => 4], $c)->eventAt()` → `strtotime('2026-11-27 00:00')`
  - no class, subject 4 on `2026-11-28` (Saturday) → message "ITEC 204 has no class on Saturdays."
  - no class when `$c->cancelled` holds `"2026-11-27|*"` → "That class is already cancelled on Fri, Nov 27."
  - no class on `2026-11-19` → "Pick today or a later day."
  - deadline change for a task with `status 'removed'` → "Pick an upcoming task."
  - deadline change to `2026-11-19` → "The deadline can’t be in the past."
  - `fromInput('deadline_change', …)->requiredPermission()` → `'publish_task'`
  - `kinds($secretaryContext)` contains no `deadline_change`; `kinds($presidentContext)` contains all six
  - payment with a `due_id` not in `$c->dues` → "Pick an open due from this section."
  - `defaultTitle()` of a no-class notice for subject 4 on 2026-11-27 → "No ITEC 204 class on Friday"
- [ ] **Step 2: Run** tests → FAIL ("Class Announcement not found").
- [ ] **Step 3: Implement** `Announcement.php`, the schema and db.php changes. `apply()` methods are written now but exercised in Task 3.
- [ ] **Step 4: Run tests** → `0 failed`; lint.

---

### Task 3: `api/announcements.php` — list, post, pin, remove

**Files:**
- Modify: `api/announcements.php` (rewrite around the classes)
- Modify: `backend/services.php` (add `announcement_context(array $user, array $ctx): AnnouncementContext`, `class_cancellations(int $sectionId): HashSet`)
- Modify: `api/dues.php`, `api/activities.php` only if needed to expose nothing new (no change expected)

**Interfaces:**
- Consumes: Task 2 classes; `load_context`, `class_slots`, `load_activities`, `section_rows`, `notify_section`, `Algorithms::mergeSortBy`.
- Produces:
  - `announcement_context(array $user, array $ctx): AnnouncementContext` (open dues as `Due` by id; activities by id; cancellations HashSet; `now = time()`).
  - `class_cancellations(int $sectionId): HashSet` — keys `"{date}|{subject_id}"`, whole-day rows as `"{date}|*"`. Used by Task 4.
  - GET response: `{ ok, coming_up: Item[], notices: Item[], earlier: Item[], earlier_total: int, kinds: Kind[], options: {dues: [{id,title,amount_label,due_date}], tasks: [{id,title,subject_code,deadline_label,deadline_type,deadline,deadline_end,deadline_time}], activities: [{id,title,kind_label,starts_at,venue}], subjects: [{id,code,title,slots:[{dow,start,end}]}], activity_kinds: Activity::kinds(), can_create_due: bool, can_create_activity: bool}, can_post: bool }`.
  - POST `action=post` body: `{kind, title?, body?, pinned?, …type fields from Task 2}` → `201 {id}`; `action=pin {id, pinned}`; `action=delete {id}`.

- [ ] **Step 1: Write the scratch API walkthrough** `scratchpad/announce_test.sh` (scratch only, like `groups_test.sh`): as President post each of the six types; assert (a) the task's deadline changed in `tasks.php?id=`, (b) the new due appears in `dues.php`, (c) the new activity appears in `activities.php`, (d) `schedule.php` week day shows the class `cancelled: true` (after Task 4; skip until then), (e) Secretary posting `deadline_change` → 403 "Only the President can move a deadline.", (f) Secretary posting `event` with `new_activity` → 201, (g) member posting anything → 403, (h) deleting the No class notice removes its `class_cancellations` row, (i) a deadline-change request with a past date leaves the task row unchanged and adds no announcement row, (j) an old row with `kind` NULL inserted directly and a Payment notice whose due is then cancelled both list without PHP warnings (server log clean) and the second shows "No longer available".
- [ ] **Step 2: Run it** against the current endpoint → fails at (a).
- [ ] **Step 3: Implement the endpoint.** GET: build items with `toArray`, split by `event_ts` vs start of today, sort coming-up by `event_ts` asc, notices and earlier by `id` desc, then a stable `mergeSortBy` on `pin_key` desc inside each list; send the first 10 of earlier and `earlier_total`. POST post: `fromInput` (catch → `fail(msg)`), check `POST_ANNOUNCEMENTS` then `requiredPermission()` (403 "Only the President can move a deadline." / "Only the Treasurer or the President can add a due." / "Only officers can add activities."), then `Store::begin()` → insert row → `apply($c, $id)` → `Store::commit()`; notify everyone but the poster with `notifyText` / `notifyLink` / `notifyType`. Delete: author or `MANAGE_SECTION`; `onRemove()` then delete, in one transaction.
- [ ] **Step 4: Run the walkthrough** → all assertions except (d) pass; `tests/dsa_test.php` `0 failed`; DSA grep empty; lint.

---

### Task 4: "No class" on the Schedule and Home

**Files:**
- Modify: `api/schedule.php` (mark classes per date)
- Modify: `Frontend/js/ui.js` (`UI.classRow` — cancelled style)
- Modify: `Frontend/js/schedule.js` (day list, side panel "Next class" skips cancelled)
- Modify: `Frontend/js/home.js` (admin "Classes today" uses `UI.classRow`, no change beyond the shared style)

**Interfaces:**
- Consumes: `class_cancellations(int $sectionId): HashSet` (Task 3).
- Produces: each item of `week.days[].classes` gains `cancelled: bool` and `cancel_note: ?string` (the announcement title).

- [ ] **Step 1: Extend the walkthrough** check (d): after posting No class for ITEC 204 on a weekday in the current week, `schedule.php` → that day's ITEC 204 entry has `cancelled: true`; the same subject on other days `false`; a whole-day cancellation marks every class that day.
- [ ] **Step 2: Run** → (d) fails.
- [ ] **Step 3: Implement**: in `schedule.php`, after building `$days`, look up `"{date}|{subject_id}"` / `"{date}|*"` in the HashSet (`// [DSA: HASHSET]`); `cancel_note` from the matching announcement. `UI.classRow(c)`: when `c.cancelled`, title `line-through`, muted, chip "No class" (`bg-danger-tint`). `schedule.js` "Next class": skip cancelled.
- [ ] **Step 4: Verify** → walkthrough passes in full; headless Chrome at 390 px on `schedule.html` shows the crossed-out class with the chip; no console errors.

---

### Task 5: Home section and composer

**Files:**
- Modify: `Frontend/js/home.js` (`Announcements` object: `render`, `card`, `compose`)
- Modify: `Frontend/js/notifications.js` (`KIND`: `schedule` already exists; nothing else expected)

**Interfaces:**
- Consumes: GET/POST shapes from Task 3.
- Produces: no new globals besides the existing `Announcements`.

- [ ] **Step 1: Card** — one skeleton for every type (spec §10): type icon in its tint (Payment `bg-peach-tint`, No class `bg-danger-tint`, Deadline change `bg-lavender-tint`, Task `bg-sun-tint`, Event `bg-mint-tint`, Notice `bg-canvas`); top line `KIND · date · in N days` (`Fmt.relative(event_ts)`; "Today" when same day) plus a "Pinned" chip; title bold; `lines` as muted lines; body clamped to 3 lines with a "Read more" toggle when longer; footer `by · posted Nov 20` and the `action` as a small charcoal button. Pin / Remove stay as small buttons for those allowed.
- [ ] **Step 2: Section** — headings "Coming up", "Notices", then a folded "Earlier ({earlier_total})" button that expands the list; on Home show the first 3 of coming-up + notices with "Show all"; empty state unchanged.
- [ ] **Step 3: Composer** — type buttons from `kinds` (one row, wraps on phones); fields per type from `options` (Payment: "Existing / New" switch, New only when `can_create_due`; No class: date input + "All classes / One subject" with only subjects that meet that weekday, recomputed when the date changes; Deadline change: task select showing its current deadline, then `createDatePicker` + `createTimeChips`; Task: task select; Event: "Existing / New" switch, New = kind, title, date, start, end, venue, seats); title placeholder = the type's default title; live preview using the same `card()` function with the typed values; Post sends `{kind, …}`.
- [ ] **Step 4: Verify in headless Chrome** (scratch copy, President and Secretary, 360/390/1280): each type posts from the composer and appears in the right list; Secretary's composer has no "Deadline"; a long message clamps; no console errors; no horizontal overflow.

---

### Task 6: Seed, docs, final checks

**Files:**
- Modify: `backend/seed.php` (replace the two plain announcements with: a pinned Payment notice for "Membership fee", a No class notice for ITEC 204 on the next day it meets, an Event notice for the seeded "Intro to Figma" seminar, and one Notice)
- Modify: `README.md` (Announcements feature section: the six types and what each does; DSA table: HashSet "cancelled classes per date", Merge Sort "announcements by date")
- Modify: `docs/CLASS_DIAGRAM.md` (Announcement family + `AnnouncementContext`; pillar rows)
- Modify: `docs/PROJECT_CONTEXT.md`, `docs/NEXT_STEPS.md`

- [ ] **Step 1: Seed** through the classes (`Announcement::fromInput(...)->apply(...)` inside the seed transaction) so seed data obeys the same rules.
- [ ] **Step 2: Fresh scratch DB** → Home shows Coming up (Event, Payment, No class) and Notices; Schedule shows the seeded cancelled class.
- [ ] **Step 3: Existing-DB scratch copy** → old announcements list as Notices, no warnings in the server log.
- [ ] **Step 4: Docs** updated as listed.
- [ ] **Step 5: Final** — `tests/dsa_test.php` `0 failed`; `php -l` all PHP; `node --check` all JS; DSA grep empty; `data/acadsync.db` untouched by the tests.
