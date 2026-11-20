# Competitions and Section Projects Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activities gets two new kinds:
- **Competition**: volunteer, then officers pick.
- **Section project**: parts given to people, with due dates and progress.

It also gets a clickable link to the original post, a sign-up deadline, events over several days, and
new page cards: Your sign-ups, Needs people, This month, Results and a better empty state.

**Architecture:**
- `Competition` and `SectionProject` extend the existing abstract `Activity` (`backend/domain/Activity.php`). Each answers new polymorphic questions: `joinsBy()`, `usesSeats()`, `canAssignDirectly()`, `partsNeedDueDates()`.
- A new `backend/domain/ActivityPart.php` holds:
  - `ActivityPart`: validated in its constructor.
  - `PartRoster`: an `ApprovalQueue` of volunteers in arrival order, plus a `HashSet` of who is picked.
- `api/activities.php` only calls these classes. The page (`Frontend/js/activities.js`) and a small Home card read its JSON.

**Tech Stack:** PHP 8.1+ (XAMPP, `C:\xampp\php\php.exe`), SQLite via PDO, vanilla JS + Tailwind CDN, the project's own `tests/dsa_test.php` runner.

**Spec:** `docs/superpowers/specs/2026-11-20-competitions-and-projects-design.md` (approved 2026-11-20). Read it first; it has the page sketch and the "Calls Claude made".

## Global Constraints

- **Reading and sorting data**
  - Reads go through `Store::all` / `section_rows` only. No `ORDER BY` / `WHERE` / `JOIN`.
  - Don't use `in_array`, `array_search`, `array_filter`, `usort` or `sort`. `grep -rnE "\b(in_array|array_search|array_filter|usort|sort)\(" api backend` must print nothing.
  - Sort with `Algorithms::mergeSortBy`. Use `HashSet` for membership and `ApprovalQueue` for first-come order.
  - Tag each use with `// [DSA: …]`, `// [ALGORITHM: …]` or `// [OOP: …]`.
- **Permissions:** check only through `Person::can(Permission::MANAGE_ACTIVITIES)` (officers) and `$me->status()->canRsvp()` (who may volunteer). Never compare roles.
- **Database changes**
  - New tables: `CREATE TABLE IF NOT EXISTS` in `backend/schema.sql`, plus a `Database::table()` allow-list entry.
  - New columns: `Database::addMissingColumns` (`backend/db.php`).
  - Do **not** change `SCHEMA_VERSION`.
- **Links:** only `http://` / `https://`, at most 500 characters. Always render them as `<a href target="_blank" rel="noopener noreferrer">` showing the site name (host without `www.`). Bad link message: "Paste a link that starts with http:// or https://."
- **Attendance:** Competition and SectionProject return `false` from both `expectsAttendanceFrom()` and `canCheckIn()`, so they never change an attendance rate.
- **Copy and design**
  - Plain sentence case.
  - Volunteers always read **"Volunteered — the officers will pick"** until they are picked; then **"Picked"** (+ " · <role>").
  - White rounded cards on `#F6F3EE`, pastel tints (`bg-mint-tint` competition, `bg-lavender-tint` project), charcoal buttons.
  - Works at 360 px with no sideways scroll.
- **Testing and commits**
  - Never test against `data/acadsync.db`. Use a scratch copy (PROJECT_CONTEXT.md, "Testing approach that worked").
  - **Do not commit.** The owner commits. Each task ends with lint + tests.
- **After each task:**
  - `C:\xampp\php\php.exe tests/dsa_test.php` ends with `0 failed`.
  - `php -l` on each edited PHP file; `node --check` on each edited JS file.

## Review Focus

1. **A hostile or broken link** (`javascript:alert(1)`, `data:text/html,…`, `ftp://x`, `www.facebook.com` with no scheme, a 600-character URL) → refused with "Paste a link that starts with http:// or https://.". Stored links render only with `target="_blank" rel="noopener noreferrer"`. Test: Task 1.
2. **Two officers picking the last spot at once** → the second gets "This part is full. Un-pick someone first." and the part never has more people picked than spots. Test: Task 2 (`PartRoster`) and Task 3 (count re-read inside `Store::begin`).
3. **Volunteering on the sign-up deadline day itself** (open until 23:59:59 that day) and the day after (refused: "Sign-ups for “Founding Anniversary” closed on Fri, Nov 20."). Test: Task 1 (`signupClosesAt`) and Task 2.
4. **Shrinking or removing a part below the people already picked** → refused: "That part already has 3 picked. Un-pick people first." Test: Task 2.
5. **Cancelling a competition with volunteers and picked people** → every one of them is notified once. The cancelled activity's parts leave Needs people and Your sign-ups. Test: Task 3.

