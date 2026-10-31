<?php
/**
 * ============================================================================
 *  AcadSync — Custom Data Structures & Algorithms
 * ============================================================================
 *  Every structure and algorithm the app relies on lives in this file and is
 *  written from scratch. No built-in PHP sorting or searching (sort, usort,
 *  array_search, in_array, array_filter, ...) and no SQL ORDER BY / WHERE is
 *  used for processing — the database only stores raw rows.
 *
 *  DATA STRUCTURES (7)                     WHERE IT IS USED
 *  ----------------------------------------------------------------------------
 *  1. Array      — FixedArray / SubjectArray   Subject grid (fixed curriculum)
 *  2. Linked List— TaskLinkedList              Active task timeline
 *  3. HashSet    — StudentHashSet              Unique @lspu.edu.ph e-mails
 *  4. Stack      — UndoStack                   Admin "Undo reject"
 *  5. Queue      — ApprovalQueue               Admin approval pipeline (FIFO)
 *  6. Tree       — SubjectTree                 Section -> Subjects -> Tasks
 *  7. Graph      — DependencyGraph             Study-group connections
 *
 *  ALGORITHMS (5)
 *  ----------------------------------------------------------------------------
 *  1. Insertion Sort — TaskLinkedList::insertChronologically()
 *  2. Bubble Sort    — Algorithms::bubbleSortByUrgency()
 *  3. Selection Sort — Algorithms::selectionSortByLastName() / ByStudentId()
 *  4. Linear Search  — Algorithms::linearSearchTasks() / linearSearchByName()
 *  5. Binary Search  — Algorithms::binarySearchByStudentId()
 * ============================================================================
 */

declare(strict_types=1);

/* ----------------------------------------------------------------------------
 * Shared helpers (tiny, hand-written so the algorithms below stay "pure")
 * ------------------------------------------------------------------------- */
final class DsaUtil
{
    /** Lower-cases ASCII letters one character at a time. */
    public static function lower(string $s): string
    {
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            $o = ord($c);
            $out .= ($o >= 65 && $o <= 90) ? chr($o + 32) : $c;
        }
        return $out;
    }

    /**
     * Compares two strings alphabetically (case-insensitive).
     * Returns <0 if $a comes first, 0 if equal, >0 if $b comes first.
     */
    public static function compareText(string $a, string $b): int
    {
        $a = self::lower($a);
        $b = self::lower($b);
        $la = strlen($a);
        $lb = strlen($b);
        $n = $la < $lb ? $la : $lb;
        for ($i = 0; $i < $n; $i++) {
            $diff = ord($a[$i]) - ord($b[$i]);
            if ($diff !== 0) {
                return $diff;
            }
        }
        return $la - $lb;
    }

    /** Naive substring check, character by character (used by Linear Search). */
    public static function contains(string $haystack, string $needle): bool
    {
        $h = self::lower($haystack);
        $n = self::lower($needle);
        $lh = strlen($h);
        $ln = strlen($n);
        if ($ln === 0) {
            return true;
        }
        for ($i = 0; $i <= $lh - $ln; $i++) {
            $j = 0;
            while ($j < $ln && $h[$i + $j] === $n[$j]) {
                $j++;
            }
            if ($j === $ln) {
                return true;
            }
        }
        return false;
    }

    /** Turns "2023-10045" into the integer 202310045 by keeping only digits. */
    public static function digitsToInt(string $s): int
    {
        $n = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($s[$i]);
            if ($o >= 48 && $o <= 57) {
                $n = $n * 10 + ($o - 48);
            }
        }
        return $n;
    }

    /** Splits text into lower-case words of at least $min letters. */
    public static function words(string $text, int $min = 4): array
    {
        $words = [];
        $cur = '';
        $text = self::lower($text) . ' ';
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($text[$i]);
            $isAlnum = ($o >= 97 && $o <= 122) || ($o >= 48 && $o <= 57);
            if ($isAlnum) {
                $cur .= $text[$i];
            } else {
                if (strlen($cur) >= $min) {
                    $words[] = $cur;
                }
                $cur = '';
            }
        }
        return $words;
    }

    /**
     * Converts a task's deadline into a comparable Unix timestamp (null = no date yet).
     * For a date range the last day of the range is the deadline.
     */
    public static function deadlineTs(array $task): ?int
    {
        $date = !empty($task['deadline_end']) ? $task['deadline_end'] : ($task['deadline'] ?? null);
        if (empty($date)) {
            return null;
        }
        $time = !empty($task['deadline_time']) ? $task['deadline_time'] : '23:59';
        $ts = strtotime($date . ' ' . $time);
        return $ts === false ? null : $ts;
    }
}

