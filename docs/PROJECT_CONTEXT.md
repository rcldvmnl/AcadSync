# AcadSync: project context (for a new conversation)

Paste or attach this file at the start of a new chat so the assistant knows the project.
Last updated: 2026-11-21 (fourth session: real subjects, motion, officers on Home, subject designs).

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
attendance, competitions and section projects (parts people volunteer for; officers pick), member
records, announcements, dues and payments, and the class's tasks, schedule, groups and checklists.

## Stack and how to run

- Frontend: plain HTML + Tailwind + vanilla JS in `Frontend/` (`js/`, `css/`). Font: Plus Jakarta Sans.
  Tailwind is a **built file**, `Frontend/css/tailwind.css` (Tailwind CLI 3.4.17, theme in `tailwind.config.js`,
  source `Frontend/css/tailwind.src.css`), linked last in each page's `<head>`. After adding a class that no
  page or script used before, run `npm run build:css` (once: `npm install`). No CDN, works offline.
- Backend: PHP 8 (XAMPP `C:\xampp\php\php.exe`, currently 8.5), JSON endpoints in `api/`, SQLite via PDO.
- Run: `copy .env.example .env`, then `C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php`
  from the project root (not `localhost`: on this PC that bound IPv6 `[::1]` only, and the VS Code port
  forward hung on loading). Open http://localhost:8000. Sharing online: README "Sharing it online for a demo";
  `is_local_request()` in `api/auth.php` refuses tunnels (proxy headers or a non-loopback Host) for the reset code. **Always use `router.php`** (it blocks
  `/data`, `/backend`, `/docs`, dotfiles). On Apache, the `.htaccess` files do the same.
- Settings in `.env` (read by `backend/Env.php`): `APP_ENV`, `APP_TIMEZONE`, `DB_PATH`
  (default `data/acadsync.db`), `SEED_DEMO_DATA`, `SHOW_RESET_CODE`, `SESSION_SECURE_COOKIE`, and for e-mailing
  the reset code `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` (ssl | tls | none), `MAIL_USERNAME`, `MAIL_PASSWORD`
  (a Gmail app password), `MAIL_FROM`, `MAIL_FROM_NAME`, `MAIL_TIMEOUT` (see `.env.example`).
- The database is created on the first request and seeded when new (if `SEED_DEMO_DATA=true`).
  New columns/tables reach an existing DB automatically (`Store::addMissingColumns` + `CREATE TABLE IF NOT EXISTS`).
  Do **not** bump `SCHEMA_VERSION` in `db.php`: that renames (backs up) the existing database.

Demo accounts (password `password123`, seeded DB): Juan `juan.delacruz` (President, 2A),
Maria `maria.santos` (Vice President), Kyla `kyla.lim` (Secretary), Marco `marco.cruz` (Treasurer),
Ana `ana.bautista` (Member), Carlo `carlo.domingo` (Inactive), Bea `bea.garcia` (President of 2B),
Leo `leo.navarro` (2B member with a pending request to join 2A). Class codes: 2A `B2826K`, 2B `K7Q4PM`.
User ids in a fresh DB: Juan 1, Maria 2, Paolo 3, Ana 4, Carlo 5, Kyla 6, Marco 7, Bea 8, Leo 9, Ivy 10.
In the owner's older real database, no officers are set until Juan assigns them (Home → Officers, or
Member Records). Since 2026-11-21 it holds 2A's real 8 subjects and schedule and **no 2A tasks** (the owner
adds them). Subjects (code, instructor): GEC 107 Bicomong, ITEC 204 Encanto, ITEC 205 Repalam, ITEP 203 Escote,
ITEL 201 Manaloto, ITEL 202 Andres, SOSLIT Balinsayo, PATHFit 3 Moreno (`bsinfo2a_subjects()` in seed.php).
The seed (new databases only) also has the *Founding Anniversary* competition (5 parts, sign-ups close
today; Ana volunteered for Cosplay, Marco picked for Culinary, Paolo for Guess the Song, Maria
volunteered as Director) and the *Booth design* project (Poster: Paolo, done; Booth frame: Maria + Marco;
Logo: Kyla; Flyers open).

