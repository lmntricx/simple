<?php

namespace App\Controllers;

use App\Models\User;
use Simple\Auth\Auth;
use Simple\Http\Request;
use Simple\Http\Response;

class AuthController
{
    public function register(Request $request): Response
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
        ]);

        $user = User::create($data);

        return Response::created($this->tokenPayload($user, 'register'), 'Registered');
    }

    public function login(Request $request): Response
    {
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

    private function tokenPayload(User $user, string $tokenName): array
    {
        return [
            'user' => $user,
            'token' => $user->createToken($tokenName)->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
