-- ============================================================================
-- AcadSync — SQLite schema (raw storage only)
--
-- Every per-section table carries a `section_id`, so in DB Browser / SQLite
-- Viewer you can filter any table by section. The `view_*` views at the bottom
-- join the tables into readable reports per section — they exist ONLY for
-- people browsing the database. The PHP app never reads them: it fetches raw
-- rows with `SELECT * FROM <table>` and does all sorting/searching/filtering in
-- the custom data structures (backend/custom_dsa.php).
-- ============================================================================

-- 1. SECTIONS — one row per class section (e.g. BSINFO-2A) -------------------
CREATE TABLE IF NOT EXISTS sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,          -- "BSINFO-2A" (kept unique via HashSet)
    class_code TEXT NOT NULL UNIQUE,    -- "B2826K" join code (kept unique via HashSet)
    program TEXT,                       -- "BS Information Technology"
    year_level TEXT,                    -- "2nd Year"
    semester TEXT,                      -- "2nd Semester"
    school_year TEXT,                   -- "2026-2027"
    created_by INTEGER,                 -- users.id of the officer who created it
    created_at DATETIME DEFAULT (datetime('now', 'localtime'))
);

-- 2. USERS — one row per account ---------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id TEXT NOT NULL UNIQUE,    -- "2023-1045" = part before @ in the e-mail (Binary Search key)
    email TEXT NOT NULL UNIQUE,         -- "2023-1045@lspu.edu.ph" (HashSet key)
    username TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,            -- Selection Sort key
    password_hash TEXT NOT NULL,
    active_section_id INTEGER,          -- section currently open in the app
    created_at DATETIME DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY(active_section_id) REFERENCES sections(id)
);

-- 3. MEMBERSHIPS — who belongs to which section, and their role there --------
CREATE TABLE IF NOT EXISTS memberships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    role TEXT NOT NULL DEFAULT 'student', -- 'admin' (class officer) | 'student'
    joined_at DATETIME DEFAULT (datetime('now', 'localtime')),
    UNIQUE (section_id, user_id),
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);

-- 4. SUBJECTS — a section's curriculum (loaded into the SubjectArray) --------
CREATE TABLE IF NOT EXISTS subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    code TEXT NOT NULL,                 -- "ITEC 204"
    title TEXT NOT NULL,                -- "Data Structures & Algorithms"
    instructor TEXT,
    room TEXT,
    color_tag TEXT,                     -- lavender | peach | yellow | mint
    FOREIGN KEY(section_id) REFERENCES sections(id)
);

-- 5. CLASS_SCHEDULES — one row per class meeting; every day can differ ------
--    e.g. GEC 107: Monday 13:00–14:00, Tuesday 14:00–17:00
CREATE TABLE IF NOT EXISTS class_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    subject_id INTEGER NOT NULL,
    day_of_week INTEGER NOT NULL,       -- 1 = Monday ... 6 = Saturday
    day_name TEXT NOT NULL,             -- "Monday" (for readability)
    start_time TEXT NOT NULL,           -- "13:00"
    end_time TEXT NOT NULL,             -- "14:00"
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(subject_id) REFERENCES subjects(id)
);

-- 6. TASKS — deadlines, student requests, rejected and removed tasks --------
CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    subject_id INTEGER NOT NULL,
    requested_by INTEGER NOT NULL,      -- users.id
    category TEXT NOT NULL,             -- Reporting | Quiz | Exam | Activity | Project
    title TEXT NOT NULL,
    description TEXT,                   -- Linear Search target
    deadline DATE,                      -- YYYY-MM-DD (first day if a range); NULL = not set yet
    deadline_end DATE,                  -- last day of a date range
    deadline_time TEXT,                 -- "13:00"
    deadline_type TEXT NOT NULL DEFAULT 'exact', -- exact | range | unset
    location TEXT,
    status TEXT NOT NULL DEFAULT 'pending', -- pending | approved | rejected | removed
    kind TEXT NOT NULL DEFAULT 'new',   -- new | change (edit request for target_task_id)
    target_task_id INTEGER,
    reviewed_by INTEGER,
    created_at DATETIME DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(subject_id) REFERENCES subjects(id),
    FOREIGN KEY(requested_by) REFERENCES users(id)
);

