<?php

namespace Simple\Mail;

use InvalidArgumentException;
use Simple\Mail\Transport\ArrayTransport;
use Simple\Mail\Transport\LogTransport;
use Simple\Mail\Transport\SmtpTransport;
use Simple\Mail\Transport\Transport;

/**
 *   Mail::send(Message::create()->to($email)->subject('Hi')->text('...'));
 *
 * The driver comes from config/mail.php (log, smtp or array).
 * In tests: $mail = Mail::fake(); ... $mail->sentTo('ada@x.io');
 */
final class Mail
{
    private static ?Transport $transport = null;

    public static function send(Message $message): void
    {
        if ($message->from === null) {
            $message->from(config('mail.from.address'), config('mail.from.name'));
        }

        self::transport()->send($message);
    }

    public static function fake(): ArrayTransport
    {
        $fake = new ArrayTransport();
        self::$transport = $fake;
        return $fake;
    }

    public static function useTransport(?Transport $transport): void
    {
        self::$transport = $transport;
    }

    public static function transport(): Transport
    {
        return self::$transport ??= match ($driver = config('mail.driver', 'log')) {
            'log' => new LogTransport(storage_path('logs/mail.log')),
            'array' => new ArrayTransport(),
            'smtp' => new SmtpTransport(
                host: config('mail.smtp.host'),
                port: (int) config('mail.smtp.port', 587),
                username: config('mail.smtp.username'),
                password: config('mail.smtp.password'),
                encryption: config('mail.smtp.encryption', 'tls'),
                timeout: (int) config('mail.smtp.timeout', 10),
                localDomain: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
            ),
            default => throw new InvalidArgumentException("Unknown mail driver [$driver]."),
        };
    }
}
