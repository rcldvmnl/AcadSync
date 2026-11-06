<?php
/**
 * SQLite connection + raw storage helpers.
 *
 * DSA rule: reads are always a plain `SELECT * FROM table` (no WHERE, ORDER BY
 * or JOIN). Filtering happens in PHP loops; sorting and searching happen in
 * custom_dsa.php. Writes use INSERT / UPDATE / DELETE by primary key only.
 */

declare(strict_types=1);

const DB_PATH = __DIR__ . '/../data/acadsync.db';

/** Bump when schema.sql changes; an older database file is backed up and rebuilt. */
const SCHEMA_VERSION = 3;

final class Store
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if (!is_dir(dirname(DB_PATH))) {
            mkdir(dirname(DB_PATH), 0777, true);
        }
        self::backupIfOutdated();
        $fresh = !file_exists(DB_PATH);

        self::$pdo = self::open(DB_PATH);
        self::$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
        self::$pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
        self::addMissingColumns();

        if ($fresh) {
            require_once __DIR__ . '/seed.php';
            seed_database();
        }
        return self::$pdo;
    }

    /** Columns added after a database was first created (keeps existing data, no rebuild). */
    private static function addMissingColumns(): void
    {
        $added = [
            'tasks' => ['group_count' => 'INTEGER'],
            'password_resets' => ['attempts' => 'INTEGER NOT NULL DEFAULT 0'],
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
        if (!file_exists(DB_PATH)) {
            return;
        }
        $old = self::open(DB_PATH);
        $version = (int) $old->query('PRAGMA user_version')->fetchColumn();
        $old = null;
        if ($version !== SCHEMA_VERSION) {
            rename(DB_PATH, dirname(DB_PATH) . '/acadsync.backup-' . date('Ymd-His') . '.db');
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

    private static function table(string $name): string
    {
        static $allowed = [
            'sections', 'users', 'memberships', 'subjects', 'class_schedules', 'tasks', 'study_groups', 'group_members',
            'approval_queue', 'undo_stack', 'notifications', 'password_resets', 'task_done', 'group_requests', 'subtasks',
        ];
        foreach ($allowed as $t) {
            if ($t === $name) {
                return $name;
            }
        }
        throw new InvalidArgumentException("Unknown table $name");
    }
}
