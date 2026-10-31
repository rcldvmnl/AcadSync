<?php
/**
 * Demo data loaded the first time data/acadsync.db is created.
 * Delete data/acadsync.db to reset everything.
 *
 * Demo accounts (password: password123) — log in with the e-mail or username
 *   BSINFO-2A admin    2023-1045@lspu.edu.ph   juan.delacruz
 *   BSINFO-2A student  2023-1071@lspu.edu.ph   maria.santos   (also in BSINFO-2B)
 *   BSINFO-2B admin    2023-2010@lspu.edu.ph   bea.garcia
 */

declare(strict_types=1);

/** The 8 BSINFO 2A core subjects from the PRD (FR-2.3). Also offered when creating a section. */
function core_curriculum(): array
{
    return [
        ['GEC 107', 'Science, Technology & Society', 'lavender'],
        ['ITEC 204', 'Data Structures & Algorithms', 'peach'],
        ['ITEC 205', 'Information Management', 'mint'],
        ['ITEP 203', 'Integrative Programming', 'lavender'],
        ['ITEL 201', 'Object-Oriented Programming', 'yellow'],
        ['ITEL 202', 'Web Systems & Technologies', 'peach'],
        ['SOSLIT', 'Sosyedad at Literatura', 'yellow'],
        ['PATHFit 3', 'Physical Activity Towards Health & Fitness', 'mint'],
    ];
}

