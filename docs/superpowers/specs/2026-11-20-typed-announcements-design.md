# Typed announcements — design

Date: 2026-11-20 · Status: **approved** by the owner · Approach chosen: **A (one class per type)**
· "Task" type confirmed as *announce an existing task* (creating tasks stays on Add Task)

## 1. Intent

Announcements on Home are hard to read: every post is the same yellow box with a title and some text,
and nothing says *when* something happens. Officers mostly announce a few recurring things: a
payment, a class that won't happen, a task whose deadline moved, a new task, an event. The owner wants:

- the Announcements section **easier to read** at a glance;
- a **type** chosen when posting: Payment, No class, Deadline change, Task, Event (plus a plain notice);
- **upcoming, dated things get more focus** than old news.

The owner decided that a typed announcement **does the real thing**, so the app never says two
different things:

- *Deadline change* moves the task's deadline (schedule, Urgent, cards all update).
- *No class* marks that day's class (or whole day) as cancelled on the Schedule.
- *Payment* and *Event* point at a real due / activity, and can create one on the spot.

## 2. Goals, non-goals, anti-goals

**Goals**
1. One look tells a member: what kind of news it is, when it happens, and what to do (pay, RSVP, open task).
2. Posting a typed announcement and making the real change happen together, in one server request,
   or not at all.
3. Dated announcements sort by soonest first with "in 3 days"; once the date passes they fold into "Earlier".
4. Built as an OOP class family (abstraction, inheritance, polymorphism, encapsulation) that the
   endpoints use without `if kind === …` checks, consistent with `GroupRule`, `Payment`, `MemberStatus`.

**Non-goals (this round)**
- Editing an announcement after posting (remove and post again).
- Scheduled / future posting, e-mail, comments or reactions.
- A separate Announcements page (Home keeps the list; "Show all" expands it).
- Inline RSVP or paying from the card (the card links to Activities / Dues).

**Anti-goals (would be a failure even if it "works")**
- An announcement that disagrees with the schedule, a due or an activity.
- Offering a type the poster isn't allowed to use (e.g. a Secretary seeing "Deadline change", which
  only the President can do today), then failing on submit.
- Home turning into a wall of text: long messages must be clamped.

## 3. Constraints (from PROJECT_CONTEXT.md)

- DSA rule: reads are `SELECT *` (`Store::all` / `section_rows`), filtering in PHP loops, sorting with
  `custom_dsa.php`; no `ORDER BY`/`WHERE`/`JOIN`; no `in_array`/`array_filter`/`usort`. Tag uses with
  `// [DSA: …]`, `// [ALGORITHM: …]`, `// [OOP: …]`.
- Permissions, not roles: `Person::can(Permission::X)`; frontend `Shell.can('x')`.
- Section-scoped; never trust ids across sections. Foreign keys are on.
- New columns via `Store::addMissingColumns`, new tables via `CREATE TABLE IF NOT EXISTS`;
  do **not** bump `SCHEMA_VERSION`.
- Design language: white rounded cards on sand `#F6F3EE`, pastel tints (lavender/peach/sun/mint),
  charcoal buttons, Plus Jakarta Sans, plain sentence-case copy. Works at 360 px.
- PHP 8.1+, vanilla JS, no new dependencies.

## 4. Approaches considered

| | Approach | Trade-off | Verdict |
| --- | --- | --- | --- |
| **A** | `abstract class Announcement` with one subclass per type; each validates its input, performs its real action, words its card and gives its date | Most code up front; one place per type; matches the project's OOP model; action + post in one transaction | **Chosen** |
| B | `kind` column + `if/else` in endpoint and page | Least code first; breaks "objects answer for themselves"; grows messy per type | Rejected |
| C | Page calls existing endpoints (dues/activities/tasks), then posts a plain announcement | Reuses endpoints as-is; two requests can half-fail (deadline moved, no notice); reading back is awkward | Rejected |

## 5. The types

| Type (`kind`) | Poster also needs | Fields | Real action (`apply`) | The card shows | Sort date |
| --- | --- | --- | --- | --- | --- |
| Notice (`general`) | — | title, message | none | title, message | none (newest first) |
| Payment (`payment`) | `MANAGE_DUES` to create a due; any officer may announce an **existing** open due | pick an open due **or** new: title, amount, due date | create the due if new (same checks as `dues.php` / `Due`) | amount, due date, "View dues" | due date |
| No class (`no_class`) | — (any officer) | date, scope: whole day **or** one subject that meets that weekday | add `class_cancellations` row | "No class · Fri, Nov 27 · ITEC 204, 1:00–2:30 PM" (or "all classes") | that date |
| Deadline change (`deadline_change`) | `PUBLISH_TASK` (President) | pick an open task; new deadline (exact / range, time) | update the task's deadline (same checks as the task form) | "Reporting — Ch. 3 · Nov 23 → Nov 26, 5:00 PM", "Open task" | new deadline |
| Task (`task`) | — | pick an upcoming task | none (announces it) | task, subject, deadline, "Open task" | its deadline |
| Event (`event`) | `MANAGE_ACTIVITIES` (all officers) | pick an upcoming activity **or** new: kind, title, date, start, end, venue, seats | create the activity if new (same checks as `Activity`) | date, time, venue, seats left, "RSVP" (link) | start time |

