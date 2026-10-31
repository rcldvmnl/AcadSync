-- ============================================================================
-- AcadSync — SQLite schema (raw storage only)
-- The 5 core tables come from the Database Schema Document. A few columns and
-- 4 support tables were added so the Figma screens have somewhere to store
-- their data (class times, rooms, notifications, persisted queue/stack order).
-- Processing (sorting, searching, filtering) never happens in SQL.
-- ============================================================================

-- 1. SECTIONS ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,                 -- "BSINFO-2A"
    class_code TEXT UNIQUE,             -- "B2826K"
    program TEXT,                       -- "BS Information Technology"
    semester TEXT,                      -- "2nd Semester · 2026"
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. USERS ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id TEXT UNIQUE,             -- "2023-10045" (Binary Search key)
    username TEXT NOT NULL,
    first_name TEXT NOT NULL DEFAULT '',
    last_name TEXT NOT NULL DEFAULT '',  -- Selection Sort key
    email TEXT UNIQUE NOT NULL,         -- @lspu.edu.ph (HashSet key)
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'student', -- 'student' | 'admin'
    section_id INTEGER,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(section_id) REFERENCES sections(id)
);

-- 3. SUBJECTS ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id INTEGER,
    code TEXT NOT NULL,                 -- "ITEC 204"
    title TEXT NOT NULL,                -- "Data Structures & Algorithms"
    color_tag TEXT,                     -- lavender | peach | yellow | mint
    instructor TEXT,
    room TEXT,
    FOREIGN KEY(section_id) REFERENCES sections(id)
);

-- 3b. CLASS_SCHEDULES (weekly class times shown on the Class Schedule screen)
CREATE TABLE IF NOT EXISTS class_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_id INTEGER,
    day_of_week INTEGER NOT NULL,       -- 1 = Mon ... 6 = Sat
    start_time TEXT NOT NULL,           -- "13:00"
    end_time TEXT NOT NULL,             -- "14:30"
    FOREIGN KEY(subject_id) REFERENCES subjects(id)
);

-- 4. TASKS ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_id INTEGER,
    requested_by INTEGER,
    category TEXT NOT NULL,             -- Reporting | Quiz | Exam | Activity | Project
    title TEXT NOT NULL,
    description TEXT,                   -- Linear Search target
    deadline DATE,                      -- YYYY-MM-DD, NULL = "not set yet"
    deadline_time TEXT,                 -- "13:00"
    deadline_end DATE,                  -- end of a date range
    deadline_type TEXT DEFAULT 'exact', -- exact | range | unset
    location TEXT,
    status TEXT DEFAULT 'pending',      -- pending | approved | rejected
    kind TEXT DEFAULT 'new',            -- new | change (edit request)
    target_task_id INTEGER,             -- task an edit request applies to
    reviewed_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(subject_id) REFERENCES subjects(id),
    FOREIGN KEY(requested_by) REFERENCES users(id)
);

-- 5. STUDY_GROUPS (graph edges) ---------------------------------------------
CREATE TABLE IF NOT EXISTS study_groups (
    student_a_id INTEGER,
    student_b_id INTEGER,
    project_context TEXT,               -- "Capstone A"
    PRIMARY KEY (student_a_id, student_b_id, project_context),
    FOREIGN KEY(student_a_id) REFERENCES users(id),
    FOREIGN KEY(student_b_id) REFERENCES users(id)
);

-- 6. APPROVAL_QUEUE — persisted FIFO order (row order = arrival order) -------
CREATE TABLE IF NOT EXISTS approval_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    FOREIGN KEY(task_id) REFERENCES tasks(id)
);

-- 7. UNDO_STACK — persisted LIFO order (last row = top of stack) -----------
CREATE TABLE IF NOT EXISTS undo_stack (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    admin_id INTEGER,
    FOREIGN KEY(task_id) REFERENCES tasks(id)
);

-- 8. NOTIFICATIONS ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,                 -- approved | rejected | due | moved | group | request | members | schedule
    message TEXT NOT NULL,
    link TEXT,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

-- 9. PASSWORD_RESETS --------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    used INTEGER DEFAULT 0
);
