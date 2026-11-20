<?php
/**
 * [OOP: ENCAPSULATION] Settings from the project's .env file.
 *
 * Every machine keeps its own .env (it is in .gitignore, so it never reaches GitHub);
 * .env.example is the committed template the team copies. Values are read once and kept in a
 * private array: nothing outside this class can change them, only ask for them.
 * A real environment variable (set by the host) wins over the .env file.
 */

declare(strict_types=1);

final class Env
{
    /** @var array<string, string> */
    private static array $values = [];
    private static bool $loaded = false;

    private function __construct()   // static helper, never instantiated
    {
    }

    /** The project root (the folder that holds .env, api/, backend/, data/). */
    public static function root(): string
    {
        return dirname(__DIR__);
    }

    /** Reads KEY=VALUE lines. Blank lines and # comments are skipped; quotes around a value are removed. */
    public static function load(?string $file = null): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        $file ??= self::root() . '/.env';
        if (!is_file($file) || !is_readable($file)) {
            return;                              // no .env: the defaults below are used
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $n = strlen($value);
            if ($n >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$n - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            } elseif (($hash = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $hash));   // inline comment
            }
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                self::$values[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return match (strtolower($v)) {
            '1', 'true', 'yes', 'on' => true,
            default => false,
        };
    }

    /** A file path; a relative one is taken from the project root (e.g. data/acadsync.db). */
    public static function path(string $key, string $default): string
    {
        $p = (string) self::get($key, $default);
        $isAbsolute = str_starts_with($p, '/') || str_starts_with($p, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $p);
        return $isAbsolute ? $p : self::root() . '/' . $p;
    }

    /** local | production. Production hides error details from users. */
    public static function isProduction(): bool
    {
        return strtolower((string) self::get('APP_ENV', 'local')) === 'production';
    }
}
