# Competitions and section projects on Activities — design

Status: waiting for approval · 2026-11-20 · Approach **A** (picked by the user)

## Why

Activities only has Meeting, Seminar and Outreach (one date, seats, RSVP, check-in). When nothing is
planned the page is an empty card, so it feels useless. Real section life needs more than that:

- **School events with parts.** Example: the President's group-chat post for the Founding
  Anniversary (Nov 25–26). It had five parts, each with its own size: Cosplay (2 people), Culinary
  Showdown (one team of 4), a dating game (any number), Guess the Song (2 people), and a 90's
  Remake video (a team of 10 with roles: director, editor, production…). Entries were due Nov 20,
  before the event days. Today people "send us a message" and the officers choose who
  represents the section. That happens in a group chat, where it's lost.
- **Section projects that need everyone.** A competition entry, a booth or a design: the work is
  split into parts, and each part goes to one person or a few people who need to know what to do
  and by when.

Success: an officer can post the Founding Anniversary in a minute with its five parts and the link to the
original post. Classmates volunteer in the app. Officers pick from the volunteer list, and the people
picked are told. A section project shows who does which part and how far along it is.

## Goals

1. Two new kinds of activity: **Competition** and **Section project**, alongside the three that exist.
2. Every activity can carry a **link to the original post** (clickable) and, optionally, a **sign-up deadline**.
3. Activities can span **more than one day** (Nov 25–26).
4. Competitions and projects are made of **parts**. A part has a name, what to do, how many people
   it needs (or no limit), optional **roles** (e.g. Director, Editor) and, for projects, a due date.
5. **Volunteer → officers pick** (the user's decision). Anyone active volunteers for a part (with an
   optional note and preferred role); officers pick up to the number needed, and can give a role.
6. Section projects: officers can also **assign** people to a part directly; assignees mark the part
   **done**; the project shows progress (done parts / all parts).
7. A more useful page: **Needs people**, **Your sign-ups**, **Results** on past activities, a
   **month strip**, and a better empty state (all of these were proposed by Claude and accepted by the user).

## Non-goals

