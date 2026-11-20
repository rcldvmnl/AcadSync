# AcadSync: next steps (for a new conversation)

Attach this file **and** [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md) at the start of a new chat.
PROJECT_CONTEXT.md explains the project, the rules the code follows and how to test safely;
this file lists what is left to do.
Last updated: 2026-11-20 (end of the second session).

**Suggested first message:**
> Read docs/PROJECT_CONTEXT.md and docs/NEXT_STEPS.md. Fix the code-review findings in item 0 under
> "Still to do" (most severe first, a failing test before each fix), then continue with the other items.
> Keep the rules in PROJECT_CONTEXT.md.

**State of the work:** everything below "Done" is **uncommitted** on `main` (the owner commits).
Last verified: `php tests/dsa_test.php` → 202 passed, 0 failed; the scratch API test → 81 passed;
`impeccable detect Frontend/*.html` → 0 issues; DSA grep prints nothing.

---

## Done on 2026-11-20

| Item | What changed | Files |
| --- | --- | --- |
| Fix 1. DSA rule | All 15 `in_array` / `array_filter` calls replaced with the hand-written `Algorithms::linearSearch` / `linearContains` (tagged `[ALGORITHM: LINEAR SEARCH]`) or a loop; `Env::bool` uses `match`. The grep check prints nothing | `backend/custom_dsa.php`, 9 endpoint/domain files, `backend/Env.php` |
| Fix 2. Class diagram | Added `Algorithms`, `DsaUtil`, `QueueNode`, `StackNode`, `TreeNode`, `UndoNotPossible`, plus the new `Treasurer`, `Payment`, `CashPayment`, `GcashPayment`, `Due`; pillar and relationship tables updated | `docs/CLASS_DIAGRAM.md` |
| Fix 4. Duplicate "Group 3" | Solved by Add 6 (Rename). Tested on a copy of the real DB; the real DB itself was not touched (see "Still to do") | |
| Fix 5. Browser check | Headless Chrome, as Member / officer / President / Treasurer, 360–1280 px: Schedule side panel (shown on desktop, hidden for Urgent and on phones), Groups cards, phone nav 3 \| + \| 3 with no overlap, Groups / Classmates highlighting. No JS errors, no sideways scroll | |
| Add 1. Submission docs | README: project objectives, team table and YouTube / Canva link placeholders, 6 screenshots, Tests section | `README.md`, `docs/screenshots/` |
| Add 2. Tests | 74 checks for every structure and algorithm; `tests/` is blocked in `router.php` and `tests/.htaccess` | `tests/dsa_test.php` |
| Add 4. Join approval | Entering the class code sends a request; officers let people in from the Approval queue ("Waiting to join"), oldest first (only #1 can be decided). Pending requests show on the section page with Cancel. New permission `review_members` | `api/section.php`, `api/join_requests.php`, `join_requests` table, `Frontend/js/section.js`, `queue.js` |
| Add 5. Announcements | Officers post, pin and remove; card on Home; everyone is notified. New permission `post_announcements` | `api/announcements.php`, `announcements` table, `Frontend/js/home.js` |
| Add 6. Rename a group | Creator or President; names already used by that task's other groups are rejected; members are notified | `api/groups.php`, `Frontend/js/group-work.js`, `groups.js` |
| Add 7. Dues and payments | `Treasurer extends Officer`; `abstract Payment` → `CashPayment` / `GcashPayment`; `Due`; `MemberStatus::owesDues()`. Dues page for members and the Treasurer; Home reminder; officer tile on Member Records | `backend/domain/Payment.php`, `api/dues.php`, `dues`/`payments` tables, `Frontend/dues.html`, `js/dues.js`, `records.js` |

New tables reach an existing database automatically (`CREATE TABLE IF NOT EXISTS`); `SCHEMA_VERSION` was not changed.
The seed (new databases only) now has Marco as Treasurer, two dues with payments, two announcements
and a pending join request from Leo.

### Later the same day

| Change | Files |
| --- | --- |
| Date ranges count as today/urgent from their first day (`Deadline::attentionAt`); range badge "20–23 NOV" | `Deadline.php`, `schedule.php`, `ui.js`, `schedule.js`, `task.js` |
| Nav: Classmates before Groups for members (desktop and phone) | `ui.js` |
| Classmates › Groups subject headers fit on phones | `classmates.css` |
| **Group setups**: `GroupRule` is abstract with `AssignedGroups`, `FixedGroupCount`, `FixedGroupSize`, `FixedSizeAndCount`. Groups appear at posting when their number is known; empty groups are joined directly; every officer can move/remove members; the task form has a setup picker with a live preview and a builder for the prof's own list; Task Details shows Groups first | `Task.php`, `groups.php`, `request_task.php`, `admin_action.php`, `services.php`, `seed.php`, `task-form.js`, `group-work.js`, `groups.js`, `task.js` |
| Task Details: Instructions and "Finished this task?" moved to the left column | `task.js` |
| **Typed announcements** (spec + plan in `docs/superpowers/`): Notice, Payment, No class, Deadline change, Task, Event, each doing its real change; Home lists Coming up / Notices / Earlier; No class crosses the class out on Schedule, Home and Whole Week. After review: permission checked before details, payment notices only to members charged dues, finished events leave Coming up, impossible dates refused, unique index on cancellations | `Announcement.php`, `announcements.php`, `services.php`, `schedule.php`, `request_task.php`, `activities.php`, `schema.sql`, `db.php`, `seed.php`, `home.js`, `ui.js`, `schedule.js`, `week.js` |
| Home Announcements: one short row (pinned first) + **See all** popup with every announcement, Pin/Remove and Earlier | `home.js` |
| **Competitions and section projects** (spec + plan in `docs/superpowers/`, built with executing-plans, not committed yet): `Competition` / `SectionProject` → `PartsActivity` → `Activity` (and `RsvpActivity` for the old three); `ActivityPart` + `PartRoster` (volunteer Queue, picked HashSet); link to the post (http/https only, new tab), sign-up deadline, several days, results; volunteer → pick / assign / step down / mark done; Your sign-ups, Needs people, This month, Results and officer starters on Activities; Home "Your sign-ups"; Event announcements point at competitions ("N spots open across M parts"); search finds activities and part names; seed has the Founding Anniversary and Booth design | `Activity.php`, `ActivityPart.php`, `activities.php`, `announcements.php`, `Announcement.php`, `search.php`, `services.php`, `schema.sql`, `db.php`, `seed.php`, `activities.js`, `home.js`, `search.js`, `ui.js`, `org.css`, `tests/dsa_test.php` |
| **Review fixes** (fresh whole-branch review): editing can't move an activity into the past or change a finished one; editing only the date keeps the number of days; a project takes sign-ups until its last day (`defaultSignupClose()`); "Start a project" defaults to tomorrow after 08:00 | `activities.php`, `Activity.php`, `activities.js`, `Announcement.php` |
| **Design fixes**: secondary text `muted` `#62626B` and placeholders `hint` `#6B6B74` meet WCAG AA; `.field` got vertical padding; pastel cards use a soft shadow instead of the solid "lip"; recorded exceptions (nav glow, sand ground) added for activities/records/my-tasks | `tailwind.config.js`, `app.css`, `ui.js`, `search.html`, `task-form.js`, `.impeccable/config.json` |
| **Undo one rejection**: the queue's undo bar has *See all* → a sheet listing every rejected request (latest first) with its own Undo; the server takes it out of the stack with `UndoStack::removeWhere` (pop onto a holding stack, push back) | `custom_dsa.php`, `admin_undo.php`, `admin_queue.php`, `services.php` (`present_undo`), `queue.js` |
| **Event kind**: `SectionEvent` (`event`) extends `RsvpActivity`: open to everyone, anyone checked in, never counts against attendance | `Activity.php`, `activities.js`, `schema.sql` |
| **Who can join**: *Everyone* or *People I choose* (searchable member picker) on New / Edit activity; `activities.audience` + `activity_invites`; `Activity::isFor()` (HashSet) used by RSVP, volunteer, pick/assign, check-in, Needs people, `MemberRecord` and `EventNotice::notifies()` | `Activity.php`, `MemberRecord.php`, `Announcement.php`, `services.php`, `activities.php`, `schema.sql`, `db.php`, `activities.js` |
| **Cancelled activities**: hidden by default with a *Show cancelled (N)* toggle; the server leaves out cancelled ones 3 days after their date (`HIDE_CANCELLED_AFTER`); officers can **Remove** a cancelled one for good (`action=delete`, with its RSVPs, attendance, invites, parts and volunteers) | `activities.php`, `activities.js` |
| **Date range**: *Several days (a date range)* → From / To; To can't go before From; moving From moves To | `activities.js` |
| **Tailwind CLI build** (was "Still to do" 6): every page links the built `css/tailwind.css` instead of `cdn.tailwindcss.com` + `js/tailwind-config.js` (removed; the theme moved to `tailwind.config.js`). Checked in headless Chrome: all 20 pages × Juan/Ana × 390/1280 px have identical computed styles to the CDN version. Rebuild with `npm run build:css` after adding classes | `tailwind.config.js`, `package.json`, `package-lock.json`, `Frontend/css/tailwind.src.css`, `Frontend/css/tailwind.css`, all `Frontend/*.html` |

---

## Still to do

### 0. Fix the findings of the latest code review (do these first)
Found by `/code-review` on the uncommitted work, most severe first. None is fixed yet. For each one:
write a failing test (`tests/dsa_test.php` for domain code; the scratch API test approach in
PROJECT_CONTEXT.md for endpoints), fix it, then run the whole suite.

1. **Critical. An Event announcement can create a broken invite-only activity** (`backend/domain/Announcement.php`
   ~line 819, `EventNotice::build` / `apply`). `new_activity: {audience:'invited', invited:[…]}` passes
   `validate_new_activity`, `toRow()` stores `audience='invited'`, but no `activity_invites` rows are written
   and the ids aren't checked against the section. Then `load_activities()` builds it with `invited=[]`, the
   constructor throws "Pick at least one person to invite.", and **every page that loads activities fails for
   the whole section** (Activities, announcements, member records).
   Fix: in `EventNotice::build`, force `audience='everyone'` for new activities (the composer has no picker),
   or check the ids and insert the invite rows in `apply()`. Also make `load_activities()` safe: an
   `invited` row with no invites should not throw for the whole page.
2. **Important. Making an activity invite-only keeps everyone else's RSVPs and volunteers** (`api/activities.php`
   ~line 650, `edit`). A meeting with 20 RSVPs edited to invite 5 keeps the other 15 "going": they hold seats,
   count in going/full, and the invited people get waitlisted, while those 15 can't be checked in.
   Fix: when the invite list changes, delete RSVPs / waitlist rows and `part_members` of people who are no longer
   `isFor()` (and notify them), inside the same transaction.
