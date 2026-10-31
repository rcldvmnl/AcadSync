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
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
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

/** The membership row linking a user to a section, or null. */
function membership(int $userId, ?int $sectionId): ?array
{
    if ($sectionId === null) {
        return null;
    }
    foreach (Store::all('memberships') as $m) {
        if ((int) $m['user_id'] === $userId && (int) $m['section_id'] === $sectionId) {
            return $m;
        }
    }
    return null;
}

/**
 * The logged-in user row, or a 401. Two convenience keys are added:
 *   section_id = the section currently open (null if none)
 *   role       = the user's role in that section ('admin' | 'student')
 */
function current_user(): array
{
    $id = $_SESSION['user_id'] ?? null;
    $user = $id ? Store::find('users', (int) $id) : null;
    if (!$user) {
        fail('Please log in first.', 401);
    }
    $active = $user['active_section_id'] !== null ? (int) $user['active_section_id'] : null;
    $m = membership((int) $user['id'], $active);
    $user['section_id'] = $m ? $active : null;
    $user['role'] = $m['role'] ?? 'student';
    return $user;
}

/** The logged-in user, who must also have a section open. */
function current_member(): array
{
    $user = current_user();
    if ($user['section_id'] === null) {
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

function notify(int $userId, ?int $sectionId, string $type, string $message, ?string $link = null): void
{
    Store::insert('notifications', [
        'user_id' => $userId, 'section_id' => $sectionId, 'type' => $type, 'message' => $message, 'link' => $link,
    ]);
}

/** Sends a notification to every admin of a section. */
function notify_admins(int $sectionId, string $type, string $message, ?string $link = null): void
{
    foreach (section_members($sectionId) as $u) {
        if ($u['role'] === 'admin') {
            notify((int) $u['id'], $sectionId, $type, $message, $link);
        }
    }
}

/** Sends a notification to everyone in a section except $exceptUserId. */
function notify_section(int $sectionId, ?int $exceptUserId, string $type, string $message, ?string $link = null): void
{
    foreach (section_members($sectionId) as $u) {
        if ((int) $u['id'] !== $exceptUserId) {
            notify((int) $u['id'], $sectionId, $type, $message, $link);
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
        'role' => $u['role'] ?? 'student',
        'section_id' => isset($u['section_id']) ? (int) $u['section_id'] : null,
        'joined_at' => $u['joined_at'] ?? $u['created_at'] ?? null,
    ];
}
