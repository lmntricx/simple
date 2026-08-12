<?php

namespace app\Controllers;

use app\Helpers\ResponseHelper;
use app\Models\User;

class UserController
{
    private ResponseHelper $response;

    public function __construct()
    {
        $this->response = new ResponseHelper();
    }

    public function index(): void
    {
        $users = array_map(
            fn(User $user) => $user->toArray(),
            User::all()
        );

        $this->response->success($users, 'Users retrieved');
    }

    public function show(array $params): void
    {
        $user = User::find($params['id'] ?? null);

        if (!$user) {
            $this->response->notFound('User not found');
        }

        $this->response->success($user->toArray(), 'User retrieved');
    }

    public function store(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $user = User::create($payload);

        if (!$user) {
            $this->response->error('Unable to create user', 400);
        }

        $this->response->created($user->toArray(), 'User created');
    }

    public function update(array $params): void
    {
        $user = User::find($params['id'] ?? null);

        if (!$user) {
            $this->response->notFound('User not found');
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!$user->update($payload)) {
            $this->response->error('Unable to update user', 400);
        }

        $this->response->success($user->toArray(), 'User updated');
    }

    public function destroy(array $params): void
    {
        $user = User::find($params['id'] ?? null);

        if (!$user) {
            $this->response->notFound('User not found');
        }

        if (!$user->delete()) {
            $this->response->error('Unable to delete user', 400);
        }

        $this->response->success(null, 'User deleted');
    }
}