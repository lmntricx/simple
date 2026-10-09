<?php

namespace Simple\Mail\Transport;

use RuntimeException;
use Simple\Mail\Message;

/**
 * Minimal SMTP client: STARTTLS ("tls"), implicit TLS ("ssl") or plain ("none"),
 * with AUTH LOGIN. Opens one connection per message.
 */
final class SmtpTransport implements Transport
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private string $host,
        private int $port = 587,
        private ?string $username = null,
        private ?string $password = null,
        private string $encryption = 'tls',
        private int $timeout = 10,
        private string $localDomain = 'localhost',
    ) {
    }

    public function send(Message $message): void
    {
        try {
            $this->connect();
            $this->expect(220);
            $this->ehlo();

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', 220);
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
                if (!stream_socket_enable_crypto($this->socket, true, $crypto)) {
                    throw new RuntimeException('SMTP: could not start TLS.');
                }
                $this->ehlo();
            }

            if ($this->username !== null && $this->username !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($this->username), 334, hide: true);
                $this->command(base64_encode((string) $this->password), 235, hide: true);
            }

            $this->command('MAIL FROM:<' . $message->from['address'] . '>', 250);
            foreach ($message->recipients() as $recipient) {
                $this->command("RCPT TO:<$recipient>", [250, 251]);
            }

            $this->command('DATA', 354);
            // Dot-stuffing: a line that starts with "." must be sent as "..".
            $data = preg_replace('/^\./m', '..', $message->toMime());
            $this->write($data . "\r\n.\r\n");
            $this->expect(250);

            $this->command('QUIT', 221);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function connect(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);

        try {
            $socket = @stream_socket_client(
                "$scheme://{$this->host}:{$this->port}",
                $errno,
                $error,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context,
            );
        } catch (\ErrorException $e) {
            $socket = false;
            $error = $e->getMessage();
        }

        if ($socket === false) {
            throw new RuntimeException("SMTP: could not connect to {$this->host}:{$this->port} ($error).");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function ehlo(): void
    {
        $this->command('EHLO ' . $this->localDomain, 250);
    }

    private function command(string $command, int|array $expected, bool $hide = false): string
    {
        $this->write($command . "\r\n");

        try {
            return $this->expect($expected);
        } catch (RuntimeException $e) {
            throw $hide ? new RuntimeException('SMTP: authentication failed.') : $e;
        }
    }

    private function expect(int|array $expected): string
    {
        $response = '';

        // Multi-line replies use "250-..." for every line except the last ("250 ...").
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, (array) $expected, true)) {
            throw new RuntimeException('SMTP: unexpected response ' . trim($response ?: '(none — timed out?)'));
        }

        return $response;
    }

    private function write(string $data): void
    {
        if (fwrite($this->socket, $data) === false) {
            throw new RuntimeException('SMTP: connection lost.');
        }
    }
}
