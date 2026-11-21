<?php
/**
 * Sends plain-text e-mail (the password-reset code) over SMTP, written by hand: no mail library.
 *
 * Settings (.env, read through Env): MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION (ssl | tls | none),
 * MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM, MAIL_FROM_NAME, MAIL_TIMEOUT. Without MAIL_HOST there is no mailer.
 * Gmail: MAIL_HOST=smtp.gmail.com, MAIL_PORT=465, MAIL_ENCRYPTION=ssl, MAIL_USERNAME = the address,
 * MAIL_PASSWORD = a Google "app password" (not the normal password).
 *
 * [OOP: ENCAPSULATION] the settings are private and checked once in the constructor; callers only send().
 */

declare(strict_types=1);

final class Mailer
{
    private function __construct(
        private string $host,
        private int $port,
        private string $encryption,      // 'ssl' (whole connection), 'tls' (STARTTLS), 'none' (tests only)
        private string $username,
        private string $password,
        private string $from,
        private string $fromName,
        private int $timeout,
    ) {
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('MAIL_FROM must be an e-mail address.');
        }
        if ($encryption !== 'ssl' && $encryption !== 'tls' && $encryption !== 'none') {
            throw new InvalidArgumentException('MAIL_ENCRYPTION must be ssl, tls or none.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('MAIL_PORT must be a port number.');
        }
    }

    /** The mailer from .env, or null when e-mail isn't set up (no MAIL_HOST). */
    public static function fromEnv(): ?Mailer
    {
        $keys = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_ENCRYPTION', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_TIMEOUT'];
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = Env::get($key, '');
        }
        return self::fromSettings($settings);
    }

    /** @param array<string,string> $s the MAIL_* settings; missing ones take Gmail-friendly defaults */
    public static function fromSettings(array $s): ?Mailer
    {
        $host = trim((string) ($s['MAIL_HOST'] ?? ''));
        if ($host === '') {
            return null;
        }
        $encryption = strtolower(trim((string) ($s['MAIL_ENCRYPTION'] ?? '')));
        $encryption = $encryption === '' ? 'ssl' : $encryption;
        $port = trim((string) ($s['MAIL_PORT'] ?? ''));
        $username = trim((string) ($s['MAIL_USERNAME'] ?? ''));
        $from = trim((string) ($s['MAIL_FROM'] ?? ''));
        return new self(
            $host,
            $port === '' ? ($encryption === 'tls' ? 587 : ($encryption === 'ssl' ? 465 : 25)) : (int) $port,
            $encryption,
            $username,
            (string) ($s['MAIL_PASSWORD'] ?? ''),
            $from === '' ? $username : $from,
            trim((string) ($s['MAIL_FROM_NAME'] ?? '')) ?: 'AcadSync',
            max(1, (int) (trim((string) ($s['MAIL_TIMEOUT'] ?? '')) ?: 15)),
        );
    }

    /** Where it connects, e.g. "ssl://smtp.gmail.com:465" (no secrets). */
    public function describe(): string
    {
        return ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . "{$this->host}:{$this->port}";
    }

    /** Sends one plain-text message. Throws InvalidArgumentException (bad address) or RuntimeException (server). */
    public function send(string $to, string $subject, string $text): void
    {
        if (preg_match('/[\r\n]/', $to . $subject) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('That is not an e-mail address.');   // no header injection
        }
        $socket = @stream_socket_client($this->describe(), $errno, $errstr, $this->timeout);
        if ($socket === false) {
            throw new RuntimeException("Could not reach the mail server ({$this->host}:{$this->port}): $errstr");
        }
        stream_set_timeout($socket, $this->timeout);
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO ' . $this->helloName(), 250);
            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Could not start an encrypted connection to the mail server.');
                }
                $this->command($socket, 'EHLO ' . $this->helloName(), 250);
            }
            if ($this->username !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($this->username), 334);
                $this->command($socket, base64_encode($this->password), 235);
            }
            $this->command($socket, "MAIL FROM:<{$this->from}>", 250);
            $this->command($socket, "RCPT TO:<$to>", 250);
            $this->command($socket, 'DATA', 354);
            $this->write($socket, $this->message($to, $subject, $text) . "\r\n.");
            $this->expect($socket, 250);
            $this->command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    /** Headers + a base64 body (UTF-8 safe, and no line can start with "." so no dot-stuffing is needed). */
    private function message(string $to, string $subject, string $text): string
    {
        $encode = fn (string $s) => preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
        $body = preg_replace("/\r\n|\r|\n/", "\r\n", $text);
        return implode("\r\n", [
            'Date: ' . date('r'),
            'From: ' . $encode($this->fromName) . " <{$this->from}>",
            "To: <$to>",
            'Subject: ' . $encode($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body), 76, "\r\n")),
        ]);
    }

    private function helloName(): string
    {
        $name = gethostname();
        return is_string($name) && preg_match('/^[A-Za-z0-9.-]+$/', $name) ? $name : 'localhost';
    }

    private function command($socket, string $line, int $code): void
    {
        $this->write($socket, $line);
        $this->expect($socket, $code);
    }

    private function write($socket, string $data): void
    {
        if (@fwrite($socket, $data . "\r\n") === false) {
            throw new RuntimeException('The mail server closed the connection.');
        }
    }

    /** Reads one (possibly multi-line) reply and checks its code. */
    private function expect($socket, int $code): void
    {
        $reply = '';
        do {
            $line = fgets($socket, 1024);
            if ($line === false) {
                throw new RuntimeException('The mail server stopped answering.');
            }
            $reply = rtrim($line, "\r\n");
        } while (isset($reply[3]) && $reply[3] === '-');   // "250-..." continues, "250 ..." is the last line
        if ((int) substr($reply, 0, 3) !== $code) {
            throw new RuntimeException("The mail server refused: $reply");
        }
    }
}
