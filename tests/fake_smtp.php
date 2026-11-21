<?php
/**
 * A tiny fake SMTP server for tests/mailer_test.php (and the scratch API test). It answers one connection,
 * writes everything the client sends to a transcript ("C: …" lines) and exits. Nothing is delivered.
 *
 *     php tests/fake_smtp.php <port> <transcript file> [ok|reject-auth]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[$_, $port, $logFile] = $argv;
$mode = $argv[3] ?? 'ok';
$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "fake_smtp: $errstr\n");
    exit(1);
}
echo "ready\n";   // the test waits for this line before connecting
fflush(STDOUT);
$conn = @stream_socket_accept($server, 30);
if (!$conn) {
    exit(1);
}
$log = fopen($logFile, 'w');
$say = function (string $line) use ($conn): void {
    fwrite($conn, $line . "\r\n");
};
$read = function () use ($conn, $log): ?string {
    $line = fgets($conn);
    if ($line === false) {
        return null;
    }
    $line = rtrim($line, "\r\n");
    fwrite($log, "C: $line\n");
    return $line;
};

$say('220 fake.smtp ready');
$expect = null;   // 'user' / 'pass' while logging in
while (($line = $read()) !== null) {
    $upper = strtoupper($line);
    if ($expect === 'user') {
        $expect = 'pass';
        $say('334 UGFzc3dvcmQ6');
    } elseif ($expect === 'pass') {
        $expect = null;
        $say($mode === 'reject-auth' ? '535 Authentication failed' : '235 Accepted');
    } elseif (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
        $say('250-fake.smtp');
        $say('250 AUTH LOGIN PLAIN');
    } elseif ($upper === 'AUTH LOGIN') {
        $expect = 'user';
        $say('334 VXNlcm5hbWU6');
    } elseif (str_starts_with($upper, 'MAIL FROM') || str_starts_with($upper, 'RCPT TO')) {
        $say('250 OK');
    } elseif ($upper === 'DATA') {
        $say('354 End data with <CR><LF>.<CR><LF>');
        while (($l = $read()) !== null && $l !== '.') {
        }
        $say('250 Queued');
    } elseif ($upper === 'QUIT') {
        $say('221 Bye');
        break;
    } else {
        $say('500 Unknown command');
    }
}
fclose($log);
fclose($conn);
