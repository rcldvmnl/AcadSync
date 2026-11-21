<?php
/**
 * Tests for backend/Mailer.php against a fake SMTP server started by this script (nothing leaves the computer).
 *
 *     C:\xampp\php\php.exe tests/mailer_test.php
 *
 * Exits with 1 when anything failed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {   // never runnable from a browser
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/Mailer.php';

$passed = 0;
$failed = 0;
function check(string $name, mixed $got, mixed $expected): void
{
    global $passed, $failed;
    if ($got === $expected) {
        $passed++;
        echo "  PASS  $name\n";
    } else {
        $failed++;
        echo "  FAIL  $name\n        expected " . var_export($expected, true) . "\n        got      " . var_export($got, true) . "\n";
    }
}

/** Starts tests/fake_smtp.php on a free port; returns [process, port, transcript file]. */
function fake_smtp(string $mode = 'ok'): array
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $log = tempnam(sys_get_temp_dir(), 'smtp');
    $proc = proc_open([PHP_BINARY, __DIR__ . '/fake_smtp.php', (string) $port, $log, $mode], [1 => ['pipe', 'w']], $pipes);
    fgets($pipes[1]);                                   // "ready": it is listening (it takes one connection only)
    return [$proc, $port, $log];
}

function finish(array $server): string
{
    [$proc, , $log] = $server;
    for ($i = 0; $i < 50 && proc_get_status($proc)['running']; $i++) {
        usleep(100000);
    }
    proc_terminate($proc);
    $text = (string) file_get_contents($log);
    @unlink($log);
    return $text;
}

$refused = function (callable $f): string {
    try {
        $f();
        return 'ok';
    } catch (InvalidArgumentException | RuntimeException $e) {
        return $e->getMessage();
    }
};

echo "Settings\n";
check('not set up without a host', Mailer::fromSettings(['MAIL_HOST' => '']), null);
check('a sender address is required', $refused(fn () => Mailer::fromSettings(['MAIL_HOST' => 'smtp.example.com', 'MAIL_FROM' => 'nope'])), 'MAIL_FROM must be an e-mail address.');
check('encryption must be ssl, tls or none', $refused(fn () => Mailer::fromSettings(['MAIL_HOST' => 'smtp.example.com', 'MAIL_FROM' => 'a@b.co', 'MAIL_ENCRYPTION' => 'rot13'])), 'MAIL_ENCRYPTION must be ssl, tls or none.');
check('Gmail defaults: port 465 over SSL', Mailer::fromSettings(['MAIL_HOST' => 'smtp.gmail.com', 'MAIL_FROM' => 'a@b.co'])->describe(), 'ssl://smtp.gmail.com:465');

echo "Sending\n";
$server = fake_smtp();
$mailer = Mailer::fromSettings(['MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => (string) $server[1], 'MAIL_ENCRYPTION' => 'none',
    'MAIL_USERNAME' => 'org@example.com', 'MAIL_PASSWORD' => 'app pass', 'MAIL_FROM' => 'org@example.com', 'MAIL_FROM_NAME' => 'AcadSync']);
$mailer->send('2023-1045@lspu.edu.ph', 'Your AcadSync reset code', "Your code is 123456.\n.hidden line\nIt expires in 15 minutes.");
$t = finish($server);
check('logs in with AUTH LOGIN', str_contains($t, "C: AUTH LOGIN\nC: " . base64_encode('org@example.com') . "\nC: " . base64_encode('app pass')), true);
check('envelope sender and recipient', [str_contains($t, 'C: MAIL FROM:<org@example.com>'), str_contains($t, 'C: RCPT TO:<2023-1045@lspu.edu.ph>')], [true, true]);
check('headers', [str_contains($t, 'C: From: AcadSync <org@example.com>'), str_contains($t, 'C: To: <2023-1045@lspu.edu.ph>'),
    str_contains($t, 'C: Subject: Your AcadSync reset code')], [true, true, true]);
preg_match('/C: Content-Transfer-Encoding: base64\nC: \n((?:C: [A-Za-z0-9+\/=]*\n)+)/', $t, $m);
$body = base64_decode(str_replace(["C: ", "\n"], '', $m[1] ?? ''));
check('body arrives intact (base64, so a line starting with "." is safe)', $body, "Your code is 123456.\r\n.hidden line\r\nIt expires in 15 minutes.");
check('ends with QUIT', str_contains($t, 'C: QUIT'), true);

echo "Failures\n";
check('a header with a line break is refused', $refused(fn () => $mailer->send("a@b.co\r\nBcc: x@y.z", 'Hi', 'x')), 'That is not an e-mail address.');
$server = fake_smtp('reject-auth');
$bad = Mailer::fromSettings(['MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => (string) $server[1], 'MAIL_ENCRYPTION' => 'none',
    'MAIL_USERNAME' => 'org@example.com', 'MAIL_PASSWORD' => 'wrong', 'MAIL_FROM' => 'org@example.com']);
$msg = $refused(fn () => $bad->send('2023-1045@lspu.edu.ph', 'Hi', 'x'));
finish($server);
check('a refused login is reported with the server reply', $msg, 'The mail server refused: 535 Authentication failed');
$closed = Mailer::fromSettings(['MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => '1', 'MAIL_ENCRYPTION' => 'none', 'MAIL_FROM' => 'org@example.com', 'MAIL_TIMEOUT' => '2']);
check('no server: a clear error', str_starts_with($refused(fn () => $closed->send('2023-1045@lspu.edu.ph', 'Hi', 'x')), 'Could not reach the mail server'), true);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
