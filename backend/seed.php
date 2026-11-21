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

/**
 * BSINFO 2A's real subjects, First Semester AY 2026-2027 (from the class and the CCS class schedule):
 * code, title, colour, instructor, usual room.
 */
function bsinfo2a_subjects(): array
{
    return [
        ['GEC 107', 'Science, Technology and Society', 'lavender', 'Marian Angela Mae Bicomong', 'CCS LEC 5'],
        ['ITEC 204', 'Data Structure and Algorithm', 'peach', 'Criselda C. Encanto', 'CCS LAB 2'],
        ['ITEC 205', 'Information Management', 'mint', 'Concepcion R. Repalam', 'CCS LEC 6'],
        ['ITEP 203', 'Quantitative Methods including Modeling and Simulation', 'lavender', 'Sarah Escote', 'CCS LEC 9'],
        ['ITEL 201', 'Object-Oriented Programming', 'yellow', 'Reymar Manaloto', 'CCS LEC 7'],
        ['ITEL 202', 'Platform Technologies', 'peach', 'Prince Ross Andres', 'CCS LEC 1'],
        ['SOSLIT', 'Sosyedad at Literatura / Panitikang Panlipunan', 'yellow', 'Janine Balinsayo', 'CCS LEC 2'],
        ['PATHFit 3', 'Choice of Dance, Sports, Martial Arts, Group Exercise, Outdoor and Adventure Activities', 'mint', 'Marson David Alfaro Moreno', 'Gym'],
    ];
}

/**
 * BSINFO 2A's weekly class schedule (CCS, First Semester AY 2026-2027): code, day (1 = Monday), start, end,
 * and the room when that meeting is not in the subject's usual room (e.g. the lab day).
 */
function bsinfo2a_schedule(): array
{
    return [
        ['ITEP 203', 1, '07:00', '08:00', null],
        ['SOSLIT', 1, '08:00', '09:00', null],
        ['ITEL 202', 1, '09:00', '10:00', null],
        ['ITEC 204', 1, '11:00', '14:00', null],
        ['ITEC 205', 1, '15:00', '18:00', 'CCS LAB 4'],
        ['ITEC 205', 2, '08:00', '09:00', null],
        ['ITEL 201', 2, '09:00', '11:00', null],
        ['ITEC 204', 2, '12:00', '13:00', 'CCS LEC 8'],
        ['ITEP 203', 2, '13:00', '14:00', 'Room TBA'],
        ['ITEL 202', 2, '15:00', '18:00', 'CCS LAB 6'],
        ['ITEL 202', 3, '08:00', '09:00', null],
        ['ITEC 205', 3, '09:00', '10:00', null],
        ['ITEP 203', 3, '10:00', '11:00', null],
        ['GEC 107', 3, '12:00', '13:00', null],
        ['ITEL 201', 3, '15:00', '18:00', 'CCS LAB 4'],
        ['PATHFit 3', 4, '07:00', '09:00', null],
        ['GEC 107', 4, '09:00', '11:00', null],
        ['SOSLIT', 4, '12:00', '14:00', null],
    ];
}