/* ============================================================================
 * 1. ARRAY — fixed-capacity one-dimensional array
 * ========================================================================= */
class FixedArray
{
    protected array $slots = [];
    protected int $size = 0;

    public function __construct(protected int $capacity)
    {
        for ($i = 0; $i < $capacity; $i++) {
            $this->slots[$i] = null;
        }
    }

    /** Appends to the next free slot. Returns false when the array is full. */
    public function add(mixed $value): bool
    {
        if ($this->size >= $this->capacity) {
            return false;
        }
        $this->slots[$this->size] = $value;
        $this->size++;
        return true;
    }

    public function get(int $index): mixed
    {
        return ($index >= 0 && $index < $this->size) ? $this->slots[$index] : null;
    }

    public function set(int $index, mixed $value): void
    {
        if ($index >= 0 && $index < $this->size) {
            $this->slots[$index] = $value;
        }
    }

    public function size(): int
    {
        return $this->size;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function toArray(): array
    {
        $out = [];
        for ($i = 0; $i < $this->size; $i++) {
            $out[] = $this->slots[$i];
        }
        return $out;
    }
}

/** The section curriculum. BSINFO-2A has 8 core subjects; officers may add a few electives. */
class SubjectArray extends FixedArray
{
    public const CORE_SUBJECTS = 8;

    public function __construct(int $capacity = 16)
    {
        parent::__construct($capacity);
    }
}

/* ============================================================================
 * 2. LINKED LIST — singly linked task timeline (+ INSERTION SORT)
 * ========================================================================= */
class TaskNode
{
    public ?TaskNode $next = null;
    public ?int $ts;

    public function __construct(public array $task)
    {
        $this->ts = DsaUtil::deadlineTs($task);
    }
}

class TaskLinkedList
{
    public ?TaskNode $head = null;
    private int $length = 0;

    /**
     * ALGORITHM 1 — INSERTION SORT
     * Walks the list until it finds the first node with a later deadline and
     * splices the new node in front of it, so the list is always chronological.
     * Tasks whose date is "not set yet" go to the tail.
     */
    public function insertChronologically(TaskNode $node): void
    {
        $this->length++;

        if ($this->head === null || $this->comesBefore($node, $this->head)) {
            $node->next = $this->head;
            $this->head = $node;
            return;
        }

        $current = $this->head;
        while ($current->next !== null && !$this->comesBefore($node, $current->next)) {
            $current = $current->next;
        }
        $node->next = $current->next;
        $current->next = $node;
    }

    private function comesBefore(TaskNode $a, TaskNode $b): bool
    {
        if ($a->ts === null) {
            return false;
        }
        if ($b->ts === null) {
            return true;
        }
        return $a->ts < $b->ts;
    }

    /**
     * Automated expired-task cleanup: since the list is sorted, expired tasks
     * are always at the front, so we simply move the head pointer forward.
     * Returns the tasks that were dropped.
     */
    public function removeExpired(int $now): array
    {
        $dropped = [];
        while ($this->head !== null && $this->head->ts !== null && $this->head->ts < $now) {
            $dropped[] = $this->head->task;
            $this->head = $this->head->next;   // head = head.next
            $this->length--;
        }
        return $dropped;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function toArray(): array
    {
        $out = [];
        for ($n = $this->head; $n !== null; $n = $n->next) {
            $out[] = $n->task;
        }
        return $out;
    }
}

/* ============================================================================
 * 3. HASHSET — O(1) duplicate e-mail detection with a custom hash function
 * ========================================================================= */
class StudentHashSet
{
    /** @var array<int, array<int, string>> buckets for separate chaining */
    private array $buckets = [];
    private int $count = 0;

    public function __construct(private int $bucketCount = 101)
    {
        for ($i = 0; $i < $bucketCount; $i++) {
            $this->buckets[$i] = [];
        }
    }

