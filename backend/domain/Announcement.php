<?php
/**
 * [OOP] Announcements: each kind of news is its own class, and a typed announcement DOES the
 * real thing it announces (moves the deadline, makes the due, cancels the class…), in the same
 * transaction as the post, so the app never says two different things.
 *
 *   Announcement (abstract)
 *    ├─ GeneralNotice         plain news: a title and a message
 *    ├─ PaymentNotice         a due (an existing one, or a new one made here)
 *    ├─ NoClassNotice         a class (or the whole day) won't happen on a date: crossed out on Schedule
 *    ├─ DeadlineChangeNotice  moves a task's deadline
 *    ├─ TaskNotice            reminds everyone of an upcoming task
 *    └─ EventNotice           an activity (an existing one, or a new one made here)
 *
 *   ABSTRACTION   Announcement declares what every kind answers: its default title, the facts on
 *                 its card, its real change (apply), and what removing it undoes (onRemove).
 *   INHERITANCE   the six kinds share the title, message, pin, author and date from Announcement.
 *   POLYMORPHISM  api/announcements.php calls fromInput(), apply(), facts(), notifyText() and never
 *                 tests the kind string; each subclass answers in its own way.
 *   ENCAPSULATION fields are private / protected and checked once in the build step (fromInput);
 *                 an invalid announcement (a class that doesn't meet that day, a past deadline…)
 *                 can't be built.
 */

declare(strict_types=1);

/** Everything a kind needs to check its input and word its card, for one section (read-only). */
final class AnnouncementContext
{
    /**
     * @param array    $tasks      taskIndex: id => tasks row
     * @param array    $subjects   subjectIndex: id => subject row
     * @param array    $slots      class_slots() rows (subject_id, day_of_week 1–6, start_time, end_time, code)
     * @param Due[]    $dues       the section's OPEN dues, by id
     * @param Activity[] $activities the section's activities, by id
     * @param HashSet  $cancelled  "Y-m-d|subjectId" / "Y-m-d|*" for classes already cancelled
     * @param array    $going      activity id => how many have a seat
     * @param array    $openSpots  activity id => ['open' => spots still open, 'parts' => number of parts] (competitions, projects)
     */
    public function __construct(
        public readonly int $sectionId,
        public readonly int $userId,
        public readonly Person $me,
        public readonly array $tasks,
        public readonly array $subjects,
        public readonly array $slots,
        public readonly array $dues,
        public readonly array $activities,
        public readonly HashSet $cancelled,
        public readonly int $now,
        public readonly array $going = [],
        public readonly array $openSpots = [],
    ) {
    }

    public function today(): string
    {
        return date('Y-m-d', $this->now);
    }
}

/** The poster may not make this choice (the endpoint answers 403). */
final class AnnouncementNotAllowed extends InvalidArgumentException
{
}

abstract class Announcement
{
    /** kind => class, in the order the composer lists them. */
    public const KINDS = [
        'general' => GeneralNotice::class,
        'payment' => PaymentNotice::class,
        'no_class' => NoClassNotice::class,
        'deadline_change' => DeadlineChangeNotice::class,
        'task' => TaskNotice::class,
        'event' => EventNotice::class,
    ];

    /** Set by each subclass: the stored kind, its label and its icon. */
    public const KIND = '';
    public const LABEL = '';
    public const ICON = 'bell';

    public function __construct(
        private int $id,
        private string $title,
        private string $body,
        private bool $pinned,
        private int $createdBy,
        private string $createdAt,
        protected ?int $refId,
        protected ?string $eventAt,
        protected array $details,
    ) {
        if (strlen($title) > 80) {
            throw new InvalidArgumentException('Give the announcement a title (up to 80 characters).');
        }
        if (strlen($body) > 1000) {
            throw new InvalidArgumentException('Keep the message under 1,000 characters.');
        }
    }

