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
require_once __DIR__ . '/../backend/Mailer.php';

const EMAIL_PATTERN = '/^(\d{4}-\d{4})@lspu\.edu\.ph$/';
const EMAIL_HINT = 'Use your university e-mail, e.g. 0000-0000@lspu.edu.ph.';
const MAX_RESET_TRIES = 5;

/**
 * True when the browser runs on this same computer (the local demo/grading setup).
 * A tunnel or port forward (VS Code Ports, Cloudflare Tunnel, ngrok) also reaches PHP from 127.0.0.1, so a
 * loopback address alone is not enough: the request must also ask for a loopback host name and carry no
 * proxy / forwarding headers. Any sign of a tunnel means "not this computer", so the reset code stays hidden.
 */
function is_local_request(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip !== '127.0.0.1' && $ip !== '::1') {
        return false;
    }
    // Headers that proxies and tunnels add (PHP exposes them as HTTP_*)
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_PORT',
        'HTTP_FORWARDED', 'HTTP_X_REAL_IP', 'HTTP_X_ORIGINAL_HOST', 'HTTP_X_ORIGINAL_URL', 'HTTP_CF_CONNECTING_IP',
        'HTTP_CF_RAY', 'HTTP_TRUE_CLIENT_IP', 'HTTP_VIA'] as $header) {
        if (isset($_SERVER[$header]) && $_SERVER[$header] !== '') {
            return false;
        }
    }
    // The host the browser asked for: a tunnel usually keeps its public name (e.g. abc.trycloudflare.com)
    $host = DsaUtil::lower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);   // drop the port
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]';
}

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
        'pending_joins' => pending_joins((int) $user['id']),   // sections still deciding on this person's request
        'stats' => null,
    ];
    if ($user['section_id'] === null) {
        return $payload;
    }
    $ctx = load_context($user);
    $payload['section'] = present_section($ctx['section'], count($ctx['members']));
    // What this person may do here, so the app shows the right buttons (the server still checks every action)
    $payload['user']['permissions'] = Person::fromRow($user)->allPermissions();

    // [OOP: POLYMORPHISM] — a Member counts requests and groups, an Officer counts subjects,
    // published tasks and reviews; this code doesn't need to know which one it has.
    $payload['stats'] = Person::fromRow($user)->accountStats($ctx);
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
        // Where the code can go: an e-mail (MAIL_* in .env), and/or the screen of a browser on THIS computer
        // (SHOW_RESET_CODE, for the local demo). Someone reaching the server from elsewhere never sees it,
        // so "forgot password" can't be used to take over someone else's account.
        $mailer = null;
        try {
            $mailer = Mailer::fromEnv();
        } catch (InvalidArgumentException $e) {
            error_log('AcadSync mail settings: ' . $e->getMessage());
        }
        $showCode = Env::bool('SHOW_RESET_CODE', true) && is_local_request();
        if ($mailer === null && !$showCode) {
            fail('Password reset by e-mail isn’t set up yet. Ask your President or the person who runs AcadSync.', 503);
        }
        // A new code replaces any older one, so only the latest code works
        foreach (Store::all('password_resets') as $r) {
            if ($r['email'] === $email && !(int) $r['used']) {
                Store::update('password_resets', (int) $r['id'], ['used' => 1]);
            }
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $resetId = Store::insert('password_resets', ['email' => $email, 'code' => $code, 'expires_at' => time() + 900]);
        $emailed = false;
        if ($mailer !== null) {
            try {
                $mailer->send($email, 'Your AcadSync reset code', "Your AcadSync code is $code.\n\nEnter it on the login page to choose a new password. "
                    . "It works for 15 minutes.\n\nIf you didn’t ask for this, you can ignore this e-mail: your password stays the same.");
                $emailed = true;
            } catch (InvalidArgumentException | RuntimeException $e) {
                error_log('AcadSync reset e-mail: ' . $e->getMessage());
                if (!$showCode) {                           // nobody can see this code: close it
                    Store::update('password_resets', $resetId, ['used' => 1]);
                    fail('We couldn’t send the e-mail right now. Try again in a few minutes.', 502);
                }
            }
        }
        respond(['message' => 'Reset code sent.', 'emailed' => $emailed] + ($showCode ? ['dev_code' => $code] : []));
    }

    case 'reset': {
        $email = DsaUtil::lower((string) param('email', ''));
        $code = (string) param('code', '');
        $password = (string) param('password', '');
        if (strlen($password) < 8) {
            fail('Password must be at least 8 characters.');
        }
        // The one live code for this e-mail (older ones were closed when it was issued)
        $live = null;
        foreach (Store::all('password_resets') as $r) {
            if ($r['email'] === $email && !(int) $r['used'] && (int) $r['expires_at'] >= time()) {
                $live = $r;
            }
        }
        if (!$live) {
            fail('That code is invalid or expired. Ask for a new one.');
        }
        if (!hash_equals($live['code'], $code)) {
            // Wrong guess: after MAX_RESET_TRIES the code is closed, so 6 digits can't be brute-forced
            $tries = (int) $live['attempts'] + 1;
            Store::update('password_resets', (int) $live['id'], ['attempts' => $tries, 'used' => $tries >= MAX_RESET_TRIES ? 1 : 0]);
            fail($tries >= MAX_RESET_TRIES
                ? 'Too many wrong codes. Ask for a new code.'
                : 'That code is wrong. ' . (MAX_RESET_TRIES - $tries) . (MAX_RESET_TRIES - $tries === 1 ? ' try' : ' tries') . ' left.');
        }
        $match = $live;
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