function seed_database(): void
{
    $now = time();
    $day = fn (int $offset) => date('Y-m-d', strtotime("today +$offset day", $now));
    $stamp = fn (string $rel) => date('Y-m-d H:i:s', strtotime($rel, $now));
    $inHours = $now + 3 * 3600;
    $pwd = password_hash('password123', PASSWORD_DEFAULT);
    $dayNames = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    Store::begin();

    // --- Sections -------------------------------------------------------------
    $sec = [];
    foreach ([
        ['BSINFO-2A', 'B2826K'],
        ['BSINFO-2B', 'K7Q4PM'],
    ] as [$name, $code]) {
        $sec[$name] = Store::insert('sections', [
            'name' => $name,
            'class_code' => $code,
            'program' => 'BS Information Technology',
            'year_level' => '2nd Year',
            'semester' => '2nd Semester',
            'school_year' => '2026-2027',
        ]);
    }

    // --- Users: e-mail is <student number>@lspu.edu.ph --------------------------
    $people = [
        ['2023-1045', 'juan.delacruz', 'Juan', 'Dela Cruz'],
        ['2023-1071', 'maria.santos', 'Maria', 'Santos'],
        ['2023-1088', 'paolo.reyes', 'Paolo', 'Reyes'],
        ['2023-1012', 'ana.bautista', 'Ana', 'Bautista'],
        ['2023-1051', 'carlo.domingo', 'Carlo', 'Domingo'],
        ['2023-1064', 'kyla.lim', 'Kyla', 'Lim'],
        ['2023-1033', 'marco.cruz', 'Marco', 'Cruz'],
        ['2023-2010', 'bea.garcia', 'Bea', 'Garcia'],
        ['2023-2022', 'leo.navarro', 'Leo', 'Navarro'],
        ['2023-2035', 'ivy.mendoza', 'Ivy', 'Mendoza'],
    ];
    $u = [];
    foreach ($people as [$sid, $username, $first, $last]) {
        $u[$username] = Store::insert('users', [
            'student_id' => $sid,
            'email' => "$sid@lspu.edu.ph",
            'username' => $username,
            'first_name' => $first,
            'last_name' => $last,
            'password_hash' => $pwd,
        ]);
    }

    // --- Memberships (role is per section) ---------------------------------------
    $members = [
        'BSINFO-2A' => ['juan.delacruz' => 'admin', 'maria.santos' => 'student', 'paolo.reyes' => 'student',
            'ana.bautista' => 'student', 'carlo.domingo' => 'student', 'kyla.lim' => 'student', 'marco.cruz' => 'student'],
        'BSINFO-2B' => ['bea.garcia' => 'admin', 'leo.navarro' => 'student', 'ivy.mendoza' => 'student', 'maria.santos' => 'student'],
    ];
    foreach ($members as $section => $list) {
        foreach ($list as $username => $role) {
            Store::insert('memberships', [
                'section_id' => $sec[$section], 'user_id' => $u[$username], 'role' => $role, 'joined_at' => $stamp('-2 months'),
            ]);
        }
    }
    foreach ($u as $username => $id) {
        $first = isset($members['BSINFO-2A'][$username]) ? 'BSINFO-2A' : 'BSINFO-2B';
        Store::update('users', $id, ['active_section_id' => $sec[$first]]);
    }
    Store::update('sections', $sec['BSINFO-2A'], ['created_by' => $u['juan.delacruz']]);
    Store::update('sections', $sec['BSINFO-2B'], ['created_by' => $u['bea.garcia']]);

    // --- Subjects ----------------------------------------------------------------
    $rooms = [
        'GEC 107' => ['Prof. A. Reyes', 'Room 204'], 'ITEC 204' => ['Prof. M. Cruz', 'Comp Lab 2'],
        'ITEC 205' => ['Prof. K. Tan', 'Room 301'], 'ITEP 203' => ['Prof. R. Garcia', 'Comp Lab 1'],
        'ITEL 201' => ['Prof. J. Lim', 'Comp Lab 3'], 'ITEL 202' => ['Prof. D. Ramos', 'Comp Lab 1'],
        'SOSLIT' => ['Prof. L. Santos', 'Room 110'], 'PATHFit 3' => ['Coach B. Villanueva', 'Gym'],
    ];
    $s = [];       // BSINFO-2A subjects by code
    $s2 = [];      // BSINFO-2B subjects by code
    $core = [];
    foreach (core_curriculum() as [$code, $title, $color]) {
        $core[$code] = [$code, $title, $color];
        $s[$code] = Store::insert('subjects', [
            'section_id' => $sec['BSINFO-2A'], 'code' => $code, 'title' => $title, 'color_tag' => $color,
            'instructor' => $rooms[$code][0], 'room' => $rooms[$code][1],
        ]);
    }
    foreach ([['GEC 107', 'Room 205'], ['ITEC 204', 'Comp Lab 4'], ['ITEL 201', 'Comp Lab 3']] as [$code, $room]) {
        $meta = $core[$code];
        $s2[$code] = Store::insert('subjects', [
            'section_id' => $sec['BSINFO-2B'], 'code' => $code, 'title' => $meta[1], 'color_tag' => $meta[2],
            'instructor' => $rooms[$code][0], 'room' => $room,
        ]);
    }

    // --- Weekly class schedule: each meeting has its own day, start and end --------
    $classes2A = [
        ['GEC 107', 1, '13:00', '14:00'],   // Monday 1 PM, 1 hour
        ['GEC 107', 2, '14:00', '17:00'],   // Tuesday 2 PM, 3 hours
        ['ITEC 204', 1, '15:00', '17:00'],
        ['ITEC 204', 4, '08:00', '11:00'],
        ['ITEL 201', 2, '08:00', '09:30'],
        ['ITEL 201', 5, '10:00', '12:00'],
        ['ITEC 205', 2, '10:00', '11:30'],
        ['ITEC 205', 5, '13:00', '14:30'],
        ['ITEP 203', 3, '08:00', '10:00'],
        ['ITEL 202', 3, '13:00', '16:00'],
        ['SOSLIT', 4, '13:00', '14:30'],
        ['PATHFit 3', 6, '07:00', '09:00'],
    ];
    foreach ($classes2A as [$code, $dow, $start, $end]) {
        Store::insert('class_schedules', [
            'section_id' => $sec['BSINFO-2A'], 'subject_id' => $s[$code],
            'day_of_week' => $dow, 'day_name' => $dayNames[$dow], 'start_time' => $start, 'end_time' => $end,
        ]);
    }
    foreach ([['GEC 107', 3, '08:00', '09:30'], ['ITEC 204', 4, '13:00', '16:00'], ['ITEL 201', 1, '10:00', '11:30']] as [$code, $dow, $start, $end]) {
        Store::insert('class_schedules', [
            'section_id' => $sec['BSINFO-2B'], 'subject_id' => $s2[$code],
            'day_of_week' => $dow, 'day_name' => $dayNames[$dow], 'start_time' => $start, 'end_time' => $end,
        ]);
    }

    // --- Approved tasks (inserted out of order on purpose: the Linked List sorts them)
    // $group = [min, max] for group work, null for individual work
    $task = function (int $sectionId, int $subjectId, int $by, string $cat, string $title, string $desc, ?string $date, ?string $time, string $where, string $status, string $created, ?array $group = null) use ($u) {
        return Store::insert('tasks', [
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'requested_by' => $by,
            'category' => $cat,
            'title' => $title,
            'description' => $desc,
            'deadline' => $date,
            'deadline_time' => $time,
            'deadline_type' => $date === null ? 'unset' : 'exact',
            'location' => $where,
            'work_mode' => $group ? 'group' : 'individual',
            'group_min' => $group[0] ?? null,
            'group_max' => $group[1] ?? null,
            'status' => $status,
            'reviewed_by' => $status === 'approved' ? $u['juan.delacruz'] : null,
            'created_at' => $created,
        ]);
    };
    $A = $sec['BSINFO-2A'];
    $admin = $u['juan.delacruz'];
    $task($A, $s['GEC 107'], $admin, 'Exam', 'Final Exam — Chapters 1–6', 'Coverage: all chapters discussed after the midterm. Bring a blue pen.', $day(30), '08:00', 'Room 204', 'approved', $stamp('-2 days'));
    $finalProject = $task($A, $s['ITEC 204'], $admin, 'Project', 'Final Project — AcadSync', "Build a working web app that uses 7 data structures and 5 algorithms.\n- Source code pushed to the class repo\n- Live demo during system checking\n- Printed documentation", null, null, 'Live demo + class drive', 'approved', $stamp('-10 days'), [3, 4]);
    $reporting = $task($A, $s['GEC 107'], $admin, 'Reporting', 'Reporting — Ch. 3', "Each group presents Chapter 3 — The Scientific Revolution — for 15 minutes, then answers questions for 5 minutes.\n- Slides uploaded to the class drive\n- One printed handout per group", date('Y-m-d', $inHours), date('H:00', $inHours), 'In class · Room 204', 'approved', $stamp('-2 days'), [2, 3]);
    $task($A, $s['ITEL 202'], $admin, 'Activity', 'Web Mockup Draft', 'Submit a low-fidelity mockup of your landing page as a PDF.', $day(1), '17:00', 'Google Classroom', 'approved', $stamp('-3 days'));
    $task($A, $s['SOSLIT'], $admin, 'Film viewing', 'Film Reflection Paper', 'Watch the assigned film and write a 2-page reflection.', $day(9), '23:59', 'Google Classroom', 'approved', $stamp('-1 day'));
    $task($A, $s['ITEC 204'], $admin, 'Activity', 'Lab Activity 3', 'Implement a singly linked list with insert, delete and traverse.', $day(0), '23:59', 'Comp Lab 2', 'approved', $stamp('-4 days'));
    $task($A, $s['GEC 107'], $admin, 'Quiz', 'Quiz 1 — Ch. 4', '20-item multiple choice quiz on Chapter 4.', $day(5), '13:00', 'Room 204', 'approved', $stamp('-1 day'));
    $task($A, $s['ITEC 205'], $admin, 'Activity', 'ERD Exercise', 'Draw an ERD for a library system with at least 5 entities.', $day(3), '10:00', 'Room 301', 'approved', $stamp('-1 day'));

    // --- Pending student requests, enqueued in arrival order ---------------------
    $pending = [
        ['ITEL 201', 'Quiz', 'OOP Quiz', '20-item quiz covering classes, objects and inheritance. Closed notes, 30 minutes.', $day(2), '08:00', 'maria.santos', '-3 days'],
        ['ITEC 204', 'Activity', 'Array Exercise', 'Solve 5 array manipulation problems and submit the .php file.', $day(4), '23:59', 'paolo.reyes', '-2 days'],
        ['GEC 107', 'Reporting', 'Group Reporting — Ch. 4', 'Groups 4–6 report on Chapter 4 — Technology and Society.', $day(6), '13:00', 'ana.bautista', '-2 days', [3, 4]],
        ['ITEC 205', 'Activity', 'Lab Activity 4', 'Normalize the given table up to 3NF.', $day(7), '10:00', 'carlo.domingo', '-1 day'],
        ['ITEL 202', 'Activity', 'Web Mockup', 'High-fidelity mockup of the landing page in Figma.', $day(8), '17:00', 'kyla.lim', '-2 hours', [2, 3]],
    ];
    foreach ($pending as $p) {
        [$code, $cat, $title, $desc, $date, $time, $by, $ago] = $p;
        $id = $task($A, $s[$code], $u[$by], $cat, $title, $desc, $date, $time, 'In class', 'pending', $stamp($ago), $p[8] ?? null);
        Store::insert('approval_queue', ['section_id' => $A, 'task_id' => $id, 'queued_at' => $stamp($ago)]);
    }

    // BSINFO-2B has its own, separate tasks
    $B = $sec['BSINFO-2B'];
    $task($B, $s2['ITEC 204'], $u['bea.garcia'], 'Quiz', 'Stacks & Queues Quiz', 'Short quiz on stack and queue operations.', $day(2), '13:00', 'Comp Lab 4', 'approved', $stamp('-1 day'));
    $reporting2B = $task($B, $s2['GEC 107'], $u['bea.garcia'], 'Reporting', 'Reporting — Ch. 2', 'Group 1 and 2 report on Chapter 2.', $day(4), '08:00', 'Room 205', 'approved', $stamp('-1 day'), [2, 3]);
    $id = $task($B, $s2['ITEL 201'], $u['leo.navarro'], 'Activity', 'Inheritance Lab', 'Build a class hierarchy for a school system.', $day(6), '11:30', 'Comp Lab 3', 'pending', $stamp('-5 hours'));
    Store::insert('approval_queue', ['section_id' => $B, 'task_id' => $id, 'queued_at' => $stamp('-5 hours')]);

    // --- Groups: [section, name, subject, task, purpose, max, creator, members] -------
    $groups = [
        [$A, 'Capstone A', $s['ITEC 204'], $finalProject, 'Final project team — AcadSync web app', 4, 'juan.delacruz', ['ana.bautista', 'juan.delacruz', 'maria.santos']],
        [$A, 'Group 1', $s['GEC 107'], $reporting, 'Chapter 3 report', 3, 'paolo.reyes', ['paolo.reyes', 'kyla.lim']],
        [$A, 'DSA Study', $s['ITEC 204'], null, 'Weekly review before quizzes (Wed after class)', null, 'paolo.reyes', ['juan.delacruz', 'paolo.reyes']],
        [$A, 'Web Dev', $s['ITEL 202'], null, 'Practice HTML/CSS together', null, 'carlo.domingo', ['carlo.domingo', 'marco.cruz', 'kyla.lim']],
        [$B, 'Capstone B', $s2['GEC 107'], $reporting2B, 'Chapter 2 report', 3, 'bea.garcia', ['bea.garcia', 'leo.navarro', 'ivy.mendoza']],
    ];
    foreach ($groups as [$sectionId, $name, $subjectId, $taskId, $purpose, $max, $creator, $list]) {
        $gid = Store::insert('study_groups', [
            'section_id' => $sectionId, 'name' => $name, 'subject_id' => $subjectId, 'task_id' => $taskId,
            'purpose' => $purpose, 'max_members' => $max, 'created_by' => $u[$creator], 'created_at' => $stamp('-3 days'),
        ]);
        foreach ($list as $member) {
            Store::insert('group_members', ['section_id' => $sectionId, 'group_id' => $gid, 'user_id' => $u[$member], 'joined_at' => $stamp('-3 days')]);
        }
    }

    // --- A few notifications ---------------------------------------------------------
    $notes = [
        ['juan.delacruz', $A, 'request', 'Kyla Lim requested “Web Mockup”', 'queue.html', 0, '-2 hours'],
        ['juan.delacruz', $A, 'members', '3 new members joined BSINFO-2A', 'classmates.html', 1, '-1 day'],
        ['maria.santos', $A, 'due', 'Reporting — Ch. 3 is due today', 'schedule.html', 0, '-1 hour'],
        ['maria.santos', $A, 'group', 'You were added to study group “Capstone A”', 'classmates.html', 1, '-4 days'],
    ];
    foreach ($notes as [$who, $sectionId, $type, $msg, $link, $read, $ago]) {
        Store::insert('notifications', [
            'user_id' => $u[$who], 'section_id' => $sectionId, 'type' => $type, 'message' => $msg,
            'link' => $link, 'is_read' => $read, 'created_at' => $stamp($ago),
        ]);
    }

    Store::commit();
}
