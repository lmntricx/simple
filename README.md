# Simple

A small, batteries-included PHP 8.2+ framework for JSON APIs. No dependencies.

```bash
cp .env.example .env
php simple key:generate
php simple migrate
php simple serve          # http://127.0.0.1:8000
```

## Layout

```
app/                  your code: Controllers, Models, Middleware
bootstrap/app.php     creates the Application and loads routes
config/               app.php, database.php, cors.php (any file here is readable via config('file.key'))
database/migrations/  schema changes, run with `php simple migrate`
database/factories/   fake-data builders for tests and seeding
database/seeders/     `php simple db:seed`
framework/            the framework itself (namespace Simple\)
public/index.php      the only web entry point; point your web server here
routes/api.php        route definitions
storage/logs/         simple.log
simple                CLI
```

## Routing

```php
return function (Router $router) {
    $router->get('/posts', [PostController::class, 'index']);
    $router->get('/posts/{id}', [PostController::class, 'show']);
    $router->get('/archive/{year?}', [ArchiveController::class, 'index']);   // optional parameter
    $router->resource('comments', CommentController::class);                 // index/store/show/update/destroy

    $router->group(['prefix' => 'admin', 'middleware' => ['auth']], function (Router $router) {
        $router->delete('/posts/{id}', [PostController::class, 'destroy']);
    });
};
```

Set `APP_ROUTE_PREFIX=api` to serve everything under `/api`.

## Controllers

Controllers get the `Request` first, then route parameters in order. Return a `Response`,
an array/model (sent as JSON), a string, or `null` (204).

```php
public function store(Request $request): Response
{
    $data = $request->validate([
        'title' => 'required|string|max:255',
        'tags'  => 'nullable|array',
    ]);

    return Response::created(Post::create($data));
}
```

`Response::success($data, $message, $status, $meta)`, `Response::created()`, `Response::error($message, $status, $errors)`,
`Response::noContent()`, `Response::json($anything)`. Call `abort(403, 'Nope')` anywhere to stop with an error.

Request: `input()`, `all()`, `only([...])`, `except([...])`, `query()`, `header()`, `bearerToken()`, `file()`, `ip()`,
`setAttribute()/attribute()` (pass data from middleware to controllers).

### Validation rules

`required nullable sometimes string integer numeric boolean array email url min:n max:n between:a,b in:a,b,c confirmed regex:/x/ unique:table,column[,ignoreId]`

Failures return a 422 with `{"errors": {"field": ["message"]}}`.

## Dependency injection

Controllers and middleware are built by the container, so they can ask for what they need in their constructor
or action. Classes are built automatically from their type hints; route parameters are matched by name.

```php
class InvoiceController
{
    public function __construct(private InvoiceService $invoices) {}

    public function show(Request $request, string $id, PdfRenderer $pdf): Response { ... }
}
```

Tell the container about interfaces and configured services in `app/Providers/AppServiceProvider.php`:

```php
$this->app->singleton(PaymentGateway::class, fn () => new StripeGateway(config('services.stripe.key')));
$this->app->bind(Clock::class, SystemClock::class);
```

`app()` is the container: `app(InvoiceService::class)`, `app()->make(...)`, `app()->call(...)`. The current
request is available as `app(Request::class)`. In tests, swap an implementation with `$this->app->instance(...)`.

## Models

```php
class Post extends Model
{
    protected array $fillable = ['title', 'body', 'user_id'];
    protected array $hidden = [];
    protected array $casts = ['published' => 'bool', 'meta' => 'array'];

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }   // comments.post_id
    public function cover(): HasOne { return $this->hasOne(Image::class); }          // images.post_id
}

Post::create([...]);
Post::find(1);            Post::findOrFail(1);       // 404 if missing
Post::where('published', true)->where('views', '>', 100)->latest()->paginate(20, $page);
Post::where('published', true)->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2));   // a AND (b OR c)
$post->update([...]);     $post->title = 'x'; $post->save();     $post->delete();

$post->author;                        // property: runs the relation once, cached, included in toArray()/JSON
$post->comments;                      // to-many as a property: array of models
$post->comments()->latest()->get();   // method: a query you can keep refining
```

Relation methods must be public, take no arguments and declare a `BelongsTo`/`HasMany`/`HasOne` return type. A
relation's own constraint can't be widened: `$post->comments()->orWhere(...)` only ever returns that post's comments.

**Eager loading.** Accessing a relation on each model in a loop runs one query per model. Load it up front instead,
one query per relation:

```php
Post::with('author', 'comments.author')->latest()->paginate(20);   // 3 queries, however many posts
$post->load('comments');                                           // for a model you already have
```

**Debugging queries:** `Connection::enableQueryLog()` … `Connection::getQueryLog()` lists every query with its
bindings and time.

