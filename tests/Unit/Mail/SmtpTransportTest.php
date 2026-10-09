<?php

namespace Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simple\Mail\Message;
use Simple\Mail\Transport\SmtpTransport;

/**
 * Talks to tests/Fixtures/fake-smtp.php running in a separate process.
 */
class SmtpTransportTest extends TestCase
{
    private function withFakeSmtp(\Closure $send, string $password = 'secret'): string
    {
        $port = random_int(20000, 40000);
        $transcript = sys_get_temp_dir() . '/simple_smtp_' . bin2hex(random_bytes(4)) . '.txt';

        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/fake-smtp.php', (string) $port, $transcript, $password],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertSame("ready\n", fgets($pipes[1]), 'fake SMTP server started');

        try {
            $send($port);
        } finally {
            proc_close($process);
        }

        $log = (string) @file_get_contents($transcript);
        @unlink($transcript);

        return $log;
    }

    public function test_full_conversation(): void
    {
        $log = $this->withFakeSmtp(function (int $port) {
            (new SmtpTransport('127.0.0.1', $port, 'user@x.io', 'secret', encryption: 'none', timeout: 5))->send(
                Message::create()->from('app@x.io')->to('ada@x.io')->to('bob@x.io')->subject('Hello')->text("Hi\n.leading dot\nbye")
            );
        });

        $this->assertStringContainsString('C: EHLO ', $log);
        $this->assertStringContainsString('C: AUTH LOGIN', $log);
        $this->assertStringContainsString('C: ' . base64_encode('user@x.io'), $log);
        $this->assertStringContainsString('S: 235 ', $log);
        $this->assertStringContainsString('C: MAIL FROM:<app@x.io>', $log);
        $this->assertStringContainsString('C: RCPT TO:<ada@x.io>', $log);
        $this->assertStringContainsString('C: RCPT TO:<bob@x.io>', $log);
        $this->assertStringContainsString('Subject: Hello', $log);
        $this->assertStringContainsString("\r\n..leading dot\r\n", $log, 'dot-stuffed');
        $this->assertStringContainsString('S: 221 Bye', $log);
    }

    public function test_wrong_password_gives_a_generic_error(): void
    {
        $this->withFakeSmtp(function (int $port) {
            try {
                (new SmtpTransport('127.0.0.1', $port, 'user@x.io', 'wrong', encryption: 'none', timeout: 5))
                    ->send(Message::create()->from('app@x.io')->to('ada@x.io')->subject('s')->text('t'));
                $this->fail('expected failure');
            } catch (RuntimeException $e) {
                $this->assertSame('SMTP: authentication failed.', $e->getMessage());
            }
        });
    }

    public function test_connection_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not connect');

        (new SmtpTransport('127.0.0.1', 1, encryption: 'none', timeout: 2))
            ->send(Message::create()->from('app@x.io')->to('ada@x.io')->subject('s')->text('t'));
    }
}
