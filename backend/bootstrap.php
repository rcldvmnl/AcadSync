<?php
/**
 * Common setup for every API endpoint: session, JSON I/O, auth guards.
 */

declare(strict_types=1);

// Settings come from .env (never committed) — see .env.example
require_once __DIR__ . '/Env.php';
Env::load();
date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Asia/Manila'));

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/custom_dsa.php';
// [OOP] domain model: the Four Pillars (see docs/CLASS_DIAGRAM.md)
require_once __DIR__ . '/domain/Deadline.php';
require_once __DIR__ . '/domain/MemberStatus.php';
require_once __DIR__ . '/domain/Person.php';
require_once __DIR__ . '/domain/Task.php';
require_once __DIR__ . '/domain/Activity.php';
require_once __DIR__ . '/domain/MemberRecord.php';
require_once __DIR__ . '/domain/UndoAction.php';
require_once __DIR__ . '/domain/Payment.php';
require_once __DIR__ . '/services.php';

session_name('acadsync');
session_set_cookie_params([
    'httponly' => true, 'samesite' => 'Lax', 'path' => '/',
    'secure' => Env::bool('SESSION_SECURE_COOKIE', false),   // true once the site runs on HTTPS
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

set_exception_handler(function (Throwable $e) {
    Store::rollbackIfOpen();                      // never leave half a change behind
    http_response_code(500);
    error_log('[AcadSync] ' . $e);                // full details go to the server log only
    // In production users see a plain message; locally the reason helps while developing
    $detail = Env::isProduction() ? '' : ': ' . $e->getMessage();
    echo json_encode(['ok' => false, 'error' => 'Server error' . $detail . '. Try again.']);
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
    $user['position'] = $m['position'] ?? null;      // vice_president | secretary | treasurer | null
    $user['status'] = $m['status'] ?? 'active';      // active | inactive | alumni
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

/** [OOP] The logged-in member as an object: a Member or an Officer (Person::fromRow decides). */
function current_person(): Person
{
    return Person::fromRow(current_member());
}

/** Stops the request unless the logged-in person has $permission (asked polymorphically). */
function require_permission(string $permission, string $message = 'Only the class officer can do that.'): array
{
    $user = current_member();
    if (!Person::fromRow($user)->can($permission)) {
        fail($message, 403);
    }
    return $user;
}

/** Officer-only endpoints (subjects, approval queue): needs Permission::MANAGE_SECTION. */
function require_admin(): array
{
    return require_permission(Permission::MANAGE_SECTION, 'Only the President can do that.');
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
        if (Person::fromRow($u)->can(Permission::REVIEW_REQUESTS)) {   // officers review requests
            notify((int) $u['id'], $sectionId, $type, $message, $link);
        }
    }
}

/** Sends a notification to everyone in a section who has $permission (e.g. every officer who reviews new members). */
function notify_permitted(int $sectionId, string $permission, string $type, string $message, ?string $link = null): void
{
    foreach (section_members($sectionId) as $u) {
        if (Person::fromRow($u)->can($permission)) {
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
    $person = Person::fromRow($u);   // [OOP] Member / VicePresident / Secretary / President
    return [
        'id' => (int) $u['id'],
        'student_id' => $u['student_id'],
        'username' => $u['username'],
        'first_name' => $u['first_name'],
        'last_name' => $u['last_name'],
        'name' => trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username'],
        'email' => $u['email'],
        'role' => $u['role'] ?? 'student',
        'position' => $person->position(),
        'role_label' => $person->roleLabel(),
        'is_officer' => $person->isOfficer(),
        'status' => $person->status()->key(),
        'section_id' => isset($u['section_id']) ? (int) $u['section_id'] : null,
        'joined_at' => $u['joined_at'] ?? $u['created_at'] ?? null,
    ];
}
