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
 *  Search the project for "[DSA:" to find every place a structure is used.
 *
 *  DATA STRUCTURES (7)       RUBRIC                                   IN ACADSYNC
 *  ----------------------------------------------------------------------------
 *  1. Array       Storing and accessing collections of records.     SubjectArray: a section's subjects;
 *                                                                   WeekGrid (2D): class-time clash checker
 *  2. Linked List Managing sequences of records through             TaskLinkedList (doubly linked): the task
 *                 insertion and deletion.                           timeline, previous / next task
 *  3. HashSet     Preventing duplicate entries or maintaining       HashSet / StudentHashSet: unique e-mails,
 *                 unique records.                                   student IDs, section names, class codes,
 *                                                                   subject codes, GCash reference numbers
 *  4. Stack       Implementing undo operations, history, or         UndoStack: admin "Undo reject" and the
 *                 backtracking.                                     students' Undo (leave, cancel, mark done)
 *  5. Queue       Managing waiting lists, service requests, or      ApprovalQueue: student task requests and
 *                 tasks in order.                                   join requests reviewed first-come,
 *                                                                   first-served; activity waitlists
 *  6. Tree        Organizing hierarchical data or implementing      SubjectTree: Section -> Subjects -> Tasks,
 *                 tree-based searching.                             searched depth-first; ChecklistTree:
 *                                                                   sub-items, progress rolled up post-order
 *  7. Graph       Representing connections, routes,                 DependencyGraph: study-group links
 *                 relationships, or networks.                       (adjacency list + BFS, shortest path)
 *
 *  ALGORITHMS (6)
 *  ----------------------------------------------------------------------------
 *  1. Insertion Sort — TaskLinkedList::insertChronologically()
 *  2. Bubble Sort    — Algorithms::bubbleSortByUrgency()
 *  3. Selection Sort — Algorithms::selectionSortByLastName() / ByStudentId()
 *  4. Linear Search  — Algorithms::linearSearchTasks() / linearSearchByName() / linearSearch()
 *  5. Binary Search  — Algorithms::binarySearchByStudentId() / countDueBy() (deadlines)
 *  6. Merge Sort     — Algorithms::mergeSortBy() (notifications, announcements, dues, rankings)
 *
 *  Tests: php tests/dsa_test.php
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
        // [OOP] the Deadline object (ExactDeadline / RangeDeadline / NoDeadline) knows its own time
        return Deadlines::fromRow($task)->timestamp();
    }
}

