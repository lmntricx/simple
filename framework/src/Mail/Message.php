<?php

namespace Simple\Mail;

use InvalidArgumentException;

/**
 *   Message::create()
 *       ->to('ada@example.com', 'Ada')
 *       ->subject('Welcome')
 *       ->text("Hi Ada,\n...")
 *       ->html('<p>Hi Ada,</p>');   // optional
 */
final class Message
{
    /** @var array<int, array{address: string, name: ?string}> */
    public array $to = [];
    /** @var array{address: string, name: ?string}|null */
    public ?array $from = null;
    /** @var array{address: string, name: ?string}|null */
    public ?array $replyTo = null;
    public string $subject = '';
    public string $text = '';
    public ?string $html = null;

    public static function create(): self
    {
        return new self();
    }

    public function to(string $address, ?string $name = null): self
    {
        $this->to[] = self::address($address, $name);
        return $this;
    }

    public function from(string $address, ?string $name = null): self
    {
        $this->from = self::address($address, $name);
        return $this;
    }

    public function replyTo(string $address, ?string $name = null): self
    {
        $this->replyTo = self::address($address, $name);
        return $this;
    }

    public function subject(string $subject): self
    {
        self::assertSingleLine($subject);
        $this->subject = $subject;
        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function html(?string $html): self
    {
        $this->html = $html;
        return $this;
    }

    /**
     * Render as an RFC 5322 message (headers + body) with CRLF line endings.
     */
    public function toMime(): string
    {
        if ($this->from === null || $this->to === []) {
            throw new InvalidArgumentException('A message needs a sender and at least one recipient.');
        }

        $domain = substr(strrchr($this->from['address'], '@'), 1) ?: 'localhost';

        $headers = [
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'From: ' . self::formatAddress($this->from),
            'To: ' . implode(', ', array_map([self::class, 'formatAddress'], $this->to)),
        ];

        if ($this->replyTo !== null) {
            $headers[] = 'Reply-To: ' . self::formatAddress($this->replyTo);
        }

        $headers[] = 'Subject: ' . self::encodeHeader($this->subject);
        $headers[] = 'MIME-Version: 1.0';

        $text = self::encodeBody($this->text);

        if ($this->html === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            return implode("\r\n", $headers) . "\r\n\r\n" . $text;
        }

        $boundary = 'b' . bin2hex(random_bytes(12));
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";

        return implode("\r\n", $headers) . "\r\n\r\n"
            . "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . $text . "\r\n"
            . "--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . self::encodeBody($this->html) . "\r\n"
            . "--$boundary--";
    }

    /** @return string[] bare recipient addresses */
    public function recipients(): array
    {
        return array_column($this->to, 'address');
    }

    private static function address(string $address, ?string $name): array
    {
        $address = trim($address);

        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n<>]/', $address)) {
            throw new InvalidArgumentException("Invalid email address [$address].");
        }
        if ($name !== null) {
            self::assertSingleLine($name);
        }

        return ['address' => $address, 'name' => $name];
    }

    /**
     * Header values must not contain line breaks, or a user-supplied value
     * (a name, a subject) could inject extra headers such as Bcc.
     */
    private static function assertSingleLine(string $value): void
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new InvalidArgumentException('Email header values cannot contain line breaks.');
        }
    }

    private static function formatAddress(array $address): string
    {
        if ($address['name'] === null || $address['name'] === '') {
            return $address['address'];
        }

        $name = preg_match('/^[\x20-\x7E]*$/', $address['name'])
            ? '"' . addcslashes($address['name'], '"\\') . '"'
            : self::encodeHeader($address['name']);

        return "$name <{$address['address']}>";
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/^[\x20-\x7E]*$/', $value) ? $value : '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function encodeBody(string $body): string
    {
        return quoted_printable_encode(preg_replace("/\r\n|\r|\n/", "\r\n", $body));
    }
}