3. **Important. `cancel_rsvp` can give a freed seat to someone who isn't invited** (`api/activities.php` ~line 901).
   The front of the waitlist is promoted without `isFor()`. Fix: skip (and drop) uninvited people when
   promoting; better, fix 2 removes them first.
4. **Important. Saving the Edit form silently changes the invite list** (`Frontend/js/activities.js` ~line 220,
   `bindWho` / `openEdit`). The picker is built from `data.people` (active members only), and the form always
   sends `audience` + `invited`, so an invited alumni member is dropped on every save, and someone who left the
   section makes the save fail with "Pick people from this section." The server's "null = keep invites" path
   is never used. Fix: send `audience`/`invited` only when the officer touched the picker, and list the
   currently invited people in the picker even if they're not in `data.people`.
5. **Important. Auto-hidden cancelled activities can't be reached any more** (`api/activities.php` ~line 304).
   After 3 days they're left out of the GET, so *Show cancelled* can't show them and **Remove** can't reach
   them; `hidden_cancelled` is returned but the page never uses it; links to `activities.html#a<id>` fail
   silently. Fix: return them in a separate `old_cancelled` list (or with `?cancelled=all`) that
   *Show cancelled* reveals, and show "N older cancelled activities" with Remove.
6. **Minor. `scrollToActivity` renders twice for a cancelled activity on the other tab, and the forced
   *Show cancelled* isn't saved** (`Frontend/js/activities.js` ~line 71). Fix: set `showCancelled` and `tab`
   first, render once, and either save the choice or show it as temporary.
