<?php

namespace App\Mail;

use App\Models\User;
use Simple\Mail\Message;

/**
 * The emails sent by AuthController. Edit freely.
 */
final class AuthMail
{
    public static function verifyEmail(User $user, string $url): Message
    {
        $app = config('app.name');
        $minutes = config('auth.verification_expire');

        return self::message($user, "Verify your email address for $app", [
            "Hi {$user->name},",
            "Please confirm your email address by opening this link:",
            $url,
            "The link expires in $minutes minutes. If you didn't create an account, you can ignore this email.",
        ], buttonText: 'Verify email', buttonUrl: $url);
    }

    public static function resetPassword(User $user, string $url): Message
    {
        $app = config('app.name');
        $minutes = config('auth.password_reset_expire');

        return self::message($user, "Reset your $app password", [
            "Hi {$user->name},",
            "We received a request to reset your password. Open this link to choose a new one:",
            $url,
            "The link expires in $minutes minutes. If you didn't ask for this, you can ignore this email; your password won't change.",
        ], buttonText: 'Reset password', buttonUrl: $url);
    }

    /**
     * @param string[] $paragraphs
     */
    private static function message(User $user, string $subject, array $paragraphs, string $buttonText, string $buttonUrl): Message
    {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $html = '';
        foreach ($paragraphs as $p) {
            $html .= $p === $buttonUrl
                ? '<p><a href="' . $e($buttonUrl) . '" style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none">' . $e($buttonText) . '</a></p>'
                : '<p>' . $e($p) . '</p>';
        }

        return Message::create()
            ->to($user->email, preg_replace('/[\r\n]+/', ' ', (string) $user->name))
            ->subject($subject)
            ->text(implode("\n\n", $paragraphs))
            ->html('<div style="font-family:sans-serif;font-size:15px;line-height:1.5;color:#111">' . $html . '</div>');
    }
}
