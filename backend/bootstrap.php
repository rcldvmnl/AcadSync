<?php
/**
 * Common setup for every API endpoint: session, JSON I/O, auth guards.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/custom_dsa.php';
require_once __DIR__ . '/services.php';

session_name('acadsync');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
});

function respond(array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $status < 400] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400, array $extra = []): never
{
    respond(['error' => $message] + $extra, $status);
}

/** JSON body merged over query-string params. */
function input(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        $data = array_merge($_GET, $_POST, is_array($json) ? $json : []);
    }
    return $data;
}

function param(string $key, mixed $default = null): mixed
{
    $v = input()[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        fail("Use $method for this endpoint.", 405);
    }
}

/** The logged-in user row, or a 401. */
function current_user(): array
{
    $id = $_SESSION['user_id'] ?? null;
    $user = $id ? Store::find('users', (int) $id) : null;
    if (!$user) {
        fail('Please log in first.', 401);
    }
    return $user;
}

/** The logged-in user, who must also belong to a section. */
function current_member(): array
{
    $user = current_user();
    if (empty($user['section_id'])) {
        fail('Join or create a section first.', 409, ['needs_section' => true]);
    }
    return $user;
}

function require_admin(): array
{
    $user = current_member();
    if ($user['role'] !== 'admin') {
        fail('Only the class admin can do that.', 403);
    }
    return $user;
}

function notify(int $userId, string $type, string $message, ?string $link = null): void
{
    Store::insert('notifications', ['user_id' => $userId, 'type' => $type, 'message' => $message, 'link' => $link]);
}

/** Sends a notification to every admin of a section. */
function notify_admins(int $sectionId, string $type, string $message, ?string $link = null): void
{
    foreach (Store::where('users', 'section_id', $sectionId) as $u) {
        if ($u['role'] === 'admin') {
            notify((int) $u['id'], $type, $message, $link);
        }
    }
}

function public_user(array $u): array
{
    return [
        'id' => (int) $u['id'],
        'student_id' => $u['student_id'],
        'username' => $u['username'],
        'first_name' => $u['first_name'],
        'last_name' => $u['last_name'],
        'name' => trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username'],
        'email' => $u['email'],
        'role' => $u['role'],
        'section_id' => $u['section_id'] !== null ? (int) $u['section_id'] : null,
        'joined_at' => $u['joined_at'],
    ];
}
