<?php

namespace App\Controllers;

use App\Mail\AuthMail;
use App\Models\User;
use Simple\Auth\Auth;
use Simple\Auth\EmailVerification;
use Simple\Auth\PasswordBroker;
use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Mail\Mail;

class AuthController
{
    public function register(Request $request): Response
    {
        $this->normaliseEmail($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
        ]);

        $user = User::create($data);
        self::sendVerificationEmail($user);

        return Response::created($this->tokenPayload($user, 'register'), 'Registered');
    }

    public function login(Request $request): Response
    {
        $this->normaliseEmail($request);

        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = Auth::attempt($data['email'], $data['password']);

        if ($user === null) {
            return Response::error('Invalid email or password', 401);
        }

        return Response::success($this->tokenPayload($user, $data['device_name'] ?? 'login'), 'Logged in');
    }

    public function me(Request $request): Response
    {
        return Response::success($request->user());
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): Response
    {
        Auth::token()->delete();

        return Response::success(null, 'Logged out');
    }

    /**
     * Revoke every token for this user (log out of all devices).
     */
    public function logoutAll(Request $request): Response
    {
        $count = $request->user()->tokens()->delete();

        return Response::success(['revoked' => $count], 'Logged out everywhere');
    }

    // ---- Email verification ------------------------------------------------

    /**
     * Opened from the link in the verification email (no token needed; the link is signed).
     */
    public function verifyEmail(Request $request, string $id, string $hash): Response
    {
        $user = EmailVerification::verify($request, $id, $hash);
        $redirect = config('auth.verified_redirect');

        if ($redirect) {
            $separator = str_contains($redirect, '?') ? '&' : '?';
            return Response::redirect($redirect . $separator . 'verified=' . ($user ? '1' : '0'));
        }

        return $user
            ? Response::success(null, 'Email verified')
            : Response::error('This verification link is invalid or has expired.', 403);
    }

    public function resendVerification(Request $request): Response
    {
        $user = $request->user();

        if (EmailVerification::isVerified($user)) {
            return Response::success(null, 'Email already verified');
        }

        self::sendVerificationEmail($user);

        return Response::success(null, 'Verification link sent');
    }

    /**
     * Sent after the response, so a slow mail server doesn't slow the request.
     */
    public static function sendVerificationEmail(User $user): void
    {
        app()->defer(fn () => Mail::send(AuthMail::verifyEmail($user, EmailVerification::url($user))));
    }

    // ---- Password reset ----------------------------------------------------

    public function forgotPassword(Request $request): Response
    {
        $this->normaliseEmail($request);
        $data = $request->validate(['email' => 'required|email']);

        // All work happens after the response, and the response is the same
        // either way, so this endpoint can't reveal which emails have accounts.
        app()->defer(function () use ($data) {
            $user = User::firstWhere('email', $data['email']);
            $token = $user ? PasswordBroker::createToken($user) : null;

            if ($token !== null) {
                $base = config('auth.password_reset_url');
                $url = $base . (str_contains($base, '?') ? '&' : '?') . http_build_query(['token' => $token, 'email' => $user->email]);
                Mail::send(AuthMail::resetPassword($user, $url));
            }
        });

        return Response::success(null, 'If an account exists for that email, a password reset link has been sent.');
    }

    public function resetPassword(Request $request): Response
    {
        $this->normaliseEmail($request);

        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|max:255',
        ]);

        $user = PasswordBroker::reset($data['email'], $data['token'], $data['password']);

        if ($user === null) {
            return Response::error('This password reset link is invalid or has expired.', 422);
        }

        return Response::success(null, 'Password has been reset. Please log in again.');
    }

    // ---- Helpers -----------------------------------------------------------

    private function tokenPayload(User $user, string $tokenName): array
    {
        return [
            'user' => $user,
            'token' => $user->createToken($tokenName)->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Emails are stored lowercase, so "Ada@X.io" and "ada@x.io" are the same account.
     */
    private function normaliseEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => strtolower(trim($email))]);
        }
    }
}