7. **Minor. Full-table scans in `delete` and `$saveInvites`** (`api/activities.php` ~line 849). They read every row
   of five tables and filter in PHP. Note: this follows the project's DSA rule (reads only through
   `Store::all` / `section_rows`, no `WHERE`), so `Store::where` is also a PHP loop over `SELECT *`. Decide
   whether to keep it (rule) or accept; at most, build the per-activity lists once.
8. **Minor. Event announcements keep a frozen invite list** (`backend/domain/Announcement.php` ~line 752). The
   invited ids are copied into the announcement's `details`, so later invite edits (or opening it to
   everyone) aren't followed by `notifies()`. Fix: decide from the live activity (`AnnouncementContext`
   activities) instead of the snapshot; `notifies()` then needs the context.
9. **Minor. `create` builds the activity twice for competitions/projects** (`api/activities.php` ~line 427):
   `validate_new_activity` runs again only to drop the capacity. Fix: pass `capacity => null` up front when
   the kind joins by parts (build once).

Also still open from the earlier whole-branch review (deferred minors, not fixed):
- Two officers picking the last spot at the same moment: the second sees "database is locked" instead of
  "This part is full." (`Store::begin` is a deferred transaction; `BEGIN IMMEDIATE` for pick/assign would fix it).
- `PartRoster::whyCantVolunteer` doesn't check `isOver()` (a direct POST after a same-day competition ended).
- `volunteer` reports any `PDOException` as "You already volunteered for this part." (check the UNIQUE error code).
- `edit_part` can drop a role someone holds; their old role stays shown.
- `edit_part` / `set_role` / `unpick` / `mark_done` don't refuse cancelled activities (the page hides the buttons).
- Event announcement facts show only the first day of a multi-day competition.
- Switching kinds in the New activity form loses the part due dates already typed.

