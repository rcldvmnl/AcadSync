# AcadSync: next steps (for a new conversation)

Attach this file **and** [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md) at the start of a new chat.
PROJECT_CONTEXT.md explains the project, the rules the code follows and how to test safely;
this file lists what is left to do.
Last updated: 2026-11-21 (end of the third session).

**Suggested first message:**
> Read docs/PROJECT_CONTEXT.md and docs/NEXT_STEPS.md, then continue with "Still to do".
> Keep the rules in PROJECT_CONTEXT.md.

**State of the work:** the 2026-11-20 work was committed by the owner. History was rewritten on 2026-11-21
(DB files purged; `main` is now `b141196`, same code as the old `154f12a`). All 2026-11-21 changes below are
**uncommitted** on `main`.
Last verified (2026-11-21): `php tests/dsa_test.php` → 214 passed, 0 failed; scratch API tests → 31 passed
(review fixes) and 81 passed (competitions/projects); headless-browser check → 12 passed, no JS errors;
DSA grep prints nothing; `npm run build:css` changes nothing. `impeccable detect` was **not** re-run (the CLI
isn't installed on this machine; the page changes only reuse existing classes and colours).

---

## Done on 2026-11-21: the code-review findings (each with a failing test first)

| # | Fix | Files |
| --- | --- | --- |
| 1 | The Event announcement always makes its new activity for **everyone** (the composer has no picker). A stored invite-only row with no invite rows no longer breaks every page: `Activity::fromRow($row, stored: true)` (used by `load_activities`) lets it load with nobody able to join until an officer picks people | `Announcement.php`, `Activity.php`, `services.php` |
| 2 | Editing an activity to invite-only (or a narrower list) removes the RSVPs, waitlist places and `part_members` of people no longer invited, in the same transaction, and tells them; the freed seats go to the invited people waiting (`ActivityRoster::seatsToFill()`) | `api/activities.php`, `Activity.php` |
| 3 | `cancel_rsvp` gives the seat to the first **invited** person waiting (`ActivityRoster::nextInLineFor()`) and drops anyone waiting who isn't invited (`waitingNotFor()`) | `api/activities.php`, `Activity.php` |
| 4 | The Edit form sends `audience`/`invited` only when the officer touched the picker; the picker also lists people already invited who aren't in `data.people` (alumni, left the section), marked *Not active*; the server lets already-invited people stay on the list | `activities.js`, `api/activities.php` |
| 5 | The GET returns cancelled activities older than 3 days in `old_cancelled` (was a `hidden_cancelled` count). With *Show cancelled* on, Past ends with a closed *N older cancelled activities* box with **Remove**; links to `#a<id>` open it | `api/activities.php`, `activities.js` |
| 6 | `scrollToActivity` sets the tab / *Show cancelled* / the old box first and renders once; turning *Show cancelled* on from a link is saved like the toggle | `activities.js` |
| 7 | **Decision: kept.** `delete` and `$saveInvites` read each table once and filter in PHP, as the DSA rule requires (no `WHERE`) | none |
| 8 | `notifies(member, context)`: an Event announcement asks the live activity's `isFor()` instead of a copy of the invite list (the copy is no longer stored) | `Announcement.php`, `api/announcements.php` |
| 9 | `PartsActivity`'s constructor drops any seat limit, so `create` builds a competition/project once (and a stray capacity can't refuse it) | `Activity.php`, `api/activities.php` |

Deferred minors from the earlier review, also fixed (tests first):
- `PartRoster::whyCantVolunteer` refuses once the activity has ended ("“X” has already ended.").
- `edit_part`, `set_role`, `unpick`, `mark_done` refuse cancelled activities (409 "This activity was cancelled.").
- `edit_part` clears a removed role from whoever had it (same transaction).
- Event announcement facts show every day of a multi-day activity ("Wed, Nov 25 – Thu, Nov 26 · …").
- Switching kinds in the New activity form keeps the part due dates (each row remembers its date in `data-due`).

Docs updated: `CLASS_DIAGRAM.md` (new `ActivityRoster` methods, `fromRow(row, stored)`, `PartsActivity`
constructor, `notifies(member, context)`), `PROJECT_CONTEXT.md`, README (cancelled activities, who can join).
Scratch tests from this session (git-ignored): `.superpowers/sdd/2026-11-21-review-fixes/` (`review_test.php`,
`fe_check.mjs`, `fe_setup.php`, `browser_router.php`, `sync.sh`, `run.sh`; see the comments in `run.sh`).

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

## Done on 2026-11-21, later: the rest of the list (tests first)

