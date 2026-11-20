# AcadSync: next steps (for a new conversation)

Attach this file **and** [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md) at the start of a new chat.
PROJECT_CONTEXT.md explains the project, the rules the code follows and how to test safely;
this file lists what is left to do.
Last updated: 2026-11-20.

**Suggested first message:**
> Read docs/PROJECT_CONTEXT.md and docs/NEXT_STEPS.md. Start with the first item under "Still to do" and keep the rules in PROJECT_CONTEXT.md.

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

---

## Still to do

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

### 6 (low). Tailwind from the CDN
All pages load `cdn.tailwindcss.com` (needs internet, logs a warning). For a real deployment, build
one CSS file with the Tailwind CLI.

### Ideas
- Show the join queue and dues in **How AcadSync works** (`dsa.html`) as live examples.
- Export a due's payment list to CSV for the Treasurer's report.

---

## After any change
- `php tests/dsa_test.php`, `php -l` each edited PHP file, `node --check` each edited JS file.
- Keep the README tables (features, permissions, DSA map, project layout) and `docs/CLASS_DIAGRAM.md`
  in sync with the code.
- Commit only when asked; never commit `.env` or anything in `data/`.
