<?php
/**
 * Router for PHP's built-in server — start the app with:
 *
 *     php -S localhost:8000 router.php
 *
 * Without a router, `php -S` serves EVERY file in the project, so anyone who can reach the
 * server could download data/acadsync.db (all accounts and records) or read .env.
 * This router answers 404 for those private paths and lets everything else through.
 * On Apache (XAMPP htdocs) the .htaccess files do the same job.
 */

declare(strict_types=1);

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$lower = strtolower(str_replace('\\', '/', $path));   // Windows paths are case-insensitive

// Private: the database, settings, server code, tests, git data, docs and this file itself
$blockedPrefixes = ['/data/', '/backend/', '/docs/', '/tests/', '/router.php'];
$blocked = str_contains($lower, '/.')                 // any dotfile or dot-folder: /.env, /.git/, /.gitignore …
    || str_contains($lower, '..')                     // no climbing out of the project
    || preg_match('/\.(db|sqlite|sqlite3|db-journal|env|sql|md|log|bak|bat|cmd|ps1)$/', $lower);   // + scripts like share.bat
foreach ($blockedPrefixes as $prefix) {
    $blocked = $blocked || str_starts_with($lower, $prefix) || $lower === rtrim($prefix, '/');
}

// Also compare the real file path, so a trick like /Frontend/../data/x.db can't get through
$real = realpath(__DIR__ . $path);
if ($real !== false) {
    foreach (['data', 'backend', 'docs', 'tests', '.git'] as $dir) {
        $private = realpath(__DIR__ . '/' . $dir);
        if ($private !== false && str_starts_with(strtolower($real), strtolower($private))) {
            $blocked = true;
        }
    }
}

if ($blocked) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    return true;
}

return false;   // serve the file (or run the PHP script) as usual
