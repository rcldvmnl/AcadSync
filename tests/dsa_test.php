<?php
/**
 * Tests for the hand-written data structures and algorithms in backend/custom_dsa.php.
 *
 * Run from the project folder:
 *
 *     C:\xampp\php\php.exe tests/dsa_test.php
 *
 * Every check prints PASS or FAIL; the script exits with 1 when anything failed.
 * It never touches the database: it only loads custom_dsa.php and the Deadline classes.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {   // never runnable from a browser
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../backend/domain/Deadline.php';
require_once __DIR__ . '/../backend/custom_dsa.php';
require_once __DIR__ . '/../backend/domain/Task.php';
require_once __DIR__ . '/../backend/domain/Activity.php';
require_once __DIR__ . '/../backend/domain/ActivityPart.php';
require_once __DIR__ . '/../backend/domain/MemberStatus.php';
require_once __DIR__ . '/../backend/domain/Person.php';
require_once __DIR__ . '/../backend/domain/MemberRecord.php';
require_once __DIR__ . '/../backend/domain/Payment.php';
require_once __DIR__ . '/../backend/domain/Announcement.php';
require_once __DIR__ . '/../backend/services.php';   // defines functions only; nothing touches the database here

$passed = 0;
$failed = 0;
/** Runs $f and returns the InvalidArgumentException message, or 'built' when nothing was refused. */
$refused = function (callable $f): string {
    try {
        $f();
        return 'built';
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
};

function check(string $name, mixed $actual, mixed $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "  PASS  $name\n";
        return;
    }
    $failed++;
    echo "  FAIL  $name\n        expected " . json_encode($expected) . "\n        got      " . json_encode($actual) . "\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

/** Values of one key, in order. */
function pluck(array $rows, string $key): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[] = $r[$key];
    }
    return $out;
}

function task(int $id, ?string $date, string $title = 'Task'): array
{
    return ['id' => $id, 'title' => $title, 'description' => '', 'subject_id' => 1, 'deadline' => $date, 'deadline_end' => null, 'deadline_time' => '12:00'];
}

// ---------------------------------------------------------------------------
section('1. Array (FixedArray, SubjectArray, WeekGrid)');
$arr = new SubjectArray(3);
check('add fills free slots', [$arr->add(['code' => 'IT 101']), $arr->add(['code' => 'GEC 107']), $arr->add(['code' => 'PE 3'])], [true, true, true]);
check('add refuses when full', $arr->add(['code' => 'NSTP']), false);
check('get by index', $arr->get(1)['code'], 'GEC 107');
check('get out of range is null', $arr->get(5), null);
check('indexOf finds a record', $arr->indexOf('code', 'PE 3'), 2);
check('indexOf misses with -1', $arr->indexOf('code', 'NSTP'), -1);

$grid = new WeekGrid();
$grid->place(1, '08:00', '09:30', 7);
check('cellRange 13:00-14:30', WeekGrid::cellRange('13:00', '14:30'), [156, 173]);
check('clash is found', $grid->occupants(1, '09:00', '10:00'), [7]);
check('back-to-back class is no clash', $grid->occupants(1, '09:30', '10:30'), []);
check('other day is free', $grid->occupants(2, '08:00', '09:30'), []);

// ---------------------------------------------------------------------------
section('2. Linked list (TaskLinkedList) + Insertion Sort');
$list = new TaskLinkedList();
foreach ([task(1, '2026-12-10'), task(2, null), task(3, '2026-12-01'), task(4, '2026-12-05')] as $t) {
    $list->insertChronologically(new TaskNode($t));
}
check('insert keeps date order, no date last', pluck($list->toArray(), 'id'), [3, 4, 1, 2]);
check('length', $list->length(), 4);
$nb = $list->neighbors(4);
check('neighbors via prev/next', [$nb['prev']['id'], $nb['next']['id'], $nb['position']], [3, 1, 2]);
check('deleteById from the middle', $list->deleteById(4)['id'], 4);
check('order after delete', pluck($list->toArray(), 'id'), [3, 1, 2]);
check('prev link re-pointed', $list->neighbors(1)['prev']['id'], 3);
check('delete missing id', $list->deleteById(99), null);
$dropped = $list->removeExpired(strtotime('2026-12-03'));
check('removeExpired drops the head', pluck($dropped, 'id'), [3]);
check('new head has no prev', $list->neighbors(1)['prev'], null);
check('positionOf', $list->positionOf(2), 2);

// ---------------------------------------------------------------------------
section('3. HashSet');
$set = new HashSet();
check('add new value', $set->add('Ana@LSPU.edu.ph'), true);
check('duplicate rejected (case/space-insensitive)', $set->add('  ana@lspu.edu.ph '), false);
check('contains', $set->contains('ANA@lspu.edu.ph'), true);
check('does not contain', $set->contains('bea@lspu.edu.ph'), false);
check('size counts unique values', $set->size(), 1);
$many = new HashSet(5);   // few buckets, so values must share (chain in) a bucket
for ($i = 0; $i < 20; $i++) {
    $many->add("id-$i");
}
check('separate chaining keeps every value', [$many->size(), $many->contains('id-17'), $many->contains('id-20')], [20, true, false]);

// ---------------------------------------------------------------------------
section('4. Stack (UndoStack)');
$stack = new UndoStack();
check('empty pop is null', $stack->pop(), null);
$stack->push('a');
$stack->push('b');
$stack->push('c');
check('peek is the last push', $stack->peek(), 'c');
check('toArray is top first', $stack->toArray(), ['c', 'b', 'a']);
check('pop is LIFO', [$stack->pop(), $stack->pop()], ['c', 'b']);
check('size after pops', $stack->size(), 1);
$mid = new UndoStack();
foreach (['r1', 'r2', 'r3', 'r4'] as $v) {
    $mid->push($v);
}
check('take one from the middle', $mid->removeWhere(fn ($v) => $v === 'r2'), 'r2');
check('the others keep their order', [$mid->toArray(), $mid->size()], [['r4', 'r3', 'r1'], 3]);
check('take a missing one', [$mid->removeWhere(fn ($v) => $v === 'zz'), $mid->toArray()], [null, ['r4', 'r3', 'r1']]);

