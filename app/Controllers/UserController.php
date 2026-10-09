<?php

namespace App\Controllers;

use App\Models\User;
use Simple\Http\Request;
use Simple\Http\Response;

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

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        return Response::created(User::create($data), 'User created');
    }

    public function update(Request $request, string $id): Response
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => "sometimes|required|email|max:255|unique:users,email,{$user->id}",
            'password' => 'sometimes|required|string|min:8',
        ]);

        $user->update($data);

        return Response::success($user, 'User updated');
    }

    public function destroy(Request $request, string $id): Response
    {
        User::findOrFail($id)->delete();

        return Response::success(null, 'User deleted');
    }
}