## Layout

```
api/                 JSON endpoints (auth, section, records, activities, schedule, subjects, tasks,
                     request_task, subtasks, admin_queue/action/undo, groups, classmates, undo, search,
                     notifications, dsa, join_requests, announcements, dues)
backend/Env.php      .env loader
backend/Mailer.php   hand-written SMTP client (SSL or STARTTLS, AUTH LOGIN) for the reset code
backend/domain/      OOP model: Person.php, MemberStatus.php, Task.php, Deadline.php, Activity.php,
                     ActivityPart.php (ActivityPart, PartRoster), MemberRecord.php, UndoAction.php,
                     Payment.php (Payment, CashPayment, GcashPayment, Due), Announcement.php
backend/custom_dsa.php   all data structures + algorithms
backend/services.php     loads a section's rows into structures/objects (load_context, member_records, …)
backend/bootstrap.php    session, JSON helpers, current_user/current_member/current_person,
                         require_permission, require_admin (= MANAGE_SECTION), public_user
backend/db.php, schema.sql, seed.php
docs/CLASS_DIAGRAM.md    Mermaid UML for all classes + pillar table (keep it in sync with the code; the fourth
                         session added no classes)
DESIGN.md, PRODUCT.md    visual system (colours, shadows/depth, components, motion) and product record (root)
docs/screenshots/        README screenshots (taken from seed data in a scratch copy)
tests/dsa_test.php       CLI tests for every structure/algorithm (blocked from the browser)
tests/mailer_test.php    CLI tests for Mailer against tests/fake_smtp.php (a one-connection fake SMTP server)
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
- Copy and UI: plain sentence-case language; match the design in `DESIGN.md` (since 2026-11-21: the owner's
  reference education app in an academic theme: white cards on the cool grey ground `#F4F4F7`, periwinkle
  `#A8A3F7`, orange `#F58D42` and yellow `#FFD34E` cards, charcoal cards and nav, SVG sparkle stars and doodle
  lines, hand-drawn SVG academic illustrations in `Frontend/assets/illus/`, Plus Jakarta Sans self-hosted).
  Mint is only for success states. Layouts were not changed by the restyle.

## Frontend extras

- **Motion** (`Motion` at the end of `ui.js`, styles under "motion & interaction" in `app.css`): `API` dispatches
  `acadsync:action` after every non-GET; `Motion.levelFor()` maps it to cap toss / burst / soft ring. Intro once per
  visit (sessionStorage `acadsync.intro`); a milestone right before a page change replays on the next page
  (`acadsync.fx`). Content is visible without JS motion; reduced motion = fades only.
- **Subject designs**: `subjects.design` (NULL = Auto). `SUBJECT_DESIGNS`, `subjectDesign(s)`, `subjectArt()` in `ui.js`;
  the server list is `DESIGNS` in `api/subjects.php` (keep both in sync). The drawing has depth (CSS only:
  `.subject-art` face colour + three stacked 0-blur drop-shadows for the extruded side + a cast shadow, per-tone
  `--art-face` / `--art-side` / `--art-cast`; `.subject-icon` raised disc). Values and rules are in `DESIGN.md`.
- **Admin Home** ends with Officers (`HomeOfficers` in `home.js`, President picks VP / Secretary / Treasurer) and the
  Subjects grid with an *Add subject* tile.

## OOP model (backend/domain)

- `Person` (abstract) → `Member` → `Officer` (abstract) → `VicePresident`, `Secretary`, `Treasurer`, `President`.
  `Person::fromRow` picks the class from `memberships.role` ('admin' = President) and `memberships.position`
  (`vice_president` | `secretary` | `treasurer`; `Person::OFFICES` lists them with labels).
  Permissions: Member = request_task, join_group. Officer adds review_requests, review_members,
  post_announcements, manage_activities, view_member_records, manage_any_group, answer_any_request
  (every officer can fix groups). Secretary adds record_attendance, update_member_records. Treasurer
  adds manage_dues. President adds record_attendance, update_member_records, manage_dues plus
  assign_officers, publish_task, remove_task, manage_section, join_directly, auto_group.
  `can()` is final and also asks the status.