---

### Task 1: Activity kinds, link, sign-up deadline, several days

**Files:**
- Modify: `backend/domain/Activity.php` (constructor, `fromRow`, `kinds`, `toRow`, `toArray`, two new subclasses)
- Modify: `backend/services.php` (`validate_new_activity`, around line 780)
- Modify: `backend/schema.sql` (`activities`: `link TEXT`, `signup_deadline TEXT`, `result TEXT`)
- Modify: `backend/db.php` (`addMissingColumns`: `'activities' => ['link' => 'TEXT', 'signup_deadline' => 'TEXT', 'result' => 'TEXT']`)
- Modify: `api/announcements.php` (`options.activity_kinds`: only kinds whose `joins_by` is `'rsvp'`, see Ruling below)
- Test: `tests/dsa_test.php`, new section `section('Competitions and projects: Activity');`

**Interfaces:**
- Produces:
  - Constructor: `Activity::__construct(…existing 9 params…, ?string $link = null, ?string $signupDeadline = null, ?string $result = null)`. Existing calls keep working.
  - Polymorphic questions: `abstract joinsBy(): string` (`'rsvp'` | `'parts'`), `abstract usesSeats(): bool`, `abstract canAssignDirectly(): bool`, `abstract partsNeedDueDates(): bool`.
  - Link and dates: `link(): ?string`, `linkHost(): ?string` (host without `www.`), `signupDeadline(): ?string`, `result(): ?string`, `createdBy(): int`, `startDate(): string` (Y-m-d).
  - `signupClosesAt(): int`: the deadline at 23:59:59 if set, else `startTs()`.
  - `signupOpen(int $now): bool`: `!isCancelled() && $now <= signupClosesAt()`.
  - New classes: `final class Competition extends Activity` (kind `'competition'`, label `'Competition'`) and `final class SectionProject extends Activity` (kind `'project'`, label `'Section project'`).
  - `Activity::kinds()` returns all five kinds, each with an added `'joins_by'`.
  - `toArray($now)` adds `link`, `link_host`, `signup_deadline`, `signup_open`, `result`, `joins_by`, `uses_seats`, `multi_day` (start date ≠ end date).
  - `validate_new_activity()` also reads `end_date` (Y-m-d, default = `date`), `link` and `signup_deadline`. `ends_at` = `"$end_date $end"`.

- [ ] **Step 1: Write the failing tests** (use the existing `check()` and `$refused` helpers)