/* ============================================================================
 * 1. ARRAY — "Storing and accessing collections of records."
 *    A fixed-capacity, index-based 1D array. AcadSync stores each section's
 *    subject records in a SubjectArray and reads them back by index for the
 *    subject grid, the curriculum list and the Tree's branches.
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

    /** Linear scan for the index of the record whose $field equals $value (-1 if absent). */
    public function indexOf(string $field, mixed $value): int
    {
        for ($i = 0; $i < $this->size; $i++) {
            if ((string) ($this->slots[$i][$field] ?? '') === (string) $value) {
                return $i;
            }
        }
        return -1;
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

/** A section's curriculum. BSINFO-2A has 8 core subjects; officers may add electives. */
class SubjectArray extends FixedArray
{
    public const CORE_SUBJECTS = 8;

    public function __construct(int $capacity = 16)
    {
        parent::__construct($capacity);
    }
}

/**
 * 2D ARRAY — the week as a grid: 6 rows (Monday–Saturday) × 288 columns (one per
 * 5 minutes of the day). Each cell holds the id of the subject meeting then, or null.
 * Checking a new meeting for clashes is just reading the cells it would cover:
 * O(length of the meeting), with no searching through other subjects' times.
 */
class WeekGrid
{
    public const DAYS = 6;
    public const STEP = 5;                         // minutes per cell
    public const CELLS = 288;                      // 24 h × 60 / 5

    /** @var array<int, array<int, ?int>> [day 0–5][cell 0–287] => subject id */
    private array $cells = [];
    private int $filled = 0;

    public function __construct()
    {
        for ($d = 0; $d < self::DAYS; $d++) {
            $row = [];
            for ($c = 0; $c < self::CELLS; $c++) {
                $row[$c] = null;
            }
            $this->cells[$d] = $row;
        }
    }

    /** "13:00"–"14:30" -> first and last cell index it covers ([156, 173]). */
    public static function cellRange(string $start, string $end): array
    {
        $s = (int) substr($start, 0, 2) * 60 + (int) substr($start, 3, 2);
        $e = (int) substr($end, 0, 2) * 60 + (int) substr($end, 3, 2);
        return [intdiv($s, self::STEP), intdiv($e + self::STEP - 1, self::STEP) - 1];
    }

    /** Distinct subject ids already sitting in any cell of this meeting (day 1 = Monday). */
    public function occupants(int $day, string $start, string $end): array
    {
        [$from, $to] = self::cellRange($start, $end);
        $found = [];
        $row = $this->cells[$day - 1];
        for ($c = $from; $c <= $to; $c++) {
            $id = $row[$c];
            if ($id !== null) {
                $seen = false;
                $n = count($found);
                for ($i = 0; $i < $n; $i++) {
                    $seen = $seen || $found[$i] === $id;
                }
                if (!$seen) {
                    $found[] = $id;
                }
            }
        }
        return $found;
    }

    /** Writes a meeting into its cells. */
    public function place(int $day, string $start, string $end, int $subjectId): void
    {
        [$from, $to] = self::cellRange($start, $end);
        for ($c = $from; $c <= $to; $c++) {
            if ($this->cells[$day - 1][$c] === null) {
                $this->filled++;
            }
            $this->cells[$day - 1][$c] = $subjectId;
        }
    }

    public function get(int $day, int $cell): ?int
    {
        return $this->cells[$day - 1][$cell] ?? null;
    }

    public function filledCells(): int
    {
        return $this->filled;
    }

    /** One day's row compressed into runs [{from, to, subject}] (cell indexes), for the "How it works" view. */
    public function runs(int $day): array
    {
        $runs = [];
        $row = $this->cells[$day - 1];
        $c = 0;
        while ($c < self::CELLS) {
            if ($row[$c] === null) {
                $c++;
                continue;
            }
            $id = $row[$c];
            $from = $c;
            while ($c < self::CELLS && $row[$c] === $id) {
                $c++;
            }
            $runs[] = ['from' => $from, 'to' => $c - 1, 'subject' => $id];
        }
        return $runs;
    }
}

/* ============================================================================
 * 2. LINKED LIST — "Managing sequences of records through insertion and deletion."
 *    A DOUBLY linked list of TaskNodes = the section's active task timeline.
 *    INSERTION: approved tasks are spliced in chronologically (Insertion Sort).
 *    DELETION:  expired tasks are dropped from the head (head = head->next) and
 *               an admin-removed task is unlinked from the middle (deleteById).
 *    Each node also points back (prev), so Task Details can step to the
 *    previous and next task by deadline in O(1) once the node is found.
 * ========================================================================= */
class TaskNode
{
    public ?TaskNode $next = null;
    public ?TaskNode $prev = null;
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
            if ($this->head !== null) {
                $this->head->prev = $node;
            }
            $this->head = $node;
            return;
        }

        $current = $this->head;
        while ($current->next !== null && !$this->comesBefore($node, $current->next)) {
            $current = $current->next;
        }
        $node->next = $current->next;
        $node->prev = $current;
        if ($current->next !== null) {
            $current->next->prev = $node;
        }
        $current->next = $node;
    }

    /**
     * The tasks just before and after one task, by deadline, read through the node's
     * prev / next pointers. Returns ['prev' => ?task, 'next' => ?task, 'position' => 1-based]
     * or null when the task isn't in the timeline (expired, removed, not approved).
     */
    public function neighbors(int $taskId): ?array
    {
        $i = 0;
        for ($n = $this->head; $n !== null; $n = $n->next) {
            $i++;
            if ((int) $n->task['id'] === $taskId) {
                return ['prev' => $n->prev?->task, 'next' => $n->next?->task, 'position' => $i];
            }
        }
        return null;
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
            if ($this->head !== null) {
                $this->head->prev = null;
            }
            $this->length--;
        }
        return $dropped;
    }

    /**
     * DELETION from anywhere in the list: walks to the node before the match
     * and re-points its `next` past it. Returns the removed task (or null).
     */
    public function deleteById(int $taskId): ?array
    {
        if ($this->head === null) {
            return null;
        }
        if ((int) $this->head->task['id'] === $taskId) {
            $removed = $this->head->task;
            $this->head = $this->head->next;
            if ($this->head !== null) {
                $this->head->prev = null;
            }
            $this->length--;
            return $removed;
        }
        $prev = $this->head;
        while ($prev->next !== null && (int) $prev->next->task['id'] !== $taskId) {
            $prev = $prev->next;
        }
        if ($prev->next === null) {
            return null;
        }
        $removed = $prev->next->task;
        $prev->next = $prev->next->next;   // unlink
        if ($prev->next !== null) {
            $prev->next->prev = $prev;      // and re-point the back link
        }
        $this->length--;
        return $removed;
    }

    /** 1-based position of a task in the timeline (0 if not found). */
    public function positionOf(int $taskId): int
    {
        $i = 0;
        for ($n = $this->head; $n !== null; $n = $n->next) {
            $i++;
            if ((int) $n->task['id'] === $taskId) {
                return $i;
            }
        }
        return 0;
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
 * 3. HASHSET — "Preventing duplicate entries or maintaining unique records."
 *    Custom djb2 hash + separate chaining gives O(1) average add/contains.
 *    Used to keep e-mails, student IDs, section names, class codes and a
 *    section's subject codes unique.
 * ========================================================================= */
class HashSet
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

    /** Keys are compared case-insensitively and without surrounding spaces. */
    protected function normalize(string $key): string
    {
        return DsaUtil::lower(trim($key));
    }

    /** djb2 string hash, reduced to a bucket index. */
    public function hash(string $key): int
    {
        $h = 5381;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $h = (($h * 33) + ord($key[$i])) % 2147483647;
        }
        return $h % $this->bucketCount;
    }

    public function contains(string $value): bool
    {
        $key = $this->normalize($value);
        $bucket = $this->buckets[$this->hash($key)];
        $n = count($bucket);
        for ($i = 0; $i < $n; $i++) {
            if ($bucket[$i] === $key) {
                return true;
            }
        }
        return false;
    }

    /** Adds a value. Returns false when it already exists (duplicate rejected). */
    public function add(string $value): bool
    {
        if ($this->contains($value)) {
            return false;
        }
        $key = $this->normalize($value);
        $this->buckets[$this->hash($key)][] = $key;
        $this->count++;
        return true;
    }

    public function size(): int
    {
        return $this->count;
    }

    /** Non-empty buckets, for the DSA viewer: [bucketIndex => [keys...]]. */
    public function buckets(): array
    {
        $out = [];
        foreach ($this->buckets as $i => $b) {
            if ($b) {
                $out[$i] = $b;
            }
        }
        return $out;
    }
}