// ---------------------------------------------------------------------------
section('5. Queue (ApprovalQueue)');
$queue = new ApprovalQueue();
check('empty dequeue is null', $queue->dequeue(), null);
foreach (['first', 'second', 'third'] as $v) {
    $queue->enqueue($v);
}
check('peek is the front', $queue->peek(), 'first');
check('dequeue is FIFO', [$queue->dequeue(), $queue->dequeue()], ['first', 'second']);
$queue->enqueue('fourth');
check('rear keeps growing', $queue->toArray(), ['third', 'fourth']);
$queue->dequeue();
$queue->dequeue();
check('empty again', [$queue->isEmpty(), $queue->size()], [true, 0]);

// ---------------------------------------------------------------------------
section('6. Tree (SubjectTree DFS, ChecklistTree post-order)');
$tree = new SubjectTree('BSINFO-2A');
$tree->addSubject(['id' => 1, 'code' => 'IT 101', 'title' => 'Programming']);
$tree->addSubject(['id' => 2, 'code' => 'GEC 107', 'title' => 'Ethics']);
$tree->addTask(2, ['id' => 9, 'title' => 'Reporting: Ch. 3']);
check('addTask to a missing subject', $tree->addTask(5, ['id' => 10, 'title' => 'x']), false);
$hits = $tree->search('report');
check('DFS finds the task with its path', [count($hits), $hits[0]['path']], [1, ['BSINFO-2A', 'GEC 107', 'Reporting: Ch. 3']]);
check('DFS matches a subject title', $tree->search('ethic')[0]['node']['code'], 'GEC 107');
check('no match', $tree->search('zzz'), []);

$check = ChecklistTree::fromRows('Project', [
    ['id' => 3, 'title' => 'Intro', 'done' => 1, 'assignee_id' => null, 'parent_id' => 1],   // child stored before its parent
    ['id' => 1, 'title' => 'Chapter 1', 'done' => 0, 'assignee_id' => null, 'parent_id' => null],
    ['id' => 4, 'title' => 'RRL', 'done' => 0, 'assignee_id' => null, 'parent_id' => 1],
    ['id' => 2, 'title' => 'Slides', 'done' => 1, 'assignee_id' => null, 'parent_id' => null],
]);
check('rollup counts leaves [done, total]', $check->rollup(), [2, 3]);
check('parent not done while a child is open', $check->find(1)->data['done'], false);
check('subtreeIds is post-order', $check->subtreeIds($check->find(1)), [3, 4, 1]);
check('depthOf a sub-item', $check->depthOf(4), 2);

// ---------------------------------------------------------------------------
section('7. Graph (DependencyGraph BFS, shortest path)');
$g = new DependencyGraph();
$g->addEdge(1, 2, 'Group 1', 10);
$g->addEdge(2, 3, 'Group 1', 10);
$g->addEdge(3, 4, 'Group 3', 30);
$g->addEdge(5, 6, 'Group 3', 31);   // same name, other task: must not mix with group 30
check('BFS team inside one group', $g->teamMembers(1, 'Group 1', 10), [1, 2, 3]);
check('same group name, other id is separate', $g->teamMembers(4, 'Group 3', 30), [4, 3]);
check('shortest path across groups', $g->shortestPath(1, 4), [1, 2, 3, 4]);
check('not connected is null', $g->shortestPath(1, 6), null);
check('isLinked / degree', [$g->isLinked(2, 3), $g->isLinked(1, 3), $g->degree(2)], [true, false, 2]);

// ---------------------------------------------------------------------------
section('Sorting algorithms');
$rows = [['n' => 5], ['n' => 2], ['n' => 9], ['n' => 2], ['n' => 1]];
$steps = 0;
check('Merge Sort ascending', pluck(Algorithms::mergeSortBy($rows, 'n', false, $steps), 'n'), [1, 2, 2, 5, 9]);
check('Merge Sort descending', pluck(Algorithms::mergeSortBy($rows, 'n', true), 'n'), [9, 5, 2, 2, 1]);
check('Bubble Sort', pluck(Algorithms::bubbleSortBy($rows, 'n', false), 'n'), [1, 2, 2, 5, 9]);
check('Selection Sort', pluck(Algorithms::selectionSortBy($rows, 'n', true), 'n'), [9, 5, 2, 2, 1]);
$stable = Algorithms::mergeSortBy([['k' => 1, 'tag' => 'a'], ['k' => 0, 'tag' => 'b'], ['k' => 1, 'tag' => 'c']], 'k', false);
check('Merge Sort is stable', pluck($stable, 'tag'), ['b', 'a', 'c']);
check('Merge Sort of empty list', Algorithms::mergeSortBy([], 'n', false), []);

$now = strtotime('2026-12-01 08:00');
$urgent = Algorithms::bubbleSortByUrgency([task(1, '2026-12-09'), task(2, null), task(3, '2026-12-02')], $now);
check('bubbleSortByUrgency: soonest first, no date last', pluck($urgent, 'id'), [3, 1, 2]);

$people = [
    ['first_name' => 'Maria', 'last_name' => 'Santos', 'student_id' => '2023-1071', 'username' => 'maria.santos'],
    ['first_name' => 'Ana', 'last_name' => 'Bautista', 'student_id' => '2023-1012', 'username' => 'ana.bautista'],
    ['first_name' => 'Carlo', 'last_name' => 'Domingo', 'student_id' => '2023-1051', 'username' => 'carlo.domingo'],
    ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'student_id' => '2023-1045', 'username' => 'juan.delacruz'],
];
check('selectionSortByLastName', pluck(Algorithms::selectionSortByLastName($people), 'last_name'), ['Bautista', 'Dela Cruz', 'Domingo', 'Santos']);
$byId = Algorithms::selectionSortByStudentId($people);
check('selectionSortByStudentId', pluck($byId, 'student_id'), ['2023-1012', '2023-1045', '2023-1051', '2023-1071']);

