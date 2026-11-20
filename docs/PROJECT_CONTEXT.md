# AcadSync: project context (for a new conversation)

Paste or attach this file at the start of a new chat so the assistant knows the project.
Last updated: 2026-11-20.

## What it is

AcadSync is a school project for LSPU (BSINFO-2A). It combines two final requirements:

- **OOP final project, Scenario 1: Student Organization Management System.** It must show the four
  pillars (encapsulation, inheritance, polymorphism, abstraction), real classes/constructors/access
  modifiers, meaningful relationships, and a UML class diagram that matches the code.
  Groups are exactly 3 members; deliverables are a YouTube demo, Canva slides, GitHub repo and docs.
- **DSA project:** 7 hand-written data structures (Array, Linked List, HashSet, Stack, Queue, Tree,
  Graph) and the algorithms Insertion, Bubble, Selection and Merge Sort, plus Linear and Binary Search.

A **section** is the organization. Its creator is the **President**, who picks a **Vice President**,
a **Secretary** and a **Treasurer**; everyone else is a **Member**. Classmates enter the class code to
ask to join and an officer lets them in. The app covers membership, officers, activities with
attendance, member records, announcements, dues and payments, and the class's tasks, schedule,
groups and checklists.

## Stack and how to run

- Frontend: plain HTML + Tailwind (CDN) + vanilla JS in `Frontend/` (`js/`, `css/`). Font: Plus Jakarta Sans.
- Backend: PHP 8 (XAMPP `C:\xampp\php\php.exe`, currently 8.5), JSON endpoints in `api/`, SQLite via PDO.
- Run: `copy .env.example .env`, then `C:\xampp\php\php.exe -S localhost:8000 router.php`
  from the project root. Open http://localhost:8000. **Always use `router.php`** (it blocks
  `/data`, `/backend`, `/docs`, dotfiles). On Apache, the `.htaccess` files do the same.
- Settings in `.env` (read by `backend/Env.php`): `APP_ENV`, `APP_TIMEZONE`, `DB_PATH`
  (default `data/acadsync.db`), `SEED_DEMO_DATA`, `SHOW_RESET_CODE`, `SESSION_SECURE_COOKIE`.
- The database is created on the first request and seeded when new (if `SEED_DEMO_DATA=true`).
  New columns/tables reach an existing DB automatically (`Store::addMissingColumns` + `CREATE TABLE IF NOT EXISTS`).
  Do **not** bump `SCHEMA_VERSION` in `db.php`: that renames (backs up) the existing database.

Demo accounts (password `password123`, seeded DB): Juan `juan.delacruz` (President, 2A),
Maria `maria.santos` (Vice President), Kyla `kyla.lim` (Secretary), Marco `marco.cruz` (Treasurer),
Ana `ana.bautista` (Member), Carlo `carlo.domingo` (Inactive), Bea `bea.garcia` (President of 2B),
Leo `leo.navarro` (2B member with a pending request to join 2A). Class codes: 2A `B2826K`, 2B `K7Q4PM`.
User ids in a fresh DB: Juan 1, Maria 2, Paolo 3, Ana 4, Carlo 5, Kyla 6, Marco 7, Bea 8, Leo 9, Ivy 10.
In the owner's older real database, no officers are set until Juan assigns them on Member Records,
and there are no dues, announcements or join requests yet.

## Layout

```
api/                 JSON endpoints (auth, section, records, activities, schedule, subjects, tasks,
                     request_task, subtasks, admin_queue/action/undo, groups, classmates, undo, search,
                     notifications, dsa, join_requests, announcements, dues)
backend/Env.php      .env loader
backend/domain/      OOP model: Person.php, MemberStatus.php, Task.php, Deadline.php, Activity.php,
                     MemberRecord.php, UndoAction.php, Payment.php (Payment, CashPayment, GcashPayment, Due)
backend/custom_dsa.php   all data structures + algorithms
backend/services.php     loads a section's rows into structures/objects (load_context, member_records, …)
backend/bootstrap.php    session, JSON helpers, current_user/current_member/current_person,
                         require_permission, require_admin (= MANAGE_SECTION), public_user
backend/db.php, schema.sql, seed.php
docs/CLASS_DIAGRAM.md    Mermaid UML for all classes + pillar table (keep it in sync with the code)
docs/screenshots/        README screenshots (taken from seed data in a scratch copy)
tests/dsa_test.php       CLI tests for every structure/algorithm (blocked from the browser)
Frontend/                pages incl. activities.html, records.html, classmates.html, task.html, dsa.html, dues.html
router.php, .htaccess, .env.example, .gitignore
```

## Rules the code follows (keep them)

- **DSA rule:** reads are only `SELECT * FROM table` (`Store::all`); filtering in PHP loops; sorting
  and searching with `custom_dsa.php`. Writes are INSERT, and UPDATE/DELETE by primary key. No
  `ORDER BY`/`WHERE`/`JOIN` in app code; no PHP `sort`/`usort`/`array_search`/`in_array`/`array_filter`
  for processing (use `Algorithms::linearSearch`/`linearContains` or a `HashSet`). Check with
  `grep -rnE "\b(in_array|array_search|array_filter|usort|sort)\(" api backend` (should print nothing).
  Tag uses with `// [DSA: …]` / `// [ALGORITHM: …]` comments; OOP spots with `// [OOP: …]`.
- **Permissions, not roles:** endpoints call `Person::fromRow($user)->can(Permission::X)`; never
  `role === 'admin'`. The frontend uses `Shell.can('permission')` (list sent by `auth.php`).