/** HashSet of student e-mails (FR-1.2 duplicate-account check). */
class StudentHashSet extends HashSet
{
    public function addEmail(string $email): bool
    {
        return $this->add($email);
    }
}

/* ============================================================================
 * 4. STACK — "Implementing undo operations, history, or backtracking."
 *    LIFO buffer of rejected requests. Reject = push; "Undo" = pop the most
 *    recent rejection and send it back to the approval queue.
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
 * 5. QUEUE — "Managing waiting lists, service requests, or tasks in order."
 *    FIFO waiting list of student task requests. Submitting = enqueue at the
 *    rear; the admin can only decide the front request (dequeue), so requests
 *    are served strictly in the order they arrived.
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
 * 6. TREE — "Organizing hierarchical data or implementing tree-based searching."
 *    HIERARCHY: Section (root) -> Subjects (branches) -> Tasks (leaves).
 *    SEARCHING: search() walks the tree depth-first and returns every node
 *    whose label matches, together with its path (e.g. BSINFO-2A > GEC 107 >
 *    Reporting — Ch. 3). Powers the search bar.
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

    /**
     * Tree-based search (depth-first, pre-order). Matches a node when its
     * label — or for subjects, their title — contains the query.
     * Returns [['node' => data, 'path' => ['BSINFO-2A', 'GEC 107', ...]], ...].
     */
    public function search(string $query, ?TreeNode $node = null, array $path = []): array
    {
        $node ??= $this->root;
        $path[] = $node->label;
        $matches = [];
        $haystack = $node->label . ' ' . ($node->data['title'] ?? '');
        if ($node !== $this->root && DsaUtil::contains($haystack, $query)) {
            $matches[] = ['node' => $node->data, 'path' => $path];
        }
        $n = count($node->children);
        for ($i = 0; $i < $n; $i++) {
            $found = $this->search($query, $node->children[$i], $path);
            $m = count($found);
            for ($j = 0; $j < $m; $j++) {
                $matches[] = $found[$j];
            }
        }
        return $matches;
    }

    /** Finds a subject branch among the root's children by id. */
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