// ---------------------------------------------------------------------------
section('Searching algorithms');
check('Binary Search finds an ID', Algorithms::binarySearchByStudentId($byId, '2023-1051')['first_name'], 'Carlo');
check('Binary Search first / last', [Algorithms::binarySearchByStudentId($byId, '2023-1012')['first_name'], Algorithms::binarySearchByStudentId($byId, '2023-1071')['first_name']], ['Ana', 'Maria']);
check('Binary Search misses with null', Algorithms::binarySearchByStudentId($byId, '2023-9999'), null);

$timeline = [task(1, '2026-12-01'), task(2, '2026-12-03'), task(3, '2026-12-04'), task(4, '2026-12-08'), task(5, null)];
$steps = 0;
check('countDueBy (binary search on deadlines)', Algorithms::countDueBy($timeline, strtotime('2026-12-04 23:59'), $steps), 3);
check('countDueBy takes log steps', $steps <= 3, true);
check('countDueBy nothing due', Algorithms::countDueBy($timeline, strtotime('2026-11-01')), 0);

check('linearSearch finds index', Algorithms::linearSearch([4, 8, 15, 16], 15), 2);
check('linearSearch is strict (=== )', Algorithms::linearSearch([4, 8, 15], '8'), -1);
check('linearContains', [Algorithms::linearContains([3, 7], 7), Algorithms::linearContains([], 1)], [true, false]);
check('linearSearchByName (partial, any case)', pluck(Algorithms::linearSearchByName($people, 'DO'), 'first_name'), ['Carlo']);
$dupes = Algorithms::linearSearchTasks([task(1, null, 'Reporting chapter three'), task(2, null, 'Quiz on loops')], 'Chapter three reporting');
check('linearSearchTasks flags a likely duplicate', pluck(pluck($dupes, 'task'), 'id'), [1]);

// ---------------------------------------------------------------------------
section('Deadline placement (where a task sits on the schedule)');
$friNoon = strtotime('2026-11-20 12:00');
$range = Deadlines::fromRow(['deadline' => '2026-11-20', 'deadline_end' => '2026-11-23', 'deadline_time' => '23:59']);
check('open range (Nov 20-23) counts from now', $range->attentionAt($friNoon), $friNoon);
check('open range still sorts by its last day', date('Y-m-d H:i', $range->timestamp()), '2026-11-23 23:59');
$later = Deadlines::fromRow(['deadline' => '2026-11-25', 'deadline_end' => '2026-11-27', 'deadline_time' => null]);
check('range opening later counts from its first day', date('Y-m-d H:i', $later->attentionAt($friNoon)), '2026-11-25 00:00');
$exact = Deadlines::fromRow(['deadline' => '2026-11-24', 'deadline_end' => null, 'deadline_time' => '08:00']);
check('exact date counts at its deadline', date('Y-m-d H:i', $exact->attentionAt($friNoon)), '2026-11-24 08:00');
check('no date has no place', Deadlines::fromRow(['deadline' => null])->attentionAt($friNoon), null);

// ---------------------------------------------------------------------------
section('Group setups (GroupRule subclasses)');
$sg = fn (int $id, int $size) => new StudyGroup($id, "Group $id", 1, 1, null, $size ? range(1, $size) : []);

// 2. "Make 5 groups", 33 classmates -> 3 groups of 7 and 2 groups of 6
$byCount = GroupRule::create(GroupRule::BY_COUNT, null, 5);
check('count: 33 in 5 groups -> 7,7,7,6,6', $byCount->sizes(33), [7, 7, 7, 6, 6]);
check('count: makes all groups at posting', $byCount->makesEmptyGroups(), true);
$state = $byCount->evaluate([$sg(1, 6), $sg(2, 6), $sg(3, 6), $sg(4, 6), $sg(5, 6)], 33);
check('count: 3 groups may still take a 7th', [$state['base'], $state['extras_left'], $state['can_create']], [6, 3, false]);
check('count: a full group of 6 has room for 1 extra', $byCount->capacityOf($sg(1, 6), $state), 7);
$state = $byCount->evaluate([$sg(1, 7), $sg(2, 7), $sg(3, 7), $sg(4, 6), $sg(5, 5)], 33);
check('count: once 3 groups have 7, the rest stay at 6', [$byCount->capacityOf($sg(4, 6), $state), $byCount->capacityOf($sg(5, 5), $state)], [6, 6]);

// 3. "4 per group", 33 classmates -> 8 groups of 4, 1 left (joins as 5th or makes a smaller group)
$bySize = GroupRule::create(GroupRule::BY_SIZE, 4, null);
check('size: 33 by 4 -> 8 groups, one may have 5', $bySize->sizes(33), [5, 4, 4, 4, 4, 4, 4, 4]);
check('size: groups are made by students', [$bySize->makesEmptyGroups(), $bySize->evaluate([], 33)['can_create']], [false, true]);
check('size: explains the leftover', str_contains($bySize->explain(33), '1 classmate left') && str_contains($bySize->explain(33), '5th member'), true);
$plan = $bySize->autoPlan([4, 4, 4, 4, 4, 4, 4, 4], 1, 33);
check('size: auto-group puts the 1 left into a group as +1', [$plan['add'][0], $plan['new'], $plan['left']], [1, [], 0]);
$plan = $bySize->autoPlan([], 7, 7);
check('size: 7 left by 4 -> a group of 4 and a smaller group of 3', $plan['new'], [4, 3]);

// 4. "8 groups of 4", 33 classmates -> 32 seats, 1 group may take a 5th
$both = GroupRule::create(GroupRule::BY_SIZE_AND_COUNT, 4, 8);
check('both: 33 in 8 groups of 4 -> one 5', $both->sizes(33), [5, 4, 4, 4, 4, 4, 4, 4]);
check('both: 30 classmates -> some groups under 4', str_contains($both->explain(30), 'fewer than 4'), true);
check('both: 42 classmates -> 2 can\'t fit', $both->seats(42)['unplaceable'], 2);
$plan = $both->autoPlan([4, 4, 4, 4, 4, 4, 4, 0], 5, 33);
check('both: auto-group fills the empty group, then 1 extra', [$plan['add'][7], array_sum($plan['add']), $plan['left']], [4, 5, 0]);

