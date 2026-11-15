<div align="center">

# AcadSync

**A student organization management system for LSPU class sections.**

Membership · Officers · Activities & attendance · Member records · Tasks, schedule & group work

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-storage-003B57?logo=sqlite&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-frontend-06B6D4?logo=tailwindcss&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-vanilla-F7DF1E?logo=javascript&logoColor=black)

</div>

---

## Table of contents

1. [Overview](#overview)
2. [Quick start](#quick-start)
3. [Configuration](#configuration)
4. [Demo accounts](#demo-accounts)
5. [Features](#features)
6. [Object-oriented design](#object-oriented-design)
7. [Data structures and algorithms](#data-structures-and-algorithms)
8. [Reading the database](#reading-the-database)
9. [Project layout](#project-layout)

---

## Overview

AcadSync treats each class section as an organization. Anyone can create a section and lead it as
**President**; classmates join with a class code, take part in activities, and share the class's
tasks, schedule and group work.

| Layer | Built with |
| --- | --- |
| Frontend | HTML, Tailwind CSS, vanilla JavaScript |
| Backend | PHP 8, designed around the **four pillars of OOP** ([`backend/domain/`](backend/domain/)) |
| Data structures | Every structure hand-written in [`backend/custom_dsa.php`](backend/custom_dsa.php) |
| Storage | SQLite |

📐 **Class diagram and OOP pillars:** [docs/CLASS_DIAGRAM.md](docs/CLASS_DIAGRAM.md)
🧮 **Data structures and algorithms:** [see below](#data-structures-and-algorithms)

---

## Quick start

**Requirements:** PHP 8.1+ with the `pdo_sqlite` extension (XAMPP's `php.exe` works).

```bash
# 1. Create your own settings file from the template (once)
copy .env.example .env          # Windows
cp .env.example .env            # Mac / Linux

# 2. Start the server from the project folder, WITH the router
C:\xampp\php\php.exe -S localhost:8000 router.php
```

Then open **<http://localhost:8000>**.

- The database is created on the first request, with demo data unless `.env` says otherwise.
- To start over, delete `data/acadsync.db`.

> [!WARNING]
> **Always start the server with `router.php`.** Without it, PHP's built-in server serves every file
> in the folder, so anyone who can reach your computer could download the database or read `.env`.
> On Apache (XAMPP `htdocs`), the `.htaccess` files do the same job.

---

## Configuration

### Settings (`.env`)

| Key | Default | What it does |
| --- | --- | --- |
| `APP_ENV` | `local` | `production` hides error details from users (they are still written to the PHP error log) |
| `APP_TIMEZONE` | `Asia/Manila` | Timezone for deadlines, activities and notifications |
| `DB_PATH` | `data/acadsync.db` | Location of the SQLite file: relative to the project, or a full path (safest: outside the project) |
| `SEED_DEMO_DATA` | `true` | A brand-new database gets the demo section and accounts. Set `false` for a real class |
| `SHOW_RESET_CODE` | `true` | "Forgot password" shows its code on screen (no e-mail server yet), only to a browser on the same computer |
| `SESSION_SECURE_COOKIE` | `false` | Set `true` once the site runs on HTTPS |

### What never goes to GitHub

`.gitignore` keeps these files on each computer only:

| File | Why |
| --- | --- |
| `.env` | Your personal settings. The team shares **`.env.example`** instead, which has no secrets |
| `data/*`, `*.db`, `*.sqlite` | The database holds every account, password hash and record |
| Logs, backups, `.taste-skill/` | Local output only |

Each teammate runs their own copy with their own database. To share demo data, everyone starts from
the same seed (`SEED_DEMO_DATA=true`) rather than committing a database file.

---

## Demo accounts

🔑 Password for every account: **`password123`**

Log in with the **e-mail**, the **student number** or the **username**. E-mails follow the format
`0000-0000@lspu.edu.ph`, where the part before `@` is the Student ID.

| Account | Role in BSINFO-2A | E-mail | Username |
| --- | --- | --- | --- |
| Juan Dela Cruz | **President** (created the section) | `2023-1045@lspu.edu.ph` | `juan.delacruz` |
| Maria Santos | **Vice President** (also a member of 2B) | `2023-1071@lspu.edu.ph` | `maria.santos` |
| Kyla Lim | **Secretary** | `2023-1064@lspu.edu.ph` | `kyla.lim` |
| Ana Bautista | Member | `2023-1012@lspu.edu.ph` | `ana.bautista` |
| Carlo Domingo | Member, **Inactive** (on leave) | `2023-1051@lspu.edu.ph` | `carlo.domingo` |
| Bea Garcia | President of BSINFO-2B | `2023-2010@lspu.edu.ph` | `bea.garcia` |

| Section | Class code |
| --- | --- |
| BSINFO-2A | **`B2826K`** |
| BSINFO-2B | **`K7Q4PM`** |

> [!NOTE]
> The officers and activities above come from the seed, so they only appear in a **new** database.
> In an older database, the President picks the officers on the **Member Records** page.

---

## Features

### 👥 Membership and officers

- **Sections are organizations.** Whoever creates a section becomes its **President**; classmates join
  with the 6-letter class code and become **Members**. One account can belong to several sections,
  with a different role in each (`memberships`).
- **The President appoints the Vice President and the Secretary** (Member Records → Officers).
  One office per person, and inactive or alumni members can't hold one. Every change is saved as a
  term (`officer_terms`), so a record shows which offices someone held and when.
- **Every officer reviews the approval queue** (approve / reject / undo).

#### Permissions

| Can… | Member | Vice President | Secretary | President |
| --- | :-: | :-: | :-: | :-: |
| Request tasks, join groups, RSVP | ✓ | ✓ | ✓ | ✓ |
| Review the approval queue | | ✓ | ✓ | ✓ |
| Add / cancel activities, see all member records | | ✓ | ✓ | ✓ |
| Take attendance, change a member's status | | | ✓ | ✓ |
| Choose officers, manage subjects, publish tasks directly, auto-group | | | | ✓ |

#### Member status

| Status | Meaning |
| --- | --- |
| **Active** | Everything counts |
| **Inactive** | On leave: may still join events, but absences don't count |
| **Alumni** | Kept in the records; can't RSVP, request tasks or join groups |

### 📅 Activities (meetings, seminars, outreach)

- Officers add activities with a date, time, venue and an optional seat limit.
- **RSVP** reserves a seat. When an activity is full you join its **waitlist**; when someone cancels,
  the first person waiting gets the seat and is notified.
- **Attendance** is taken on an attendance sheet, and each kind of activity has its own rule:

| Kind | Attendance rule |
| --- | --- |
| **Meeting** | Expects every active member |
| **Seminar** | Only people with a reserved seat can check in |
| **Outreach** | Counts volunteers who signed up, and still accepts walk-in helpers |

### 📊 Member records

- Officers see every member ranked by **attendance rate**, filterable by status.
- Every member can open their own record.
- A record shows status, offices held, attendance history (activity by activity), groups and requests.

### ✅ Tasks, schedule and groups

- **Tasks:** Reporting, Quiz, Exam, Activity, Project or a custom label, as individual or group work.
  Members' requests wait in the **approval queue** (at most 3 per person at a time).
- **Group work:** a size range (e.g. 3–4), an optional exact number of groups, and a strict "+1" seat
  only when classmates can't fit otherwise. Groups are shown as seats, and suggested groupmates are
  people you've never worked with.
- **Checklists** on a task, with sub-steps, shared by the whole group.
- **Undo** for leaving a group, cancelling a join request and ticking off a task.
- **Schedule:** a class grid per day, plus Today / Urgent / This week / Due by Fri / Due soon views.
  Saving a subject warns you when its class times clash with another subject.

---

## Object-oriented design

The domain model lives in [`backend/domain/`](backend/domain/). Full UML diagram:
[docs/CLASS_DIAGRAM.md](docs/CLASS_DIAGRAM.md).

| Pillar | Where | In practice |
| --- | --- | --- |
| **Abstraction** | `abstract class Person`, `Officer`, `Task`, `Activity`, `MemberStatus`, `UndoAction`, `DatedDeadline`; `interface Deadline` | Endpoints call what an object *can do* (`can()`, `undo()`, `timestamp()`), never how it does it |
| **Inheritance** | `President` / `VicePresident` / `Secretary` → `Officer` → `Member` → `Person`<br>`Meeting` / `Seminar` / `Outreach` → `Activity`<br>`GroupTask` / `IndividualTask` → `Task`<br>`ActiveStatus` / `InactiveStatus` / `AlumniStatus` → `MemberStatus` | `Officer::permissions()` builds on `parent::permissions()`; officers keep a member's stats |
| **Polymorphism** | `$person->can(Permission::…)`, `$activity->canCheckIn()`, `$activity->expectsAttendanceFrom()`, `$status->canRsvp()`, `$task->acceptsGroups()`, `$action->undo()` | No `role === 'admin'` checks in the endpoints; each object answers for itself |
| **Encapsulation** | Private fields + getters everywhere; `Activity` and `GroupRule` validate in their constructors; `Env` keeps settings private; `readonly` context objects | An invalid activity or group rule can't be built; settings can only be read |

---

## Data structures and algorithms

All structures are written from scratch in [`backend/custom_dsa.php`](backend/custom_dsa.php).

> [!TIP]
> Every place a structure is used is tagged with a comment. **Search the project for `[DSA:`** (and
> `[ALGORITHM:`). In the app, **My Account → How AcadSync works** shows each structure filled with live data.

### Data structures

| Structure | Class | Used for |
| --- | --- | --- |
| **Array** — storing and accessing records | `FixedArray`, `SubjectArray`; 2D `WeekGrid` | A section's subjects (`load_subjects`); the week as 6 days × 288 five-minute cells to catch clashing class times (`subjects.php`) |
| **Linked List** — insertion and deletion | `TaskLinkedList` (doubly linked) | The task timeline in date order, with expired tasks dropped from the head; previous / next task (`tasks.php`) |
| **HashSet** — unique records | `HashSet`, `StudentHashSet` | Unique e-mails, IDs, usernames, section names, class codes and subject codes; one group per task; **who is going and who checked in** at an activity; **one office per person** (`records.php`) |
| **Stack** — undo, history | `UndoStack` | The queue's "undo reject"; students' Undo (`undo.php`), holding `UndoAction` objects |
| **Queue** — waiting lists | `ApprovalQueue` | Task requests reviewed first-come, first-served; **activity waitlists** (the front gets the next seat); auto-grouping |
| **Tree** — hierarchy, tree search | `SubjectTree`, `ChecklistTree` | Section › Subjects › Tasks searched depth-first; checklists rolled up post-order |
| **Graph** — relationships | `DependencyGraph` | Study-group links: My groupmates (BFS), shortest path between classmates, suggested groupmates |

### Algorithms

| Algorithm | Method | Used for |
| --- | --- | --- |
| Insertion Sort | `TaskLinkedList::insertChronologically` | Timeline order |
| Bubble Sort | `Algorithms::bubbleSortByUrgency` | "Urgent" view |
| Selection Sort | `Algorithms::selectionSortByLastName` / `ByStudentId` | Classmate directory, attendance sheet |
| Linear Search | `Algorithms::linearSearchTasks` / `linearSearchByName` | Duplicate-task warning, name search; `Person::can()` |
| Binary Search | `Algorithms::binarySearchByStudentId` / `countDueBy` | Student ID search; Schedule › Due by Fri |
| Merge Sort | `Algorithms::mergeSortBy` | Notifications, activity lists, **member ranking by attendance** |

### SQL is storage only

Every read is `SELECT * FROM <table>`; writes are `INSERT`, plus `UPDATE` / `DELETE` by primary key.
There is **no `ORDER BY`, `WHERE` filtering or `JOIN`** in the application code: sorting, searching
and relating records is done by the structures above.

---

## Reading the database

Open your local `data/acadsync.db` in **DB Browser for SQLite** or the VS Code **SQLite Viewer** extension.

- Every per-section table has a `section_id` column.
- Readable views (filter by the `section` column): `view_section_members`, `view_section_subjects`,
  `view_section_schedule`, `view_section_tasks`, `view_approval_queue`, `view_study_groups`.
  The views are for people only; the PHP code never uses them.
- New columns and tables are added to an existing database automatically on the next request.

---

## Project layout

```
.env.example              settings template (copy to .env, which stays private)
router.php                router for `php -S` (keeps data/, backend/, docs/ and dotfiles private)
.htaccess                 the same protection on Apache
index.php                 redirects to Frontend/index.html
api/                      JSON endpoints
  auth.php                login, register, password reset, account stats
  section.php             join / create / switch / leave sections
  records.php             member records, officers (assign VP / Secretary), member status
  activities.php          activities, RSVP + waitlist, attendance
  schedule.php            timeline, urgency, week grid, due by Friday
  subjects.php            subjects and class times, clash check
  tasks.php, request_task.php, subtasks.php           tasks, requests, checklists
  admin_queue.php, admin_action.php, admin_undo.php   approval queue (officers)
  groups.php, classmates.php, undo.php, search.php, notifications.php, dsa.php
backend/
  Env.php                 reads .env
  domain/                 OOP model: Person, MemberStatus, Task, Deadline, Activity, MemberRecord, UndoAction
  custom_dsa.php          all data structures and algorithms
  services.php            loads one section's rows into the structures and objects
  db.php, schema.sql, seed.php, bootstrap.php
docs/CLASS_DIAGRAM.md     UML class diagram (Mermaid)
Frontend/                 *.html pages, js/, css/, assets/
```
