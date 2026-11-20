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

$passed = 0;
$failed = 0;

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
section('Group planning');
check('10 students, 3-4 per group', Algorithms::planGroupSizes(10, 3, 4), [4, 3, 3]);
check('17 students, exactly 3', Algorithms::planGroupSizes(17, 3, 3), [4, 4, 3, 3, 3]);
check('17 students, 5 groups', Algorithms::planGroupSizes(17, 3, 3, 5), [4, 4, 3, 3, 3]);
check('nobody to group', Algorithms::planGroupSizes(0, 3, 4), []);
$shuffled = Algorithms::shuffle([1, 2, 3, 4, 5]);
check('shuffle keeps every item', Algorithms::mergeSortBy(array_map(fn ($v) => ['v' => $v], $shuffled), 'v', false), [['v' => 1], ['v' => 2], ['v' => 3], ['v' => 4], ['v' => 5]]);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