// 1. Assigned by the prof: students can't organize, no auto-grouping
$assigned = GroupRule::create(GroupRule::ASSIGNED, null, 3);
check('assigned: students don\'t organize', [$assigned->studentsOrganize(), $assigned->evaluate([], 10)['can_create']], [false, false]);
check('assigned: auto-group leaves people to the officers', $assigned->autoPlan([3, 3], 4, 10)['left'], 4);

check('invalid rule is refused', (function () { try { GroupRule::create(GroupRule::BY_SIZE, 1, null); return 'built'; } catch (InvalidArgumentException) { return 'refused'; } })(), 'refused');
check('old rows: count + strict size -> both', GroupRule::fromRow(['group_min' => 3, 'group_max' => 3, 'group_count' => 5])->mode(), 'size_count');
check('old rows: a size range only -> per group', GroupRule::fromRow(['group_min' => 2, 'group_max' => 3, 'group_count' => null])->mode(), 'size');

// ---------------------------------------------------------------------------
section('Shared validation');
$ok = validate_deadline(['deadline_type' => 'range', 'deadline' => '2026-11-20', 'deadline_end' => '2026-11-23', 'deadline_time' => '23:59'], '2026-11-20');
check('deadline: range kept', [$ok['deadline_type'], $ok['deadline_end']], ['range', '2026-11-23']);
check('deadline: unset clears dates', validate_deadline(['deadline_type' => 'unset', 'deadline' => '2026-11-25'], '2026-11-20')['deadline'], null);
check('deadline: past exact refused', $refused(fn () => validate_deadline(['deadline_type' => 'exact', 'deadline' => '2026-11-19'], '2026-11-20')), 'The deadline can’t be in the past.');
check('deadline: bad time refused', $refused(fn () => validate_deadline(['deadline_type' => 'exact', 'deadline' => '2026-11-25', 'deadline_time' => '25:00'], '2026-11-20')), 'Pick a valid due time.');
check('deadline: range over refused', $refused(fn () => validate_deadline(['deadline_type' => 'range', 'deadline' => '2026-11-10', 'deadline_end' => '2026-11-12'], '2026-11-20')), 'That date range is already over.');
$a = validate_new_activity(['kind' => 'seminar', 'title' => 'Intro to Figma', 'venue' => 'AVR', 'date' => '2026-11-28', 'start' => '13:00', 'end' => '15:00', 'capacity' => '30'], 1, strtotime('2026-11-20 10:00'));
check('activity: built', [$a->kind(), $a->capacity()], ['seminar', 30]);
check('activity: past start refused', $refused(fn () => validate_new_activity(['kind' => 'meeting', 'title' => 'X', 'date' => '2026-11-19', 'start' => '13:00', 'end' => '14:00'], 1, strtotime('2026-11-20 10:00'))), 'That start time has already passed. Pick a later time or another day.');
check('activity: missing times refused', $refused(fn () => validate_new_activity(['kind' => 'meeting', 'title' => 'X', 'date' => '2026-11-28'], 1, strtotime('2026-11-20 10:00'))), 'Pick a date and the start and end times.');

// ---------------------------------------------------------------------------
section('Announcements (typed)');
$person = fn (int $id, string $role, ?string $position) => Person::fromRow(['id' => $id, 'first_name' => 'P' . $id, 'last_name' => 'X',
    'student_id' => "2023-00$id", 'email' => "$id@x", 'role' => $role, 'position' => $position, 'status' => 'active']);
$pres = $person(1, 'admin', null);
$secr = $person(6, 'student', 'secretary');
$taskRow = fn (int $id, string $status) => ['id' => $id, 'section_id' => 1, 'subject_id' => 4, 'title' => "Task $id", 'status' => $status, 'kind' => 'new',
    'work_mode' => 'individual', 'deadline' => '2026-11-25', 'deadline_end' => null, 'deadline_time' => '17:00', 'deadline_type' => 'exact'];
$annCtx = fn (Person $p, ?HashSet $cancelled = null) => new AnnouncementContext(
    sectionId: 1, userId: $p->id(), me: $p,
    tasks: [3 => $taskRow(3, 'approved'), 9 => $taskRow(9, 'removed')],
    subjects: [4 => ['id' => 4, 'code' => 'ITEC 204', 'title' => 'Data Structures']],
    slots: [['subject_id' => 4, 'day_of_week' => 5, 'start_time' => '13:00', 'end_time' => '14:30', 'code' => 'ITEC 204']],   // Fridays
    dues: [2 => new Due(2, 'Membership fee', 15000, '2026-11-30', 'open', 7)],
    activities: [], cancelled: $cancelled ?? new HashSet(), now: strtotime('2026-11-20 10:00'));   // Friday, Nov 20