- `MemberStatus` (abstract) → `ActiveStatus`, `InactiveStatus` (absences don't count, can't hold office,
  no dues), `AlumniStatus` (can't RSVP, request tasks or join groups; no dues). `owesDues()` decides who
  is charged. Stored in `memberships.status`.
- `Payment` (abstract) → `CashPayment` (receipt no. optional), `GcashPayment` (13-digit ref, must be
  unique: `referenceIsUnique()`); built by `Payment::create($method, …)`. `Due` (final) holds a fee,
  `balance()` and `standing()` (paid / partial / overdue / unpaid). Money is stored as centavos.
  "I already paid": `PaymentClaim` (final, HAS-A Payment, table `payment_claims`, status pending | approved |
  rejected). Members `claim_payment` / `withdraw_claim` (one waiting claim per due; amount ≤ balance; GCash ref
  unused by payments and other waiting claims); Treasurer/President `confirm_claim` (re-reads payments under the
  write lock, then inserts the payment) or `reject_claim` (optional reason). Shown in `dues.js` (`claimBox`).
- `Announcement` (abstract) → `GeneralNotice`, `PaymentNotice`, `NoClassNotice`, `DeadlineChangeNotice`,
  `TaskNotice`, `EventNotice` (`announcements.kind`; old rows without a kind read as `GeneralNotice`).
  Built by `Announcement::fromInput($kind, $input, AnnouncementContext)`, which validates; `apply()` does the
  real change in the same transaction as the insert (new due / activity, moved deadline, a
  `class_cancellations` row); `onRemove()` (No class: deletes the cancellation). `requiredPermission()`
  per choice (Deadline change → publish_task, new due → manage_dues, new activity → manage_activities).
  Shared checks: `validate_deadline()` and `validate_new_activity()` in services.php (also used by
  request_task.php and activities.php). Schedule reads cancellations through `class_cancellations()` (HashSet).
  Spec: docs/superpowers/specs/2026-11-20-typed-announcements-design.md.
- `Task` → `IndividualTask`, `GroupTask` (has a `GroupRule`); `StudyGroup`.
  `GroupRule` (abstract) is how the prof set the groups up, one subclass each (`tasks.group_mode`):
  `AssignedGroups` (`assigned`: the prof's list in `tasks.group_plan` JSON; students can't change it),
  `FixedGroupCount` (`count`: 33 in 5 → 3×7 + 2×6), `FixedGroupSize` (`size`: 4 per group, leftovers
  join as +1 or make a smaller group), `FixedSizeAndCount` (`size_count`: 8×4, extras take +1).
  `seats()` gives base size / extra seats / fixed count; `explain()` the sentence shown on the page;
  `evaluate()`, `capacityOf()`, `autoPlan()` are shared. Members per group is stored in group_max
  (= group_min); tasks saved before group_mode existed are read by `GroupRule::fromRow` (count+strict
  size → both, count → number of groups, size → per group). `sync_task_groups()` (services.php) makes
  the groups when a task is published or approved (empty slots when the count is known); emptied
  groups stay when the count is fixed. Empty groups are joined directly (first in = leader).
- `Deadline` interface → `DatedDeadline` → `ExactDeadline`/`RangeDeadline`, plus `NoDeadline`
  (`opensAt()` / `attentionAt()`: an open date range counts as today on the schedule).
  The due time may have a length (`tasks.deadline_until`, "How long" chips `createLengthChips` in `ui.js`:
  No length / 1 / 2 / 4 hours / Until…): label "9:00 AM – 11:00 AM", and the task is due at the end time.
  Same day only (`validate_deadline` refuses an end that isn't after the start). `Fmt.dueTime(t)` words it.
- `Activity` (abstract) → `RsvpActivity` (abstract) → `Meeting` (everyone expected, anyone checked in),
  `Seminar` (seat needed), `Outreach` (volunteers expected, walk-ins allowed), `SectionEvent` (kind `event`:
  open to everyone, anyone checked in, never counts against anyone); and `PartsActivity`
  (abstract, no attendance at all) → `Competition` (volunteer → officers pick) and `SectionProject`
  (parts with due dates; officers may assign directly; parts are marked done). Every activity has an
  optional `link` (http/https only, ≤ 500 chars, shown by host in a new tab with `rel="noopener noreferrer"`),
  `signup_deadline` (open until 23:59:59 that day, on or before the first day), `result`, and may span
  several days. Polymorphic: `joinsBy()` ('rsvp' | 'parts'), `usesSeats()`, `canAssignDirectly()`,
  `partsNeedDueDates()`. `ActivityPart` (domain/ActivityPart.php, table `activity_parts`) validates
  itself (name, 1–100 spots or any number, ≤ 10 roles, due date only for projects); `PartRoster`
  (table `part_members`, status volunteer | picked) = `ApprovalQueue` of volunteers + `HashSet` of picked,
  `whyCantVolunteer()` / `whyCantPick()`. Loaders `load_parts()` / `part_rosters()` in services.php.
  `api/activities.php` has the actions create (with parts), edit, add/edit/remove_part, volunteer, withdraw,
  pick, assign, unpick, set_role, step_down, mark_done, set_result; picks re-read the part inside a
  transaction. The Event announcement can point at a competition (facts: "N spots open across M parts")
  but only creates RSVP kinds. `ActivityRoster` = HashSets (going, checked-in)
  + `ApprovalQueue` waitlist. `MemberRecord` computes attendance from finished activities.
  Spec: docs/superpowers/specs/2026-11-20-competitions-and-projects-design.md.
- **Cancelled activities:** hidden in the page unless *Show cancelled* (localStorage `acadsync.showCancelled`);
  the GET sends cancelled ones that ended over 3 days ago apart, in `old_cancelled` (`HIDE_CANCELLED_AFTER`); the page lists
  them under Past in a closed *N older cancelled activities* box when *Show cancelled* is on;
  `action=delete` (officers, cancelled only) removes the activity with its part_members, activity_parts,
  activity_rsvps, attendance and activity_invites in one transaction.
- **Date range:** the form has *Several days* → From/To (`date`, `end_date`); one day sends `end_date = date`.
- **Who can join:** `activities.audience` (`everyone` | `invited`) + table `activity_invites`. `Activity` holds the
  invite list in a HashSet; `isFor(user id)` is asked by RSVP, volunteer, pick/assign, check-in, Needs people,
  `MemberRecord` (not invited = never expected) and `EventNotice::notifies(member, context)` (asks the live activity).
  `create`/`edit` take `audience` + `invited` (checked against the section members; on edit, people already invited
  may stay even if they left). Edit sends them only when the picker was touched. When an edit makes the list
  narrower, the people left out lose their RSVP / waitlist place / part (and are told) and
  `ActivityRoster::seatsToFill()` gives the freed seats to the invited people waiting; `cancel_rsvp` uses
  `nextInLineFor()` and drops anyone waiting who isn't invited. A stored invite-only row with no invite rows
  still loads (`Activity::fromRow($row, stored: true)`: nobody can join until an officer picks people).
  The Event announcement always makes its new activity for everyone (the composer has no picker).
  `PartsActivity`'s constructor drops any seat limit, so competitions/projects are built once.
- `UndoAction` (abstract) → `ToggleDoneAction`, `RejoinGroupAction`, `RequestAgainAction`; stored in
  the session and rebuilt into an `UndoStack`. `Env` is a final static settings class.

## DSA map (short)

Array: `SubjectArray`, 2D `WeekGrid` (class-time clash check). Linked list: doubly linked
`TaskLinkedList` (timeline, prev/next task). HashSet: unique emails/IDs/codes, one group per task,
unique group names per task, check-ins, one office per person, GCash refs used once, who paid a due,
who is picked for a part, one "cancelled" notification per person, the month's activity days.
Stack: queue "undo reject" (Undo = pop the latest; See all → undo one with `UndoStack::removeWhere`, which pops the ones above onto a holding stack and pushes them back), student Undo. Queue: approval queue, join requests (`build_join_queue`,
only the front can be decided), activity waitlist, volunteers for a part (`PartRoster`), auto-grouping.
Tree: `SubjectTree` (DFS search),
`ChecklistTree` (post-order rollup). Graph: `DependencyGraph` (edges tagged with group id; BFS
groupmates, shortest path, suggestions). Merge Sort: notifications, activity lists, member attendance
ranking, announcements (by id, then stable by pinned), dues by date, parts by position, Needs people,
Your sign-ups. Linear search: `linearSearch` /
`linearContains` for "is this id in the group" and "is this role one of the part's"; search over
activity titles and part names. Binary search: student ID, "Due by Fri".
"How it works" (`dsa.html`) shows them live.

## Security state

- `.gitignore` excludes `.env`, `data/*` (except `data/.htaccess`), all `*.db`/`*.sqlite`, logs, `.taste-skill/`.
- The 3 DB files and `.taste-skill/` were removed from git tracking (files kept on disk).
- **History purged on 2026-11-21:** the 3 DB files and `.taste-skill/` were removed from every commit with
  `git filter-branch` and `main` was force-pushed (`154f12a` → `b141196`; same final code; 24 commits, the
  "v2" commit that only deleted those files dropped out). Teammates must re-clone. GitHub can keep old commits
  reachable by SHA for a while; for a full purge ask GitHub Support to clear cached views. Change any real
  passwords that were in the old database.
- Password reset: the code is e-mailed when `MAIL_*` is set (`Mailer`); it is shown on screen only when
  `SHOW_RESET_CODE=true` AND the browser is on the server's computer; with neither, `forgot` answers 503
  ("isn't set up yet"); a failed send closes the code (502). 5 wrong tries close a code.
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
- Scratch test scripts from the last session (not committed, git-ignored, may be gone) are in
  `.superpowers/sdd/2026-11-20-competitions-and-projects/`: `parts_test.php` (API, 81 checks; run with
  `run_api.sh`, which needs a scratch server on port 8123) and headless-browser checks (`*_check.mjs`, scratch
  server with a `/login-as` router on port 8124). The plan ledger `progress.md` there lists every ruling made.
  The 2026-11-21 scripts are in `.superpowers/sdd/2026-11-21-review-fixes/` (see the comments in its `run.sh`).
- Edit files with the editor tools. Never pipe a script's output over a project file (`cmd > tmp; cp tmp file`):
  if the script fails, the file is emptied.

## Open items / ideas

The current to-do list is in [NEXT_STEPS.md](NEXT_STEPS.md): start with its **Recommended next steps**
(commit the uncommitted fourth session, decide the demo seed's 2A tasks, record the demo on a separate
`DB_PATH=data/demo.db`, retake screenshots, test on a real phone). What remains after that needs the team
(README names and links) or the owner (a Gmail app password in `.env`). The items below are older notes.

- Phone nav: members Home, Schedule, Classmates | + | Groups, Activities, Account; officers Home, Schedule,
  Queue | + | Members, Groups, Activities, with My account from the avatar in the phone header
  (`Shell.accountInHeader()`; the desktop rail has the avatar for everyone).
- 2026-11-21 restyle: the sand ground and the yellow nav glow are gone (the old `cream-palette` / `dark-glow`
  exceptions in `.impeccable/config.json` no longer match anything). The active nav item is a periwinkle
  rounded square; the + is a white disc. `PRODUCT.md` holds the product record, `DESIGN.md` the visual system,
  `.impeccable/surfaces/frontend-home-html.md` the direction contract.
- Text colours meet WCAG AA: `muted` `#5E5E68` (secondary text, ≥ 4.5:1 on white, the grey ground and every
  tint, never on the strong periwinkle/orange/yellow fills), `hint` `#666670` (placeholders), `success`
  `#23774F`, `danger` `#C8402F`. `subtle` `#A9A9B4` is only for icons and borders, never text.
  `impeccable detect --changed` reported 0 findings after the restyle.
- Fonts: Plus Jakarta Sans is self-hosted (`Frontend/fonts/`, OFL licence beside it, `@font-face` in `app.css`);
  no page loads Google Fonts any more, so the app works fully offline.
- Dues/payments, the join approval queue and announcements were added on 2026-11-20.
- README still needs the team members and the YouTube / Canva links (placeholders are there).