```php
$base = ['title' => 'Founding Anniversary', 'starts_at' => '2026-11-25 08:00', 'ends_at' => '2026-11-26 17:00'];
$c = Activity::fromRow($base + ['kind' => 'competition', 'link' => 'https://www.facebook.com/ssc/posts/1', 'signup_deadline' => '2026-11-20']);
check('competition kind', [$c->kind(), $c->kindLabel(), $c->joinsBy(), $c->usesSeats(), $c->canAssignDirectly(), $c->partsNeedDueDates()],
      ['competition', 'Competition', 'parts', false, false, false]);
$p = Activity::fromRow($base + ['kind' => 'project']);
check('project kind', [$p->kind(), $p->kindLabel(), $p->joinsBy(), $p->canAssignDirectly(), $p->partsNeedDueDates()],
      ['project', 'Section project', 'parts', true, true]);
check('seminar still rsvp', [Activity::fromRow($base + ['kind' => 'seminar'])->joinsBy(), Activity::fromRow($base + ['kind' => 'seminar'])->usesSeats()], ['rsvp', true]);
check('no attendance for competitions/projects', [$c->expectsAttendanceFrom(true), $c->canCheckIn(true), $p->expectsAttendanceFrom(true), $p->canCheckIn(true)], [false, false, false, false]);
check('link host', $c->linkHost(), 'facebook.com');
foreach (['javascript:alert(1)', 'data:text/html,hi', 'ftp://x.org', 'www.facebook.com', 'https://' . str_repeat('a', 600) . '.com'] as $bad) {
    check("bad link refused: " . substr($bad, 0, 20), $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'link' => $bad])), 'Paste a link that starts with http:// or https://.');
}
check('empty link is no link', Activity::fromRow($base + ['kind' => 'competition', 'link' => ''])->link(), null);
check('deadline after start refused', $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'signup_deadline' => '2026-11-27'])), 'The sign-up deadline must be on or before the first day.');
check('impossible deadline refused', $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'signup_deadline' => '2026-02-30'])), 'Pick a real date for the sign-up deadline.');
check('open on the deadline day', $c->signupOpen(strtotime('2026-11-20 23:59:00')), true);
check('closed the day after', $c->signupOpen(strtotime('2026-11-21 00:00:01')), false);
check('no deadline closes at the start', Activity::fromRow($base + ['kind' => 'competition'])->signupClosesAt(), strtotime('2026-11-25 08:00'));
check('kinds lists five', count(Activity::kinds()), 5);
$multi = validate_new_activity(['kind' => 'competition', 'title' => 'FA', 'date' => '2030-11-25', 'end_date' => '2030-11-26', 'start' => '08:00', 'end' => '17:00'], 1, time());
check('several days', [$multi->toArray(time())['multi_day'], $multi->endTs()], [true, strtotime('2030-11-26 17:00')]);
check('end date before start refused', $refused(fn () => validate_new_activity(['kind' => 'competition', 'title' => 'FA', 'date' => '2030-11-25', 'end_date' => '2030-11-24', 'start' => '08:00', 'end' => '17:00'], 1, time())), 'The end time must be after the start time.');
check('result too long refused', $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'result' => str_repeat('x', 301)])), 'Keep the result under 300 characters.');
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `C:\xampp\php\php.exe tests/dsa_test.php`
Expected: FAIL ("Pick Meeting, Seminar or Outreach." thrown for `competition`)

- [ ] **Step 3: Implement**
  - Validation goes in the `Activity` constructor ([OOP: ENCAPSULATION]):
    - Link: trim; empty → null; otherwise `filter_var($v, FILTER_VALIDATE_URL)` plus a `parse_url` scheme of `http`/`https`, at most 500 characters.
    - Signup deadline: `is_real_date`, and not after `substr(startsAt, 0, 10)`.
    - Result: at most 300 characters; empty → null.
  - `fromRow` reads `link`, `signup_deadline` and `result`, and adds the `match` arms `'competition'` and `'project'`. Default message: "Pick a kind of activity."
  - Attendance rule texts (spec): Competition "Volunteer for a part. Officers pick who represents the section."; SectionProject "Everyone helps. Each part has its people and a due date."
  - `toRow()` includes the three new columns.
  - Add the migration columns (db.php and schema.sql, see Files).
  - In `api/announcements.php`, build `activity_kinds` by looping over `Activity::kinds()` and keeping only `joins_by === 'rsvp'`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `C:\xampp\php\php.exe tests/dsa_test.php`
Expected: `… passed, 0 failed`. The existing Activity/Records sections stay green, and `member_records` is unchanged for the old kinds.

**Ruling for the executor (already decided):** the Event announcement composer may only *create* RSVP kinds (a competition needs parts, which that small form doesn't have), but it may *point to* any existing activity, including competitions (Task 5).

---

### Task 2: Parts and the volunteer roster

**Files:**
- Create: `backend/domain/ActivityPart.php` (header comment listing the OOP pillars + DSA used, like `Activity.php`)
- Modify: `backend/bootstrap.php` (require it after `Activity.php`)
- Modify: `backend/schema.sql`, with two tables:
  - `activity_parts` (id, section_id, activity_id, name, details, spots INTEGER NULL, together INTEGER NOT NULL DEFAULT 0, roles TEXT (JSON), due_date TEXT, position INTEGER NOT NULL DEFAULT 0, done_at TEXT, done_by INTEGER, result TEXT, created_at DATETIME DEFAULT (datetime('now','localtime')))
  - `part_members` (id, section_id, part_id, user_id, status TEXT NOT NULL DEFAULT 'volunteer', volunteered INTEGER NOT NULL DEFAULT 1, role TEXT, note TEXT, picked_by INTEGER, picked_at TEXT, created_at DATETIME DEFAULT (datetime('now','localtime')), UNIQUE(part_id, user_id))
- Modify: `backend/db.php`: add `'activity_parts', 'part_members'` to the `table()` allow-list.
- Modify: `backend/services.php`: two loaders next to `activity_rosters`.
- Test: `tests/dsa_test.php`, section `section('Competitions and projects: parts');`

**Interfaces:**
- Consumes: Task 1's `Activity::partsNeedDueDates()`, `canAssignDirectly()`, `signupOpen()`, `title()`, `endTs()`.
- Produces:
  - **`ActivityPart`**
    - `__construct(int $id, int $activityId, string $name, string $details, ?int $spots, bool $together, array $roles, ?string $dueDate, int $position, ?string $doneAt = null, ?int $doneBy = null, ?string $result = null, bool $needsDueDate = false, ?string $lastDay = null)`
    - `static fromRow(array $r, Activity $a): ActivityPart`
    - Getters: `id()`, `activityId()`, `name()`, `spots(): ?int`, `roles(): array`, `dueDate(): ?string`, `isDone(): bool`
    - `openSpots(int $picked): ?int` (null = any number), `isFull(int $picked): bool`
    - `whyCantResize(?int $newSpots, int $picked): ?string`
    - `spotsLabel(): string`: "team of 4" when `together`, "2 spots", or "Any number"
    - `toArray(string $today): array`: includes `overdue` = not done && due date < today
  - **`PartRoster`**
    - `__construct(array $memberRows)` (`part_members` rows of one part, in stored order)
    - Lookups: `statusOf(int $userId): string` (`'none'` | `'volunteer'` | `'picked'`), `roleOf(int $userId): ?string`
    - Counts: `pickedCount(): int`, `volunteerCount(): int` (volunteers not yet picked)
    - Lists: `volunteers(): array` (rows in arrival order, from the `ApprovalQueue`), `picked(): array` (rows)
    - `whyCantVolunteer(Activity $a, int $userId, bool $canRsvp, int $now): ?string`
    - `whyCantPick(ActivityPart $p, Activity $a, int $userId, bool $direct): ?string`
  - **Loaders:** `load_parts(int $sectionId, array $activities): array` (activityId => ActivityPart[] in `position` order, merge sort) and `part_rosters(int $sectionId): array` (partId => PartRoster).

Exact messages (spec wording; the executor uses them verbatim):

| Condition | Message |
|---|---|
| name empty or > 80 | Give the part a name (up to 80 characters). |
| spots outside 1–100 | Spots must be between 1 and 100, or leave it empty for any number. |
| > 10 roles or a role > 40 chars | Up to 10 roles, each up to 40 characters. |
| project part without due date | Give each part a due date. |
| competition part with due date | Competition parts don’t have due dates. |
| project part due after the last day | Pick a due date on or before the project’s last day (Fri, Dec 4). |
| details > 1000 | Keep the details under 1,000 characters. |
| volunteer: alumni | Alumni members can’t volunteer. |
| volunteer: closed | Sign-ups for “{title}” closed on {D, M j}. (no deadline: "Sign-ups for “{title}” are closed.") |
| volunteer: already on it | You already volunteered for this part. (picked: "You’re already picked for this part.") |
| pick: full | This part is full. Un-pick someone first. |
| pick: not a volunteer (competition) | Pick someone who volunteered. |
| pick: already picked | Already picked. |
| resize below picked | That part already has {n} picked. Un-pick people first. |

- [ ] **Step 1: Write the failing tests**

```php
$comp = Activity::fromRow(['kind' => 'competition', 'title' => 'Founding Anniversary', 'starts_at' => '2026-11-25 08:00', 'ends_at' => '2026-11-26 17:00', 'signup_deadline' => '2026-11-20']);
$proj = Activity::fromRow(['kind' => 'project', 'title' => 'Booth design', 'starts_at' => '2026-11-21 08:00', 'ends_at' => '2026-12-04 17:00']);
$cos = ActivityPart::fromRow(['id' => 1, 'activity_id' => 1, 'name' => 'Cosplay Competition', 'spots' => 2, 'roles' => '[]'], $comp);
$cul = ActivityPart::fromRow(['id' => 2, 'activity_id' => 1, 'name' => 'College Culinary Showdown', 'spots' => 4, 'together' => 1], $comp);
$any = ActivityPart::fromRow(['id' => 3, 'activity_id' => 1, 'name' => 'Dating game', 'spots' => null], $comp);
check('spots labels', [$cos->spotsLabel(), $cul->spotsLabel(), $any->spotsLabel()], ['2 spots', 'team of 4', 'Any number']);
check('any number never full', [$any->isFull(500), $any->openSpots(3)], [false, null]);
check('part name required', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => ' '], $comp)), 'Give the part a name (up to 80 characters).');
check('spots 0 refused', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => 'X', 'spots' => 0], $comp)), 'Spots must be between 1 and 100, or leave it empty for any number.');
check('competition part has no due date', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => 'X', 'due_date' => '2026-11-24'], $comp)), 'Competition parts don’t have due dates.');
check('project part needs a due date', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 2, 'name' => 'Poster'], $proj)), 'Give each part a due date.');
check('project due after last day refused', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 2, 'name' => 'Poster', 'due_date' => '2026-12-05'], $proj)), 'Pick a due date on or before the project’s last day (Fri, Dec 4).');
check('too many roles', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => 'Video', 'roles' => json_encode(array_fill(0, 11, 'Editor'))], $comp)), 'Up to 10 roles, each up to 40 characters.');
check('shrink below picked', $cul->whyCantResize(2, 3), 'That part already has 3 picked. Un-pick people first.');
check('grow is fine', $cul->whyCantResize(6, 3), null);

