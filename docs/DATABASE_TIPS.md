# Finding data in the AcadSync database

## Which file is the live one?

The app uses only the file named by `DB_PATH` in `.env`:

```
DB_PATH=data/acadsync.db
```

So **`data/acadsync.db` is the live database.** The other files in `data/` are not used by the app:

| File | What it is |
|---|---|
| `acadsync.db` | **Live.** Everything you do in the app is saved here. |
| `demo.db` | A separate copy for recording the demo (only used if `.env` says `DB_PATH=data/demo.db`). |
| `acadsync.backup-*.db` | Old backups made before big changes. The date in the name is when the backup was made. Never edited. |

To check which one changed last: in the VS Code Explorer, hover over the files in `data/`, or run
`ls -lt data/` in Git Bash (newest first).

If the picker list gets long, you can move old `acadsync.backup-*.db` files you no longer need into a
folder outside the project (keep at least the newest one). Don't rename or move `acadsync.db`.

## Opening it in VS Code

1. Command Palette (`Ctrl+Shift+P`) → **SQLite: Open Database** → pick `data\acadsync.db` (the one
   without "backup" in the name).
2. The **SQLite Explorer** panel appears at the bottom of the Explorer. Expand `acadsync.db` → a table →
   click the ▶ (Show Table) next to it.

## Forgot which e-mail you signed up with?

Command Palette → **SQLite: New Query**, choose `data\acadsync.db`, paste this, then right-click →
**Run Query**:

```sql
SELECT id, username, email, first_name, last_name, created_at
FROM users
ORDER BY id DESC;
```

The newest account is at the top. You can sign in with **either the username or the e-mail**.

Find one person by name:

```sql
SELECT username, email FROM users WHERE first_name LIKE '%arcel%' OR last_name LIKE '%arcel%';
```

(`ORDER BY`/`WHERE` are fine here: the DSA rule is only for the app's code, not for looking at the data yourself.)

## Other useful lookups

```sql
-- Sections and their class codes
SELECT id, name, class_code FROM sections;

-- Who is in which section, with role and office
SELECT u.username, s.name AS section, m.role, m.position, m.status
FROM memberships m JOIN users u ON u.id = m.user_id JOIN sections s ON s.id = m.section_id;

-- Newest activities
SELECT id, title, kind, starts_at, status FROM activities ORDER BY id DESC LIMIT 10;

-- Your latest notifications (put your user id in place of 11)
SELECT message, link, created_at FROM notifications WHERE user_id = 11 ORDER BY id DESC LIMIT 10;
```

To see every table's columns, open `backend/schema.sql` (each column has a short comment).

## Safe habits

- **Look, don't edit.** Only run `SELECT` queries. Change data through the app; editing rows by hand can
  break links between tables.
- If you must edit by hand: stop the PHP server first, and copy `acadsync.db` somewhere safe before you start.
- Passwords are stored hashed (`password_hash`), so you can't read them. If you forgot a password, use
  **Forgot password** on the sign-in page.
- Never commit any `.db` file to git (`.gitignore` already blocks them).