    /** djb2 string hash, reduced to a bucket index. */
    private function hash(string $key): int
    {
        $h = 5381;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $h = (($h * 33) + ord($key[$i])) % 2147483647;
        }
        return $h % $this->bucketCount;
    }

    public function contains(string $email): bool
    {
        $key = DsaUtil::lower(trim($email));
        $bucket = $this->buckets[$this->hash($key)];
        $n = count($bucket);
        for ($i = 0; $i < $n; $i++) {
            if ($bucket[$i] === $key) {
                return true;
            }
        }
        return false;
    }

    /** Adds an e-mail. Returns false when it already exists (duplicate). */
    public function addEmail(string $email): bool
    {
        if ($this->contains($email)) {
            return false;
        }
        $key = DsaUtil::lower(trim($email));
        $this->buckets[$this->hash($key)][] = $key;
        $this->count++;
        return true;
    }

    public function size(): int
    {
        return $this->count;
    }
}

/* ============================================================================
 * 4. STACK — LIFO buffer of rejected requests (Admin Undo)
 * ========================================================================= */
class StackNode
{
    public ?StackNode $below = null;

    public function __construct(public mixed $value)
    {
    }
}

class UndoStack
{
    private ?StackNode $top = null;
    private int $size = 0;

    public function push(mixed $value): void
    {
        $node = new StackNode($value);
        $node->below = $this->top;
        $this->top = $node;
        $this->size++;
    }

    public function pop(): mixed
    {
        if ($this->top === null) {
            return null;
        }
        $value = $this->top->value;
        $this->top = $this->top->below;
        $this->size--;
        return $value;
    }

    public function peek(): mixed
    {
        return $this->top?->value;
    }

    public function isEmpty(): bool
    {
        return $this->top === null;
    }

    public function size(): int
    {
        return $this->size;
    }

    /** Top of the stack first. */
    public function toArray(): array
    {
        $out = [];
        for ($n = $this->top; $n !== null; $n = $n->below) {
            $out[] = $n->value;
        }
        return $out;
    }
}

/* ============================================================================
 * 5. QUEUE — FIFO approval pipeline
 * ========================================================================= */
class QueueNode
{
    public ?QueueNode $next = null;

    public function __construct(public mixed $value)
    {
    }
}

class ApprovalQueue
{
    private ?QueueNode $front = null;
    private ?QueueNode $rear = null;
    private int $size = 0;

    public function enqueue(mixed $value): void
    {
        $node = new QueueNode($value);
        if ($this->rear === null) {
            $this->front = $this->rear = $node;
        } else {
            $this->rear->next = $node;
            $this->rear = $node;
        }
        $this->size++;
    }

    public function dequeue(): mixed
    {
        if ($this->front === null) {
            return null;
        }
        $value = $this->front->value;
        $this->front = $this->front->next;
        if ($this->front === null) {
            $this->rear = null;
        }
        $this->size--;
        return $value;
    }

    public function peek(): mixed
    {
        return $this->front?->value;
    }

    public function isEmpty(): bool
    {
        return $this->front === null;
    }

    public function size(): int
    {
        return $this->size;
    }

    /** Oldest (front) first. */
    public function toArray(): array
    {
        $out = [];
        for ($n = $this->front; $n !== null; $n = $n->next) {
            $out[] = $n->value;
        }
        return $out;
    }
}

/* ============================================================================
 * 6. TREE — Section (root) -> Subjects (branches) -> Tasks (leaves)
 * ========================================================================= */
class TreeNode
{
    /** @var TreeNode[] */
    public array $children = [];

    public function __construct(public string $label, public array $data = [])
    {
    }

    public function addChild(TreeNode $child): TreeNode
    {
        $this->children[] = $child;
        return $child;
    }
}

class SubjectTree
{
    public TreeNode $root;

    public function __construct(string $sectionName)
    {
        $this->root = new TreeNode($sectionName, ['type' => 'section']);
    }

    public function addSubject(array $subject): TreeNode
    {
        return $this->root->addChild(new TreeNode($subject['code'], $subject + ['type' => 'subject']));
    }