/** The 8 BSINFO 2A core subjects (code, title, colour). Also offered when creating a section. */
function core_curriculum(): array
{
    $out = [];
    foreach (bsinfo2a_subjects() as [$code, $title, $color]) {
        $out[] = [$code, $title, $color];
    }
    return $out;
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

    // --- Subjects (BSINFO-2A's real subjects; 2B is a smaller demo section) ---------------
    $rooms = [];
    $s = [];       // BSINFO-2A subjects by code
    $s2 = [];      // BSINFO-2B subjects by code
    $core = [];
    foreach (bsinfo2a_subjects() as [$code, $title, $color, $instructor, $room]) {
        $core[$code] = [$code, $title, $color];
        $rooms[$code] = [$instructor, $room];
        $s[$code] = Store::insert('subjects', [
            'section_id' => $sec['BSINFO-2A'], 'code' => $code, 'title' => $title, 'color_tag' => $color,
            'instructor' => $instructor, 'room' => $room,
        ]);
    }
    foreach ([['GEC 107', 'Room 205'], ['ITEC 204', 'Comp Lab 4'], ['ITEL 201', 'Comp Lab 3']] as [$code, $room]) {
        $meta = $core[$code];
        $s2[$code] = Store::insert('subjects', [
            'section_id' => $sec['BSINFO-2B'], 'code' => $code, 'title' => $meta[1], 'color_tag' => $meta[2],
            'instructor' => $rooms[$code][0], 'room' => $room,
        ]);
    }

    // --- Weekly class schedule: each meeting has its own day, start, end (and room on lab days) --------
    foreach (bsinfo2a_schedule() as [$code, $dow, $start, $end, $room]) {
        Store::insert('class_schedules', [
            'section_id' => $sec['BSINFO-2A'], 'subject_id' => $s[$code],
            'day_of_week' => $dow, 'day_name' => $dayNames[$dow], 'start_time' => $start, 'end_time' => $end, 'room' => $room,
        ]);
    }
    foreach ([['GEC 107', 3, '08:00', '09:30'], ['ITEC 204', 4, '13:00', '16:00'], ['ITEL 201', 1, '10:00', '11:30']] as [$code, $dow, $start, $end]) {
        Store::insert('class_schedules', [
            'section_id' => $sec['BSINFO-2B'], 'subject_id' => $s2[$code],
            'day_of_week' => $dow, 'day_name' => $dayNames[$dow], 'start_time' => $start, 'end_time' => $end,
        ]);
    }

    // --- Approved tasks (inserted out of order on purpose: the Linked List sorts them)
    // $group = [setup, members per group, number of groups, assigned list] for group work (a GroupRule
    // subclass), null for individual work. Setups: count | size | size_count | assigned.
    $task = function (int $sectionId, int $subjectId, int $by, string $cat, string $title, string $desc, ?string $date, ?string $time, string $where, string $status, string $created, ?array $group = null) use ($u) {
        $columns = [];
        if ($group) {
            $plan = null;
            if (isset($group[3])) {
                $plan = [];
                foreach ($group[3] as $name => $people) {
                    $ids = [];
                    foreach ($people as $username) {
                        $ids[] = $u[$username];
                    }
                    $plan[] = ['name' => $name, 'member_ids' => $ids];
                }
            }
            $columns = GroupRule::create($group[0], $group[1] ?? null, $plan !== null ? count($plan) : ($group[2] ?? null))->toColumns()
                + ['group_plan' => $plan !== null ? json_encode($plan) : null];
        }
        return Store::insert('tasks', $columns + [
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
            'status' => $status,
            'reviewed_by' => $status === 'approved' ? $u['juan.delacruz'] : null,
            'created_at' => $created,
        ]);
    };
    $A = $sec['BSINFO-2A'];
    $admin = $u['juan.delacruz'];
    $task($A, $s['GEC 107'], $admin, 'Exam', 'Final Exam — Chapters 1–6', 'Coverage: all chapters discussed after the midterm. Bring a blue pen.', $day(30), '08:00', 'Room 204', 'approved', $stamp('-2 days'));
    $finalProject = $task($A, $s['ITEC 204'], $admin, 'Project', 'Final Project — AcadSync', "Build a working web app that uses 7 data structures and 5 algorithms.\n- Source code pushed to the class repo\n- Live demo during system checking\n- Printed documentation", null, null, 'Live demo + class drive', 'approved', $stamp('-10 days'), ['size', 4]);
    $reporting = $task($A, $s['GEC 107'], $admin, 'Reporting', 'Reporting — Ch. 3', "Each group presents Chapter 3 — The Scientific Revolution — for 15 minutes, then answers questions for 5 minutes.\n- Slides uploaded to the class drive\n- One printed handout per group", date('Y-m-d', $inHours), date('H:00', $inHours), 'In class · Room 204', 'approved', $stamp('-2 days'), ['count', null, 3]);
    // The prof gave the groups and their members (AssignedGroups): made at posting, only officers change them
    $task($A, $s['ITEC 205'], $admin, 'Project', 'Case Study — Library Database', "Design and normalize a database for the campus library.\n- ERD and data dictionary\n- SQL script", $day(12), '17:00', 'Room 301', 'approved', $stamp('-1 day'),
        ['assigned', null, null, ['Team Dewey' => ['juan.delacruz', 'maria.santos', 'paolo.reyes'], 'Team Turing' => ['ana.bautista', 'kyla.lim', 'marco.cruz', 'carlo.domingo']]]);
    $task($A, $s['ITEL 202'], $admin, 'Activity', 'Web Mockup Draft', 'Submit a low-fidelity mockup of your landing page as a PDF.', $day(1), '17:00', 'Google Classroom', 'approved', $stamp('-3 days'));
    $task($A, $s['SOSLIT'], $admin, 'Film viewing', 'Film Reflection Paper', 'Watch the assigned film and write a 2-page reflection.', $day(9), '23:59', 'Google Classroom', 'approved', $stamp('-1 day'));
    $task($A, $s['ITEC 204'], $admin, 'Activity', 'Lab Activity 3', 'Implement a singly linked list with insert, delete and traverse.', $day(0), '23:59', 'Comp Lab 2', 'approved', $stamp('-4 days'));
    $task($A, $s['GEC 107'], $admin, 'Quiz', 'Quiz 1 — Ch. 4', '20-item multiple choice quiz on Chapter 4.', $day(5), '13:00', 'Room 204', 'approved', $stamp('-1 day'));
    $task($A, $s['ITEC 205'], $admin, 'Activity', 'ERD Exercise', 'Draw an ERD for a library system with at least 5 entities.', $day(3), '10:00', 'Room 301', 'approved', $stamp('-1 day'));

    // --- Pending student requests, enqueued in arrival order ---------------------
    $pending = [
        ['ITEL 201', 'Quiz', 'OOP Quiz', '20-item quiz covering classes, objects and inheritance. Closed notes, 30 minutes.', $day(2), '08:00', 'maria.santos', '-3 days'],
        ['ITEC 204', 'Activity', 'Array Exercise', 'Solve 5 array manipulation problems and submit the .php file.', $day(4), '23:59', 'paolo.reyes', '-2 days'],
        ['GEC 107', 'Reporting', 'Group Reporting — Ch. 4', 'Groups 4–6 report on Chapter 4 — Technology and Society.', $day(6), '13:00', 'ana.bautista', '-2 days', ['size_count', 3, 2]],
        ['ITEC 205', 'Activity', 'Lab Activity 4', 'Normalize the given table up to 3NF.', $day(7), '10:00', 'carlo.domingo', '-1 day'],
        ['ITEL 202', 'Activity', 'Web Mockup', 'High-fidelity mockup of the landing page in Figma.', $day(8), '17:00', 'kyla.lim', '-2 hours', ['size', 3]],
    ];
    foreach ($pending as $p) {
        [$code, $cat, $title, $desc, $date, $time, $by, $ago] = $p;
        $id = $task($A, $s[$code], $u[$by], $cat, $title, $desc, $date, $time, 'In class', 'pending', $stamp($ago), $p[8] ?? null);
        Store::insert('approval_queue', ['section_id' => $A, 'task_id' => $id, 'queued_at' => $stamp($ago)]);
    }

    // BSINFO-2B has its own, separate tasks
    $B = $sec['BSINFO-2B'];
    $task($B, $s2['ITEC 204'], $u['bea.garcia'], 'Quiz', 'Stacks & Queues Quiz', 'Short quiz on stack and queue operations.', $day(2), '13:00', 'Comp Lab 4', 'approved', $stamp('-1 day'));
    $reporting2B = $task($B, $s2['GEC 107'], $u['bea.garcia'], 'Reporting', 'Reporting — Ch. 2', 'Group 1 and 2 report on Chapter 2.', $day(4), '08:00', 'Room 205', 'approved', $stamp('-1 day'), ['size_count', 2, 2]);
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

    // --- Officers: Juan (admin) is the President; he chose a Vice President, a Secretary and a Treasurer ---
    foreach ([['maria.santos', 'vice_president'], ['kyla.lim', 'secretary'], ['marco.cruz', 'treasurer']] as [$who, $position]) {
        foreach (Store::all('memberships') as $m) {
            if ((int) $m['section_id'] === $A && (int) $m['user_id'] === $u[$who]) {
                Store::update('memberships', (int) $m['id'], ['position' => $position]);
            }
        }
        Store::insert('officer_terms', ['section_id' => $A, 'user_id' => $u[$who], 'position' => $position, 'started_at' => $stamp('-6 weeks')]);
    }
    // Carlo is on leave this semester (his absences don't count)
    foreach (Store::all('memberships') as $m) {
        if ((int) $m['section_id'] === $A && (int) $m['user_id'] === $u['carlo.domingo']) {
            Store::update('memberships', (int) $m['id'], ['status' => 'inactive']);
        }
    }

    // --- Activities: [kind, title, venue, day offset, start, end, capacity, description, going, came] ---
    $activities = [
        ['meeting', 'General Assembly', 'Room 204', -14, '15:00', '16:30', null, 'Opening assembly: officers, plans for the semester.', [], ['juan.delacruz', 'maria.santos', 'kyla.lim', 'ana.bautista', 'marco.cruz']],
        ['seminar', 'Git & GitHub Workshop', 'Comp Lab 1', -7, '13:00', '15:00', 6, 'Hands-on: branches, pull requests, merge conflicts.', ['maria.santos', 'ana.bautista', 'paolo.reyes', 'kyla.lim'], ['maria.santos', 'ana.bautista', 'kyla.lim']],
        ['outreach', 'Coding Lesson for Grade 10', 'San Pablo City NHS', -3, '08:00', '11:00', 5, 'Teach basic HTML to Grade 10 students.', ['juan.delacruz', 'marco.cruz'], ['juan.delacruz', 'marco.cruz', 'paolo.reyes']],
        ['meeting', 'Midterm Planning Meeting', 'Room 204', 3, '15:00', '16:00', null, 'Plan review sessions before the midterms.', [], []],
        ['seminar', 'Intro to Figma', 'AVR Hall', 6, '13:00', '15:00', 3, 'Design a mobile screen from scratch.', ['ana.bautista', 'kyla.lim', 'marco.cruz', 'paolo.reyes'], []],
    ];
    foreach ($activities as [$kind, $title, $venue, $offset, $start, $end, $cap, $desc, $going, $came]) {
        $aid = Store::insert('activities', [
            'section_id' => $A, 'kind' => $kind, 'title' => $title, 'description' => $desc, 'venue' => $venue,
            'starts_at' => $day($offset) . " $start", 'ends_at' => $day($offset) . " $end", 'capacity' => $cap,
            'created_by' => $u['juan.delacruz'], 'created_at' => $stamp('-3 weeks'),
        ]);
        foreach ($going as $i => $who) {   // past the capacity, people wait in line (the QUEUE)
            Store::insert('activity_rsvps', ['section_id' => $A, 'activity_id' => $aid, 'user_id' => $u[$who],
                'status' => $cap !== null && $i >= $cap ? 'waitlist' : 'going', 'created_at' => $stamp('-2 weeks')]);
        }
        foreach ($came as $who) {
            Store::insert('attendance', ['section_id' => $A, 'activity_id' => $aid, 'user_id' => $u[$who], 'checked_in_by' => $u['kyla.lim']]);
        }
    }

    // --- A competition and a section project, built through the Activity / ActivityPart classes so the seed
    //     follows the same rules. Parts: [name, spots (null = any number), together, roles, due offset, details]
    //     People: [part index, username, status, role, note, done]
    $partsActivity = function (array $row, array $parts, array $people) use ($A, $u, $day, $stamp): void {
        $a = Activity::fromRow($row + ['created_by' => $u['juan.delacruz']]);
        $aid = Store::insert('activities', $a->toRow() + ['section_id' => $A, 'created_at' => $stamp('-2 days')]);
        $ids = [];
        foreach ($parts as $i => [$name, $spots, $together, $roles, $due, $details]) {
            $p = ActivityPart::fromRow(['activity_id' => $aid, 'name' => $name, 'spots' => $spots, 'together' => $together, 'roles' => $roles,
                'due_date' => $due === null ? null : $day($due), 'details' => $details, 'position' => $i], $a);
            $ids[$i] = Store::insert('activity_parts', $p->toRow() + ['section_id' => $A]);
        }
        foreach ($people as [$i, $who, $status, $role, $note, $done]) {
            Store::insert('part_members', ['section_id' => $A, 'part_id' => $ids[$i], 'user_id' => $u[$who], 'status' => $status,
                'volunteered' => $status === 'volunteer' || $note !== null ? 1 : 0, 'role' => $role, 'note' => $note,
                'picked_by' => $status === 'picked' ? $u['juan.delacruz'] : null, 'picked_at' => $status === 'picked' ? $stamp('-1 day') : null,
                'created_at' => $stamp('-1 day')]);
            if ($done) {
                Store::update('activity_parts', $ids[$i], ['done_at' => $stamp('-3 hours'), 'done_by' => $u[$who]]);
            }
        }
    };
    $partsActivity(['kind' => 'competition', 'title' => 'Founding Anniversary', 'venue' => 'LSPU Gym',
        'description' => 'Two days of contests for the college anniversary. Volunteer for a part; the officers pick who represents the section.',
        'starts_at' => $day(5) . ' 08:00', 'ends_at' => $day(6) . ' 17:00', 'link' => 'https://www.facebook.com/', 'signup_deadline' => $day(0)], [
        ['Cosplay Competition', 2, false, [], null, 'Come in costume as any character.'],
        ['College Culinary Showdown', 4, true, [], null, 'One team of 4 cooks a dish in 60 minutes.'],
        ['Dating game', null, false, [], null, 'Anyone can join on the day.'],
        ['Guess the Song', 2, false, [], null, ''],
        ["90's Remake: Video", 10, true, ['Director', 'Editor', 'Camera', 'Actor'], null, 'Remake a 90’s scene in 3 minutes.'],
    ], [
        [0, 'ana.bautista', 'volunteer', null, 'I sew my own costumes', false],
        [1, 'marco.cruz', 'picked', null, 'I cook at our carinderia', false],
        [3, 'paolo.reyes', 'picked', null, 'I know every OPM song', false],
        [4, 'maria.santos', 'volunteer', 'Director', 'I directed our Grade 12 short film', false],
    ]);
    $partsActivity(['kind' => 'project', 'title' => 'Booth design', 'venue' => 'Quadrangle',
        'description' => 'Our section’s booth for the anniversary fair.', 'starts_at' => $day(0) . ' 08:00', 'ends_at' => $day(14) . ' 17:00'], [
        ['Poster', 1, false, [], 3, 'A3 poster with the section name and theme.'],
        ['Booth frame', 2, true, [], 10, 'Bamboo frame, 2 m × 2 m.'],
        ['Logo', 1, false, [], 5, ''],
        ['Flyers', 2, false, [], 12, '100 copies, half-page.'],
    ], [
        [0, 'paolo.reyes', 'picked', null, null, true],
        [1, 'maria.santos', 'picked', null, null, false],
        [1, 'marco.cruz', 'picked', null, null, false],
        [2, 'kyla.lim', 'picked', null, null, false],
    ]);

    // --- Dues: Marco (Treasurer) collects the membership fee; amounts are centavos ----------------
    $fee = Store::insert('dues', ['section_id' => $A, 'title' => 'Membership fee', 'amount' => 15000, 'due_date' => $day(10),
        'created_by' => $u['marco.cruz'], 'created_at' => $stamp('-10 days')]);
    $shirt = Store::insert('dues', ['section_id' => $A, 'title' => 'Org shirt', 'amount' => 35000, 'due_date' => $day(21),
        'created_by' => $u['marco.cruz'], 'created_at' => $stamp('-2 days')]);
    foreach ([
        [$fee, 'juan.delacruz', 15000, 'cash', 'OR-0001', '-9 days'],
        [$fee, 'kyla.lim', 15000, 'gcash', '1009876543210', '-8 days'],
        [$fee, 'maria.santos', 10000, 'cash', null, '-5 days'],          // part-payment: ₱50 left
        [$shirt, 'ana.bautista', 35000, 'gcash', '1012345678901', '-1 day'],
    ] as [$dueId, $who, $amount, $method, $ref, $ago]) {
        Store::insert('payments', ['section_id' => $A, 'due_id' => $dueId, 'user_id' => $u[$who], 'amount' => $amount,
            'method' => $method, 'reference' => $ref, 'received_by' => $u['marco.cruz'], 'paid_at' => $stamp($ago)]);
    }

    // --- Announcements: made through the Announcement classes, so seed data follows the same rules
    //     (a typed announcement does its real change: the No class one cancels a class on the Schedule)
    $announce = function (string $by, string $kind, array $in, string $ago) use ($A, $u, $stamp): void {
        $user = null;
        foreach (section_members($A) as $m) {
            if ((int) $m['id'] === $u[$by]) {
                $user = $m;
            }
        }
        $c = announcement_context($user, load_context($user));
        $a = Announcement::fromInput($kind, $in, $c);
        $id = Store::insert('announcements', ['created_at' => $stamp($ago)] + $a->toRow($A));
        $a->apply($c, $id);
    };
    $announce('marco.cruz', 'payment', ['due_id' => $fee, 'pinned' => true,
        'body' => 'Pay Marco (Treasurer) in cash or by GCash, and send him the reference number.'], '-3 days');
    $figma = null;
    foreach (Store::all('activities') as $act) {
        $figma = $act['title'] === 'Intro to Figma' ? (int) $act['id'] : $figma;
    }
    $announce('kyla.lim', 'event', ['activity_id' => $figma, 'body' => 'Bring a laptop with Figma installed.'], '-2 days');
    // No ITEC 204 class on the next day it meets
    $itec = $s['ITEC 204'];
    $meets = [];
    foreach (Store::all('class_schedules') as $cs) {
        if ((int) $cs['subject_id'] === $itec) {
            $meets[(int) $cs['day_of_week']] = true;
        }
    }
    for ($i = 1; $i <= 7; $i++) {
        if (isset($meets[(int) date('N', strtotime("+$i day", $now))])) {
            $announce('juan.delacruz', 'no_class', ['date' => $day($i), 'subject_id' => $itec,
                'body' => 'Prof is at the regional seminar. Use the time for the final project.'], '-1 day');
            break;
        }
    }
    $announce('kyla.lim', 'general', ['title' => 'Room change for the planning meeting',
        'body' => 'The Midterm Planning Meeting stays in Room 204. Bring your class schedule.'], '-1 day');

    // --- Leo (BSINFO-2B) entered 2A's class code and waits for an officer --------------
    Store::insert('join_requests', ['section_id' => $A, 'user_id' => $u['leo.navarro'], 'created_at' => $stamp('-5 hours')]);

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

    // --- Make each published group task's groups, as posting a task does (empty groups when the
    //     number of groups is set; the prof's own list for an assigned task)
    foreach (Store::all('tasks') as $t) {
        if ($t['status'] === 'approved' && $t['work_mode'] === 'group') {
            sync_task_groups((int) $t['section_id'], (int) $t['id'], (int) $t['requested_by']);
        }
    }

    Store::commit();
}