/**
 * A task's checklist as a TREE: the task is the root, items are children, and an
 * item can have sub-items ("Chapter 1" -> "Introduction", "RRL"). Progress is
 * rolled up with a POST-ORDER traversal: every child is counted before its parent,
 * so a parent's total/done is the sum of the leaves under it.
 */
class ChecklistTree
{
    public TreeNode $root;

    public function __construct(string $label)
    {
        $this->root = new TreeNode($label, ['type' => 'root', 'id' => 0]);
    }

    /** Builds the tree from flat rows that point at their parent (parent_id null = top level). */
    public static function fromRows(string $label, array $rows): self
    {
        $tree = new self($label);
        $nodes = [];
        foreach ($rows as $r) {
            $nodes[(int) $r['id']] = new TreeNode((string) $r['title'], [
                'type' => 'item', 'id' => (int) $r['id'], 'done' => (bool) (int) $r['done'],
                'assignee_id' => $r['assignee_id'] !== null ? (int) $r['assignee_id'] : null,
                'parent_id' => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
            ]);
        }
        // Second pass, so a child is attached even if it was stored before its parent
        foreach ($rows as $r) {
            $node = $nodes[(int) $r['id']];
            $parent = $r['parent_id'] !== null ? ($nodes[(int) $r['parent_id']] ?? null) : null;
            ($parent ?? $tree->root)->addChild($node);
        }
        return $tree;
    }