    /** Depth-first search for a subject branch by id. */
    public function findSubject(int $subjectId): ?TreeNode
    {
        $n = count($this->root->children);
        for ($i = 0; $i < $n; $i++) {
            $child = $this->root->children[$i];
            if ((int) $child->data['id'] === $subjectId) {
                return $child;
            }
        }
        return null;
    }

    public function addTask(int $subjectId, array $task): bool
    {
        $branch = $this->findSubject($subjectId);
        if ($branch === null) {
            return false;
        }
        $branch->addChild(new TreeNode($task['title'], $task + ['type' => 'task']));
        return true;
    }

    /** Recursively converts the tree into nested arrays for JSON. */
    public function toArray(?TreeNode $node = null): array
    {
        $node ??= $this->root;
        $children = [];
        $n = count($node->children);
        for ($i = 0; $i < $n; $i++) {
            $children[] = $this->toArray($node->children[$i]);
        }
        return ['label' => $node->label, 'data' => $node->data, 'children' => $children];
    }
}

/* ============================================================================
 * 7. GRAPH — study-group collaboration (undirected adjacency list)
 * ========================================================================= */
class DependencyGraph
{
    /** @var array<int, array<int, array{to:int, context:string}>> */
    private array $adjacency = [];

    public function addVertex(int $id): void
    {
        if (!isset($this->adjacency[$id])) {
            $this->adjacency[$id] = [];
        }
    }

    public function addEdge(int $a, int $b, string $context): void
    {
        $this->addVertex($a);
        $this->addVertex($b);
        $this->adjacency[$a][] = ['to' => $b, 'context' => $context];
        $this->adjacency[$b][] = ['to' => $a, 'context' => $context];
    }

    public function neighbors(int $id): array
    {
        return $this->adjacency[$id] ?? [];
    }

    /** Distinct group names a student belongs to (from their edges). */
    public function groupsOf(int $id): array
    {
        $groups = [];
        foreach ($this->neighbors($id) as $edge) {
            $seen = false;
            $n = count($groups);
            for ($i = 0; $i < $n; $i++) {
                if ($groups[$i] === $edge['context']) {
                    $seen = true;
                    break;
                }
            }
            if (!$seen) {
                $groups[] = $edge['context'];
            }
        }
        return $groups;
    }

    /** Every distinct group name in the graph. */
    public function allGroups(): array
    {
        $groups = [];
        foreach ($this->adjacency as $id => $_) {
            foreach ($this->groupsOf($id) as $g) {
                $seen = false;
                $n = count($groups);
                for ($i = 0; $i < $n; $i++) {
                    if ($groups[$i] === $g) {
                        $seen = true;
                        break;
                    }
                }
                if (!$seen) {
                    $groups[] = $g;
                }
            }
        }
        return $groups;
    }

    /**
     * Breadth-first traversal from one student, following only edges of one
     * group, returns everyone reachable (the whole team).
     */
    public function teamMembers(int $start, string $context): array
    {
        $visited = [$start => true];
        $queue = new ApprovalQueue();
        $queue->enqueue($start);
        $team = [];
        while (!$queue->isEmpty()) {
            $id = $queue->dequeue();
            $team[] = $id;
            foreach ($this->neighbors($id) as $edge) {
                if ($edge['context'] === $context && !isset($visited[$edge['to']])) {
                    $visited[$edge['to']] = true;
                    $queue->enqueue($edge['to']);
                }
            }
        }
        return $team;
    }

    public function vertexCount(): int
    {
        return count($this->adjacency);
    }
}

/* ============================================================================
 * ALGORITHMS
 * ========================================================================= */
final class Algorithms
{
    /**
     * ALGORITHM 2 — BUBBLE SORT
     * Re-orders tasks from most to least urgent (least time left first).
     * Tasks without a date are treated as the least urgent.
     */
    public static function bubbleSortByUrgency(array $tasks, int $now): array
    {
        $n = count($tasks);
        for ($pass = 0; $pass < $n - 1; $pass++) {
            $swapped = false;
            for ($i = 0; $i < $n - 1 - $pass; $i++) {
                if (self::urgency($tasks[$i], $now) > self::urgency($tasks[$i + 1], $now)) {
                    $tmp = $tasks[$i];
                    $tasks[$i] = $tasks[$i + 1];
                    $tasks[$i + 1] = $tmp;
                    $swapped = true;
                }
            }
            if (!$swapped) {
                break; // already sorted
            }
        }
        return $tasks;
    }

