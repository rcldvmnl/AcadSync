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

final class Store
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $fresh = !file_exists(DB_PATH);
        if (!is_dir(dirname(DB_PATH))) {
            mkdir(dirname(DB_PATH), 0777, true);
        }

        self::$pdo = new PDO('sqlite:' . DB_PATH);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

        if ($fresh) {
            require_once __DIR__ . '/seed.php';
            seed_database();
        }
        return self::$pdo;
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
            'sections', 'users', 'subjects', 'class_schedules', 'tasks', 'study_groups',
            'approval_queue', 'undo_stack', 'notifications', 'password_resets',
        ];
        foreach ($allowed as $t) {
            if ($t === $name) {
                return $name;
            }
        }
        throw new InvalidArgumentException("Unknown table $name");
    }
}