    /**
     * POST-ORDER roll-up: children first, then the node itself. A leaf counts as 1;
     * a parent's total and done are the sums over its children. Returns [done, total].
     */
    public function rollup(?TreeNode $node = null): array
    {
        $node ??= $this->root;
        $n = count($node->children);
        if ($n === 0) {
            $isItem = $node !== $this->root;
            $done = $isItem && $node->data['done'] ? 1 : 0;
            $node->data['total'] = $isItem ? 1 : 0;
            $node->data['done_count'] = $done;
            return [$done, $node->data['total']];
        }
        $done = 0;
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            [$d, $t] = $this->rollup($node->children[$i]);   // visit the children first…
            $done += $d;
            $total += $t;
        }
        $node->data['done_count'] = $done;                     // …then the node itself
        $node->data['total'] = $total;
        if ($node !== $this->root) {
            $node->data['done'] = $done === $total;
        }
        return [$done, $total];
    }

    /** The node with this id (depth-first), or null. */
    public function find(int $id, ?TreeNode $node = null): ?TreeNode
    {
        $node ??= $this->root;
        if ($node !== $this->root && (int) $node->data['id'] === $id) {
            return $node;
        }
        $n = count($node->children);
        for ($i = 0; $i < $n; $i++) {
            $hit = $this->find($id, $node->children[$i]);
            if ($hit !== null) {
                return $hit;
            }
        }
        return null;
    }

    /** Ids of a node and everything under it, children before parents (post-order). */
    public function subtreeIds(TreeNode $node): array
    {
        $ids = [];
        $n = count($node->children);
        for ($i = 0; $i < $n; $i++) {
            $child = $this->subtreeIds($node->children[$i]);
            $m = count($child);
            for ($j = 0; $j < $m; $j++) {
                $ids[] = $child[$j];
            }
        }
        if ($node !== $this->root) {
            $ids[] = (int) $node->data['id'];
        }
        return $ids;
    }

    /** Depth of a node (top-level items are 1). */
    public function depthOf(int $id, ?TreeNode $node = null, int $depth = 0): int
    {
        $node ??= $this->root;
        if ($node !== $this->root && (int) $node->data['id'] === $id) {
            return $depth;
        }
        $n = count($node->children);
        for ($i = 0; $i < $n; $i++) {
            $d = $this->depthOf($id, $node->children[$i], $depth + 1);
            if ($d > 0) {
                return $d;
            }
        }
        return 0;
    }

    public function toArray(?TreeNode $node = null): array
    {
        $node ??= $this->root;
        $children = [];
        $n = count($node->children);
        for ($i = 0; $i < $n; $i++) {
            $children[] = $this->toArray($node->children[$i]);
        }
        return ['title' => $node->label] + $node->data + ['children' => $children];
    }
}

/* ============================================================================
 * 7. GRAPH — "Representing connections, routes, relationships, or networks."
 *    Undirected graph stored as an adjacency list. Vertices = classmates,
 *    edges = "works with" relationships labelled by study group. Breadth-first
 *    traversal (teamMembers) finds everyone connected inside one group.
 * ========================================================================= */
class DependencyGraph
{
    /** @var array<int, array<int, array{to:int, context:string, group:?int}>> */
    private array $adjacency = [];

    public function addVertex(int $id): void
    {
        if (!isset($this->adjacency[$id])) {
            $this->adjacency[$id] = [];
        }
    }

