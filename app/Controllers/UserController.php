<?php

namespace App\Controllers;

use App\Models\User;
use Simple\Http\Request;
use Simple\Http\Response;

/**
 * All routes require authentication (see routes/api.php). New users sign up
 * through POST /auth/register.
 */
class UserController
{
    public function index(Request $request): Response
    {
        $page = User::query()->latest()->paginate(
            perPage: min((int) $request->query('per_page', 15), 100),
            page: (int) $request->query('page', 1),
        );

        return Response::success($page['data'], 'Users retrieved', meta: $page['meta']);
    }

    public function show(Request $request, string $id): Response
    {
        return Response::success(User::findOrFail($id), 'User retrieved');
    }

    public function update(Request $request, string $id): Response
    {
        $user = $this->ownAccount($request, $id);

        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => "sometimes|required|email|max:255|unique:users,email,{$user->id}",
            'password' => 'sometimes|required|string|min:8|max:255',
        ]);

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);
        if ($emailChanged) {
            // The new address has to be verified again.
            $user->forceFill(['email_verified_at' => null]);
        }
        $user->save();

        if ($emailChanged) {
            AuthController::sendVerificationEmail($user);
        }

        return Response::success($user, 'User updated');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->ownAccount($request, $id)->delete();

        return Response::success(null, 'User deleted');
    }

    /**
     * Users may only change or delete their own account.
     */
    private function ownAccount(Request $request, string $id): User
    {
        $user = User::findOrFail($id);

        if ((string) $user->id !== (string) $request->user()->getKey()) {
            abort(403, 'You can only modify your own account');
        }

        return $user;
    }
}
