# AcadSync: next steps (for a new conversation)

Attach this file **and** [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md) at the start of a new chat.
PROJECT_CONTEXT.md explains the project, the rules the code follows and how to test safely;
this file lists what to fix and what to add next, checked against the README's claims.
Last updated: 2026-11-15.

**Suggested first message:**
> Read docs/PROJECT_CONTEXT.md and docs/NEXT_STEPS.md. Start with "Fix 1" and keep the rules in PROJECT_CONTEXT.md.

---

## Done in the last session (2026-11-15)

| Change | Files |
| --- | --- |
| README rewritten: table of contents, Quick start, badges, tables, GitHub callouts (same facts) | `README.md` |
| VS Code opened files as UTF-16 (text looked Chinese). Workspace now forces UTF-8 | `.vscode/settings.json` |
| Class Schedule, desktop: panel under "See whole week" with **Next class**, **Your progress**, **Subjects** (due in 7 days). Hidden on phones | `Frontend/js/schedule.js` (`sidePanel()` and the 3 card functions) |
| Groups card redesigned: your group first, one row per member, "(you)" / "Made the group", dashed "Open spot" rows, plain chips (Full / 1 spot left / Needs 1 more), separate red **Leave group** button | `Frontend/js/group-work.js` |
| Bug fix: new groups no longer repeat a name ("Group 3" twice). Lowest free "Group N"; typed duplicate names are rejected | `api/groups.php` (`group_names_for_task`, `next_group_name`) |
| Phone nav is now 3 \| + \| 3 for everyone. Members got a **Groups** icon; officers keep the Approval queue there and get Groups on the desktop rail | `Frontend/js/ui.js` (`navItems`, `renderNav`), `Frontend/js/groups.js`, `Frontend/css/app.css` |

All edited files passed `php -l` / `node --check`, but **none of these UI changes were checked in a
browser yet** (see Fix 5). Nothing from this session is committed.

---

## Fix (most important first)

### Fix 1. Built-in PHP search/filter calls break the DSA rule
The header of `backend/custom_dsa.php` and PROJECT_CONTEXT.md both say no built-in searching or
filtering is used (`in_array`, `array_filter`, …). These 15 places still use them; a grader who
searches the code will find them:

| File | Line | Call |
| --- | --- | --- |
| `api/schedule.php` | 28 | `in_array` (is the user in this group) |
| `api/groups.php` | 257, 445, 497 | `in_array` |
| `api/groups.php` | 291 | `array_filter` (groups of one task, for `auto_plan`) |
| `api/subtasks.php` | 39, 141 | `in_array` |
| `api/section.php` | 61 | `in_array` |
| `api/records.php` | 64 | `in_array` |
| `api/classmates.php` | 46 | `in_array` |
| `api/dsa.php` | 87 | `in_array` |
| `backend/domain/Person.php` | 208 | `in_array` |
| `backend/domain/UndoAction.php` | 34, 218 | `in_array` |
| `backend/Env.php` | 74 | `in_array` (allowed values of a setting) |

Replace each with a `HashSet` lookup (`contains`) or `Algorithms::linearSearch…`, and tag it
`// [DSA: HASHSET]` / `// [ALGORITHM: LINEAR SEARCH]`. Re-run the search afterwards:
`grep -rnE "\b(in_array|array_search|array_filter|usort|sort)\(" api backend`.

### Fix 2. Class diagram is missing classes
The OOP rubric wants the UML to match the code. These classes exist but are not in
`docs/CLASS_DIAGRAM.md`: `Algorithms`, `DsaUtil`, `QueueNode`, `StackNode`, `TreeNode`,
`UndoNotPossible` (exception). Add them (the node classes can sit next to their structure), or state
in the diagram that helper/node classes are left out on purpose.

### Fix 3. Old database still in GitHub history
The DB files were untracked, but earlier commits on `origin/main` still contain them (accounts and
password hashes). Purging needs a history rewrite (`git filter-repo`) + force-push, and every
teammate re-cloning. Decide with the team; at minimum change any real passwords that were in it.

### Fix 4. Duplicate "Group 3" in the existing local database
The naming bug is fixed for new groups, but the two groups already named "Group 3" keep their name.
Options: everyone leaves one and re-creates it, delete `data/acadsync.db` for fresh demo data, or
add a **Rename group** action (see Add 6).

### Fix 5. Check last session's UI in a real browser
Test as a Member (`ana.bautista`), an officer (`kyla.lim`) and the President (`juan.delacruz`):
- Schedule (desktop ≥1024px): the new side panel; Urgent filter hides it; phone width hides it.
- Groups card on Task Details and Classmates › Groups: your group first, open spots, Leave, Ask to join, Accept/Decline, Add classmates.
- Phone nav at 360px and 390px wide: 3 | + | 3, no overlap; Groups icon highlights on the Groups tab;
  officers' phone bar keeps Classmates lit on both tabs.
Use the scratch-folder method in PROJECT_CONTEXT.md, not the real database.

### Fix 6 (low). Tailwind from the CDN
All 19 pages load `cdn.tailwindcss.com`, which logs "should not be used in production" and needs
internet. Fine for the demo; for a real deployment, build one CSS file with the Tailwind CLI.

---

## Add

### Add 1. Submission docs in the README (required deliverables)
PROJECT_CONTEXT.md lists the OOP deliverables: YouTube demo, Canva slides, GitHub repo, docs. The
README has none of these yet. Add:
- **Project overview and objectives** (still marked "to write")
- **Team members** (groups are exactly 3) and who did what
- Links to the **YouTube demo** and **Canva slides**
- 3–4 **screenshots** (Home, Schedule, Groups, Member Records)

### Add 2. Tests for the data structures
No tests exist. A small `tests/dsa_test.php` (run with `php tests/dsa_test.php`) that checks each
sort and search on known input, the HashSet, Stack, Queue, linked list insert/delete, tree DFS and
graph BFS. Useful for the DSA defense and to catch regressions while doing Fix 1.
Keep `tests/` blocked in `router.php` / `.htaccess` like `backend/`.

### Add 3. Officers' Groups icon on phones
Officers can't fit a Groups icon on the phone bar (it would be 4 | + | 3). If it's wanted, one icon
must move, for example the Approval queue into Home as a card with the count.

### Add 4. Membership approval when joining with a class code
Today anyone with the code joins at once. An officer-approved join queue would reuse `ApprovalQueue`
(fits the DSA "Queue" story) and the officer permission model.

### Add 5. Announcements
Officers post announcements to the section; members see them on Home and get a notification.
Simple, and a natural part of an organization system.

### Add 6. Rename a group
Let the group's creator (or the President) rename a group, rejecting names already used by that task
(reuse `group_names_for_task()` in `api/groups.php`).

### Add 7 (bigger). Dues and payments
A **Treasurer** officer (`Treasurer extends Officer`) and an `abstract Payment` with subclasses
(e.g. `CashPayment`, `GcashPayment`). Strong extra OOP example; update the class diagram and the
permission table in the README if added.

### Add 8 (later). E-mail for password reset
`SHOW_RESET_CODE=true` shows the code on screen because there is no mail server. Sending it by
e-mail would let `SHOW_RESET_CODE` be turned off.

---

## After any change
- `php -l` each edited PHP file, `node --check` each edited JS file.
- Keep the README tables (features, permissions, DSA map, project layout) and `docs/CLASS_DIAGRAM.md`
  in sync with the code.
- Commit only when asked; never commit `.env` or anything in `data/`.