    /** Factory for a stored row; a row without a kind (made before kinds existed) is a plain notice. */
    public static function fromRow(array $row): Announcement
    {
        $class = self::KINDS[$row['kind'] ?? 'general'] ?? GeneralNotice::class;
        $details = !empty($row['details']) ? json_decode((string) $row['details'], true) : [];
        return new $class((int) $row['id'], (string) $row['title'], (string) ($row['body'] ?? ''), (bool) (int) ($row['pinned'] ?? 0),
            (int) $row['created_by'], (string) ($row['created_at'] ?? ''), isset($row['ref_id']) ? (int) $row['ref_id'] : null,
            ($row['event_at'] ?? null) ?: null, is_array($details) ? $details : []);
    }

    /** Factory for a new announcement from the composer. Throws InvalidArgumentException with the message to show. */
    public static function fromInput(string $kind, array $in, AnnouncementContext $c): Announcement
    {
        $class = self::KINDS[$kind] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException('Pick what kind of announcement it is.');
        }
        // Who may do this is checked first, so someone not allowed never sees a detail error first
        $needs = $class::permissionFor($in);
        if ($needs !== null && !$c->me->can($needs)) {
            throw new AnnouncementNotAllowed(self::deniedMessage($needs));
        }
        return $class::build($in, $c);
    }

    /** The permission this kind needs for this input, before it is built (null = posting is enough). */
    public static function permissionFor(array $in): ?string
    {
        return null;
    }

    /** What someone without $permission is told. */
    public static function deniedMessage(string $permission): string
    {
        return match ($permission) {
            Permission::PUBLISH_TASK => 'Only the President can move a deadline.',
            Permission::MANAGE_DUES => 'Only the Treasurer or the President can add a due.',
            default => 'Only officers can add activities.',
        };
    }

    /** Should this member get the notification? (Payment: only members who are charged dues; Event: only the invited.) */
    public function notifies(array $member, AnnouncementContext $c): bool
    {
        return true;
    }

    /** Has it happened already? Dated kinds: their day is before today; Event: the activity has ended. */
    public function isOverIn(AnnouncementContext $c): bool
    {
        $at = $this->eventAtIn($c);
        return $at !== null && $at < (int) strtotime(date('Y-m-d', $c->now));
    }

    /** The kinds this person may post: [['kind', 'label', 'icon'], …]. */
    public static function kinds(AnnouncementContext $c): array
    {
        $out = [];
        foreach (self::KINDS as $kind => $class) {
            if ($class::canPost($c)) {
                $out[] = ['kind' => $kind, 'label' => $class::LABEL, 'icon' => $class::ICON];
            }
        }
        return $out;
    }

    /** May this person post this kind at all? (A kind may also need more for some choices: requiredPermission.) */
    public static function canPost(AnnouncementContext $c): bool
    {
        return true;
    }

    /** Checks the composer's input for this kind and builds it. */
    abstract protected static function build(array $in, AnnouncementContext $c): Announcement;

    /** Title, message and pin, common to every kind. */
    protected static function common(array $in, AnnouncementContext $c): array
    {
        return [
            0, preg_replace('/\s+/', ' ', trim((string) ($in['title'] ?? ''))), trim((string) ($in['body'] ?? '')),
            filter_var($in['pinned'] ?? false, FILTER_VALIDATE_BOOLEAN), $c->userId, date('Y-m-d H:i:s', $c->now),
        ];
    }

    /** The title written for this kind when the poster leaves it empty. */
    abstract public function defaultTitle(): string;

    /** The lines on the card and its button: ['lines' => [string…], 'action' => ['label', 'href'] | null]. */
    abstract public function facts(AnnouncementContext $c): array;

    /** The real change, run right after the announcement row is inserted, in the same transaction. */
    abstract public function apply(AnnouncementContext $c, int $announcementId): void;

    /** What removing the announcement undoes (most kinds: nothing; the change stays). */
    public function onRemove(AnnouncementContext $c): void
    {
    }

    /** A permission the poster also needs for this particular choice (null = posting is enough). */
    public function requiredPermission(): ?string
    {
        return null;
    }

    public function kind(): string
    {
        return static::KIND;
    }

    public function kindLabel(): string
    {
        return static::LABEL;
    }

    public function icon(): string
    {
        return static::ICON;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title !== '' ? $this->title : $this->defaultTitle();
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function createdBy(): int
    {
        return $this->createdBy;
    }

    /** When it happens (sort date) as stored at posting, or null for undated news. */
    public function eventAt(): ?int
    {
        return $this->eventAt !== null ? ((int) strtotime($this->eventAt) ?: null) : null;
    }

    /**
     * When it happens NOW, for listing and the "in 3 days" chip. Most kinds keep their posted date;
     * Task and Deadline change notices follow the task's current deadline, so a later move doesn't
     * leave an old notice showing (or sorting by) the old date.
     */
    public function eventAtIn(AnnouncementContext $c): ?int
    {
        return $this->eventAt();
    }

    /** The notification everyone else gets. */
    public function notifyText(AnnouncementContext $c): string
    {
        return "{$c->me->roleLabel()} {$c->me->firstName()}: {$this->title()}";
    }

    public function notifyLink(): string
    {
        return 'home.html#announcements';
    }

    public function notifyType(): string
    {
        return 'announcement';
    }

    public function toRow(int $sectionId): array
    {
        return [
            'section_id' => $sectionId, 'kind' => $this->kind(), 'title' => $this->title(), 'body' => $this->body,
            'pinned' => $this->pinned ? 1 : 0, 'created_by' => $this->createdBy, 'ref_id' => $this->refId,
            'event_at' => $this->eventAt, 'details' => $this->details ? json_encode($this->details, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    public function toArray(AnnouncementContext $c, bool $canDelete): array
    {
        $facts = $this->facts($c);
        return [
            'id' => $this->id, 'kind' => $this->kind(), 'kind_label' => $this->kindLabel(), 'icon' => $this->icon(),
            'title' => $this->title(), 'body' => $this->body, 'lines' => $facts['lines'], 'action' => $facts['action'],
            'event_at' => $this->eventAt, 'event_ts' => $this->eventAtIn($c),
            // the calendar day in the section's time zone, so a browser elsewhere still shows the right day
            'event_on' => $this->eventAtIn($c) !== null ? date('Y-m-d', $this->eventAtIn($c)) : null,
            'pinned' => $this->pinned, 'pin_key' => $this->pinned ? 1 : 0,
            'by_id' => $this->createdBy, 'created_at' => $this->createdAt, 'can_delete' => $canDelete,
        ];
    }

    /** Points the announcement at the item its apply() just made. */
    protected function linkTo(int $announcementId, int $refId): void
    {
        $this->refId = $refId;
        Store::update('announcements', $announcementId, ['ref_id' => $refId]);
    }

    /** A task that is published and still ahead (or has no date yet), or null. */
    protected static function upcomingTask(AnnouncementContext $c, int $taskId, bool $mustBeAhead): ?array
    {
        $t = $c->tasks[$taskId] ?? null;
        if ($t === null || $t['status'] !== 'approved' || ($t['kind'] ?? 'new') !== 'new') {
            return null;
        }
        $ts = Deadlines::fromRow($t)->timestamp();
        return $mustBeAhead && $ts !== null && $ts < $c->now ? null : $t;
    }

    /** The current deadline of the task this notice points at (null when it has no date or is gone). */
    protected function liveTaskDeadline(AnnouncementContext $c): ?int
    {
        $t = $this->refId !== null ? self::upcomingTask($c, $this->refId, false) : null;
        return $t !== null ? Deadlines::fromRow($t)->timestamp() : null;
    }

    /** "1:00–2:30 PM" */
    protected static function timeRange(string $start, string $end): string
    {
        return date('g:i', (int) strtotime($start)) . '–' . date('g:i A', (int) strtotime($end));
    }
}

/** Plain news: a title and a message. */
final class GeneralNotice extends Announcement
{
    public const KIND = 'general';
    public const LABEL = 'Notice';
    public const ICON = 'bell';

    protected static function build(array $in, AnnouncementContext $c): Announcement
    {
        $args = self::common($in, $c);
        if ($args[1] === '') {
            throw new InvalidArgumentException('Give the announcement a title (up to 80 characters).');
        }
        return new self(...[...$args, null, null, []]);
    }

    public function defaultTitle(): string
    {
        return 'Announcement';
    }

    public function facts(AnnouncementContext $c): array
    {
        return ['lines' => [], 'action' => null];
    }

    public function apply(AnnouncementContext $c, int $announcementId): void
    {
    }
}

/** A due: an existing open one, or a new one made from the announcement (Treasurer / President). */
final class PaymentNotice extends Announcement
{
    public const KIND = 'payment';
    public const LABEL = 'Payment';
    public const ICON = 'clipboard';

    /** Only offered when there's an open due to point at, or this person may make one. */
    public static function canPost(AnnouncementContext $c): bool
    {
        return $c->dues !== [] || $c->me->can(Permission::MANAGE_DUES);
    }

    public static function permissionFor(array $in): ?string
    {
        return is_array($in['new_due'] ?? null) && $in['new_due'] ? Permission::MANAGE_DUES : null;
    }

    /** Only members who are charged dues (Active) are told about a payment. */
    public function notifies(array $member, AnnouncementContext $c): bool
    {
        return Person::fromRow($member)->status()->owesDues();
    }

    protected static function build(array $in, AnnouncementContext $c): Announcement
    {
        $new = $in['new_due'] ?? null;
        if (is_array($new) && $new) {
            $amount = Due::centavos((string) ($new['amount'] ?? ''));
            if ($amount === null) {
                throw new InvalidArgumentException('Enter the amount in pesos, e.g. 150 or 150.50.');
            }
            $date = trim((string) ($new['due_date'] ?? '')) ?: null;
            if ($date !== null && $date < $c->today()) {
                throw new InvalidArgumentException('Pick today or a later day for the due date.');
            }
            // [OOP: ENCAPSULATION] — the Due constructor rejects a bad title, amount or date
            $due = new Due(0, preg_replace('/\s+/', ' ', trim((string) ($new['title'] ?? ''))), $amount, $date, 'open', $c->userId);
            $details = ['create' => true, 'title' => $due->title(), 'amount' => $due->amount(), 'due_date' => $due->dueDate()];
            return new self(...[...self::common($in, $c), null, $date ? "$date 23:59" : null, $details]);
        }
        $due = $c->dues[(int) ($in['due_id'] ?? 0)] ?? null;
        if ($due === null) {
            throw new InvalidArgumentException('Pick an open due from this section.');
        }
        $details = ['title' => $due->title(), 'amount' => $due->amount(), 'due_date' => $due->dueDate()];
        // A reminder for a due that is already overdue stays undated (under Notices), so it isn't
        // folded straight into "Earlier" where nobody sees it
        $when = $due->dueDate() !== null && $due->dueDate() >= $c->today() ? $due->dueDate() . ' 23:59' : null;
        return new self(...[...self::common($in, $c), $due->id(), $when, $details]);
    }

    public function requiredPermission(): ?string
    {
        return !empty($this->details['create']) ? Permission::MANAGE_DUES : null;
    }

    public function defaultTitle(): string
    {
        return 'Payment: ' . ($this->details['title'] ?? 'dues');
    }

    public function facts(AnnouncementContext $c): array
    {
        if ($this->refId !== null && !isset($c->dues[$this->refId])) {
            return ['lines' => [$this->details['title'] ?? 'Due', 'No longer available'], 'action' => null];
        }
        $when = !empty($this->details['due_date']) ? 'due ' . date('D, M j', (int) strtotime($this->details['due_date'])) : 'no deadline';
        return ['lines' => [Due::peso((int) ($this->details['amount'] ?? 0)) . " · $when"], 'action' => ['label' => 'View dues', 'href' => 'dues.html']];
    }

    public function apply(AnnouncementContext $c, int $announcementId): void
    {
        if (empty($this->details['create'])) {
            return;
        }
        $due = new Due(0, (string) $this->details['title'], (int) $this->details['amount'], $this->details['due_date'] ?? null, 'open', $c->userId);
        $this->linkTo($announcementId, Store::insert('dues', $due->toRow($c->sectionId)));
    }

    public function notifyText(AnnouncementContext $c): string
    {
        $when = !empty($this->details['due_date']) ? ', due ' . date('M j', (int) strtotime($this->details['due_date'])) : '';
        return (!empty($this->details['create']) ? 'New due: ' : 'Payment reminder: ') . ($this->details['title'] ?? '')
            . ', ' . Due::peso((int) ($this->details['amount'] ?? 0)) . $when;
    }

    public function notifyLink(): string
    {
        return 'dues.html';
    }

    public function notifyType(): string
    {
        return 'dues';
    }
}

/** A class (or every class that day) won't happen on one date. The Schedule crosses it out. */
final class NoClassNotice extends Announcement
{
    public const KIND = 'no_class';
    public const LABEL = 'No class';
    public const ICON = 'x';

    protected static function build(array $in, AnnouncementContext $c): Announcement
    {
        $date = trim((string) ($in['date'] ?? ''));
        $ts = is_real_date($date) ? strtotime($date) : false;
        if ($ts === false) {
            throw new InvalidArgumentException('Pick the day there’s no class.');
        }
        if ($date < $c->today()) {
            throw new InvalidArgumentException('Pick today or a later day.');
        }
        $dow = (int) date('N', $ts);
        $weekdays = date('l', $ts) . 's';
        $subjectId = (int) ($in['subject_id'] ?? 0) ?: null;
        // [DSA: HASHSET] — a class can only be cancelled once on a date (whole day covers every subject)
        $already = $c->cancelled->contains("$date|*") || ($subjectId !== null && $c->cancelled->contains("$date|$subjectId"));
        $slots = [];
        foreach ($c->slots as $s) {
            if ((int) $s['day_of_week'] === $dow && ($subjectId === null || (int) $s['subject_id'] === $subjectId)) {
                $slots[] = $s;
            }
        }
        if ($subjectId !== null) {
            $subject = $c->subjects[$subjectId] ?? null;
            if ($subject === null) {
                throw new InvalidArgumentException('Pick a subject from this section.');
            }
            if (!$slots) {
                throw new InvalidArgumentException("{$subject['code']} has no class on $weekdays.");
            }
        } elseif (!$slots) {
            throw new InvalidArgumentException("There are no classes on $weekdays.");
        }
        if ($already) {
            throw new InvalidArgumentException('That class is already cancelled on ' . date('D, M j', $ts) . '.');
        }
        $details = ['date' => $date, 'subject_id' => $subjectId, 'code' => $subjectId !== null ? $c->subjects[$subjectId]['code'] : null,
            'times' => $subjectId !== null ? self::meetingTimes($slots) : null];
        return new self(...[...self::common($in, $c), null, "$date 00:00", $details]);
    }

    /**
     * Every meeting that day, earliest first: "1:00–2:30 PM, 4:00–5:00 PM".
     * [ALGORITHM: INSERTION SORT] on start time (a subject meets at most a few times a day).
     */
    private static function meetingTimes(array $slots): string
    {
        $sorted = [];
        foreach ($slots as $s) {
            $i = count($sorted) - 1;
            while ($i >= 0 && DsaUtil::compareText($sorted[$i]['start_time'], $s['start_time']) > 0) {
                $sorted[$i + 1] = $sorted[$i];
                $i--;
            }
            $sorted[$i + 1] = $s;
        }
        $parts = [];
        foreach ($sorted as $s) {
            $parts[] = self::timeRange($s['start_time'], $s['end_time']);
        }
        return implode(', ', $parts);
    }

    public function defaultTitle(): string
    {
        $day = date('l', (int) strtotime((string) $this->details['date']));
        return $this->details['code'] ? "No {$this->details['code']} class on $day" : "No class on $day";
    }

    public function facts(AnnouncementContext $c): array
    {
        $line = $this->details['code'] ? "{$this->details['code']} · {$this->details['times']}" : 'All classes that day';
        return ['lines' => [$line], 'action' => ['label' => 'Open schedule', 'href' => 'schedule.html']];
    }

    public function apply(AnnouncementContext $c, int $announcementId): void
    {
        Store::insert('class_cancellations', [
            'section_id' => $c->sectionId, 'announcement_id' => $announcementId, 'date' => $this->details['date'],
            'subject_id' => $this->details['subject_id'], 'created_by' => $c->userId,
        ]);
    }

    /** Removing the notice brings the class back. */
    public function onRemove(AnnouncementContext $c): void
    {
        foreach (section_rows('class_cancellations', $c->sectionId) as $row) {
            if ((int) $row['announcement_id'] === $this->id()) {
                Store::delete('class_cancellations', (int) $row['id']);
            }
        }
    }

    public function notifyText(AnnouncementContext $c): string
    {
        $day = date('D, M j', (int) strtotime((string) $this->details['date']));
        return "No class on $day" . ($this->details['code'] ? " · {$this->details['code']}" : ' (all classes)');
    }

    public function notifyLink(): string
    {
        return 'schedule.html';
    }

    public function notifyType(): string
    {
        return 'schedule';
    }
}

/** Moves a task's deadline (the President). The schedule, Urgent list and every card follow. */
final class DeadlineChangeNotice extends Announcement
{
    public const KIND = 'deadline_change';
    public const LABEL = 'Deadline change';
    public const ICON = 'clock';

    public static function canPost(AnnouncementContext $c): bool
    {
        return $c->me->can(Permission::PUBLISH_TASK);
    }

    public static function permissionFor(array $in): ?string
    {
        return Permission::PUBLISH_TASK;
    }

    protected static function build(array $in, AnnouncementContext $c): Announcement
    {
        $task = self::upcomingTask($c, (int) ($in['task_id'] ?? 0), false);
        if ($task === null) {
            throw new InvalidArgumentException('Pick an upcoming task.');
        }
        $new = validate_deadline($in, $c->today());
        $from = Deadlines::fromRow($task)->label();
        $to = Deadlines::fromRow($new)->label();
        if ($from === $to) {
            throw new InvalidArgumentException('That’s the same deadline it has now.');
        }
        $ts = Deadlines::fromRow($new)->timestamp();
        $details = ['task_title' => $task['title'], 'from' => $from, 'to' => $to, 'new' => $new];
        return new self(...[...self::common($in, $c), (int) $task['id'], $ts !== null ? date('Y-m-d H:i', $ts) : null, $details]);
    }

    public function requiredPermission(): ?string
    {
        return Permission::PUBLISH_TASK;
    }

    public function defaultTitle(): string
    {
        return 'Deadline moved: ' . ($this->details['task_title'] ?? 'task');
    }

    public function eventAtIn(AnnouncementContext $c): ?int
    {
        return $this->liveTaskDeadline($c) ?? $this->eventAt();
    }

    public function facts(AnnouncementContext $c): array
    {
        $lines = [$this->details['task_title'] ?? 'Task', ($this->details['from'] ?? '') . ' → ' . ($this->details['to'] ?? '')];
        $task = self::upcomingTask($c, (int) $this->refId, false);
        if ($task === null) {
            return ['lines' => [...$lines, 'No longer available'], 'action' => null];
        }
        $now = Deadlines::fromRow($task)->label();
        if ($now !== ($this->details['to'] ?? '')) {
            $lines[] = "Moved again: $now";   // a later change moved it again
        }
        return ['lines' => $lines, 'action' => ['label' => 'Open task', 'href' => "task.html?id={$this->refId}"]];
    }

    public function apply(AnnouncementContext $c, int $announcementId): void
    {
        Store::update('tasks', (int) $this->refId, $this->details['new']);
    }

    public function notifyText(AnnouncementContext $c): string
    {
        return "Deadline moved: {$this->details['task_title']} · {$this->details['to']}";
    }

    public function notifyLink(): string
    {
        return "task.html?id={$this->refId}";
    }

    public function notifyType(): string
    {
        return 'moved';
    }
}

/** A reminder about an upcoming task (creating tasks stays on Add Task). */
final class TaskNotice extends Announcement
{
    public const KIND = 'task';
    public const LABEL = 'Task';
    public const ICON = 'notebook';

    protected static function build(array $in, AnnouncementContext $c): Announcement
    {
        $task = self::upcomingTask($c, (int) ($in['task_id'] ?? 0), true);
        if ($task === null) {
            throw new InvalidArgumentException('Pick an upcoming task.');
        }
        $deadline = Deadlines::fromRow($task);
        $code = $c->subjects[(int) $task['subject_id']]['code'] ?? '';
        $details = ['task_title' => $task['title'], 'code' => $code, 'label' => $deadline->label()];
        return new self(...[...self::common($in, $c), (int) $task['id'],
            $deadline->timestamp() !== null ? date('Y-m-d H:i', $deadline->timestamp()) : null, $details]);
    }

    public function defaultTitle(): string
    {
        return ($this->details['task_title'] ?? 'A task') . ' is coming up';
    }

    public function eventAtIn(AnnouncementContext $c): ?int
    {
        return $this->liveTaskDeadline($c) ?? $this->eventAt();
    }

    public function facts(AnnouncementContext $c): array
    {
        $task = self::upcomingTask($c, (int) $this->refId, false);
        if ($task === null) {
            return ['lines' => [$this->details['task_title'] ?? 'Task', 'No longer available'], 'action' => null];
        }
        $line = trim(($this->details['code'] ?? '') . ' · ' . Deadlines::fromRow($task)->label(), ' ·');
        return ['lines' => [$line], 'action' => ['label' => 'Open task', 'href' => "task.html?id={$this->refId}"]];
    }

    public function apply(AnnouncementContext $c, int $announcementId): void
    {
    }

    public function notifyText(AnnouncementContext $c): string
    {
        return "{$this->details['task_title']} · {$this->details['label']}";
    }

    public function notifyLink(): string
    {
        return "task.html?id={$this->refId}";
    }

    public function notifyType(): string
    {
        return 'schedule';
    }
}

/** An activity: an existing upcoming one, or a new one made from the announcement. */
final class EventNotice extends Announcement
{
    public const KIND = 'event';
    public const LABEL = 'Event';
    public const ICON = 'flag';

    protected static function build(array $in, AnnouncementContext $c): Announcement
    {
        $new = $in['new_activity'] ?? null;
        if (is_array($new) && $new) {
            // The composer has no member picker, so an activity made here is always for everyone
            // (apply() only inserts the activity row, never invite rows)
            $a = validate_new_activity(['audience' => 'everyone', 'invited' => []] + $new, $c->userId, $c->now);   // [OOP: ENCAPSULATION] Activity checks itself
            if ($a->joinsBy() !== 'rsvp') {                            // [OOP: POLYMORPHISM] parts kinds need their parts
                throw new InvalidArgumentException('Post a competition or project from Activities: it needs its parts.');
            }
            return new self(...[...self::common($in, $c), null, date('Y-m-d H:i', $a->startTs()), ['create' => true] + self::snapshot($a)]);
        }
        $a = $c->activities[(int) ($in['activity_id'] ?? 0)] ?? null;
        if ($a === null || $a->isCancelled() || $a->isOver($c->now)) {
            throw new InvalidArgumentException('Pick an upcoming activity.');
        }
        return new self(...[...self::common($in, $c), $a->id(), date('Y-m-d H:i', $a->startTs()), self::snapshot($a)]);
    }

    private static function snapshot(Activity $a): array
    {
        $row = $a->toRow();
        return ['row' => $row, 'title' => $a->title(), 'kind_label' => $a->kindLabel(), 'venue' => $row['venue'] ?? '',
            'starts_at' => $row['starts_at'], 'ends_at' => $row['ends_at'], 'capacity' => $a->capacity()];
    }

    /**
     * An invite-only activity: only the people invited NOW are told ([DSA: HASHSET] Activity::isFor on the live
     * activity, not a copy kept in the announcement). A new activity made here is for everyone.
     */
    public function notifies(array $member, AnnouncementContext $c): bool
    {
        $a = $this->refId !== null ? ($c->activities[$this->refId] ?? null) : null;
        return $a === null || $a->isFor((int) $member['id']);
    }

    public function requiredPermission(): ?string
    {
        return !empty($this->details['create']) ? Permission::MANAGE_ACTIVITIES : null;
    }

    public static function permissionFor(array $in): ?string
    {
        return is_array($in['new_activity'] ?? null) && $in['new_activity'] ? Permission::MANAGE_ACTIVITIES : null;
    }

    /** Over as soon as the activity has ended (not at midnight), so it moves to "Earlier". */
    public function isOverIn(AnnouncementContext $c): bool
    {
        $a = $this->refId !== null ? ($c->activities[$this->refId] ?? null) : null;
        return $a !== null ? $a->isOver($c->now) : parent::isOverIn($c);
    }

    public function defaultTitle(): string
    {
        return ($this->details['kind_label'] ?? 'Event') . ': ' . ($this->details['title'] ?? '');
    }

    public function facts(AnnouncementContext $c): array
    {
        $a = $this->refId !== null ? ($c->activities[$this->refId] ?? null) : null;
        if ($a === null || $a->isCancelled()) {
            return ['lines' => [$this->details['title'] ?? 'Activity', 'No longer available'], 'action' => null];
        }
        $start = (string) $this->details['starts_at'];
        $endsAt = (string) $this->details['ends_at'];
        $days = date('D, M j', (int) strtotime($start))
            . (substr($endsAt, 0, 10) !== substr($start, 0, 10) ? ' – ' . date('D, M j', (int) strtotime($endsAt)) : '');   // several days
        $line = $days . ' · ' . self::timeRange(substr($start, 11, 5), substr($endsAt, 11, 5))
            . ($this->details['venue'] !== '' ? ' · ' . $this->details['venue'] : '');
        $lines = [$line];
        if ($a->isOver($c->now)) {
            return ['lines' => [...$lines, 'Finished'], 'action' => null];   // no RSVP once it has ended
        }
        $href = "activities.html#a{$a->id()}";
        if ($a->joinsBy() === 'parts') {                 // [OOP: POLYMORPHISM] competitions and projects: parts, not seats
            if (!$a->signupOpen($c->now)) {
                return ['lines' => [...$lines, 'Sign-ups closed'], 'action' => ['label' => 'Open', 'href' => $href]];
            }
            $o = $c->openSpots[$a->id()] ?? ['open' => 0, 'parts' => 0];
            $lines[] = ($o['open'] === 1 ? '1 spot open' : "{$o['open']} spots open") . ' across ' . ($o['parts'] === 1 ? '1 part' : "{$o['parts']} parts");
            $lines[] = 'Sign up by ' . date('D, M j', $a->signupClosesAt());   // [OOP: POLYMORPHISM] each kind's own close
            return ['lines' => $lines, 'action' => ['label' => 'Volunteer', 'href' => $href]];
        }
        if ($a->capacity() !== null) {
            $left = max(0, $a->capacity() - ($c->going[$a->id()] ?? 0));
            $lines[] = $left === 0 ? 'Full · join the waitlist' : ($left === 1 ? '1 seat left' : "$left seats left");
        }
        return ['lines' => $lines, 'action' => ['label' => 'RSVP', 'href' => 'activities.html']];
    }

    public function apply(AnnouncementContext $c, int $announcementId): void
    {
        if (empty($this->details['create'])) {
            return;
        }
        $this->linkTo($announcementId, Store::insert('activities', $this->details['row'] + ['section_id' => $c->sectionId]));
    }

    public function notifyText(AnnouncementContext $c): string
    {
        $start = (string) $this->details['starts_at'];
        return "{$this->details['kind_label']}: {$this->details['title']} · " . date('D, M j · g:i A', (int) strtotime($start))
            . ($this->details['venue'] !== '' ? " · {$this->details['venue']}" : '');
    }

    public function notifyLink(): string
    {
        return 'activities.html';
    }

    public function notifyType(): string
    {
        return 'schedule';
    }
}