    /** Seconds left until the deadline; smaller = more urgent. */
    public static function urgency(array $task, int $now): int
    {
        $ts = DsaUtil::deadlineTs($task);
        return $ts === null ? PHP_INT_MAX : $ts - $now;
    }

    /**
     * ALGORITHM 3 — SELECTION SORT
     * Alphabetises the classmate directory by last name, then first name.
     */
    public static function selectionSortByLastName(array $students): array
    {
        $n = count($students);
        for ($i = 0; $i < $n - 1; $i++) {
            $min = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                if (self::compareNames($students[$j], $students[$min]) < 0) {
                    $min = $j;
                }
            }
            if ($min !== $i) {
                $tmp = $students[$i];
                $students[$i] = $students[$min];
                $students[$min] = $tmp;
            }
        }
        return $students;
    }

    private static function compareNames(array $a, array $b): int
    {
        $c = DsaUtil::compareText($a['last_name'] ?? '', $b['last_name'] ?? '');
        return $c !== 0 ? $c : DsaUtil::compareText($a['first_name'] ?? '', $b['first_name'] ?? '');
    }

    /** SELECTION SORT by numeric Student ID — prepares the array for Binary Search. */
    public static function selectionSortByStudentId(array $students): array
    {
        $n = count($students);
        for ($i = 0; $i < $n - 1; $i++) {
            $min = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                if (DsaUtil::digitsToInt($students[$j]['student_id'] ?? '') < DsaUtil::digitsToInt($students[$min]['student_id'] ?? '')) {
                    $min = $j;
                }
            }
            if ($min !== $i) {
                $tmp = $students[$i];
                $students[$i] = $students[$min];
                $students[$min] = $tmp;
            }
        }
        return $students;
    }

    /**
     * ALGORITHM 5 — BINARY SEARCH
     * Finds a student by numeric Student ID in an array sorted by ID.
     * Returns null when the ID does not exist.
     */
    public static function binarySearchByStudentId(array $sorted, string $studentId): ?array
    {
        $target = DsaUtil::digitsToInt($studentId);
        $low = 0;
        $high = count($sorted) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $value = DsaUtil::digitsToInt($sorted[$mid]['student_id'] ?? '');
            if ($value === $target) {
                return $sorted[$mid];
            }
            if ($value < $target) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }
        return null;
    }

    /**
     * ALGORITHM 4 — LINEAR SEARCH
     * Scans every existing task's title + description, one by one, for the
     * keywords of a new request. Used to warn about possible duplicates.
     * Returns matching tasks with the keywords that matched.
     */
    public static function linearSearchTasks(array $tasks, string $text, ?int $subjectId = null, ?int $ignoreId = null): array
    {
        $keywords = DsaUtil::words($text);
        $matches = [];
        $n = count($tasks);
        $k = count($keywords);
        for ($i = 0; $i < $n; $i++) {
            $t = $tasks[$i];
            if ($ignoreId !== null && (int) $t['id'] === $ignoreId) {
                continue;
            }
            if ($subjectId !== null && (int) $t['subject_id'] !== $subjectId) {
                continue;
            }
            $haystack = ($t['title'] ?? '') . ' ' . ($t['description'] ?? '');
            $hits = [];
            for ($j = 0; $j < $k; $j++) {
                if (DsaUtil::contains($haystack, $keywords[$j])) {
                    $hits[] = $keywords[$j];
                }
            }
            // Flag when at least half of the keywords (min. 1) appear.
            if ($k > 0 && count($hits) * 2 >= $k) {
                $matches[] = ['task' => $t, 'keywords' => $hits];
            }
        }
        return $matches;
    }

    /** LINEAR SEARCH over the directory by (partial) name. */
    public static function linearSearchByName(array $students, string $query): array
    {
        $out = [];
        $n = count($students);
        for ($i = 0; $i < $n; $i++) {
            $s = $students[$i];
            $full = $s['first_name'] . ' ' . $s['last_name'] . ' ' . $s['username'];
            if (DsaUtil::contains($full, trim($query))) {
                $out[] = $s;
            }
        }
        return $out;
    }
}