$pc = $annCtx($pres);
check('ann: old row without kind reads as a notice', Announcement::fromRow(['id' => 1, 'title' => 'Hi', 'body' => '', 'pinned' => 0, 'created_by' => 1, 'created_at' => '2026-11-20 08:00']) instanceof GeneralNotice, true);
check('ann: no class sorts by its date', Announcement::fromInput('no_class', ['date' => '2026-11-27', 'subject_id' => 4], $pc)->eventAt(), strtotime('2026-11-27 00:00'));
check('ann: no class on a day the subject doesn\'t meet', $refused(fn () => Announcement::fromInput('no_class', ['date' => '2026-11-28', 'subject_id' => 4], $pc)), 'ITEC 204 has no class on Saturdays.');
$taken = new HashSet();
$taken->add('2026-11-27|*');
check('ann: no class twice refused', $refused(fn () => Announcement::fromInput('no_class', ['date' => '2026-11-27', 'subject_id' => 4], $annCtx($pres, $taken))), 'That class is already cancelled on Fri, Nov 27.');
check('ann: no class in the past refused', $refused(fn () => Announcement::fromInput('no_class', ['date' => '2026-11-19'], $pc)), 'Pick today or a later day.');
check('ann: no class default title', Announcement::fromInput('no_class', ['date' => '2026-11-27', 'subject_id' => 4], $pc)->title(), 'No ITEC 204 class on Friday');
check('ann: deadline change needs an upcoming task', $refused(fn () => Announcement::fromInput('deadline_change', ['task_id' => 9, 'deadline_type' => 'exact', 'deadline' => '2026-11-26'], $pc)), 'Pick an upcoming task.');
check('ann: deadline change to the past refused', $refused(fn () => Announcement::fromInput('deadline_change', ['task_id' => 3, 'deadline_type' => 'exact', 'deadline' => '2026-11-19'], $pc)), 'The deadline can’t be in the past.');
check('ann: deadline change needs the President\'s permission', Announcement::fromInput('deadline_change', ['task_id' => 3, 'deadline_type' => 'exact', 'deadline' => '2026-11-26', 'deadline_time' => '17:00'], $pc)->requiredPermission(), 'publish_task');
$kindsOf = fn (AnnouncementContext $c) => array_map(fn ($k) => $k['kind'], Announcement::kinds($c));
check('ann: the Secretary can\'t pick deadline change', Algorithms::linearContains($kindsOf($annCtx($secr)), 'deadline_change'), false);
check('ann: the President can pick all six', count($kindsOf($pc)), 6);
check('ann: payment for a due from elsewhere refused', $refused(fn () => Announcement::fromInput('payment', ['due_id' => 99], $pc)), 'Pick an open due from this section.');
check('ann: a new due needs manage_dues', Announcement::fromInput('payment', ['new_due' => ['title' => 'Org shirt', 'amount' => '350', 'due_date' => '2026-12-11']], $pc)->requiredPermission(), 'manage_dues');
check('ann: a notice needs a title', $refused(fn () => Announcement::fromInput('general', ['title' => '  '], $pc)), 'Give the announcement a title (up to 80 characters).');
check('ann: unknown kind refused', $refused(fn () => Announcement::fromInput('party', [], $pc)), 'Pick what kind of announcement it is.');

// Review fixes
$noDues = new AnnouncementContext(sectionId: 1, userId: 6, me: $secr, tasks: [], subjects: [], slots: [], dues: [], activities: [],
    cancelled: new HashSet(), now: strtotime('2026-11-20 10:00'));
check('fix: no open dues and can\'t add one → no Payment kind', Algorithms::linearContains($kindsOf($noDues), 'payment'), false);
check('fix: a new due in the past refused', $refused(fn () => Announcement::fromInput('payment', ['new_due' => ['title' => 'Late', 'amount' => '50', 'due_date' => '2026-11-19']], $pc)), 'Pick today or a later day for the due date.');
$overdueCtx = new AnnouncementContext(sectionId: 1, userId: 1, me: $pres, tasks: [], subjects: [], slots: [],
    dues: [5 => new Due(5, 'Old fee', 10000, '2026-11-10', 'open', 7)], activities: [], cancelled: new HashSet(), now: strtotime('2026-11-20 10:00'));
check('fix: a reminder for an overdue due is undated (stays in Notices)', Announcement::fromInput('payment', ['due_id' => 5], $overdueCtx)->eventAt(), null);
$moved = Announcement::fromInput('deadline_change', ['task_id' => 3, 'deadline_type' => 'exact', 'deadline' => '2026-11-26', 'deadline_time' => '17:00'], $pc);
$taskNote = Announcement::fromInput('task', ['task_id' => 3], $pc);
$later = $taskRow(3, 'approved');
$later['deadline'] = '2026-11-30';
$laterCtx = new AnnouncementContext(sectionId: 1, userId: 1, me: $pres, tasks: [3 => $later], subjects: [4 => ['id' => 4, 'code' => 'ITEC 204', 'title' => 'DS']],
    slots: [], dues: [], activities: [], cancelled: new HashSet(), now: strtotime('2026-11-20 10:00'));
check('fix: a task notice follows the task\'s current deadline', $taskNote->eventAtIn($laterCtx), strtotime('2026-11-30 17:00'));
check('fix: an older deadline change follows it too', $moved->eventAtIn($laterCtx), strtotime('2026-11-30 17:00'));
check('fix: an older deadline change says it moved again', Algorithms::linearContains($moved->facts($laterCtx)['lines'], 'Moved again: Due Mon, Nov 30 · 5:00 PM'), true);

// Deferred minors
$twice = new AnnouncementContext(sectionId: 1, userId: 1, me: $pres, tasks: [], subjects: [4 => ['id' => 4, 'code' => 'ITEC 204', 'title' => 'DS']],
    slots: [['subject_id' => 4, 'day_of_week' => 5, 'start_time' => '16:00', 'end_time' => '17:00', 'code' => 'ITEC 204'],
            ['subject_id' => 4, 'day_of_week' => 5, 'start_time' => '13:00', 'end_time' => '14:30', 'code' => 'ITEC 204']],
    dues: [], activities: [], cancelled: new HashSet(), now: strtotime('2026-11-20 10:00'));
check('minor: a subject meeting twice that day shows both times', Announcement::fromInput('no_class', ['date' => '2026-11-27', 'subject_id' => 4], $twice)->facts($twice)['lines'][0], 'ITEC 204 · 1:00–2:30 PM, 4:00–5:00 PM');
check('minor: an impossible no-class date refused', $refused(fn () => Announcement::fromInput('no_class', ['date' => '2026-02-31'], $pc)), 'Pick the day there’s no class.');
check('minor: an impossible deadline refused', $refused(fn () => validate_deadline(['deadline_type' => 'exact', 'deadline' => '2026-02-31'], '2026-01-01')), 'Pick a deadline date.');
check('minor: an impossible due date refused', $refused(fn () => new Due(0, 'X', 100, '2026-02-31', 'open', 1)), 'Pick a valid date.');
$morning = Activity::fromRow(['id' => 8, 'kind' => 'meeting', 'title' => 'Assembly', 'venue' => 'Room 1', 'starts_at' => '2026-11-20 07:00', 'ends_at' => '2026-11-20 08:00', 'created_by' => 1]);
$evCtx = new AnnouncementContext(sectionId: 1, userId: 1, me: $pres, tasks: [], subjects: [], slots: [], dues: [], activities: [8 => $morning],
    cancelled: new HashSet(), now: strtotime('2026-11-20 10:00'));