Transactions: `Connection::transaction(function () { ... })` commits, or rolls back if the closure throws.

The table name defaults to the snake_case plural of the class (`BlogPost` => `blog_posts`). `created_at`/`updated_at`
are set automatically (`protected bool $timestamps = false;` to turn off). Define `setXxxAttribute($value)` to
transform a value before it's stored (see `User::setPasswordAttribute`).

The query builder (`Model::query()`, or `new QueryBuilder($pdo, 'table')` for plain arrays) supports
`select where orWhere whereNull whereNotNull whereIn whereNotIn join leftJoin orderBy latest limit offset get first find pluck count exists paginate insert update delete raw`.
Column names are validated, so they can't be used for SQL injection; values are always bound.

## Factories and seeders

Factories build models with fake data (`database/factories`, one per model, used via `use HasFactory`):

```php
class UserFactory extends Factory
{
    protected string $model = User::class;

    public function definition(): array
    {
        return ['name' => $this->fake->name(), 'email' => $this->fake->email(), 'password' => 'password'];
    }

    public function unverified(): static { return $this->state(['email_verified_at' => null]); }
}

User::factory()->create();                                // saved
User::factory()->count(10)->unverified()->create();       // array of 10
User::factory()->make(['name' => 'Ada']);                 // not saved
// In a definition, 'user_id' => UserFactory::new() creates the related model.
```

`fake()` generates names, emails (unique), words, sentences, numbers, dates and UUIDs; `fake()->seed(1)` makes it
repeatable. Seeders live in `database/seeders`; `DatabaseSeeder` creates `test@example.com` / `password` and 10
random users:

```
php simple db:seed                       # DatabaseSeeder
php simple db:seed --class=PostSeeder
php simple db:fresh --seed               # drop everything, migrate, seed (refuses in production without --force)
```