    /**
     * $context is the group's name (for display); $groupId tells apart two groups that share
     * a name ("Group 3" of two different assignments), so a traversal never mixes them.
     */
    public function addEdge(int $a, int $b, string $context, ?int $groupId = null): void
    {
        $this->addVertex($a);
        $this->addVertex($b);
        $this->adjacency[$a][] = ['to' => $b, 'context' => $context, 'group' => $groupId];
        $this->adjacency[$b][] = ['to' => $a, 'context' => $context, 'group' => $groupId];
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
     * group, returns everyone reachable (the whole team). With $groupId the
     * edges are matched by the group's id, otherwise by its name.
     */
    public function teamMembers(int $start, string $context, ?int $groupId = null): array
    {
        $visited = [$start => true];
        $queue = new ApprovalQueue();
        $queue->enqueue($start);
        $team = [];
        while (!$queue->isEmpty()) {
            $id = $queue->dequeue();
            $team[] = $id;
            foreach ($this->neighbors($id) as $edge) {
                $sameGroup = $groupId !== null ? $edge['group'] === $groupId : $edge['context'] === $context;
                if ($sameGroup && !isset($visited[$edge['to']])) {
                    $visited[$edge['to']] = true;
                    $queue->enqueue($edge['to']);
                }
            }
        }
        return $team;
    }

    /** True when a and b share an edge, i.e. have been in a group together. */
    public function isLinked(int $a, int $b): bool
    {
        foreach ($this->neighbors($a) as $edge) {
            if ($edge['to'] === $b) {
                return true;
            }
        }
        return false;
    }

    /** Number of edges at a vertex (0 = has never grouped with anyone). */
    public function degree(int $id): int
    {
        return count($this->neighbors($id));
    }

    /**
     * Shortest chain of classmates from a to b: BREADTH-FIRST SEARCH that remembers
     * which vertex it came from, then walks those parents back from b.
     * Returns [a, …, b] (ids), or null when they aren't connected at all.
     */
    public function shortestPath(int $a, int $b): ?array
    {
        if ($a === $b) {
            return [$a];
        }
        $parent = [$a => $a];
        $queue = new ApprovalQueue();
        $queue->enqueue($a);
        while (!$queue->isEmpty()) {
            $id = $queue->dequeue();
            foreach ($this->neighbors($id) as $edge) {
                $to = $edge['to'];
                if (isset($parent[$to])) {
                    continue;
                }
                $parent[$to] = $id;
                if ($to === $b) {
                    $path = [$b];
                    for ($v = $b; $v !== $a; $v = $parent[$v]) {
                        $path[] = $parent[$v];
                    }
                    $out = [];
                    for ($i = count($path) - 1; $i >= 0; $i--) {
                        $out[] = $path[$i];
                    }
                    return $out;
                }
                $queue->enqueue($to);
            }
        }
        return null;
    }

    public function vertexCount(): int
    {
        return count($this->adjacency);
    }

    /** The raw adjacency list, for the DSA viewer. */
    public function adjacencyList(): array
    {
        return $this->adjacency;
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

    /**
     * Group-size planner for group work with a size RANGE (e.g. 3–4 per group),
     * optionally with an exact number of groups ("make 5 groups").
     *   - count given:  g = count
     *   - otherwise:    g = ceil(n / max), the fewest groups that never exceed max;
     *                   if that would leave groups under min (e.g. strict 3 and
     *                   4 students -> [2, 2]) use fewer groups, g = floor(n / min).
     * Students are then spread evenly; (n % g) groups get one extra member. When
     * there aren't enough people left for another proper group, that extra is
     * the "+1" a group may take above max.
     * Examples: 10 students, 3–4 -> [4, 3, 3];  17 students, exactly 3 -> [4, 4, 3, 3, 3];
     *           17 students, 3 each, 5 groups -> [4, 4, 3, 3, 3].
     */
    public static function planGroupSizes(int $n, int $min, int $max, ?int $count = null): array
    {
        if ($n <= 0) {
            return [];
        }
        if ($count !== null && $count > 0) {
            $g = $count;
        } else {
            $g = intdiv($n + $max - 1, $max);      // ceil(n / max)
            if (intdiv($n, $g) < $min) {
                $g = max(1, intdiv($n, $min));      // fewer, fuller groups; leftovers become +1s
            }
        }
        $base = intdiv($n, $g);
        $extra = $n % $g;
        $sizes = [];
        for ($i = 0; $i < $g; $i++) {
            $sizes[] = $base + ($i < $extra ? 1 : 0);
        }
        return $sizes;
    }

    /** Fisher–Yates shuffle, hand-written (used before auto-grouping). */
    public static function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }
        return $items;
    }

