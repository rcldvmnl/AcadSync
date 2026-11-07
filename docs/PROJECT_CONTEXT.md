# AcadSync: project context (for a new conversation)

Paste or attach this file at the start of a new chat so the assistant knows the project.
Last updated: 2026-11-06.

## What it is

AcadSync is a school project for LSPU (BSINFO-2A). It combines two final requirements:

- **OOP final project, Scenario 1: Student Organization Management System.** It must show the four
  pillars (encapsulation, inheritance, polymorphism, abstraction), real classes/constructors/access
  modifiers, meaningful relationships, and a UML class diagram that matches the code.
  Groups are exactly 3 members; deliverables are a YouTube demo, Canva slides, GitHub repo and docs.
- **DSA project:** 7 hand-written data structures (Array, Linked List, HashSet, Stack, Queue, Tree,
  Graph) and the algorithms Insertion, Bubble, Selection and Merge Sort, plus Linear and Binary Search.

A **section** is the organization. Its creator is the **President**, who picks a **Vice President**
and a **Secretary**; everyone else is a **Member**. The app covers membership, officers, activities
with attendance, member records, and the class's tasks, schedule, groups and checklists.

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
Maria `maria.santos` (Vice President), Kyla `kyla.lim` (Secretary), Ana `ana.bautista` (Member),
Carlo `carlo.domingo` (Inactive), Bea `bea.garcia` (President of 2B). Class codes: 2A `B2826K`, 2B `K7Q4PM`.
In the owner's older real database, no VP/Secretary is set until Juan assigns them on Member Records.

## Layout

```
api/                 JSON endpoints (auth, section, records, activities, schedule, subjects, tasks,
                     request_task, subtasks, admin_queue/action/undo, groups, classmates, undo, search,
                     notifications, dsa)
backend/Env.php      .env loader
backend/domain/      OOP model: Person.php, MemberStatus.php, Task.php, Deadline.php, Activity.php,
                     MemberRecord.php, UndoAction.php
backend/custom_dsa.php   all data structures + algorithms
backend/services.php     loads a section's rows into structures/objects (load_context, member_records, …)
backend/bootstrap.php    session, JSON helpers, current_user/current_member/current_person,
                         require_permission, require_admin (= MANAGE_SECTION), public_user
backend/db.php, schema.sql, seed.php
docs/CLASS_DIAGRAM.md    Mermaid UML for all classes + pillar table (keep it in sync with the code)
Frontend/                pages incl. activities.html, records.html, classmates.html, task.html, dsa.html
router.php, .htaccess, .env.example, .gitignore
```

## Rules the code follows (keep them)

- **DSA rule:** reads are only `SELECT * FROM table` (`Store::all`); filtering in PHP loops; sorting
  and searching with `custom_dsa.php`. Writes are INSERT, and UPDATE/DELETE by primary key. No
  `ORDER BY`/`WHERE`/`JOIN` in app code; no PHP `sort`/`usort`/`array_search` for processing.
  Tag uses with `// [DSA: …]` / `// [ALGORITHM: …]` comments; OOP spots with `// [OOP: …]`.
- **Permissions, not roles:** endpoints call `Person::fromRow($user)->can(Permission::X)`; never
  `role === 'admin'`. The frontend uses `Shell.can('permission')` (list sent by `auth.php`).
- Every endpoint is section-scoped (`section_rows`, `load_context`); never trust ids across sections.
- Foreign keys are ON. Delete a group with `delete_group()` (removes requests/members first).
- Copy and UI: plain sentence-case language; match the existing design (white rounded cards on the
  sand ground `#F6F3EE`, pastel tints lavender/peach/sun/mint, charcoal buttons, Plus Jakarta Sans).

## OOP model (backend/domain)

- `Person` (abstract) → `Member` → `Officer` (abstract) → `VicePresident`, `Secretary`, `President`.
  `Person::fromRow` picks the class from `memberships.role` ('admin' = President) and `memberships.position`.
  Permissions: Member = request_task, join_group. Officer adds review_requests, manage_activities,
  view_member_records. Secretary adds record_attendance, update_member_records. President adds those
  plus assign_officers, publish_task, remove_task, manage_section, manage_any_group,
  answer_any_request, join_directly, auto_group. `can()` is final and also asks the status.
- `MemberStatus` (abstract) → `ActiveStatus`, `InactiveStatus` (absences don't count, can't hold office),
  `AlumniStatus` (can't RSVP, request tasks or join groups). Stored in `memberships.status`.
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
check-ins, one office per person. Stack: queue "undo reject", student Undo. Queue: approval queue,
activity waitlist, auto-grouping. Tree: `SubjectTree` (DFS search), `ChecklistTree` (post-order rollup).
Graph: `DependencyGraph` (edges tagged with group id; BFS groupmates, shortest path, suggestions).
Merge Sort: notifications, activity lists, member attendance ranking. Binary search: student ID,
"Due by Fri". "How it works" (`dsa.html`) shows them live.

## Security state

- `.gitignore` excludes `.env`, `data/*` (except `data/.htaccess`), all `*.db`/`*.sqlite`, logs, `.taste-skill/`.
- The 3 DB files and `.taste-skill/` were removed from git tracking (files kept on disk).
- **Open issue:** the old database is still in earlier commits on GitHub (`origin/main`,
  https://github.com/rcldvmnl/AcadSync). Purging needs a history rewrite + force-push; not done.
- Password reset shows its code only to a browser on the same machine (`SHOW_RESET_CODE`), 5 tries max.
- `APP_ENV=production` hides error details from users.

## Testing approach that worked

- Never test against the real `data/acadsync.db`: copy `api/ backend/ Frontend/ router.php .env` and the
  DB into a scratch folder and run `php -S` there (with `-d log_errors=1 -d error_log=…` to catch warnings).
- For headless-browser checks, a scratch-only router that sets `$_SESSION['user_id']` from `?as=<id>`
  and then `require`s `router.php` (never put this in the project).
- `php -l` every PHP file and `node --check` every JS file after edits.

## Open items / ideas

- Decide whether to purge the old DB from GitHub history.
- The yellow glow on the active nav item (`app.css`) is flagged by the design checker; kept on purpose
  for now. Cream background on activities/records has a recorded exception in `.impeccable/config.json`.
- Possible next features: dues/payments (Treasurer, `abstract Payment`), membership approval queue
  for joining with the class code, announcements.
- Docs still to write for the OOP submission: Project Overview and Objectives.