## Migrations

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('title');
    $table->text('body')->nullable();
    $table->boolean('published')->default(false);
    $table->json('meta')->nullable();
    $table->timestamps();
    $table->index('title');
});
```

Column types: `id integer bigInteger foreignId string text boolean date datetime timestamp decimal float json`.
Modifiers: `nullable() unique() unsigned() default($v) useCurrent() constrained() cascadeOnDelete() nullOnDelete()`.
Works on MySQL, PostgreSQL and SQLite.

Change an existing table with `Schema::table()` (`php simple make:migration add_slug_to_posts` generates one):

```php
Schema::table('posts', function (Blueprint $table) {
    $table->string('slug')->nullable();   // SQLite: new columns need nullable() or a default()
    $table->index('slug');
    $table->dropColumn('legacy');
});
```

On SQLite and PostgreSQL each migration runs in a transaction, so a failure leaves nothing half-applied.
MySQL commits schema changes immediately, so a failed migration there may need manual cleanup.

## Tests

PHPUnit (`composer install` first):

```
composer test                          # or vendor/bin/phpunit
vendor/bin/phpunit --filter PasswordReset
vendor/bin/phpunit --testsuite Unit
```

Tests run on in-memory SQLite and ignore your `.env`. Each test gets a freshly booted app, a fresh database
with migrations applied, and a fake mailer. Extend `Simple\Testing\TestCase`:

```php
class PostTest extends TestCase
{
    public function test_users_can_create_posts(): void
    {
        $user = User::create(['name' => 'Ada', 'email' => 'ada@x.io', 'password' => 'password123']);

        $this->actingAs($user)
            ->postJson('/posts', ['title' => 'Hello'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Hello');

        $this->assertCount(1, $this->mail->sentTo('ada@x.io'));
    }
}
```

Requests: `getJson postJson putJson patchJson deleteJson`, plus `actingAs($user)`, `withToken($token)`,
`withServerVariables([...])`. Assertions: `assertStatus assertOk assertCreated assertUnauthorized assertForbidden
assertNotFound assertUnprocessable assertJsonPath assertJsonMissingPath assertJsonValidationErrors assertHeader`.

GitHub Actions (`.github/workflows/tests.yml`) runs the suite on PHP 8.2–8.4 with SQLite, and on MySQL 8.4 and
PostgreSQL 17. To run against MySQL locally, set `DB_CONNECTION=mysql` and the `DB_*` variables in your shell.

## Authentication

API tokens, sent as `Authorization: Bearer {token}`. Built-in endpoints (`app/Controllers/AuthController.php`):

| Method | Path               | Auth | Description                                  |
|--------|--------------------|------|----------------------------------------------|
| POST   | `/auth/register`   |      | `name, email, password` → `{user, token}`    |
| POST   | `/auth/login`      |      | `email, password[, device_name]` → `{user, token}` |
| GET    | `/auth/me`         | ✓    | The current user                             |
| POST   | `/auth/logout`     | ✓    | Revoke the token used for this request       |
| POST   | `/auth/logout-all` | ✓    | Revoke all of the user's tokens              |
| GET    | `/auth/verify-email/{id}/{hash}` | | The signed link from the verification email |
| POST   | `/auth/email/verification-notification` | ✓ | Resend the verification email     |
| POST   | `/auth/forgot-password` |  | `email` → emails a reset link (same response whether or not the account exists) |
| POST   | `/auth/reset-password`  |  | `email, token, password` → sets the password and logs out every device |

Register and login are rate limited (`throttle:10,1`, per IP). Tokens look like `12|8f3a...`; only a SHA-256
hash is stored, so a database leak doesn't expose usable tokens. The plain token is returned once, at creation.

Protect routes with the `auth` middleware, then use `$request->user()` or `Auth::user()`:

```php
$router->group(['middleware' => ['auth']], function (Router $router) {
    $router->post('/posts', [PostController::class, 'store']);
});

$router->delete('/posts/{id}', [PostController::class, 'destroy'])->middleware('auth', 'ability:posts:delete');
```

```php
$token = $user->createToken('cli', ['posts:read'], new DateTime('+30 days'));   // abilities and expiry are optional
$token->plainTextToken;     // give this to the client
$user->tokenCan('posts:read');
$user->tokens()->delete();  // revoke all
Auth::attempt($email, $password);   // returns the user or null
```

### Email verification

Registering sends a verification email with a signed link that expires (`auth.verification_expire`, 60 minutes)
and stops working if the user changes their email. Changing your email via `PATCH /users/{id}` marks it
unverified and sends a new link. Restrict routes to verified users with `->middleware('auth', 'verified')`.

The link points at this API. Set `EMAIL_VERIFIED_REDIRECT` to send the browser to your frontend afterwards
(with `?verified=1`, or `?verified=0` for a bad link); otherwise the link responds with JSON.

### Password reset

`POST /auth/forgot-password` emails a link to `PASSWORD_RESET_URL?token=...&email=...` (your frontend's page,
default `APP_URL/reset-password`). That page should `POST /auth/reset-password` with the email, token and new
password. Tokens are random, stored hashed, single-use, expire after 60 minutes, and can be requested at most once a
minute per address. A reset revokes all of the user's API tokens.

Edit the email wording in `app/Mail/AuthMail.php`.

Any model can authenticate by adding `use HasApiTokens` and pointing `config/auth.php` at it. Set
`AUTH_TOKEN_EXPIRATION` (minutes) to expire tokens globally, and run `php simple auth:prune` (e.g. daily from
cron) to delete expired ones.

## Mail

```php
Mail::send(
    Message::create()->to('ada@example.com', 'Ada')->subject('Welcome')->text("Hi Ada!")->html('<p>Hi Ada!</p>')
);

app()->defer(fn () => Mail::send($message));   // send after the response has gone out
```

Drivers (`MAIL_DRIVER`): `log` writes readable emails to `storage/logs/mail.log` (the default, handy for copying
links in development); `smtp` sends through `MAIL_HOST`/`MAIL_PORT` with `MAIL_ENCRYPTION` = `tls` (STARTTLS),
`ssl` or `none`; `array` keeps them in memory. In tests, `$mail = Mail::fake()` then `$mail->sentTo('ada@x.io')`.

## Signed URLs

Links that can't be tampered with, signed with `APP_KEY` (run `php simple key:generate` once):

```php
$url = UrlSigner::sign('/invites/42', ['role' => 'editor'], expiresAt: time() + 3600);
UrlSigner::isValid($request);   // in the route that handles it
```

## Middleware

```php
class Authenticate implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->bearerToken()) {
            return Response::error('Unauthorized', 401);
        }
        return $next($request);
    }
}
```

Register a short name in `config/app.php` under `middleware_aliases`, then use `->middleware('name')` on a route or group.
Parameters after a colon go to the middleware's constructor: `throttle:5,1` calls `new Throttle('5', '1')`.
Global middleware (run on every request) is listed under `middleware`. CORS is on by default; configure it in `config/cors.php`.

Built in: `auth`, `verified`, `ability:a,b`, `throttle:maxAttempts,minutes`.

## CLI

```
php simple serve [--port=8000]
php simple routes
php simple migrate | migrate:rollback | migrate:status
php simple db:seed [--class=Name] | db:fresh [--seed]
php simple auth:prune
php simple key:generate [--show] [--force]
php simple make:controller PostController
php simple make:model Post -m
php simple make:migration add_slug_to_posts
php simple make:middleware Authenticate
php simple make:factory Post
php simple make:seeder PostSeeder
```

## Deploying on Apache

Point the document root at `public/`. On shared hosting where you can't, upload the whole project; the root `.htaccess`
forwards everything into `public/`. Set `APP_DEBUG=false` in production.