    /**
     * BINARY SEARCH on deadlines. $sorted is the timeline (Linked List order:
     * earliest deadline first, tasks with no date at the end). Finds the first task
     * due AFTER $limitTs by halving the range, so every task before that index is
     * "due by" the limit. $steps counts how many middles were checked.
     */
    public static function countDueBy(array $sorted, int $limitTs, int &$steps = 0): int
    {
        $low = 0;
        $high = count($sorted);                       // answer is in [low, high]
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            $steps++;
            $ts = DsaUtil::deadlineTs($sorted[$mid]);
            if ($ts !== null && $ts <= $limitTs) {
                $low = $mid + 1;                      // due by the limit: the answer is further right
            } else {
                $high = $mid;                         // later or no date: the answer is here or left
            }
        }
        return $low;
    }

    /**
     * ALGORITHM 6 — MERGE SORT (divide and conquer). Splits the list in halves,
     * sorts each half, then merges them by repeatedly taking the better front item.
     * O(n log n) comparisons. $steps counts comparisons so it can be compared with
     * Bubble and Selection Sort on the same list. Stable: equal keys keep their order.
     */
    public static function mergeSortBy(array $items, string $key, bool $desc, int &$steps = 0): array
    {
        $n = count($items);
        if ($n <= 1) {
            return $items;
        }
        $mid = intdiv($n, 2);
        $left = [];
        $right = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i < $mid) {
                $left[] = $items[$i];
            } else {
                $right[] = $items[$i];
            }
        }
        $left = self::mergeSortBy($left, $key, $desc, $steps);
        $right = self::mergeSortBy($right, $key, $desc, $steps);

        $out = [];
        $i = 0;
        $j = 0;
        $nl = count($left);
        $nr = count($right);
        while ($i < $nl && $j < $nr) {
            $steps++;
            $takeLeft = $desc ? $left[$i][$key] >= $right[$j][$key] : $left[$i][$key] <= $right[$j][$key];
            if ($takeLeft) {
                $out[] = $left[$i++];
            } else {
                $out[] = $right[$j++];
            }
        }
        while ($i < $nl) {
            $out[] = $left[$i++];
        }
        while ($j < $nr) {
            $out[] = $right[$j++];
        }
        return $out;
    }

    /** BUBBLE SORT on any key, counting comparisons — for the side-by-side comparison only. */
    public static function bubbleSortBy(array $items, string $key, bool $desc, int &$steps = 0): array
    {
        $n = count($items);
        for ($pass = 0; $pass < $n - 1; $pass++) {
            $swapped = false;
            for ($i = 0; $i < $n - 1 - $pass; $i++) {
                $steps++;
                $wrong = $desc ? $items[$i][$key] < $items[$i + 1][$key] : $items[$i][$key] > $items[$i + 1][$key];
                if ($wrong) {
                    $tmp = $items[$i];
                    $items[$i] = $items[$i + 1];
                    $items[$i + 1] = $tmp;
                    $swapped = true;
                }
            }
            if (!$swapped) {
                break;
            }
        }
        return $items;
    }

    /** SELECTION SORT on any key, counting comparisons — for the side-by-side comparison only. */
    public static function selectionSortBy(array $items, string $key, bool $desc, int &$steps = 0): array
    {
        $n = count($items);
        for ($i = 0; $i < $n - 1; $i++) {
            $best = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                $steps++;
                $better = $desc ? $items[$j][$key] > $items[$best][$key] : $items[$j][$key] < $items[$best][$key];
                if ($better) {
                    $best = $j;
                }
            }
            if ($best !== $i) {
                $tmp = $items[$i];
                $items[$i] = $items[$best];
                $items[$best] = $tmp;
            }
        }
        return $items;
    }

    /**
     * LINEAR SEARCH on a plain list: checks each item in turn with ===.
     * Returns the index of the first match, or -1 when it isn't there.
     * Used for "is this person in the group's member list" (a group holds a few ids).
     */
    public static function linearSearch(array $items, mixed $target): int
    {
        $i = 0;
        foreach ($items as $item) {
            if ($item === $target) {
                return $i;
            }
            $i++;
        }
        return -1;
    }

    /** True when linearSearch() finds $target in $items. */
    public static function linearContains(array $items, mixed $target): bool
    {
        return self::linearSearch($items, $target) !== -1;
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