### 1. Fill in the README submission section (needs the team)
Team member names, student IDs and who did what; YouTube demo and Canva slides links.
The placeholders are under **Team and submission links** in `README.md`.

### 2. Rename one of the two "Group 3" groups in the real database
In the app as Juan (President): open the task's Groups, press **Rename** on one "Group 3", e.g. to
"Group 4". (Tested on a copy: the second one then can't be given the same name.)

### 3. Old database still in GitHub history (team decision)
Earlier commits on `origin/main` still contain the DB files (accounts and password hashes). Purging
needs a history rewrite (`git filter-repo`) + force-push, and every teammate re-cloning. Not done:
it rewrites shared history. At minimum, change any real passwords that were in it.

### 4. Officers' Groups icon on phones (design decision)
Officers' phone bar is Home, Schedule, Queue | + | Classmates, Activities, Account; Groups is reached
through Classmates. Adding it needs one icon to move (e.g. the queue into a Home card). The queue now
also holds join requests, so it was left on the bar.

### 5. E-mail for password reset (needs a mail server)
`SHOW_RESET_CODE=true` shows the code on screen. Sending it by e-mail needs SMTP settings (e.g. a
Gmail app password) and a mail library; then `SHOW_RESET_CODE` can be turned off.


### Ideas
- Show the join queue, dues and a part's volunteer queue in **How AcadSync works** (`dsa.html`) as live examples.
- Export a due's payment list to CSV for the Treasurer's report.

---

## After any change
- `php tests/dsa_test.php`, `php -l` each edited PHP file, `node --check` each edited JS file.
- New Tailwind classes in a page or script: `npm run build:css` (the built `Frontend/css/tailwind.css` is committed).
- Keep the README tables (features, permissions, DSA map, project layout) and `docs/CLASS_DIAGRAM.md`
  in sync with the code.
- Commit only when asked; never commit `.env` or anything in `data/`.