| Item | What changed | Files |
| --- | --- | --- |
| Open point: DB errors | `is_unique_violation()`: `volunteer` and the No class announcement only call a UNIQUE clash a double tap; any other database error is a real error | `services.php`, `api/activities.php`, `api/announcements.php` |
| Open point: last-spot race | Not changed: with a write lock held from another connection, the second pick waited and then answered "This part is full. Un-pick someone first." | none |
| 2. "Group 3" | Real DB: task "report", group 11 renamed to **Group 4** through the app as Juan (it notified the members); renaming another to "Group 4" was refused. Backup first: `data/acadsync.backup-before-rename-20261121-100415.db` | data only |
| 3. History purge | The 3 DB files and `.taste-skill/` removed from every commit (`git filter-branch` in a separate clone, verified: no `.db` anywhere, final tree identical), force-pushed with `--force-with-lease` (`154f12a` → `b141196`, 24 commits). Local `main` moved with `git reset --mixed origin/main` (uncommitted work untouched) | history |
| 4. Officers' phone nav | Officers: Home, Schedule, Queue \| + \| Members, Groups, Activities; My account from an avatar in the phone header (`Shell.accountInHeader()`). Members unchanged | `ui.js` |
| 5. Reset code by e-mail | `Mailer` (hand-written SMTP: SSL or STARTTLS, AUTH LOGIN, base64 UTF-8 body, no header injection) with `MAIL_*` settings; `forgot` e-mails the code; `SHOW_RESET_CODE` is now actually read (it was documented but unused); with neither, 503 "isn't set up yet"; a failed send closes the code (502). Login page says "Enter your code" when nothing was e-mailed | `backend/Mailer.php`, `api/auth.php`, `auth.js`, `.env.example`, `tests/mailer_test.php`, `tests/fake_smtp.php` |
| Idea: CSV export | Dues → **Export CSV** (`dues.php?export=<id>`, Treasurer/President): one row per charged member A–Z, payments with receipt lines, totals; UTF-8 BOM; `csv_line()` guards against formula injection | `api/dues.php`, `services.php`, `dues.js`, `ui.js` (download icon) |
| Idea: live examples | How AcadSync works: the join queue, the volunteer queue of the busiest open part, and the HashSet of who paid the soonest due in full. Names only for the officers who see them elsewhere (review_members / manage_activities / manage_dues); members see counts and their own place | `api/dsa.php`, `dsa.js` |

Verified after these: `tests/dsa_test.php` 220/0, `tests/mailer_test.php` 12/0; scratch API tests 31 + 81 + 9
(reset e-mail) + 11 (CSV) + 7 (live examples); browser checks 12 + 11 (nav) + 2 (reset) + 4 (dues) + 3 (dsa page),
no JS errors, clean server log; DSA grep empty; `npm run build:css` unchanged.

## Done on 2026-11-21, last: app-wide restyle to the owner's reference (layouts unchanged)

| Change | Files |
| --- | --- |
| New look from the owner's reference education app, academic theme: cool grey ground `#F4F4F7`, white hairline cards, periwinkle `#A8A3F7` / orange `#F58D42` / yellow `#FFD34E` cards, charcoal cards and nav (active item a periwinkle rounded square, + a white disc), SVG sparkle stars and doodle lines on hero and dark cards. Text colours re-checked for WCAG AA (`muted` `#5E5E68`, `success` `#23774F`, `danger` `#C8402F`) | `tailwind.config.js`, `css/app.css`, `css/org.css`, `css/task.css`, `css/classmates.css` |
| Hand-drawn SVG illustrations (trophy, books, pencil, diploma) on the login / sign-up heroes, the officer Home hero and every empty state; login hero orange, sign-up periwinkle | `assets/illus/*.svg`, `index.html`, `signup.html`, `js/home.js`, `js/ui.js` |
| Mint now only marks success; decorative mint (activity kinds, the Members quick action, Treasurer tint, chips) moved to the reference tints; a subject stored as `mint` shows as grey (picker label "Grey") | `js/*.js`, `classmates.css` |
| Plus Jakarta Sans self-hosted (`Frontend/fonts/`, OFL); Google Fonts removed from all 20 pages, so the app works fully offline | `css/app.css`, all `Frontend/*.html` |
| README screenshots retaken in the new design | `docs/screenshots/*.png` |
| Design records: `PRODUCT.md` (product truth), `DESIGN.md` + `.impeccable/design.json` (visual system), `.impeccable/surfaces/frontend-home-html.md` (direction contract). Small uppercase labels above headings were kept by the owner's decision | root, `.impeccable/` |

Checked: all 23 page views at 390 and 1280 px (font loads with Google blocked, no sideways scroll, no JS errors);
`impeccable detect --changed` 0 findings; independent finish review: ship (7 material fixes, all resolved);
`tests/dsa_test.php` 220/0 and the five browser checks still pass.
Not changed (frozen layout, pre-existing): at 390 px the Dues member rows cut long names short and the Activities
part rows wrap narrowly.

---

## Still to do

### 1. Fill in the README submission section (needs the team)
Team member names, student IDs and who did what; YouTube demo and Canva slides links.
The placeholders are under **Team and submission links** in `README.md`.

### 2. Turn on e-mail for password reset (needs the owner)
In `.env`: `MAIL_HOST=smtp.gmail.com`, `MAIL_USERNAME` / `MAIL_FROM` = the sending Gmail address,
`MAIL_PASSWORD` = a Google **app password** (needs 2-Step Verification). Test "Forgot password" once, then set
`SHOW_RESET_CODE=false`.

### 3. After the history purge (team)
- Every teammate (e.g. arcelll) must **re-clone** (or `git fetch` + `git reset --hard origin/main` on a clean copy);
  pushing from an old clone would bring the old history back.
- Change any real passwords that were in the old database.
- Optional: ask GitHub Support to clear cached views of the old commits (they can stay reachable by SHA).

### Ideas
- None open.

---

## After any change
- `php tests/dsa_test.php`, `php tests/mailer_test.php`, `php -l` each edited PHP file, `node --check` each edited JS file.
- New Tailwind classes in a page or script: `npm run build:css` (the built `Frontend/css/tailwind.css` is committed).
- Keep the README tables (features, permissions, DSA map, project layout) and `docs/CLASS_DIAGRAM.md`
  in sync with the code.
- Commit only when asked; never commit `.env` or anything in `data/`.