- No school-wide (cross-section) events: everything stays inside one section, like the rest of the app.
- No file uploads or submissions for parts (the link field covers "here is our entry").
- No automatic picking. Officers always choose.
- No reminders sent on a timer (there is no cron); due parts show on Home instead.
- Project parts do **not** appear on the class Schedule (Claude's call, accepted): the Schedule stays about school work.

## Anti-goals (it fails even if it works, if…)

- A volunteer thinks they are "in" before an officer picked them. The difference between
  *Volunteered* and *Picked* must always be obvious.
- A link can run script or open in the app's own tab: links must be `http(s)` only, shown with
  their site name, opened in a new tab.
- Competitions or projects lower anyone's attendance rate.
- The page gets busier than the group chat it replaces.

## Constraints (from PROJECT_CONTEXT.md, binding)

- DSA rule: rows only through `Store::all` / `section_rows`; no `ORDER BY` / `WHERE` / `JOIN`; no
  `in_array` / `array_search` / `array_filter` / `usort` / `sort`. Use the hand-written
  structures and tag them (`[DSA:]`, `[ALGORITHM:]`, `[OOP:]`).
- Permissions, not roles: officers act through `Permission::MANAGE_ACTIVITIES`; members through
  `MemberStatus::canRsvp()` (only active members volunteer).
- New tables via `CREATE TABLE IF NOT EXISTS`; new columns via `Database::addMissingColumns`.
  Do **not** bump `SCHEMA_VERSION`. Add new tables to the `table()` allow-list.
- Never test against `data/acadsync.db`; scratch copies only. Commit only when asked.
- Keep README tables and `docs/CLASS_DIAGRAM.md` in step with the code.

## Approaches considered

| | Approach | Trade-off | Verdict |
|---|---|---|---|
| **A** | Two new `Activity` subclasses (Competition, SectionProject) sharing a "parts" model, on the Activities page | One place for everything; reuses the abstract `Activity`, rosters, notifications and the Event announcement. Activity and its page grow; the new kinds don't use seats or check-in | **Picked** |
| B | Section projects as Tasks (group work), only Competition in Activities | Free Schedule and deadlines, but Tasks are class work: they go through the approval queue, appear next to quizzes, and "one person does the poster, three the booth" doesn't fit equal groups | Rejected |
| C | A separate Events page | Needs another nav icon (the phone bar is full) and splits attendance across two pages | Rejected |

## The design (decided)

### Domain (OOP)

- `Activity` (abstract) gets the new fields `link` (nullable URL), `signupDeadline` (nullable date)
  and `result` (nullable text, e.g. "2nd place in Cosplay"). It also gets new polymorphic questions,
  each answered by every kind:
  - `joinsBy(): string`: `'rsvp'` (Meeting/Seminar/Outreach) or `'parts'` (Competition/SectionProject).
  - `usesSeats(): bool`: only the RSVP kinds use capacity, waitlist and check-in.
  - `canAssignDirectly(): bool`: false for Competition (volunteer → pick only), true for SectionProject.
  - `partsNeedDueDates(): bool`: true for SectionProject.
- **`Competition extends Activity`**:
  - `expectsAttendanceFrom()` = false and `canCheckIn()` = false, so it never touches attendance.
  - Rule text: "Volunteer for a part. Officers pick who represents the section."
- **`SectionProject extends Activity`**:
  - Attendance is the same as Competition (none).
  - Rule text: "Everyone helps. Each part has its people and a due date."
  - Its date range is "work starts → final day" (e.g. the competition day).
- **`ActivityPart`** (new, encapsulated, validated in the constructor):
  - `name` (1–80 chars), `details` (≤ 1000 chars).
  - `spots`: 1–100, or null for "any number".
  - `roles`: 0–10 short names.
  - `dueDate`: required for project parts and refused for competition parts, via `partsNeedDueDates()`.
  - `position` (display order), `doneAt`/`doneBy`, `result`.
- **`PartRoster`** (composition, like `ActivityRoster`). The volunteer list is a `Queue`, so the
  earliest volunteer is shown first; a `HashSet` holds who is picked.
  - `canVolunteer(userId)`: not already on it, active member, sign-ups still open.
  - `canPick(userId)`: the person volunteered (or the kind allows direct assignment), and
    picked < spots or spots is null.
  - `isFull()`, `openSpots()`.
- Multi-day: the "New activity" form gets an optional end date; `ends_at` may then be on a later day.
  The constructor rule (end after start) already covers it.
- Link validation: `filter_var(FILTER_VALIDATE_URL)` plus the scheme must be `http` or `https`, and
  at most 500 chars. Anything else: "Paste a link that starts with http:// or https://."
- Sign-up deadline: a real date (`is_real_date`) on or before the activity's start date.
  Volunteering closes at the end of that day. Officers can still pick and assign after it.

### Data

- `activities` new columns: `link`, `signup_deadline`, `result`.
- New table `activity_parts`: id, section_id, activity_id, name, details, spots, roles (JSON), due_date,
  position, done_at, done_by, result, created_at.
- New table `part_members`: id, section_id, part_id, user_id, status (`volunteer` | `picked`), role,
  note, created_at, picked_by, picked_at; UNIQUE(part_id, user_id).

### Who can do what

| Action | Who |
|---|---|
| Create / edit / cancel a competition or project, add / edit / remove parts, enter results | `MANAGE_ACTIVITIES` |
| Pick a volunteer, give a role, un-pick, assign directly (projects only) | `MANAGE_ACTIVITIES` |
| Volunteer (with note + preferred role), withdraw while still a volunteer | active members (`canRsvp()`) |
| Step down after being picked | the picked person (officers are notified) |
| Mark a project part done / not done | anyone picked or assigned to it, and officers |

### Notifications

- New competition or project: everyone.
- You were picked or assigned (with your role, if any): you.
- Someone stepped down: the activity's creator.
- A part was marked done: the activity's creator.
- Volunteering does **not** notify officers one by one. They see the volunteer list instead (keeps it quiet).

### The Activities page

Tabs stay **Upcoming / Past**. An Upcoming competition or project card shows:

```
COMPETITION · Nov 25–26 · Sign up by Fri, Nov 20 (Today)
Founding Anniversary
facebook.com ↗  (the original post, opens in a new tab)
Parts
  Cosplay Competition            2 spots · 1 picked · 3 volunteers    [Volunteer]
  College Culinary Showdown      team of 4 · 0 picked                [Volunteer]
  90's Remake: Video             10 · roles: Director, Editor, …     [Volunteered ✓ — waiting]
```

- **Volunteering:** tapping a part's **Volunteer** button opens a small sheet: a note ("what I can do"),
  a preferred role if the part has roles, then Send.
  - Your state on that part reads *Volunteered — the officers will pick* or *Picked · Director*.
- **The officer's view** of a part lists its volunteers in the order they came (the Queue).
  - Each volunteer has their note and **Pick** / **Un-pick** buttons, plus a role menu.
  - Projects also get **Assign someone**.
- **A project card** shows a progress bar (done / all parts). Each part shows its people, its due date
  (overdue in red), and **Mark done** for the people on it.

The right column gains these cards:

1. **Your sign-ups**: every part you volunteered for or were picked/assigned to, with its status, your
   role and its due date. This is also on Home (as a small card) whenever you have a picked or
   assigned part that isn't done.
2. **Needs people**: parts that still have open spots, with sign-ups still open, soonest deadline first.
   It's hidden when there are none.
3. **This month**: a strip of the month's days with dots on days that have activities. Tapping a day
   scrolls to its card.
4. **Your attendance** and **How it counts** stay. "How it counts" gains Competition and Section project.

Past tab: a finished activity shows its **result** (and per-part results) when officers entered one.

Empty state (no upcoming): instead of one blank card, show the **latest results**, the
**progress of any open project**, and for officers three starters: *Plan a meeting*, *Post a
competition*, *Start a project*.

### Fits with existing features

- The **Event** announcement already points at an activity. For a competition its facts become
  "N spots open across M parts · Sign up by …" and its action is *Volunteer*.
- **Search** should find competitions, projects and part names (uses the existing Linear Search).
- **Seed** (new databases only): the Founding Anniversary competition with the five parts above (dates
  relative to today), plus one section project, "Booth design", with 4 parts, some assigned and one done.
- **How AcadSync works** (`dsa.html`) may show the volunteer Queue as a live example (optional).

## Calls Claude made (check these)

- **Team or separate people:** a part has a `together` flag, used only for wording. Culinary reads
  "team of 4"; Cosplay reads "2 spots".
- **Who sees names:** members see who was **picked** and the **number** of volunteers. Only officers
  see the volunteers' names and notes.
- **Editing:** officers can edit a competition or project and its parts after posting. A part can't be
  removed or shrunk below the number already picked; un-pick people first.
- **Withdrawing:** a volunteer can withdraw at any time before being picked, even after the sign-up deadline.
- **Results:** results are plain text, entered by officers after the activity ends. No ranking or points.

## Left to the builder

Exact copy beyond the strings above, colours (use the existing tint tokens: `bg-mint-tint` for events,
`bg-lavender-tint` for projects), the sheet layouts, how the month strip scrolls on phones, and the
JSON shape of the new API fields, all following the existing `activities.php` / `activities.js` patterns.