$ev = Announcement::fromRow(['id' => 3, 'kind' => 'event', 'title' => 'Meeting: Assembly', 'body' => '', 'pinned' => 0, 'created_by' => 1, 'created_at' => '2026-11-19 08:00',
    'ref_id' => 8, 'event_at' => '2026-11-20 07:00', 'details' => json_encode(['title' => 'Assembly', 'kind_label' => 'Meeting', 'venue' => 'Room 1', 'starts_at' => '2026-11-20 07:00', 'ends_at' => '2026-11-20 08:00', 'capacity' => null])]);
check('minor: an event that ended today is over', $ev->isOverIn($evCtx), true);
check('minor: a finished event has no RSVP button', [$ev->facts($evCtx)['action'], Algorithms::linearContains($ev->facts($evCtx)['lines'], 'Finished')], [null, true]);
$alum = ['id' => 9, 'first_name' => 'A', 'last_name' => 'B', 'student_id' => '1', 'email' => 'x', 'role' => 'student', 'position' => null, 'status' => 'alumni'];
check('minor: payment notices skip people who aren\'t charged', Announcement::fromInput('payment', ['due_id' => 2], $pc)->notifies($alum), false);
check('minor: other notices reach everyone', Announcement::fromInput('general', ['title' => 'Hi'], $pc)->notifies($alum), true);
check('minor: permission is checked before the details', $refused(fn () => Announcement::fromInput('deadline_change', ['task_id' => 3, 'deadline_type' => 'exact', 'deadline' => '2026-11-19'], $annCtx($secr))), 'Only the President can move a deadline.');
check('minor: a new due without permission is refused first', $refused(fn () => Announcement::fromInput('payment', ['new_due' => ['title' => '', 'amount' => 'x']], $annCtx($secr))), 'Only the Treasurer or the President can add a due.');

// ---------------------------------------------------------------------------
section('Competitions and projects: Activity');
$base = ['title' => 'Founding Anniversary', 'starts_at' => '2026-11-25 08:00', 'ends_at' => '2026-11-26 17:00'];
$c = Activity::fromRow($base + ['kind' => 'competition', 'link' => 'https://www.facebook.com/ssc/posts/1', 'signup_deadline' => '2026-11-20']);
check('competition kind', [$c->kind(), $c->kindLabel(), $c->joinsBy(), $c->usesSeats(), $c->canAssignDirectly(), $c->partsNeedDueDates()],
      ['competition', 'Competition', 'parts', false, false, false]);
$p = Activity::fromRow($base + ['kind' => 'project']);
check('project kind', [$p->kind(), $p->kindLabel(), $p->joinsBy(), $p->canAssignDirectly(), $p->partsNeedDueDates()],
      ['project', 'Section project', 'parts', true, true]);
$ev = Activity::fromRow($base + ['kind' => 'event']);
check('event kind', [$ev->kind(), $ev->kindLabel(), $ev->joinsBy(), $ev->usesSeats()], ['event', 'Event', 'rsvp', true]);
check('event: anyone checks in, nobody is marked absent', [$ev->canCheckIn(false), $ev->expectsAttendanceFrom(true), $ev->expectsAttendanceFrom(false)], [true, false, false]);
check('seminar still rsvp', [Activity::fromRow($base + ['kind' => 'seminar'])->joinsBy(), Activity::fromRow($base + ['kind' => 'seminar'])->usesSeats()], ['rsvp', true]);
check('no attendance for competitions/projects', [$c->expectsAttendanceFrom(true), $c->canCheckIn(true), $p->expectsAttendanceFrom(true), $p->canCheckIn(true)], [false, false, false, false]);
check('link host', $c->linkHost(), 'facebook.com');
foreach (['javascript:alert(1)', 'data:text/html,hi', 'ftp://x.org', 'www.facebook.com', 'https://' . str_repeat('a', 600) . '.com'] as $bad) {
    check("bad link refused: " . substr($bad, 0, 20), $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'link' => $bad])), 'Paste a link that starts with http:// or https://.');
}
check('empty link is no link', Activity::fromRow($base + ['kind' => 'competition', 'link' => ''])->link(), null);
check('deadline after start refused', $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'signup_deadline' => '2026-11-27'])), 'The sign-up deadline must be on or before the first day.');
check('impossible deadline refused', $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'signup_deadline' => '2026-02-30'])), 'Pick a real date for the sign-up deadline.');
check('open on the deadline day', $c->signupOpen(strtotime('2026-11-20 23:59:00')), true);
check('closed the day after', $c->signupOpen(strtotime('2026-11-21 00:00:01')), false);
check('no deadline closes at the start', Activity::fromRow($base + ['kind' => 'competition'])->signupClosesAt(), strtotime('2026-11-25 08:00'));
check('kinds lists six', count(Activity::kinds()), 6);
$multi = validate_new_activity(['kind' => 'competition', 'title' => 'FA', 'date' => '2030-11-25', 'end_date' => '2030-11-26', 'start' => '08:00', 'end' => '17:00'], 1, time());
check('several days', [$multi->toArray(time())['multi_day'], $multi->endTs()], [true, strtotime('2030-11-26 17:00')]);
check('end date before start refused', $refused(fn () => validate_new_activity(['kind' => 'competition', 'title' => 'FA', 'date' => '2030-11-25', 'end_date' => '2030-11-24', 'start' => '08:00', 'end' => '17:00'], 1, time())), 'The end time must be after the start time.');
check('result too long refused', $refused(fn () => Activity::fromRow($base + ['kind' => 'competition', 'result' => str_repeat('x', 301)])), 'Keep the result under 300 characters.');
check('an Event announcement can\'t create a competition', $refused(fn () => Announcement::fromInput('event', ['new_activity' => ['kind' => 'competition', 'title' => 'FA', 'date' => '2030-11-25', 'start' => '08:00', 'end' => '17:00']], $pc)), 'Post a competition or project from Activities: it needs its parts.');