Title: required for Notice; for the other types it is **optional** and a default is written from
the type (e.g. "No class on Friday", "Deadline moved: Reporting — Ch. 3"). The message is optional for all.

Every type keeps **Pin to the top**.

## 6. Data

**`announcements`** — new columns (added to old databases automatically):

| Column | Meaning |
| --- | --- |
| `kind` TEXT NOT NULL DEFAULT `'general'` | which subclass |
| `ref_id` INTEGER | the due / task / activity it points at (null for general, no class) |
| `event_at` TEXT | `YYYY-MM-DD HH:MM` used for sorting and "in 3 days" (null = undated) |
| `details` TEXT | JSON snapshot for the card, e.g. `{"from": "...", "to": "..."}` for a deadline change, `{"subject_id": 4}` for no class |

Existing rows read as `general` (no migration needed).

**`class_cancellations`** — new table:

| Column | Meaning |
| --- | --- |
| `id`, `section_id` | |
| `announcement_id` | the No class notice that made it |
| `date` TEXT | `YYYY-MM-DD` |
| `subject_id` INTEGER | null = every class that day |
| `created_by` | |

`Store::table` allow-list gets `class_cancellations`.

## 7. OOP model (`backend/domain/Announcement.php`)

```
abstract Announcement
  - id, title, body, pinned, createdBy, createdAt (private, getters)
  + fromRow(row)$ / fromInput(kind, input, context)$     factories: kind decides the subclass
  + kinds(person)$          the types this person may post (for the composer)
  + kind()* / kindLabel()* / icon()*
  + requiredPermission()    null = any poster; subclasses override (DeadlineChange → PUBLISH_TASK…)
  + defaultTitle()*         used when the title is left empty
  + apply(AnnouncementContext)*  the real change; called inside the same transaction as the insert
  + onRemove(AnnouncementContext)  default nothing; NoClassNotice deletes its cancellation
  + eventAt() ?int          sort date (null = undated)
  + facts(ctx)* array       the lines for the card: [{icon, text}], plus an action {label, href}
  + toRow() / toArray(ctx)
GeneralNotice, PaymentNotice, NoClassNotice, DeadlineChangeNotice, TaskNotice, EventNotice  (final)
AnnouncementContext (final, readonly): sectionId, userId, Person me, ctx (load_context), now
```

Validation lives in each subclass constructor / `fromInput` and throws `InvalidArgumentException`
(the endpoint turns it into a 400), exactly like `Due`, `Payment`, `GroupRule`. Where the checks
already exist they are reused, not copied:
- deadline checks → extract the deadline part of `task_fields()` in `request_task.php` into a shared
  `validate_deadline(array $input): array` in `services.php` (used by both);
- due → `Due` constructor + `Due::centavos`;
- activity → `Activity::fromRow` + the date/time checks from `activities.php` (move them into a
  shared helper the same way).

## 8. Server (`api/announcements.php`)

- `GET` → `{ coming_up: [...], notices: [...], earlier: [...], kinds: [...], can_post }`
  - **Coming up**: `eventAt >= start of today`, soonest first ([ALGORITHM: MERGE SORT] on `event_at`).
  - **Notices**: undated, newest first.
  - **Earlier**: dated and past, newest first; only the first 10 are sent with a count of the rest.
  - Pinned items go first inside their group (stable merge sort on pin, as today).
  - Each item: `kind`, `kind_label`, `icon`, `title`, `body`, `facts`, `action`, `event_at`, `by`,
    `created_at`, `pinned`, `can_delete`.
  - `kinds`: the types this user may post, with what each needs (open dues, upcoming tasks,
    upcoming activities, subjects and the weekdays they meet), so the composer never offers a
    type or a choice the server would refuse.
- `POST action=post {kind, title?, body?, pinned?, …type fields}` → `Announcement::fromInput`,
  check `POST_ANNOUNCEMENTS` and `requiredPermission()`, then in one transaction: `apply()`,
  insert the row, commit; notify everyone except the poster with a type-specific message and link.
- `POST action=pin` unchanged. `POST action=delete` → `onRemove()` then delete (No class: the class
  comes back on the Schedule). Deleting a Deadline change / Payment / Event notice does **not** undo
  the deadline, due or activity; those are edited on their own pages *(design call)*.

## 9. Schedule integration (No class)

