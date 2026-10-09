<?php

/**
 * Mail tests: message encoding, header-injection protection, and the SMTP
 * client against a fake SMTP server process.
 *   php tests/mail.php
 */

$app = require __DIR__ . '/helpers.php';

use Simple\Mail\Message;
use Simple\Mail\Transport\LogTransport;
use Simple\Mail\Transport\SmtpTransport;

section('Message');

test('plain text message has correct headers and CRLF line endings', function () {
    $mime = Message::create()->from('app@x.io', 'App')->to('ada@x.io', 'Ada')->subject('Hello')->text("Line 1\nLine 2")->toMime();
    eq(true, str_contains($mime, "From: \"App\" <app@x.io>\r\n"));
    eq(true, str_contains($mime, "To: \"Ada\" <ada@x.io>\r\n"));
    eq(true, str_contains($mime, "Subject: Hello\r\n"));
    eq(true, str_contains($mime, "Content-Type: text/plain; charset=UTF-8\r\n"));
    eq(true, str_contains($mime, "Line 1\r\nLine 2"));
    eq(0, preg_match("/(?<!\r)\n/", $mime), 'no bare LF');
});

test('html message is multipart/alternative with both parts', function () {
    $mime = Message::create()->from('app@x.io')->to('ada@x.io')->subject('Hi')->text('plain')->html('<b>rich</b>')->toMime();
    eq(1, preg_match('/Content-Type: multipart\/alternative; boundary="(b[0-9a-f]+)"/', $mime, $m));
    eq(3, substr_count($mime, '--' . $m[1]), 'two parts + closing');
    eq(true, str_contains($mime, "--{$m[1]}--"));
    eq(true, str_contains($mime, 'text/html'));
});

test('non-ASCII subject and names are encoded', function () {
    $mime = Message::create()->from('app@x.io', 'Zoë')->to('ada@x.io')->subject('Café ☕')->text('x')->toMime();
    eq(true, str_contains($mime, 'Subject: =?UTF-8?B?' . base64_encode('Café ☕') . '?='));
    eq(true, str_contains($mime, 'From: =?UTF-8?B?' . base64_encode('Zoë') . '?= <app@x.io>'));
});

test('header injection through subject, name or address is rejected', function () {
    throws(InvalidArgumentException::class, fn () => Message::create()->subject("Hi\r\nBcc: victim@x.io"));
    throws(InvalidArgumentException::class, fn () => Message::create()->to('ada@x.io', "Ada\nBcc: victim@x.io"));
    throws(InvalidArgumentException::class, fn () => Message::create()->to("ada@x.io\r\nBcc: victim@x.io"));
    throws(InvalidArgumentException::class, fn () => Message::create()->to('not-an-email'));
});

test('long lines and "=" in links survive quoted-printable encoding', function () {
    $link = 'http://x.io/verify?' . str_repeat('a', 120) . '&signature=abc';
    $mime = Message::create()->from('app@x.io')->to('ada@x.io')->subject('s')->text($link)->toMime();
    $body = substr($mime, strpos($mime, "\r\n\r\n") + 4);
    eq($link, quoted_printable_decode($body));
    foreach (explode("\r\n", $body) as $line) {
        eq(true, strlen($line) <= 76, 'line length');
    }
});

section('Log transport');

test('writes a readable copy with the raw (unencoded) link', function () {
    $file = sys_get_temp_dir() . '/simple_mail_' . uniqid() . '.log';
    (new LogTransport($file))->send(Message::create()->from('app@x.io')->to('ada@x.io')->subject('Verify')->text('Open http://x.io/v?a=1&signature=abc'));
    $log = file_get_contents($file);
    unlink($file);
    eq(true, str_contains($log, 'To: ada@x.io'));
    eq(true, str_contains($log, 'http://x.io/v?a=1&signature=abc'));
});

section('SMTP');

/**
 * Start the fake server, run $send, return the transcript.
 */
function withFakeSmtp(Closure $send, string $password = 'secret'): string
{
    $port = random_int(20000, 40000);
    $transcript = sys_get_temp_dir() . '/simple_smtp_' . uniqid() . '.txt';

    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/fixtures/fake-smtp.php', (string) $port, $transcript, $password],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    eq("ready\n", fgets($pipes[1]), 'fake server started');

    try {
        $send($port);
    } finally {
        proc_close($process);
    }

    $log = @file_get_contents($transcript) ?: '';
    @unlink($transcript);
    return $log;
}

test('sends a message: EHLO, AUTH LOGIN, MAIL/RCPT, DATA, QUIT', function () {
    $log = withFakeSmtp(function (int $port) {
        $smtp = new SmtpTransport('127.0.0.1', $port, 'user@x.io', 'secret', encryption: 'none', timeout: 5);
        $smtp->send(
            Message::create()->from('app@x.io')->to('ada@x.io')->to('bob@x.io')->subject('Hello')->text("Hi\n.leading dot\nbye")
        );
    });

    eq(true, str_contains($log, 'C: EHLO '));
    eq(true, str_contains($log, 'C: AUTH LOGIN'));
    eq(true, str_contains($log, 'C: ' . base64_encode('user@x.io')));
    eq(true, str_contains($log, 'S: 235 '));
    eq(true, str_contains($log, 'C: MAIL FROM:<app@x.io>'));
    eq(true, str_contains($log, 'C: RCPT TO:<ada@x.io>'));
    eq(true, str_contains($log, 'C: RCPT TO:<bob@x.io>'));
    eq(true, str_contains($log, 'Subject: Hello'));
    eq(true, str_contains($log, "\r\n..leading dot\r\n"), 'dot-stuffed');
    eq(true, str_contains($log, 'S: 221 Bye'));
});

test('wrong password raises an error without leaking the server reply', function () {
    withFakeSmtp(function (int $port) {
        $smtp = new SmtpTransport('127.0.0.1', $port, 'user@x.io', 'wrong', encryption: 'none', timeout: 5);
        try {
            $smtp->send(Message::create()->from('app@x.io')->to('ada@x.io')->subject('s')->text('t'));
            throw new Exception('expected failure');
        } catch (RuntimeException $e) {
            eq('SMTP: authentication failed.', $e->getMessage());
        }
    });
});

test('connection refused gives a clear error', function () {
    $smtp = new SmtpTransport('127.0.0.1', 1, encryption: 'none', timeout: 2);
    throws(RuntimeException::class, fn () => $smtp->send(Message::create()->from('app@x.io')->to('ada@x.io')->subject('s')->text('t')));
});

finish();