check('impossible activity date refused', $refused(fn () => validate_new_activity(['kind' => 'meeting', 'title' => 'X', 'date' => '2030-02-30', 'start' => '08:00', 'end' => '09:00'], 1, time())), 'Pick a real date.');

// ---------------------------------------------------------------------------
section('Competitions and projects: parts');
$comp = Activity::fromRow(['kind' => 'competition', 'title' => 'Founding Anniversary', 'starts_at' => '2026-11-25 08:00', 'ends_at' => '2026-11-26 17:00', 'signup_deadline' => '2026-11-20']);
$proj = Activity::fromRow(['kind' => 'project', 'title' => 'Booth design', 'starts_at' => '2026-11-21 08:00', 'ends_at' => '2026-12-04 17:00']);
$cos = ActivityPart::fromRow(['id' => 1, 'activity_id' => 1, 'name' => 'Cosplay Competition', 'spots' => 2, 'roles' => '[]'], $comp);
$cul = ActivityPart::fromRow(['id' => 2, 'activity_id' => 1, 'name' => 'College Culinary Showdown', 'spots' => 4, 'together' => 1], $comp);
$any = ActivityPart::fromRow(['id' => 3, 'activity_id' => 1, 'name' => 'Dating game', 'spots' => null], $comp);
check('spots labels', [$cos->spotsLabel(), $cul->spotsLabel(), $any->spotsLabel()], ['2 spots', 'team of 4', 'Any number']);
check('any number never full', [$any->isFull(500), $any->openSpots(3)], [false, null]);
check('part name required', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => ' '], $comp)), 'Give the part a name (up to 80 characters).');
check('spots 0 refused', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => 'X', 'spots' => 0], $comp)), 'Spots must be between 1 and 100, or leave it empty for any number.');
check('competition part has no due date', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => 'X', 'due_date' => '2026-11-24'], $comp)), 'Competition parts don’t have due dates.');
check('project part needs a due date', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 2, 'name' => 'Poster'], $proj)), 'Give each part a due date.');
check('project due after last day refused', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 2, 'name' => 'Poster', 'due_date' => '2026-12-05'], $proj)), 'Pick a due date on or before the project’s last day (Fri, Dec 4).');
check('too many roles', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => 'Video', 'roles' => json_encode(array_fill(0, 11, 'Editor'))], $comp)), 'Up to 10 roles, each up to 40 characters.');
check('details too long', $refused(fn () => ActivityPart::fromRow(['id' => 0, 'activity_id' => 1, 'name' => 'Video', 'details' => str_repeat('x', 1001)], $comp)), 'Keep the details under 1,000 characters.');
check('shrink below picked', $cul->whyCantResize(2, 3), 'That part already has 3 picked. Un-pick people first.');
check('grow is fine', $cul->whyCantResize(6, 3), null);

$rows = [['id' => 10, 'user_id' => 5, 'status' => 'volunteer', 'volunteered' => 1, 'role' => null],
         ['id' => 11, 'user_id' => 3, 'status' => 'volunteer', 'volunteered' => 1, 'role' => 'Director'],
         ['id' => 12, 'user_id' => 4, 'status' => 'picked', 'volunteered' => 1, 'role' => null]];
$r = new PartRoster($rows);
check('arrival order', array_column($r->volunteers(), 'user_id'), [5, 3]);
check('counts', [$r->pickedCount(), $r->volunteerCount(), $r->statusOf(4), $r->statusOf(5), $r->statusOf(99), $r->roleOf(3)], [1, 2, 'picked', 'volunteer', 'none', 'Director']);
$before = strtotime('2026-11-20 12:00'); $after = strtotime('2026-11-21 08:00');
check('volunteer ok', $r->whyCantVolunteer($comp, 99, true, $before), null);
check('volunteer twice', $r->whyCantVolunteer($comp, 5, true, $before), 'You already volunteered for this part.');
check('volunteer when picked', $r->whyCantVolunteer($comp, 4, true, $before), 'You’re already picked for this part.');
check('alumni', $r->whyCantVolunteer($comp, 99, false, $before), 'Alumni members can’t volunteer.');
check('after deadline', $r->whyCantVolunteer($comp, 99, true, $after), 'Sign-ups for “Founding Anniversary” closed on Fri, Nov 20.');
check('pick a volunteer', $r->whyCantPick($cos, $comp, 5, false), null);
check('pick a stranger in a competition', $r->whyCantPick($cos, $comp, 99, false), 'Pick someone who volunteered.');
check('pick twice', $r->whyCantPick($cos, $comp, 4, false), 'Already picked.');
$full = new PartRoster([['id' => 1, 'user_id' => 1, 'status' => 'picked', 'volunteered' => 1], ['id' => 2, 'user_id' => 2, 'status' => 'picked', 'volunteered' => 1], ['id' => 3, 'user_id' => 3, 'status' => 'volunteer', 'volunteered' => 1]]);
check('last spot taken', $full->whyCantPick($cos, $comp, 3, false), 'This part is full. Un-pick someone first.');
$poster = ActivityPart::fromRow(['id' => 4, 'activity_id' => 2, 'name' => 'Poster', 'spots' => 1, 'due_date' => '2026-11-28'], $proj);
check('project assigns directly', (new PartRoster([]))->whyCantPick($poster, $proj, 7, true), null);
check('overdue flag', [$poster->toArray('2026-11-29')['overdue'], $poster->toArray('2026-11-28')['overdue']], [true, false]);

// Final review fixes
$running = Activity::fromRow(['kind' => 'project', 'title' => 'Booth design', 'starts_at' => '2026-11-20 08:00', 'ends_at' => '2026-12-04 17:00']);
check('fix I3: a project without a deadline takes sign-ups until its last day', [$running->signupClosesAt(), $running->signupOpen(strtotime('2026-11-25 10:00'))],
    [strtotime('2026-12-04 17:00'), true]);
check('fix I3: a competition without a deadline still closes at its start', Activity::fromRow(['kind' => 'competition', 'title' => 'X', 'starts_at' => '2026-11-25 08:00', 'ends_at' => '2026-11-25 17:00'])->signupOpen(strtotime('2026-11-25 09:00')), false);

