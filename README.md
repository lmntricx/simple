# Simple

A small, batteries-included PHP 8.1+ framework for JSON APIs. No dependencies.

```bash
cp .env.example .env
php simple migrate
php simple serve          # http://127.0.0.1:8000
```

## Layout

```
app/                  your code: Controllers, Models, Middleware
bootstrap/app.php     creates the Application and loads routes
config/               app.php, database.php, cors.php (any file here is readable via config('file.key'))
database/migrations/  schema changes, run with `php simple migrate`
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

## Models

```php
class Post extends Model
{
    protected array $fillable = ['title', 'body', 'user_id'];
    protected array $hidden = [];
    protected array $casts = ['published' => 'bool', 'meta' => 'array'];

    public function author(): ?User { return $this->belongsTo(User::class, 'user_id'); }
}

Post::create([...]);
Post::find(1);            Post::findOrFail(1);       // 404 if missing
Post::where('published', true)->where('views', '>', 100)->latest()->paginate(20, $page);
$post->update([...]);     $post->title = 'x'; $post->save();     $post->delete();
$user->hasMany(Post::class)->get();
```

The table name defaults to the snake_case plural of the class (`BlogPost` => `blog_posts`). `created_at`/`updated_at`
are set automatically (`protected bool $timestamps = false;` to turn off). Define `setXxxAttribute($value)` to
transform a value before it's stored (see `User::setPasswordAttribute`).

The query builder (`Model::query()`, or `new QueryBuilder($pdo, 'table')` for plain arrays) supports
`select where orWhere whereNull whereNotNull whereIn join leftJoin orderBy latest limit offset get first find count exists paginate insert update delete raw`.
Column names are validated, so they can't be used for SQL injection; values are always bound.

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

Register a short name in `config/app.php` under `middleware_aliases`, then use `->middleware('auth')` on a route or group.
Global middleware (run on every request) is listed under `middleware`. CORS is on by default; configure it in `config/cors.php`.

## CLI

```
php simple serve [--port=8000]
php simple routes
php simple migrate | migrate:rollback | migrate:status
php simple make:controller PostController
php simple make:model Post -m
php simple make:migration add_slug_to_posts
php simple make:middleware Authenticate
```

## Deploying on Apache

Point the document root at `public/`. On shared hosting where you can't, upload the whole project; the root `.htaccess`
forwards everything into `public/`. Set `APP_DEBUG=false` in production.
