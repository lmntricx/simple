# Simple API Framework Documentation

## Overview

This framework is a lightweight PHP API scaffold with:
- JSON routing in `api/index.php`
- environment-based database config
- automatic table creation via ORM schema definitions
- a query builder and model base for CRUD
- controller + response helper conventions

## File structure

- `api/index.php` — router bootstrap
- `api/config/` — config files
- `core/Database.php` — PDO connection manager
- `app/ORM/` — query builder, schema, model, relations
- `app/Models/` — application models
- `app/Controllers/` — request handlers
- `app/Helpers/ResponseHelper.php` — JSON response helper

## Configuration

### `api/config/environment.php`

Define environment values, for example:

```php
<?php

return [
    'APP_NAME' => 'Simple API',
    'APP_ENV' => 'local',
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://localhost',
    'DB_CONNECTION' => 'sqlite',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => __DIR__ . '/../database.sqlite',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
];
```

### `api/config/database.php`

Uses `APP_ENV` to choose development or production credentials automatically.

### `api/config/app.php`

Loads environment and database config, defines application constants, and returns the app config array.

### `api/config/cors.php`

Sets CORS headers and handles OPTIONS preflight requests.

### `api/config/errors.php`

Converts PHP errors and exceptions into JSON responses for API consistency.

## Routing

Create routes in `api/config/routes.php`:

```php
return [
    'GET' => [
        '/' => 'MainController@index',
        '/users' => 'UserController@index',
        '/users/{id}' => 'UserController@show',
    ],
    'POST' => [
        '/users' => 'UserController@store',
    ],
    'PUT' => [
        '/users/{id}' => 'UserController@update',
    ],
    'DELETE' => [
        '/users/{id}' => 'UserController@destroy',
    ],
];
```

The router supports parameterized routes like `/users/{id}` and passes params to controller actions.

## Response Helper

Use `app\Helpers\ResponseHelper` in controllers for consistent API output.

Example:

```php
$response = new ResponseHelper();
$response->success($data, 'OK');
$response->created($newResource->toArray(), 'Created');
$response->notFound('User not found');
$response->error('Invalid request', 400);
```

## ORM and Model schema

### Model schema definition

Each model can define its table structure using `schema()` and `Blueprint`.

Example `app/Models/User.php`:

```php
<?php

namespace app\Models;

use app\ORM\Blueprint;

class User extends BaseModel
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];

    protected static function schema(Blueprint $table): void
    {
        $table->id();
        $table->string('name', 255);
        $table->string('email', 255)->unique();
        $table->string('password', 255);
        $table->timestamps();
    }
}
```

When the model is instantiated, the framework checks whether the table exists and creates it automatically if missing.

### Blueprint column types

Available schema builder methods:

- `id()`
- `integer($name, $length = 11)`
- `string($name, $length = 255)`
- `text($name)`
- `boolean($name)`
- `datetime($name)`
- `date($name)`
- `decimal($name, $precision = 10, $scale = 2)`
- `json($name)`
- `timestamps()`

Column modifiers:

- `nullable()`
- `unique()`
- `primary()`
- `autoIncrement()`
- `unsigned()`
- `default($value)`

Indexes and constraints:

- `unique($columns, $name = null)`
- `primary($columns, $name = null)`
- `index($columns, $name = null)`

## Model usage

Model methods:

- `User::all()`
- `User::find($id)`
- `User::findBy($column, $value)`
- `User::create($data)`
- `$user->update($data)`
- `$user->delete()`
- `$user->toArray()`

## Sample controller

Example `app/Controllers/UserController.php`:

```php
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
        $users = array_map(fn(User $user) => $user->toArray(), User::all());
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
```

## Sample requests

- GET all users: `GET /api/users`
- GET user by ID: `GET /api/users/1`
- Create user: `POST /api/users`
- Update user: `PUT /api/users/1`
- Delete user: `DELETE /api/users/1`

## Notes

- `Database` chooses database credentials by environment.
- ORM schema creation works for SQLite and MySQL.
- Use `BaseModel` or extend `app\ORM\Model` directly.
- Controller actions with route params should accept `array $params`.