-- 7. STUDY_GROUPS — graph edges between two classmates ----------------------
CREATE TABLE IF NOT EXISTS study_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    student_a_id INTEGER NOT NULL,
    student_b_id INTEGER NOT NULL,
    project_context TEXT NOT NULL,      -- group name, e.g. "Capstone A"
    UNIQUE (section_id, student_a_id, student_b_id, project_context),
    FOREIGN KEY(section_id) REFERENCES sections(id),
    FOREIGN KEY(student_a_id) REFERENCES users(id),
    FOREIGN KEY(student_b_id) REFERENCES users(id)
);

-- 8. APPROVAL_QUEUE — persisted FIFO order (lower id = arrived earlier) ------
CREATE TABLE IF NOT EXISTS approval_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    task_id INTEGER NOT NULL,
    queued_at DATETIME DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY(task_id) REFERENCES tasks(id)
);

-- 9. UNDO_STACK — persisted LIFO order (highest id = top of the stack) -----
CREATE TABLE IF NOT EXISTS undo_stack (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER NOT NULL,
    task_id INTEGER NOT NULL,
    admin_id INTEGER,
    rejected_at DATETIME DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY(task_id) REFERENCES tasks(id)
);

-- 10. NOTIFICATIONS ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    section_id INTEGER,
    type TEXT NOT NULL,                 -- approved | rejected | due | moved | group | request | members | schedule
    message TEXT NOT NULL,
    link TEXT,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY(user_id) REFERENCES users(id)
);

-- 11. PASSWORD_RESETS -------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    used INTEGER NOT NULL DEFAULT 0
);

-- ============================================================================
-- READ-ONLY REPORT VIEWS (for humans browsing the .db file — not used by PHP)
-- Open any view and filter the `section` column to see one section's data.
-- ============================================================================

CREATE VIEW IF NOT EXISTS view_section_members AS
SELECT s.name AS section, u.student_id, u.last_name || ', ' || u.first_name AS name,
       u.email, u.username, m.role, m.joined_at
FROM memberships m
JOIN sections s ON s.id = m.section_id
JOIN users u ON u.id = m.user_id
ORDER BY s.name, m.role, u.last_name;

CREATE VIEW IF NOT EXISTS view_section_subjects AS
SELECT s.name AS section, sub.code, sub.title, sub.instructor, sub.room
FROM subjects sub
JOIN sections s ON s.id = sub.section_id
ORDER BY s.name, sub.code;

CREATE VIEW IF NOT EXISTS view_section_schedule AS
SELECT s.name AS section, c.day_name AS day, c.start_time AS starts, c.end_time AS ends,
       sub.code, sub.title, sub.room, sub.instructor
FROM class_schedules c
JOIN sections s ON s.id = c.section_id
JOIN subjects sub ON sub.id = c.subject_id
ORDER BY s.name, c.day_of_week, c.start_time;

CREATE VIEW IF NOT EXISTS view_section_tasks AS
SELECT s.name AS section, sub.code AS subject, t.category, t.title,
       CASE t.deadline_type WHEN 'unset' THEN 'not set yet'
            WHEN 'range' THEN t.deadline || ' to ' || t.deadline_end
            ELSE t.deadline END AS deadline,
       t.deadline_time AS due_time, t.status, t.kind,
       u.last_name || ', ' || u.first_name AS requested_by, t.created_at
FROM tasks t
JOIN sections s ON s.id = t.section_id
JOIN subjects sub ON sub.id = t.subject_id
JOIN users u ON u.id = t.requested_by
ORDER BY s.name, t.deadline;

CREATE VIEW IF NOT EXISTS view_approval_queue AS
SELECT s.name AS section, q.id AS queue_order, t.title, sub.code AS subject,
       u.last_name || ', ' || u.first_name AS requested_by, q.queued_at
FROM approval_queue q
JOIN sections s ON s.id = q.section_id
JOIN tasks t ON t.id = q.task_id
JOIN subjects sub ON sub.id = t.subject_id
JOIN users u ON u.id = t.requested_by
ORDER BY s.name, q.id;

CREATE VIEW IF NOT EXISTS view_study_groups AS
SELECT s.name AS section, g.project_context AS study_group,
       a.last_name || ', ' || a.first_name AS student_a,
       b.last_name || ', ' || b.first_name AS student_b
FROM study_groups g
JOIN sections s ON s.id = g.section_id
JOIN users a ON a.id = g.student_a_id
JOIN users b ON b.id = g.student_b_id
ORDER BY s.name, g.project_context;
