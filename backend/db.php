<?php
/**
 * SQLite connection + raw storage helpers.
 *
 * DSA rule: reads are always a plain `SELECT * FROM table` (no WHERE, ORDER BY
 * or JOIN). Filtering happens in PHP loops; sorting and searching happen in
 * custom_dsa.php. Writes use INSERT / UPDATE / DELETE by primary key only.
 */

declare(strict_types=1);

/** Bump when schema.sql changes; an older database file is backed up and rebuilt. */
const SCHEMA_VERSION = 3;

final class Store
{
    private static ?PDO $pdo = null;

    /** Where the SQLite file lives: DB_PATH in .env (default data/acadsync.db). */
    public static function path(): string
    {
        return Env::path('DB_PATH', 'data/acadsync.db');
    }

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $path = self::path();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        self::backupIfOutdated();
        $fresh = !file_exists($path);

        self::$pdo = self::open($path);
        self::$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
        self::$pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
        self::addMissingColumns();
        self::addUniqueIndexes();

        // A brand-new database gets the demo section and accounts unless .env turns that off
        if ($fresh && Env::bool('SEED_DEMO_DATA', true)) {
            require_once __DIR__ . '/seed.php';
            seed_database();
        }
        return self::$pdo;
    }

    /** Columns added after a database was first created (keeps existing data, no rebuild). */
    private static function addMissingColumns(): void
    {
        $added = [
            'tasks' => ['group_count' => 'INTEGER', 'group_mode' => 'TEXT', 'group_plan' => 'TEXT'],
            // typed announcements (Announcement subclasses); older rows read as plain notices
            'announcements' => ['kind' => "TEXT NOT NULL DEFAULT 'general'", 'ref_id' => 'INTEGER', 'event_at' => 'TEXT', 'details' => 'TEXT'],
            'password_resets' => ['attempts' => 'INTEGER NOT NULL DEFAULT 0'],
            // officer positions (vice_president | secretary | treasurer) and member status (active | inactive | alumni)
            'memberships' => ['position' => 'TEXT', 'status' => "TEXT NOT NULL DEFAULT 'active'"],
            // competitions and section projects: the original post, when sign-ups close, the result
            'activities' => ['link' => 'TEXT', 'signup_deadline' => 'TEXT', 'result' => 'TEXT'],
        ];
        foreach ($added as $table => $cols) {
            $have = [];
            foreach (self::$pdo->query("PRAGMA table_info($table)")->fetchAll() as $c) {
                $have[$c['name']] = true;
            }
            foreach ($cols as $name => $type) {
                if (!isset($have[$name])) {
                    self::$pdo->exec("ALTER TABLE $table ADD COLUMN $name $type");
                }
            }
        }
    }

    /**
     * Safety nets against two requests arriving at the same moment (the HashSet checks catch
     * everything else first). Skipped quietly on a database that already holds a duplicate.
     */
    private static function addUniqueIndexes(): void
    {
        try {
            // one cancellation per class (or whole day, subject 0) per date
            self::$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_class_cancellations ON class_cancellations (section_id, date, COALESCE(subject_id, 0))');
        } catch (PDOException $e) {
            error_log('[AcadSync] unique index skipped: ' . $e->getMessage());
        }
    }

    private static function open(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    /** Renames a database built from an older schema to data/acadsync.backup-<date>.db. */
    private static function backupIfOutdated(): void
    {
        $path = self::path();
        if (!file_exists($path)) {
            return;
        }
        $old = self::open($path);
        $version = (int) $old->query('PRAGMA user_version')->fetchColumn();
        $old = null;
        if ($version !== SCHEMA_VERSION) {
            rename($path, dirname($path) . '/acadsync.backup-' . date('Ymd-His') . '.db');
        }
    }

    /** Raw read: SELECT * FROM table. Rows come back in insertion (rowid) order. */
    public static function all(string $table): array
    {
        return self::pdo()->query('SELECT * FROM ' . self::table($table))->fetchAll();
    }

    /** Linear scan for a row by primary key. */
    public static function find(string $table, int $id): ?array
    {
        foreach (self::all($table) as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    /** Linear scan keeping rows whose $field equals $value. */
    public static function where(string $table, string $field, mixed $value): array
    {
        $out = [];
        foreach (self::all($table) as $row) {
            if ((string) $row[$field] === (string) $value) {
                $out[] = $row;
            }
        }
        return $out;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::table($table),
            implode(', ', $cols),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols))
        );
        self::pdo()->prepare($sql)->execute($data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn ($c) => "$c = :$c", array_keys($data)));
        $stmt = self::pdo()->prepare(sprintf('UPDATE %s SET %s WHERE id = :id', self::table($table), $sets));
        $stmt->execute($data + ['id' => $id]);
    }

    public static function delete(string $table, int $id): void
    {
        self::pdo()->prepare(sprintf('DELETE FROM %s WHERE id = :id', self::table($table)))->execute(['id' => $id]);
    }

    public static function begin(): void
    {
        self::pdo()->beginTransaction();
    }

    public static function commit(): void
    {
        self::pdo()->commit();
    }

    /** Undoes an unfinished transaction (used when a request crashes halfway). */
    public static function rollbackIfOpen(): void
    {
        if (self::$pdo !== null && self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
    }

    private static function table(string $name): string
    {
        static $allowed = [
            'sections', 'users', 'memberships', 'subjects', 'class_schedules', 'tasks', 'study_groups', 'group_members',
            'approval_queue', 'undo_stack', 'notifications', 'password_resets', 'task_done', 'group_requests', 'subtasks',
            'activities', 'activity_rsvps', 'attendance', 'officer_terms', 'join_requests', 'announcements', 'dues', 'payments', 'class_cancellations',
            'activity_parts', 'part_members',
        ];
        foreach ($allowed as $t) {
            if ($t === $name) {
                return $name;
            }
        }
        throw new InvalidArgumentException("Unknown table $name");
    }
}