// ---------------------------------------------------------------------------
section('Competitions and projects: announcement');
$faAct = Activity::fromRow(['id' => 1, 'kind' => 'competition', 'title' => 'Founding Anniversary', 'venue' => 'Gym', 'starts_at' => '2026-11-25 08:00',
    'ends_at' => '2026-11-26 17:00', 'signup_deadline' => '2026-11-20', 'created_by' => 1]);
$faCtx = fn (int $now) => new AnnouncementContext(sectionId: 1, userId: 1, me: $pres, tasks: [], subjects: [], slots: [], dues: [],
    activities: [1 => $faAct], cancelled: new HashSet(), now: $now, openSpots: [1 => ['open' => 7, 'parts' => 5]]);
$n = Announcement::fromRow(['id' => 4, 'kind' => 'event', 'title' => 'Competition: Founding Anniversary', 'body' => '', 'pinned' => 0, 'created_by' => 1,
    'created_at' => '2026-11-19 08:00', 'ref_id' => 1, 'event_at' => '2026-11-25 08:00',
    'details' => json_encode(['title' => 'Founding Anniversary', 'kind_label' => 'Competition', 'venue' => 'Gym', 'starts_at' => '2026-11-25 08:00', 'ends_at' => '2026-11-26 17:00', 'capacity' => null])]);
$ctxOpen = $faCtx(strtotime('2026-11-20 10:00'));
check('event facts for a competition', $n->facts($ctxOpen)['lines'][1] ?? null, '7 spots open across 5 parts');
check('event facts: sign-up deadline', $n->facts($ctxOpen)['lines'][2] ?? null, 'Sign up by Fri, Nov 20');
check('volunteer action', [$n->facts($ctxOpen)['action']['label'] ?? null, $n->facts($ctxOpen)['action']['href'] ?? null], ['Volunteer', 'activities.html#a1']);
$ctxClosed = $faCtx(strtotime('2026-11-22 10:00'));
check('event facts: sign-ups closed', [$n->facts($ctxClosed)['lines'][1] ?? null, $n->facts($ctxClosed)['action']['label'] ?? null], ['Sign-ups closed', 'Open']);

// ---------------------------------------------------------------------------
section('Who can join (invite-only activities)');
$open = Activity::fromRow(['id' => 20, 'kind' => 'meeting', 'title' => 'Assembly', 'starts_at' => '2026-11-02 08:00', 'ends_at' => '2026-11-02 09:00']);
$inv = Activity::fromRow(['id' => 21, 'kind' => 'meeting', 'title' => 'Officers only', 'starts_at' => '2026-11-03 08:00', 'ends_at' => '2026-11-03 09:00',
    'audience' => 'invited', 'invited' => [4, 6, 6]]);
check('everyone: open to all', [$open->isInviteOnly(), $open->isFor(99)], [false, true]);
check('invite only: only the people picked', [$inv->isInviteOnly(), $inv->isFor(4), $inv->isFor(6), $inv->isFor(5)], [true, true, true, false]);
check('invite list has no duplicates', $inv->invitedIds(), [4, 6]);
check('invite only with nobody refused', $refused(fn () => Activity::fromRow(['kind' => 'meeting', 'title' => 'X', 'starts_at' => '2026-11-03 08:00',
    'ends_at' => '2026-11-03 09:00', 'audience' => 'invited', 'invited' => []])), 'Pick at least one person to invite.');
check('stored audience', [$open->toRow()['audience'], $inv->toRow()['audience']], ['everyone', 'invited']);
$member = fn (int $id) => Person::fromRow(['id' => $id, 'first_name' => 'M', 'last_name' => (string) $id, 'student_id' => "s$id", 'email' => "$id@x",
    'role' => 'student', 'position' => null, 'status' => 'active']);
$emptyRosters = [20 => new ActivityRoster([], []), 21 => new ActivityRoster([], [])];
check('an invited member is expected at an invite-only meeting', MemberRecord::fromActivities($member(4), '2026-10-01', [$open, $inv], $emptyRosters, 0, [])->expected(), 2);
$invFuture = Activity::fromRow(['id' => 22, 'kind' => 'meeting', 'title' => 'Officers only', 'starts_at' => '2026-11-27 08:00', 'ends_at' => '2026-11-27 09:00',
    'audience' => 'invited', 'invited' => [4], 'created_by' => 1]);
$invCtx = new AnnouncementContext(sectionId: 1, userId: 1, me: $pres, tasks: [], subjects: [], slots: [], dues: [], activities: [22 => $invFuture],
    cancelled: new HashSet(), now: strtotime('2026-11-20 10:00'));
$invNote = Announcement::fromInput('event', ['activity_id' => 22], $invCtx);
$row = fn (int $id) => ['id' => $id, 'first_name' => 'M', 'last_name' => 'X', 'student_id' => 's', 'email' => 'x', 'role' => 'student', 'position' => null, 'status' => 'active'];
check('an Event announcement for an invite-only activity reaches only the invited', [$invNote->notifies($row(4)), $invNote->notifies($row(5))], [true, false]);
check('someone not invited is not counted absent', MemberRecord::fromActivities($member(5), '2026-10-01', [$open, $inv], $emptyRosters, 0, [])->expected(), 1);

// ---------------------------------------------------------------------------
section('Group planning');
check('10 students, 3-4 per group', Algorithms::planGroupSizes(10, 3, 4), [4, 3, 3]);
check('17 students, exactly 3', Algorithms::planGroupSizes(17, 3, 3), [4, 4, 3, 3, 3]);
check('17 students, 5 groups', Algorithms::planGroupSizes(17, 3, 3, 5), [4, 4, 3, 3, 3]);
check('nobody to group', Algorithms::planGroupSizes(0, 3, 4), []);
$shuffled = Algorithms::shuffle([1, 2, 3, 4, 5]);
check('shuffle keeps every item', Algorithms::mergeSortBy(array_map(fn ($v) => ['v' => $v], $shuffled), 'v', false), [['v' => 1], ['v' => 2], ['v' => 3], ['v' => 4], ['v' => 5]]);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