$rows = [['id' => 10, 'user_id' => 5, 'status' => 'volunteer', 'volunteered' => 1, 'role' => null],
         ['id' => 11, 'user_id' => 3, 'status' => 'volunteer', 'volunteered' => 1, 'role' => 'Director'],
         ['id' => 12, 'user_id' => 4, 'status' => 'picked', 'volunteered' => 1, 'role' => null]];
$r = new PartRoster($rows);
check('arrival order', array_column($r->volunteers(), 'user_id'), [5, 3]);
check('counts', [$r->pickedCount(), $r->volunteerCount(), $r->statusOf(4), $r->statusOf(5), $r->statusOf(99), $r->roleOf(3)], [1, 2, 'picked', 'volunteer', 'none', 'Director']);
$before = strtotime('2026-11-20 12:00'); $after = strtotime('2026-11-21 08:00');
check('volunteer ok', $r->whyCantVolunteer($comp, 99, true, $before), null);
check('volunteer twice', $r->whyCantVolunteer($comp, 5, true, $before), 'You already volunteered for this part.');
check('volunteer when picked', $r->whyCantVolunteer($comp, 4, true, $before), 'You’re already picked for this part.');
check('alumni', $r->whyCantVolunteer($comp, 99, false, $before), 'Alumni members can’t volunteer.');
check('after deadline', $r->whyCantVolunteer($comp, 99, true, $after), 'Sign-ups for “Founding Anniversary” closed on Fri, Nov 20.');
check('pick a volunteer', $r->whyCantPick($cos, $comp, 5, false), null);
check('pick a stranger in a competition', $r->whyCantPick($cos, $comp, 99, false), 'Pick someone who volunteered.');
$full = new PartRoster([['id' => 1, 'user_id' => 1, 'status' => 'picked', 'volunteered' => 1], ['id' => 2, 'user_id' => 2, 'status' => 'picked', 'volunteered' => 1], ['id' => 3, 'user_id' => 3, 'status' => 'volunteer', 'volunteered' => 1]]);
check('last spot taken', $full->whyCantPick($cos, $comp, 3, false), 'This part is full. Un-pick someone first.');
$poster = ActivityPart::fromRow(['id' => 4, 'activity_id' => 2, 'name' => 'Poster', 'spots' => 1, 'due_date' => '2026-11-28'], $proj);
check('project assigns directly', (new PartRoster([]))->whyCantPick($poster, $proj, 7, true), null);
check('overdue flag', [$poster->toArray('2026-11-29')['overdue'], $poster->toArray('2026-11-28')['overdue']], [true, false]);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `C:\xampp\php\php.exe tests/dsa_test.php`
Expected: FAIL / fatal "Class "ActivityPart" not found"