- Every endpoint is section-scoped (`section_rows`, `load_context`); never trust ids across sections.
- Foreign keys are ON. Delete a group with `delete_group()` (removes requests/members first).
- Copy and UI: plain sentence-case language; match the existing design (white rounded cards on the
  sand ground `#F6F3EE`, pastel tints lavender/peach/sun/mint, charcoal buttons, Plus Jakarta Sans).

## OOP model (backend/domain)

- `Person` (abstract) → `Member` → `Officer` (abstract) → `VicePresident`, `Secretary`, `Treasurer`, `President`.
  `Person::fromRow` picks the class from `memberships.role` ('admin' = President) and `memberships.position`
  (`vice_president` | `secretary` | `treasurer`; `Person::OFFICES` lists them with labels).
  Permissions: Member = request_task, join_group. Officer adds review_requests, review_members,
  post_announcements, manage_activities, view_member_records. Secretary adds record_attendance,
  update_member_records. Treasurer adds manage_dues. President adds record_attendance,
  update_member_records, manage_dues plus assign_officers, publish_task, remove_task, manage_section,
  manage_any_group, answer_any_request, join_directly, auto_group. `can()` is final and also asks the status.
- `MemberStatus` (abstract) → `ActiveStatus`, `InactiveStatus` (absences don't count, can't hold office,
  no dues), `AlumniStatus` (can't RSVP, request tasks or join groups; no dues). `owesDues()` decides who
  is charged. Stored in `memberships.status`.
- `Payment` (abstract) → `CashPayment` (receipt no. optional), `GcashPayment` (13-digit ref, must be
  unique: `referenceIsUnique()`); built by `Payment::create($method, …)`. `Due` (final) holds a fee,
  `balance()` and `standing()` (paid / partial / overdue / unpaid). Money is stored as centavos.
- `Task` → `IndividualTask`, `GroupTask` (has `GroupRule`: min/max/exact count, strict "+1" seat);
  `StudyGroup`. `Deadline` interface → `DatedDeadline` → `ExactDeadline`/`RangeDeadline`, plus `NoDeadline`.
- `Activity` (abstract) → `Meeting` (everyone expected, anyone checked in), `Seminar` (seat needed),
  `Outreach` (volunteers expected, walk-ins allowed). `ActivityRoster` = HashSets (going, checked-in)
  + `ApprovalQueue` waitlist. `MemberRecord` computes attendance from finished activities.
- `UndoAction` (abstract) → `ToggleDoneAction`, `RejoinGroupAction`, `RequestAgainAction`; stored in
  the session and rebuilt into an `UndoStack`. `Env` is a final static settings class.

## DSA map (short)

Array: `SubjectArray`, 2D `WeekGrid` (class-time clash check). Linked list: doubly linked
`TaskLinkedList` (timeline, prev/next task). HashSet: unique emails/IDs/codes, one group per task,
unique group names per task, check-ins, one office per person, GCash refs used once, who paid a due.
Stack: queue "undo reject", student Undo. Queue: approval queue, join requests (`build_join_queue`,
only the front can be decided), activity waitlist, auto-grouping. Tree: `SubjectTree` (DFS search),
`ChecklistTree` (post-order rollup). Graph: `DependencyGraph` (edges tagged with group id; BFS
groupmates, shortest path, suggestions). Merge Sort: notifications, activity lists, member attendance
ranking, announcements (by id, then stable by pinned), dues by date. Linear search: `linearSearch` /
`linearContains` for "is this id in the group". Binary search: student ID, "Due by Fri".
"How it works" (`dsa.html`) shows them live.

## Security state

- `.gitignore` excludes `.env`, `data/*` (except `data/.htaccess`), all `*.db`/`*.sqlite`, logs, `.taste-skill/`.
- The 3 DB files and `.taste-skill/` were removed from git tracking (files kept on disk).
- **Open issue:** the old database is still in earlier commits on GitHub (`origin/main`,
  https://github.com/rcldvmnl/AcadSync). Purging needs a history rewrite + force-push; not done.
- Password reset shows its code only to a browser on the same machine (`SHOW_RESET_CODE`), 5 tries max.
- `APP_ENV=production` hides error details from users.

## Testing approach that worked

- Never test against the real `data/acadsync.db`: copy `api/ backend/ Frontend/ router.php .env` and the
  DB into a scratch folder and run `php -S` there (with `-d log_errors=1 -d error_log=…` to catch
  warnings; the log path must not contain `~`, or PHP rejects the setting).
- API checks: `curl` with a cookie jar per user after `{"action":"login","login":"<username>","password":"password123"}`.
- Headless-browser checks: Chrome (`C:/Program Files/Google/Chrome/Application/chrome.exe`) driven
  over the DevTools protocol from a small Node script (Node 24 has `WebSocket` built in), with a
  scratch-only router whose `/login-as?as=<id>&to=<page>` sets `$_SESSION['user_id']` and redirects,
  then `require`s `router.php` (never put this in the project). Give Chrome ~30 s to start and an
  absolute `--user-data-dir`.
- `php tests/dsa_test.php` (must say 0 failed), `php -l` every PHP file and `node --check` every JS
  file after edits.
- Edit files with the editor tools. Never pipe a script's output over a project file (`cmd > tmp; cp tmp file`):
  if the script fails, the file is emptied.

## Open items / ideas

The current to-do list (fixes and features, checked against the README) is in
[NEXT_STEPS.md](NEXT_STEPS.md). The items below are older notes.

- Decide whether to purge the old DB from GitHub history.
- The yellow glow on the active nav item (`app.css`) is flagged by the design checker; kept on purpose
  for now. Cream background on activities/records has a recorded exception in `.impeccable/config.json`.
- Dues/payments, the join approval queue and announcements were added on 2026-11-20.
- README still needs the team members and the YouTube / Canva links (placeholders are there).
