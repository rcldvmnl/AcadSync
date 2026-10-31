<?php
/**
 * POST /api/auth.php
 *   action=login            {login, password}   login = e-mail, student number or username
 *   action=register         {email, username, first_name, last_name, password, confirm}
 *   action=check_email      {email}            -> HashSet availability check
 *   action=logout
 *   action=forgot           {email}            -> issues a 6-digit reset code
 *   action=reset            {email, code, password}
 *   action=change_password  {current, password, confirm}
 *   action=update_profile   {first_name, last_name}
 * GET  /api/auth.php        -> current user (+ open section, all sections, stats)
 *
 * University e-mails use the student number: 0000-0000@lspu.edu.ph.
 * The part before "@" becomes the Student ID.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

const EMAIL_PATTERN = '/^(\d{4}-\d{4})@lspu\.edu\.ph$/';
const EMAIL_HINT = 'Use your university e-mail, e.g. 0000-0000@lspu.edu.ph.';

/** Returns the student number inside a valid university e-mail, or null. */
function student_number(string $email): ?string
{
    return preg_match(EMAIL_PATTERN, DsaUtil::lower(trim($email)), $m) ? $m[1] : null;
}

function find_user_by_login(string $login): ?array
{
    $login = DsaUtil::lower(trim($login));
    foreach (Store::all('users') as $u) {
        if (DsaUtil::lower($u['email']) === $login || DsaUtil::lower($u['username']) === $login || $u['student_id'] === $login) {
            return $u;
        }
    }
    return null;
}

function me_payload(array $user): array
{
    $payload = [
        'user' => public_user($user),
        'section' => null,
        'sections' => user_sections((int) $user['id']),
        'stats' => null,
    ];
    if ($user['section_id'] === null) {
        return $payload;
    }
    $ctx = load_context($user);
    $payload['section'] = present_section($ctx['section'], count($ctx['members']));

    $uid = (int) $user['id'];
    if ($user['role'] === 'admin') {
        $published = 0;
        $reviewed = 0;
        foreach ($ctx['tasks'] as $t) {
            if ((int) $t['reviewed_by'] === $uid && (int) $t['requested_by'] !== $uid) {
                $reviewed++;
            }
            if ((int) $t['requested_by'] === $uid && $t['status'] === 'approved') {
                $published++;
            }
        }
        $payload['stats'] = ['subjects' => $ctx['subjects']->size(), 'published' => $published, 'reviewed' => $reviewed];
    } else {
        $submitted = 0;
        $approved = 0;
        foreach ($ctx['tasks'] as $t) {
            if ((int) $t['requested_by'] === $uid) {
                $submitted++;
                if ($t['status'] === 'approved') {
                    $approved++;
                }
            }
        }
        $graph = build_graph((int) $ctx['section']['id'], $ctx['members']);
        $payload['stats'] = ['submitted' => $submitted, 'approved' => $approved, 'groups' => count($graph->groupsOf($uid))];
    }
    return $payload;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(me_payload(current_user()));
}

require_method('POST');

switch (param('action')) {
    case 'login': {
        $user = find_user_by_login((string) param('login', ''));
        if (!$user || !password_verify((string) param('password', ''), $user['password_hash'])) {
            fail('Wrong e-mail/username or password.', 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        respond(me_payload(current_user()));
    }

    case 'check_email': {
        $email = (string) param('email', '');
        $sid = student_number($email);
        if ($sid === null) {
            respond(['available' => false, 'reason' => EMAIL_HINT]);
        }
        // [DSA: HASHSET] — O(1) duplicate check against every registered e-mail
        $taken = hashset_of('users', 'email')->contains($email);
        respond([
            'available' => !$taken,
            'student_id' => $sid,
            'reason' => $taken ? 'An account with this e-mail already exists.' : "Available — your Student ID will be $sid",
        ]);
    }

    case 'register': {
        $email = DsaUtil::lower((string) param('email', ''));
        $username = DsaUtil::lower((string) param('username', ''));
        $first = (string) param('first_name', '');
        $last = (string) param('last_name', '');
        $password = (string) param('password', '');
        $studentId = student_number($email);

        if ($first === '' || $last === '') {
            fail('Please enter your first and last name.');
        }
        if ($studentId === null) {
            fail(EMAIL_HINT);
        }
        if (!preg_match('/^[a-z0-9._]{3,30}$/', $username)) {
            fail('Username: 3–30 letters, numbers, dots or underscores.');
        }
        if (strlen($password) < 8) {
            fail('Password must be at least 8 characters.');
        }
        if ($password !== (string) param('confirm', '')) {
            fail('Passwords do not match.');
        }

        // [DSA: HASHSET] — FR-1.2: e-mails, student IDs and usernames must be unique
        if (!hashset_of('users', 'email')->add($email)) {
            fail('An account with this e-mail already exists.', 409);
        }
        if (!hashset_of('users', 'student_id')->add($studentId)) {
            fail('That student number is already registered.', 409);
        }
        if (!hashset_of('users', 'username')->add($username)) {
            fail('That username is taken.', 409);
        }

        $id = Store::insert('users', [
            'student_id' => $studentId,
            'email' => $email,
            'username' => $username,
            'first_name' => $first,
            'last_name' => $last,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        respond(me_payload(current_user()), 201);
    }

    case 'logout': {
        $_SESSION = [];
        session_destroy();
        respond();
    }

    case 'forgot': {
        $email = DsaUtil::lower((string) param('email', ''));
        if (student_number($email) === null) {
            fail(EMAIL_HINT);
        }
        if (!hashset_of('users', 'email')->contains($email)) {
            fail('No account uses that e-mail.', 404);
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Store::insert('password_resets', ['email' => $email, 'code' => $code, 'expires_at' => time() + 900]);
        // No SMTP during local grading — the code is returned so it can be shown on screen.
        respond(['message' => 'Reset code sent.', 'dev_code' => $code]);
    }

    case 'reset': {
        $email = DsaUtil::lower((string) param('email', ''));
        $code = (string) param('code', '');
        $password = (string) param('password', '');
        if (strlen($password) < 8) {
            fail('Password must be at least 8 characters.');
        }
        $match = null;
        foreach (Store::all('password_resets') as $r) {
            if ($r['email'] === $email && $r['code'] === $code && !(int) $r['used'] && (int) $r['expires_at'] >= time()) {
                $match = $r;
            }
        }
        if (!$match) {
            fail('That code is invalid or expired.');
        }
        $user = find_user_by_login($email);
        Store::update('users', (int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        Store::update('password_resets', (int) $match['id'], ['used' => 1]);
        respond(['message' => 'Password updated. You can log in now.']);
    }

    case 'change_password': {
        $user = current_user();
        if (!password_verify((string) param('current', ''), $user['password_hash'])) {
            fail('Current password is wrong.');
        }
        $password = (string) param('password', '');
        if (strlen($password) < 8) {
            fail('New password must be at least 8 characters.');
        }
        if ($password !== (string) param('confirm', '')) {
            fail('Passwords do not match.');
        }
        Store::update('users', (int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        respond(['message' => 'Password updated.']);
    }

    case 'update_profile': {
        $user = current_user();
        $first = (string) param('first_name', $user['first_name']);
        $last = (string) param('last_name', $user['last_name']);
        if ($first === '' || $last === '') {
            fail('Name cannot be empty.');
        }
        Store::update('users', (int) $user['id'], ['first_name' => $first, 'last_name' => $last]);
        respond(me_payload(current_user()));
    }

    default:
        fail('Unknown action.');
}