- `schedule.php` marks each class in `week.days[].classes` with `cancelled: true` and
  `cancelled_by` (announcement title) when a cancellation matches that **date** and subject (or the
  whole day). Lookup: one [DSA: HASHSET] of `"date|subjectId"` and `"date|*"` keys built once.
- Schedule page and Home "Classes today": a cancelled class is shown crossed out, muted, with a
  "No class" chip; it is skipped by "Next class" in the Schedule side panel.
- A cancellation only affects that one date; the weekly pattern is untouched.

## 10. Page

**Card** (replaces the yellow box; every type uses the same skeleton):

```
┌───────────────────────────────────────────────────────┐
│ (icon)  NO CLASS · FRI, NOV 27 · in 3 days   [Pinned] │  ← type colour + date chip
│         No class on Friday                            │  ← title (bold)
│         ITEC 204 · 1:00–2:30 PM                       │  ← facts line(s)
│         Prof is at a seminar. Use the time for the…   │  ← message, 3 lines max, "Read more"
│         Juan Dela Cruz · posted Nov 20   [Open task →]│  ← author + action button
└───────────────────────────────────────────────────────┘
```

Type colours/icons: Payment peach + clipboard · No class danger tint + x · Deadline change
lavender + clock · Task sun + notebook · Event mint + flag · Notice white/canvas + bell.

**Section on Home:**

```
Announcements                                   [+ Post]
COMING UP
  [Event  · Sat, Nov 28 · in 4 days]  Intro to Figma …
  [Payment · due Nov 30 · in 6 days]  Membership fee ₱150 …
NOTICES
  [Notice] Bring laptops Friday …
▸ Earlier (5)
```

Home shows the first 3 of Coming up + Notices with "Show all"; Earlier stays folded.

**Composer (sheet):** first a row of type buttons (only the types this person may post), then the
fields for that type, a live preview of the card, and Post.

```
Post an announcement
[Notice] [Payment] [No class] [Deadline] [Task] [Event]
— No class —
Date       [ Fri, Nov 27 ▾ ]
Which      ( ) All classes that day   (•) One subject [ ITEC 204 · 1:00–2:30 ▾ ]
Title      [ No class on Friday        ] (optional)
Message    [                           ] (optional)
[ ] Pin to the top
Preview: ┌ NO CLASS · FRI, NOV 27 … ┐
[ Post ]
```

Payment / Event: a switch "Existing · New" (New only when allowed). Deadline change: task picker
showing the current deadline, then the same date picker as the task form (`createDatePicker`,
`createTimeChips`).

## 11. Notifications

| Type | Message | Link |
| --- | --- | --- |
| Notice | "{role} {name}: {title}" (as today) | home.html#announcements |
| Payment | "New due: Membership fee, ₱150, due Nov 30" | dues.html |
| No class | "No class on Fri, Nov 27 · ITEC 204" | schedule.html |
| Deadline change | "Deadline moved: Reporting — Ch. 3 is now due Wed, Nov 26 · 5:00 PM" (type `moved`) | task.html?id= |
| Task | "{title} is due {date}" | task.html?id= |
| Event | "{Seminar}: Intro to Figma · Sat, Nov 28 · AVR Hall" | activities.html |

The existing "New due" / "New activity" notifications from `dues.php` / `activities.php` are not
sent twice when the announcement created the item.

## 12. Testing

- `tests/dsa_test.php`: factory picks the right subclass for each `kind`; input validation (bad date,
  past date, subject that doesn't meet that weekday, missing task); `eventAt()` per type; the
  coming-up / earlier split and sort with fixed times.
- Scratch-copy API walkthrough (never the real DB), as officer / President / member:
  each type posts and does its real change; permission refusals (Secretary → Deadline change);
  delete No class restores the class; old rows still read as Notice.
- Headless Chrome at 360/390/1280: Home section, composer per type, Schedule with a cancelled class.

## 13. Docs to update

README (features, permissions note, DSA map: HashSet cancellations, Merge Sort announcements),
`docs/CLASS_DIAGRAM.md` (Announcement family + AnnouncementContext), PROJECT_CONTEXT.md, NEXT_STEPS.md.

## 14. Calls made in this design (check while reading)

1. Title optional for typed announcements, with a written default.
2. Any officer may announce an *existing* due; only Treasurer/President may create one here.
3. Removing a No class notice brings the class back; removing other typed notices does not undo
   the deadline / due / activity.
4. Home shows 3 + "Show all"; past dated items fold into "Earlier" (10 sent, with a count).
5. Card links to Dues / Activities / Task instead of paying or RSVP-ing inline.
6. Old announcements become Notices.

## 15. Left to the builder

Exact markup and class names, icon choices inside the listed tints, the default-title wording, how
the composer preview is laid out on phones, and how the shared validation helpers are named.
