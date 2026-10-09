<?php

namespace Tests\Unit\Mail;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simple\Mail\Message;
use Simple\Mail\Transport\LogTransport;

class MessageTest extends TestCase
{
    public function test_plain_text_message(): void
    {
        $mime = Message::create()->from('app@x.io', 'App')->to('ada@x.io', 'Ada')->subject('Hello')->text("Line 1\nLine 2")->toMime();

        $this->assertStringContainsString("From: \"App\" <app@x.io>\r\n", $mime);
        $this->assertStringContainsString("To: \"Ada\" <ada@x.io>\r\n", $mime);
        $this->assertStringContainsString("Subject: Hello\r\n", $mime);
        $this->assertStringContainsString("Content-Type: text/plain; charset=UTF-8\r\n", $mime);
        $this->assertStringContainsString("Line 1\r\nLine 2", $mime);
        $this->assertDoesNotMatchRegularExpression("/(?<!\r)\n/", $mime, 'no bare LF');
    }

    public function test_html_message_is_multipart_alternative(): void
    {
        $mime = Message::create()->from('app@x.io')->to('ada@x.io')->subject('Hi')->text('plain')->html('<b>rich</b>')->toMime();

        $this->assertMatchesRegularExpression('/Content-Type: multipart\/alternative; boundary="(b[0-9a-f]+)"/', $mime);
        preg_match('/boundary="(b[0-9a-f]+)"/', $mime, $m);
        $this->assertSame(3, substr_count($mime, '--' . $m[1]), 'two parts + closing');
        $this->assertStringContainsString('text/html', $mime);
    }

    public function test_non_ascii_headers_are_encoded(): void
    {
        $mime = Message::create()->from('app@x.io', 'Zoë')->to('ada@x.io')->subject('Café ☕')->text('x')->toMime();

        $this->assertStringContainsString('Subject: =?UTF-8?B?' . base64_encode('Café ☕') . '?=', $mime);
        $this->assertStringContainsString('From: =?UTF-8?B?' . base64_encode('Zoë') . '?= <app@x.io>', $mime);
    }

    public static function headerInjections(): array
    {
        return [
            'subject' => [fn () => Message::create()->subject("Hi\r\nBcc: victim@x.io")],
            'name' => [fn () => Message::create()->to('ada@x.io', "Ada\nBcc: victim@x.io")],
            'address' => [fn () => Message::create()->to("ada@x.io\r\nBcc: victim@x.io")],
            'not an email' => [fn () => Message::create()->to('not-an-email')],
        ];
    }

    #[DataProvider('headerInjections')]
    public function test_header_injection_is_rejected(\Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    public function test_long_links_survive_quoted_printable(): void
    {
        $link = 'http://x.io/verify?' . str_repeat('a', 120) . '&signature=abc';
        $mime = Message::create()->from('app@x.io')->to('ada@x.io')->subject('s')->text($link)->toMime();
        $body = substr($mime, strpos($mime, "\r\n\r\n") + 4);

        $this->assertSame($link, quoted_printable_decode($body));
        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
        }
    }

    public function test_log_transport_writes_a_readable_copy(): void
    {
        $file = sys_get_temp_dir() . '/simple_mail_' . bin2hex(random_bytes(4)) . '.log';
        (new LogTransport($file))->send(
            Message::create()->from('app@x.io')->to('ada@x.io')->subject('Verify')->text('Open http://x.io/v?a=1&signature=abc')
        );
        $log = file_get_contents($file);
        unlink($file);

        $this->assertStringContainsString('To: ada@x.io', $log);
        $this->assertStringContainsString('http://x.io/v?a=1&signature=abc', $log, 'link not QP-encoded');
    }
}
