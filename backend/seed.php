<?php
/**
 * Demo data loaded the first time data/acadsync.db is created.
 * Delete data/acadsync.db to reset everything.
 *
 * Demo accounts (password: password123)
 *   Admin   juan.delacruz@lspu.edu.ph   (or username juan.delacruz)
 *   Student maria.santos@lspu.edu.ph    (or username maria.santos)
 */

declare(strict_types=1);

function seed_database(): void
{
    $now = time();
    $day = fn (int $offset) => date('Y-m-d', strtotime("today +$offset day", $now));
    $inHours = $now + 3 * 3600;
    $pwd = password_hash('password123', PASSWORD_DEFAULT);

    Store::begin();

    $sectionId = Store::insert('sections', [
        'name' => 'BSINFO-2A',
        'class_code' => 'B2826K',
        'program' => 'BS Information Technology',
        'semester' => '2nd Semester · 2026',
    ]);

    // --- Users --------------------------------------------------------------
    $people = [
        ['2023-10045', 'juan.delacruz', 'Juan', 'Dela Cruz', 'admin'],
        ['2023-10071', 'maria.santos', 'Maria', 'Santos', 'student'],
        ['2023-10088', 'paolo.reyes', 'Paolo', 'Reyes', 'student'],
        ['2023-10012', 'ana.bautista', 'Ana', 'Bautista', 'student'],
        ['2023-10051', 'carlo.domingo', 'Carlo', 'Domingo', 'student'],
        ['2023-10064', 'kyla.lim', 'Kyla', 'Lim', 'student'],
        ['2023-10033', 'marco.cruz', 'Marco', 'Cruz', 'student'],
    ];
    $u = [];
    foreach ($people as [$sid, $username, $first, $last, $role]) {
        $u[$username] = Store::insert('users', [
            'student_id' => $sid,
            'username' => $username,
            'first_name' => $first,
            'last_name' => $last,
            'email' => "$username@lspu.edu.ph",
            'password_hash' => $pwd,
            'role' => $role,
            'section_id' => $sectionId,
        ]);
    }
    Store::update('sections', $sectionId, ['created_by' => $u['juan.delacruz']]);

    // --- Curriculum (the 8 BSINFO 2A subjects from the PRD) ------------------
    $subjects = [
        ['GEC 107', 'Science, Technology & Society', 'lavender', 'Prof. A. Reyes', 'Room 204'],
        ['ITEC 204', 'Data Structures & Algorithms', 'peach', 'Prof. M. Cruz', 'Comp Lab 2'],
        ['ITEC 205', 'Information Management', 'mint', 'Prof. K. Tan', 'Room 301'],
        ['ITEP 203', 'Integrative Programming', 'lavender', 'Prof. R. Garcia', 'Comp Lab 1'],
        ['ITEL 201', 'Object-Oriented Programming', 'yellow', 'Prof. J. Lim', 'Comp Lab 3'],
        ['ITEL 202', 'Web Systems & Technologies', 'peach', 'Prof. D. Ramos', 'Comp Lab 1'],
        ['SOSLIT', 'Sosyedad at Literatura', 'yellow', 'Prof. L. Santos', 'Room 110'],
        ['PATHFit 3', 'Physical Activity Towards Health & Fitness', 'mint', 'Coach B. Villanueva', 'Gym'],
    ];
    $s = [];
    foreach ($subjects as [$code, $title, $color, $instructor, $room]) {
        $s[$code] = Store::insert('subjects', [
            'section_id' => $sectionId,
            'code' => $code,
            'title' => $title,
            'color_tag' => $color,
            'instructor' => $instructor,
            'room' => $room,
        ]);
    }

    // --- Weekly class schedule (1 = Monday) ----------------------------------
    $classes = [
        [1, 'GEC 107', '13:00', '14:30'], [1, 'ITEC 204', '15:00', '17:00'],
        [2, 'ITEL 201', '08:00', '09:30'], [2, 'ITEC 205', '10:00', '11:30'],
        [3, 'ITEP 203', '08:00', '10:00'], [3, 'ITEL 202', '13:00', '15:00'],
        [4, 'SOSLIT', '08:00', '09:30'], [4, 'GEC 107', '13:00', '14:30'], [4, 'ITEC 204', '15:00', '17:00'],
        [5, 'PATHFit 3', '08:00', '10:00'], [5, 'ITEL 201', '10:00', '11:30'], [5, 'ITEC 205', '13:00', '14:30'],
    ];
    foreach ($classes as [$dow, $code, $start, $end]) {
        Store::insert('class_schedules', [
            'subject_id' => $s[$code], 'day_of_week' => $dow, 'start_time' => $start, 'end_time' => $end,
        ]);
    }

    // --- Approved tasks (inserted out of order on purpose: the Linked List sorts them)
    $approved = [
        ['GEC 107', 'Exam', 'Final Exam — Chapters 1–6', 'Coverage: all chapters discussed after the midterm. Bring a blue pen.', $day(30), '08:00', 'Room 204'],
        ['ITEC 204', 'Project', 'Final Project — AcadSync', "Build a working web app that uses 7 data structures and 5 algorithms.\n- Source code pushed to the class repo\n- Live demo during system checking\n- Printed documentation", null, null, 'Live demo + class drive'],
        ['GEC 107', 'Reporting', 'Reporting — Ch. 3', "Each group presents Chapter 3 — The Scientific Revolution — for 15 minutes, then answers questions for 5 minutes.\n- Slides uploaded to the class drive\n- One printed handout per group", date('Y-m-d', $inHours), date('H:00', $inHours), 'In class · Room 204'],
        ['ITEL 202', 'Activity', 'Web Mockup Draft', 'Submit a low-fidelity mockup of your landing page as a PDF.', $day(1), '17:00', 'Google Classroom'],
        ['ITEC 204', 'Activity', 'Lab Activity 3', 'Implement a singly linked list with insert, delete and traverse.', $day(0), '23:59', 'Comp Lab 2'],
        ['GEC 107', 'Quiz', 'Quiz 1 — Ch. 4', '20-item multiple choice quiz on Chapter 4.', $day(5), '13:00', 'Room 204'],
        ['ITEC 205', 'Activity', 'ERD Exercise', 'Draw an ERD for a library system with at least 5 entities.', $day(3), '10:00', 'Room 301'],
    ];
    foreach ($approved as [$code, $cat, $title, $desc, $date, $time, $where]) {
        Store::insert('tasks', [
            'subject_id' => $s[$code],
            'requested_by' => $u['juan.delacruz'],
            'category' => $cat,
            'title' => $title,
            'description' => $desc,
            'deadline' => $date,
            'deadline_time' => $time,
            'deadline_type' => $date === null ? 'unset' : 'exact',
            'location' => $where,
            'status' => 'approved',
            'reviewed_by' => $u['juan.delacruz'],
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days', $now)),
        ]);
    }

    // --- Pending student requests, enqueued in arrival order -----------------
    $pending = [
        ['ITEL 201', 'Quiz', 'OOP Quiz', '20-item quiz covering classes, objects and inheritance. Closed notes, 30 minutes.', $day(2), '08:00', 'maria.santos', -3],
        ['ITEC 204', 'Activity', 'Array Exercise', 'Solve 5 array manipulation problems and submit the .php file.', $day(4), '23:59', 'paolo.reyes', -2],
        ['GEC 107', 'Reporting', 'Group Reporting — Ch. 4', 'Groups 4–6 report on Chapter 4 — Technology and Society.', $day(6), '13:00', 'ana.bautista', -2],
        ['ITEC 205', 'Activity', 'Lab Activity 4', 'Normalize the given table up to 3NF.', $day(7), '10:00', 'carlo.domingo', -1],
        ['ITEL 202', 'Activity', 'Web Mockup', 'High-fidelity mockup of the landing page in Figma.', $day(8), '17:00', 'kyla.lim', 0],
    ];
    foreach ($pending as [$code, $cat, $title, $desc, $date, $time, $by, $ago]) {
        $taskId = Store::insert('tasks', [
            'subject_id' => $s[$code],
            'requested_by' => $u[$by],
            'category' => $cat,
            'title' => $title,
            'description' => $desc,
            'deadline' => $date,
            'deadline_time' => $time,
            'deadline_type' => 'exact',
            'location' => 'In class',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', strtotime("$ago day", $now)),
        ]);
        Store::insert('approval_queue', ['task_id' => $taskId]);
    }

    // --- Study groups (graph edges between every pair in a team) -------------
    $groups = [
        'Capstone A' => ['ana.bautista', 'juan.delacruz', 'maria.santos'],
        'DSA Study' => ['juan.delacruz', 'paolo.reyes'],
        'Web Dev' => ['carlo.domingo', 'marco.cruz', 'kyla.lim'],
    ];
    foreach ($groups as $name => $members) {
        $n = count($members);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                Store::insert('study_groups', [
                    'student_a_id' => $u[$members[$i]],
                    'student_b_id' => $u[$members[$j]],
                    'project_context' => $name,
                ]);
            }
        }
    }

    // --- A few notifications --------------------------------------------------
    $notes = [
        ['juan.delacruz', 'request', 'Kyla Lim requested “Web Mockup”', 'queue.html', 0],
        ['juan.delacruz', 'members', '3 new members joined BSINFO-2A', 'members.html', 1],
        ['maria.santos', 'due', 'Reporting — Ch. 3 is due today', 'schedule.html', 0],
        ['maria.santos', 'group', 'You were added to study group “Capstone A”', 'classmates.html', 1],
    ];
    foreach ($notes as [$who, $type, $msg, $link, $read]) {
        Store::insert('notifications', [
            'user_id' => $u[$who], 'type' => $type, 'message' => $msg, 'link' => $link, 'is_read' => $read,
        ]);
    }

    Store::commit();
}