- [ ] **Step 3: Implement `ActivityPart`, `PartRoster` and the two loaders**
  - Roles are stored as a JSON array of trimmed, non-empty strings.
  - `fromRow` passes `$a->partsNeedDueDates()` and `date('Y-m-d', $a->endTs())` as the last day.
  - `PartRoster` composes an `ApprovalQueue` (volunteers, [DSA: QUEUE]) and a `HashSet` (picked ids, [DSA: HASHSET]), like `ActivityRoster`.
  - Format dates in messages with `date('D, M j', …)`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `C:\xampp\php\php.exe tests/dsa_test.php` → `0 failed`. Also run `php -l` on the new and edited files.

---

### Task 3: The API

**Files:**
- Modify: `api/activities.php` (header doc block lists every new action; GET adds the new fields; new POST cases)
- Test: a scratch script `parts_test.sh` (curl + `php -r` JSON helpers, in the style the previous plan's `announce_test.sh` used). Run it against a **scratch copy** with a fresh seed. It is not committed.

**Interfaces:**
- Consumes: Tasks 1–2 (everything in their Produces).
- Produces the JSON that Tasks 4–5 read.

GET `/api/activities.php` keeps `upcoming`, `past`, `my_attendance`, `kinds`, `can_manage`, `can_record` and `my_status`. Each activity row also gets Task 1's `toArray` fields, plus:
- `parts` (only when `joins_by === 'parts'`): each part is `ActivityPart::toArray()` plus:
  - `picked: [{id, name, role}]`, `volunteer_count`, `open_spots`
  - `me: {status, role, note}`
  - `volunteers: [{id, name, note, role, created_at}]`: **only when `can_manage`**, in arrival order (spec call: members see who's picked and the count only).
- `progress: {done, total}` for projects.

Top-level additions:
- `needs_people: [{activity_id, activity_title, part_id, part_name, open_spots, closes_at}]`
  - Parts with spots and `open_spots > 0` on activities with `signupOpen(now)`.
  - Soonest `closes_at` first (merge sort).
- `my_signups: [{activity_id, activity_title, kind, part_id, part_name, status, role, due_date, overdue, done}]`
  - Every part where `statusOf(me) !== 'none'`, on activities that aren't cancelled or over.
  - Due date first, then start (merge sort).
- `month: {first: 'Y-m-01', days: ['Y-m-d', …]}`: every day of this month covered by a non-cancelled activity (start→end).
- `latest_results: [{activity_id, title, result, parts: [{name, result}]}]`: the past activities that have a result or part results, newest first, at most 3.
- `GET ?mine=1` returns only `{my_signups}`, for Home.

POST actions (all officer actions need `MANAGE_ACTIVITIES` → 403 "Only officers can …" in the existing style):

| action | body | effect / rules |
|---|---|---|
| `create` | existing + `end_date?`, `link?`, `signup_deadline?`, `parts?: [{name, details?, spots?, together?, roles?, due_date?}]` | parts kinds need ≥1 part ("Add at least one part."); activity + parts inserted inside one `Store::begin`/`commit`; `notify_section` "New Competition: “…” · sign up by Fri, Nov 20" (or the existing wording for RSVP kinds) |
| `edit` | `id`, any of title, description, venue, date, end_date, start, end, link, signup_deadline | rebuilds through `Activity::fromRow` so the constructor validates |
| `add_part` / `edit_part` / `remove_part` | `id` + part fields / `part_id` + fields / `part_id` | `edit_part` and `remove_part` use `whyCantResize` (remove = resize to 0 when anyone is picked) |
| `volunteer` | `part_id`, `note?` (≤ 300), `role?` (must be one of the part's roles, Linear Search) | `whyCantVolunteer`; inserts `status='volunteer'`; **no notification** (spec: officers read the list instead) |
| `withdraw` | `part_id` | only while `volunteer`; deletes the row |
| `pick` | `part_id`, `user_id`, `role?` | inside `Store::begin`: re-read this part's rows, rebuild the `PartRoster`, `whyCantPick(direct: false)`, update to `picked`; notify the person "You were picked for “Cosplay Competition” (Founding Anniversary)" + " as Director" |
| `assign` | `part_id`, `user_id`, `role?` | only if `canAssignDirectly()` (else 400 "In a competition, pick from the volunteers."); same transaction rule; inserts `picked`, `volunteered=0` (or updates a volunteer row); notify "You’re on “Poster” for Booth design · due Fri, Nov 28" |
| `unpick` | `part_id`, `user_id` | `volunteered=1` → back to `volunteer`; else delete; notify the person |
| `set_role` | `part_id`, `user_id`, `role` | role must be one of the part's roles or empty |
| `step_down` | `part_id` | the picked person leaves (row deleted); notify `createdBy()` "{name} stepped down from “…”" |
| `mark_done` | `part_id`, `done: bool` | only people picked on it or officers (403 "Only the people on this part can mark it done."); sets/clears `done_at`, `done_by`; notify `createdBy()` when done |
| `set_result` | `id`, `part_id?`, `result` | officers, only after `isOver(now)` (409 "Add the result after the activity ends.") |
| `rsvp` (existing) | | refuse parts kinds: 409 "Volunteer for a part instead." |
| `cancel` (existing) | | also notifies every volunteer and picked person of its parts, once each (HashSet of notified ids) |

- [ ] **Step 1: Write `parts_test.sh`** (scratch). It runs against a fresh-seeded scratch copy (the seed is Task 5's, so for now create the competition with `create`). Assertions, each `ok "<name>" got expected`:
  1. Juan creates the competition with the five parts from the spec and `link=https://www.facebook.com/x` → `ok true`; GET shows `link_host` `facebook.com` and 5 parts.
  2. Kyla creates with `link=javascript:alert(1)` → error "Paste a link that starts with http:// or https://."
  3. Ana volunteers for Cosplay with note "I sew my own costumes" → `ok`. A second time → "You already volunteered for this part."
  4. Ana's GET: Cosplay `me.status` `volunteer`; **no** `volunteers` key on any part. Juan's GET: `volunteers[0].note` `I sew my own costumes`.
  5. Ana tries `pick` → 403.
  6. Juan picks Ana → `ok`; Ana gets one notification containing "You were picked for “Cosplay Competition”".
  7. Two volunteers, then Juan picks both on a 2-spot part where one spot is already taken: the second pick → "This part is full. Un-pick someone first.". The part's picked count is 2 in the DB.
  8. Juan `edit_part` Cosplay `spots=0` while 2 are picked → refused with "Spots must be between 1 and 100, or leave it empty for any number.". `remove_part` with 2 picked → "That part already has 2 picked. Un-pick people first."
  9. A project: Juan `assign`s Paolo to "Poster" → `ok`. Paolo `mark_done` → `ok`, and `progress.done` is 1. Ana `mark_done` on Poster → 403.
  10. In the competition, Juan `assign` → "In a competition, pick from the volunteers."
  11. `rsvp` on the competition → "Volunteer for a part instead."
  12. `cancel` the competition → each volunteer/picked person gets exactly 1 "was cancelled" notification. It no longer appears in `needs_people` or `my_signups`.
  13. `GET ?mine=1` as Paolo → `my_signups` holds only the Poster part, with `done: true`.
  14. Signup closed: set `signup_deadline` to yesterday directly in the scratch DB → Ana volunteering gets "Sign-ups for “…” closed on …".

- [ ] **Step 2: Run it to verify it fails**
Run: `bash parts_test.sh <port> <scratch db path>`
Expected: most checks FAIL ("Unknown action." / missing fields)

- [ ] **Step 3: Implement the GET additions and the POST cases** in `api/activities.php`, using only Task 1–2 methods for decisions.

- [ ] **Step 4: Run it to verify it passes**
Run: `bash parts_test.sh <port> <scratch db path>` → `14 passed, 0 failed`. Also: `tests/dsa_test.php` `0 failed`, the server log is clean, and the DSA grep prints nothing.

---

### Task 4: The Activities page

**Files:**
- Modify: `Frontend/js/activities.js` (cards, sheets, right column, empty state, Past results)
- Modify: `Frontend/activities.html` only if a new container is needed
- Modify: `Frontend/js/ui.js` only for a shared `linkChip(url, host)` helper (also used by Task 5)

**Interfaces:**
- Consumes: Task 3's GET JSON and POST actions (exact names above).
- Produces: `UI.linkChip(url: string, host: string): string`, which returns `<a href="…" target="_blank" rel="noopener noreferrer" class="chip …">{host} ↗</a>` with both values escaped through `esc()`.

What to build (spec "The Activities page" has the sketch):
- [ ] **Step 1: "New activity" form**
  - Kind picker with all five kinds; for parts kinds the Seats field is hidden.
  - Optional **End date**, **Link to the post** and **Sign up by** (date) fields.
  - For a parts kind, a **Parts** builder (add/remove rows): name, details, spots (empty = any number), "Works as one team" checkbox, roles (comma-separated → array), due date (projects only).
  - Projects default the times to 08:00–17:00.
- [ ] **Step 2: Competition/project card**
  - Header: kind · date range (`Nov 25–26`) · "Sign up by Fri, Nov 20" with a Today/in N days chip, or "Sign-ups closed". Then the title, then the `UI.linkChip`.
  - Part rows: name, `spotsLabel` · "N picked · M volunteers", the picked names (+ role).
  - The member's button per part: **Volunteer** → a sheet with note + role select → `volunteer`.
    - *Volunteered — the officers will pick* + **Withdraw**.
    - *Picked · Director* + **Step down**.
  - **Officers:** **Manage** on each part opens a sheet:
    - the volunteers in arrival order, with note, role select, **Pick** / **Un-pick**;
    - for projects, **Assign someone** (member select);
    - **Edit part** / **Remove part**.
  - **Projects:** a progress bar "3 of 5 parts done", each part's due date (red when `overdue`), and **Mark done** / **Not done yet** for the people on it.
- [ ] **Step 3: Right column, above Your attendance**
  1. **Your sign-ups** (`my_signups`; hidden when empty).
  2. **Needs people** (`needs_people`; hidden when empty; tapping scrolls to the card `#a{id}`).
  3. **This month** (`month`): a 7-column mini calendar, dots on `days`, tapping a dot scrolls to the first activity that day.

  **How it counts** lists all five kinds from `kinds[].rule`.
- [ ] **Step 4: Past tab and empty state**
  - **Past tab:** a card with `result` shows a "Result" line (plain text, no emoji), plus the part results.
  - **Empty Upcoming:** `latest_results` (if any), the progress of any open project, and for officers three buttons: *Plan a meeting* / *Post a competition* / *Start a project* (each opens the form with that kind preselected). Members see "Nothing planned yet. When officers post an activity, it shows here."
- [ ] **Step 5: Browser check on a scratch copy** (headless Chrome DevTools driver, as in PROJECT_CONTEXT.md), as Ana (member) and Juan (President), at 360, 390 and 1280 px:
  - the volunteer → picked flow shows the right words;
  - the link opens in a new tab (`target=_blank`, `rel` contains `noopener`);
  - no JS errors and no sideways scroll;
  - the empty state shows the officer starters only for Juan.
  `node --check Frontend/js/activities.js Frontend/js/ui.js`.

---

### Task 5: Home, announcements, search, seed and docs

**Files:**
- Modify: `Frontend/js/home.js`: a "Your sign-ups" card after the dues reminder, from `API.get('activities.php?mine=1')`. Shown only when some item is `picked` and not `done`; one short row per part ("Poster · due Fri, Nov 28" / "Cosplay Competition · Picked"), linking to `activities.html#a{id}`.
- Modify: `backend/domain/Announcement.php`, `backend/services.php` (`announcement_context`):
  - Add `public readonly array $openSpots = []` (activityId => `['open' => int, 'parts' => int]`) to `AnnouncementContext`.
  - In `EventNotice::facts`, for a parts activity: lines "N spots open across M parts" and "Sign up by Fri, Nov 20" (or "Sign-ups closed"); action `['label' => 'Volunteer', 'href' => 'activities.html#a{id}']`. Seats are no longer mentioned.
- Modify: `api/search.php` + `Frontend/js/search.js`: a new `activities` result group. Use the Linear Search over activity titles and part names (`Algorithms::linearSearchByName`, or a loop with `stripos`), limited to non-cancelled ones.
- Modify: `backend/seed.php` (new databases only):
  - "Founding Anniversary", Competition, today+5 → today+6, sign-up deadline today, link `https://www.facebook.com/`, with the five parts from the spec. Cosplay: 2 spots; Culinary: team of 4; dating game: any number; Guess the Song: 2 spots; 90's Remake: 10, roles Director, Editor, Camera, Actor. Ana volunteered for Cosplay, Bea was picked for Culinary.
  - "Booth design", SectionProject, today → today+14, four parts with due dates: Poster (Paolo, done), Booth frame (Leo + Ivy), Logo (Kyla), Flyers (open).
- Modify: `README.md` (features + permissions + DSA map rows), `docs/CLASS_DIAGRAM.md` (Competition, SectionProject, ActivityPart, PartRoster and their relationships), `docs/PROJECT_CONTEXT.md` (Activities description), `docs/NEXT_STEPS.md` (move item 0 into "Done").
- Test: `tests/dsa_test.php`, section `section('Competitions and projects: announcement');`

- [ ] **Step 1: Write the failing test.** Build an `EventNotice` for the seeded-style competition with an `AnnouncementContext` whose `openSpots` is `[1 => ['open' => 7, 'parts' => 5]]`, then:
  `check('event facts for a competition', $n->facts($ctx)['lines'][1] ?? null, '7 spots open across 5 parts');` and `check('volunteer action', $n->facts($ctx)['action']['label'], 'Volunteer');`
  Use the existing announcement tests' context builder as the pattern.
- [ ] **Step 2:** Run `tests/dsa_test.php` → FAIL (the line says seats).
- [ ] **Step 3:** Implement the Home card, `EventNotice` facts, search, seed and docs.
- [ ] **Step 4: Verify**
  - `tests/dsa_test.php` `0 failed`.
  - A fresh scratch seed loads, and Activities shows both seeded items.
  - Home shows "Your sign-ups" for Paolo; the search "booth" finds the project.
  - The DSA grep prints nothing; `php -l` and `node --check` are clean.
  - Do one final browser pass at 360 px and 1280 px.

---

## Order and handoff

Tasks are sequential (each consumes the previous one's interfaces). Ledger:
`.superpowers/sdd/2026-11-20-competitions-and-projects/progress.md`, first line
`# SDD ledger — plan: docs/superpowers/plans/2026-11-20-competitions-and-projects.md`.
When it's done: one fresh whole-branch review, then fix Critical/Important findings with a failing test first. Report Minor findings to the owner. Nothing is committed.
