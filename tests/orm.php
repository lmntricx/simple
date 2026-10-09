<?php

/**
 * ORM test suite. Runs against an in-memory SQLite database:
 *   php tests/orm.php
 */

putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE_PATH=:memory:');

$app = require __DIR__ . '/../bootstrap/app.php';
restore_error_handler();
set_error_handler(fn ($l, $m, $f, $line) => throw new ErrorException($m, 0, $l, $f, $line));

use Simple\Database\Connection;
use Simple\Database\Migrator;
use Simple\Database\Model;
use Simple\Database\QueryBuilder;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Column;
use Simple\Database\Schema\Schema;
use Simple\Http\HttpException;

// ---- Tiny test runner -----------------------------------------------------

$passed = $failed = 0;

function test(string $name, Closure $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  \033[31m✗ $name\033[0m\n      " . $e::class . ': ' . $e->getMessage() . "\n";
    }
}

function eq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new Exception(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function throws(string $class, Closure $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new Exception("expected $class, got " . $e::class . ': ' . $e->getMessage());
    }
    throw new Exception("expected $class, nothing thrown");
}

function section(string $name): void
{
    echo "\n$name\n";
}

// ---- Fixtures --------------------------------------------------------------

Schema::create('users', function (Blueprint $t) {
    $t->id();
    $t->string('name');
    $t->string('email')->unique();
    $t->string('password')->nullable();
    $t->boolean('active')->default(true);
    $t->json('settings')->nullable();
    $t->timestamps();
});

Schema::create('posts', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $t->string('title');
    $t->integer('views')->default(0);
    $t->timestamps();
});

Schema::create('profiles', function (Blueprint $t) {
    $t->id();
    $t->foreignId('test_user_id')->constrained('users')->cascadeOnDelete();
    $t->string('bio');
});

class TestUser extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password', 'active', 'settings'];
    protected array $hidden = ['password'];
    protected array $casts = ['id' => 'int', 'active' => 'bool', 'settings' => 'array'];

    public function posts(): QueryBuilder
    {
        return $this->hasMany(TestPost::class, 'user_id');
    }

    public function profile(): ?TestProfile
    {
        return $this->hasOne(TestProfile::class);
    }

    public function shout(): string // not a relation: wrong return type
    {
        return 'HEY';
    }

    protected function setPasswordAttribute(?string $value): ?string
    {
        return $value === null ? null : password_hash($value, PASSWORD_DEFAULT);
    }
}

class TestPost extends Model
{
    protected string $table = 'posts';
    protected ?array $guarded = [];
    protected array $casts = ['views' => 'int'];

    public function author(): ?TestUser
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }
}

class TestProfile extends Model
{
    protected string $table = 'profiles';
    protected ?array $guarded = [];
    protected bool $timestamps = false;
}

class Category extends Model {}
class BlogPost extends Model {}

$ada = TestUser::create(['name' => 'Ada', 'email' => 'ada@x.io', 'password' => 'secret', 'settings' => ['theme' => 'dark']]);
$bob = TestUser::create(['name' => 'Bob', 'email' => 'bob@x.io', 'active' => false]);
$cy = TestUser::create(['name' => 'Cy', 'email' => 'cy@x.io']);
TestPost::create(['user_id' => $ada->id, 'title' => 'Ada 1', 'views' => 10]);
TestPost::create(['user_id' => $ada->id, 'title' => 'Ada 2', 'views' => 50]);
TestPost::create(['user_id' => $bob->id, 'title' => 'Bob 1', 'views' => 99]);
TestProfile::create(['test_user_id' => $ada->id, 'bio' => 'Mathematician']);

// ---- Tests -----------------------------------------------------------------

section('Model basics');

test('create sets id and exists', function () use ($ada) {
    eq(1, $ada->id);
    eq(true, $ada->exists);
});

test('find returns model / null', function () {
    eq('Ada', TestUser::find(1)->name);
    eq(null, TestUser::find(999));
    eq(null, TestUser::find(null));
    eq(null, TestUser::find(''));
});

test('findOrFail throws 404', function () {
    throws(HttpException::class, fn () => TestUser::findOrFail(999));
});

test('all() returns models, not arrays', function () {
    $all = TestUser::all();
    eq(3, count($all));
    eq(true, $all[0] instanceof TestUser);
});

test('table name inferred: Category => categories, BlogPost => blog_posts', function () {
    eq('categories', (new Category())->getTable());
    eq('blog_posts', (new BlogPost())->getTable());
});

test('mass assignment respects $fillable', function () {
    $u = new TestUser(['name' => 'X', 'id' => 500, 'is_admin' => 1]);
    eq(null, $u->id);
    eq(null, $u->is_admin);
});

test('mutator hashes password; hidden from toArray/json', function () {
    $u = TestUser::find(1);
    eq(true, password_verify('secret', $u->password));
    eq(false, array_key_exists('password', $u->toArray()));
    eq(false, str_contains(json_encode($u), 'password'));
});

test('casts: bool, int, array round-trip', function () {
    $u = TestUser::find(2);
    eq(false, $u->active);
    eq(2, $u->id);
    eq(['theme' => 'dark'], TestUser::find(1)->settings);
});

test('timestamps set on create, updated_at on update', function () {
    $u = TestUser::find(3);
    eq(true, $u->created_at !== null);
    $u->update(['name' => 'Cy2']);
    eq('Cy2', TestUser::find(3)->name);
});

test('save() only writes dirty columns', function () {
    $u = TestUser::find(3);
    eq([], $u->getDirty());
    $u->name = 'Cy3';
    eq(['name' => 'Cy3'], $u->getDirty());
    $u->save();
    eq([], $u->getDirty());
});

test('setting a bool cast to the same value is not dirty', function () {
    $u = TestUser::find(1);
    $u->active = true;
    eq([], $u->getDirty());
});

test('save() on "existing" model with no id throws instead of updating NULL ids', function () {
    $u = new TestUser(['name' => 'ghost']);
    $u->exists = true;
    throws(LogicException::class, fn () => $u->save());
});

test('refresh() reloads from DB', function () {
    $u = TestUser::find(1);
    TestUser::query()->where('id', 1)->update(['name' => 'Ada L']);
    eq('Ada L', $u->refresh()->name);
});

test('delete() removes row and cascades FK', function () {
    $tmp = TestUser::create(['name' => 'Tmp', 'email' => 'tmp@x.io']);
    TestPost::create(['user_id' => $tmp->id, 'title' => 'tmp']);
    eq(true, $tmp->delete());
    eq(null, TestUser::find($tmp->id));
    eq(0, TestPost::where('title', 'tmp')->count());
    eq(false, $tmp->delete(), 'second delete');
});

section('Query builder');

test('multiple where() are ANDed', function () {
    eq(1, TestUser::where('name', 'Ada L')->where('email', 'ada@x.io')->count());
    eq(0, TestUser::where('name', 'Ada L')->where('email', 'bob@x.io')->count());
});

test('operators', function () {
    eq(2, TestPost::where('views', '>=', 50)->count());
    eq(3, TestPost::where('title', 'like', '%1')->count() + TestPost::where('title', 'like', '%2')->count());
    eq(2, TestPost::where('views', '!=', 50)->count());
});

test('where(col, null) => IS NULL; where(col, !=, null) => IS NOT NULL', function () {
    eq(2, TestUser::where('password', null)->count());
    eq(1, TestUser::where('password', '!=', null)->count());
    eq(2, TestUser::whereNull('password')->count());
    eq(1, TestUser::whereNotNull('password')->count());
});

test('whereIn / whereNotIn, including empty lists', function () {
    eq(2, TestUser::whereIn('id', [1, 2])->count());
    eq(0, TestUser::whereIn('id', [])->count());
    eq(1, TestUser::whereNotIn('id', [1, 2])->count());
    eq(3, TestUser::whereNotIn('id', [])->count());
});

test('nested where groups: a AND (b OR c)', function () {
    $q = TestPost::where('views', '>', 20)->where(fn ($q) => $q->where('title', 'Ada 1')->orWhere('title', 'Ada 2'));
    eq(1, $q->count());
    eq(true, str_contains($q->toSql(), 'AND ("title" = ? OR "title" = ?)'));
});

test('orderBy, limit, offset, first', function () {
    eq('Bob 1', TestPost::query()->orderBy('views', 'desc')->first()->title);
    eq(['Ada 2'], array_map(fn ($p) => $p->title, TestPost::query()->orderBy('views')->limit(1)->offset(1)->get()));
    eq(['Ada 2', 'Bob 1'], array_map(fn ($p) => $p->title, TestPost::query()->orderBy('views')->offset(1)->get()));
});

test('first() and count() do not mutate the builder', function () {
    $q = TestPost::query()->orderBy('views');
    $q->first();
    $q->count();
    eq(3, count($q->get()));
});

test('paginate', function () {
    $p = TestPost::query()->orderBy('id')->paginate(2, 2);
    eq(1, count($p['data']));
    eq(['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $p['meta']);
});

test('paginate(0) does not divide by zero', function () {
    eq(1, TestPost::query()->paginate(0)['meta']['per_page']);
});

test('pluck, exists', function () {
    eq(['Ada 1', 'Ada 2', 'Bob 1'], TestPost::query()->orderBy('id')->pluck('title'));
    eq(true, TestPost::where('views', 99)->exists());
    eq(false, TestPost::where('views', 12345)->exists());
});

test('select with alias', function () {
    eq('Ada L', TestUser::query()->select('name as n')->orderBy('id')->first()->n);
});

test('join on a model query does not let joined columns overwrite the model', function () {
    $post = TestPost::query()->join('users', 'users.id', '=', 'posts.user_id')->where('posts.id', 3)->first();
    eq(3, $post->id);
    eq(null, $post->email);
});

test('plain QueryBuilder returns arrays', function () {
    $row = (new QueryBuilder(Connection::get(), 'users'))->where('id', 1)->first();
    eq(true, is_array($row));
});

test('identifiers are validated (no SQL injection through column names)', function () {
    throws(InvalidArgumentException::class, fn () => TestUser::query()->orderBy('name; DROP TABLE users'));
    throws(InvalidArgumentException::class, fn () => TestUser::where('id = 1 OR 1', 1));
    throws(InvalidArgumentException::class, fn () => TestUser::query()->orderBy('name', 'DESC; --'));
    throws(InvalidArgumentException::class, fn () => TestUser::where('id', '; DROP', 1));
});

test('values are bound, not interpolated', function () {
    eq(0, TestUser::where('name', "' OR '1'='1")->count());
});

test('array value in where() gives a clear error', function () {
    throws(InvalidArgumentException::class, fn () => TestUser::where('id', [1, 2])->get());
});

test('update/delete without where are refused', function () {
    throws(InvalidArgumentException::class, fn () => (new QueryBuilder(Connection::get(), 'posts'))->update(['views' => 0]));
    throws(InvalidArgumentException::class, fn () => (new QueryBuilder(Connection::get(), 'posts'))->delete());
});

test('update returns affected rows (0 when unchanged is not an error)', function () {
    eq(1, TestPost::where('id', 1)->update(['views' => 11]));
});

section('Relations');

test('hasMany via method returns a query; via property returns models', function () use ($ada) {
    $u = TestUser::find($ada->id);
    eq(2, $u->posts()->count());
    eq(2, count($u->posts));
    eq(true, $u->posts[0] instanceof TestPost);
});

test('hasMany()->orWhere() cannot leak other users\' rows', function () use ($ada) {
    $titles = TestUser::find($ada->id)->posts()->where('views', 11)->orWhere('title', 'Bob 1')->pluck('title');
    eq(['Ada 1'], $titles);
});

test('belongsTo via property', function () {
    eq('Ada L', TestPost::find(1)->author->name);
    eq(true, isset(TestPost::find(1)->author));
});

test('hasOne with inferred foreign key (test_user_id)', function () use ($ada) {
    eq('Mathematician', TestUser::find($ada->id)->profile->bio);
    eq(null, TestUser::find(2)->profile);
});

test('loaded relations are included in toArray / JSON', function () {
    $post = TestPost::find(1);
    $post->author;
    eq('Ada L', $post->toArray()['author']['name']);
    eq(false, array_key_exists('password', $post->toArray()['author']));
});

test('non-relation methods are not triggered by property access', function () {
    eq(null, TestUser::find(1)->shout);
    eq(null, TestUser::find(1)->save); // Model's own methods never run
});

section('Transactions');

test('transaction commits', function () {
    Connection::transaction(fn () => TestPost::create(['user_id' => 1, 'title' => 'tx']));
    eq(1, TestPost::where('title', 'tx')->count());
});

test('transaction rolls back on exception', function () {
    throws(RuntimeException::class, fn () => Connection::transaction(function () {
        TestPost::create(['user_id' => 1, 'title' => 'rollback-me']);
        throw new RuntimeException('boom');
    }));
    eq(0, TestPost::where('title', 'rollback-me')->count());
});

section('Schema');

test('Schema::table adds and drops columns, adds FK on SQLite', function () {
    Schema::table('posts', function (Blueprint $t) {
        $t->string('slug')->nullable();
        $t->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
        $t->index('slug');
    });
    TestPost::where('id', 1)->update(['slug' => 'ada-1', 'editor_id' => 2]);
    eq('ada-1', TestPost::find(1)->slug);

    Schema::table('posts', function (Blueprint $t) {
        $t->dropIndex(['slug']);
        $t->dropColumn('slug');
    });
    eq(false, array_key_exists('slug', TestPost::find(1)->toArray()));
});

test('hasTable / dropIfExists', function () {
    Schema::create('tmp', fn (Blueprint $t) => $t->string('x'));
    eq(true, Schema::hasTable('tmp'));
    Schema::dropIfExists('tmp');
    eq(false, Schema::hasTable('tmp'));
});

test('insert into a table without an auto-increment id', function () {
    Schema::create('tags', fn (Blueprint $t) => $t->string('slug'));
    (new QueryBuilder(Connection::get(), 'tags'))->insert(['slug' => 'php']);
    eq(1, (new QueryBuilder(Connection::get(), 'tags'))->count());
});

test('MySQL DDL: id is a primary key, FK and defaults correct', function () {
    $b = new Blueprint('posts');
    $b->id();
    $b->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $b->boolean('published')->default(false);
    $sql = $b->toSql('mysql')[0];
    eq(true, str_contains($sql, '`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'), $sql);
    eq(true, str_contains($sql, '`user_id` BIGINT UNSIGNED NOT NULL'), $sql);
    eq(true, str_contains($sql, '`published` TINYINT(1) NOT NULL DEFAULT 0'), $sql);
    eq(true, str_contains($sql, 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'), $sql);
});

test('PostgreSQL DDL: BIGSERIAL id, boolean defaults TRUE/FALSE, JSONB', function () {
    $b = new Blueprint('posts');
    $b->id();
    $b->boolean('published')->default(false);
    $b->boolean('visible')->default(1);
    $b->json('meta');
    $sql = $b->toSql('pgsql')[0];
    eq(true, str_contains($sql, '"id" BIGSERIAL PRIMARY KEY'), $sql);
    eq(true, str_contains($sql, '"published" BOOLEAN NOT NULL DEFAULT FALSE'), $sql);
    eq(true, str_contains($sql, '"visible" BOOLEAN NOT NULL DEFAULT TRUE'), $sql);
    eq(true, str_contains($sql, '"meta" JSONB NOT NULL'), $sql);
});

test('MySQL ALTER: FK added as a separate statement', function () {
    $b = new Blueprint('posts', altering: true);
    $b->foreignId('editor_id')->nullable()->constrained('users');
    $sql = $b->toSql('mysql');
    eq('ALTER TABLE `posts` ADD COLUMN `editor_id` BIGINT UNSIGNED NULL', $sql[0]);
    eq('ALTER TABLE `posts` ADD FOREIGN KEY (`editor_id`) REFERENCES `users` (`id`)', $sql[1]);
});

test('string defaults are escaped', function () {
    eq("\"x\" VARCHAR(255) NOT NULL DEFAULT 'it''s'", (new Column('x', 'string', ['length' => 255]))->default("it's")->toSql('sqlite'));
});

section('Migrations');

test('migrate / rollback / status, and a failing migration leaves nothing behind', function () {
    $dir = sys_get_temp_dir() . '/simple_migrations_' . uniqid();
    mkdir($dir);
    $stub = fn (string $body) => "<?php\nuse Simple\\Database\\Migration; use Simple\\Database\\Schema\\{Schema, Blueprint};\n"
        . "return new class extends Migration { $body };";

    file_put_contents("$dir/2026_01_01_000000_create_a.php", $stub(
        "public function up(): void { Schema::create('a', fn (Blueprint \$t) => \$t->id()); }
         public function down(): void { Schema::dropIfExists('a'); }"
    ));

    $m = new Migrator($dir);
    eq(['2026_01_01_000000_create_a'], $m->migrate());
    eq([], $m->migrate(), 'second run');
    eq(['2026_01_01_000000_create_a' => 1], $m->status());

    file_put_contents("$dir/2026_01_02_000000_broken.php", $stub(
        "public function up(): void { Schema::create('b', fn (Blueprint \$t) => \$t->id()); throw new RuntimeException('fail'); }
         public function down(): void { Schema::dropIfExists('b'); }"
    ));
    throws(RuntimeException::class, fn () => $m->migrate());
    eq(false, Schema::hasTable('b'), 'table from failed migration rolled back');
    eq(null, $m->status()['2026_01_02_000000_broken']);
    unlink("$dir/2026_01_02_000000_broken.php");

    eq(['2026_01_01_000000_create_a'], $m->rollback());
    eq(false, Schema::hasTable('a'));

    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
});

// ---- Summary ---------------------------------------------------------------

echo "\n" . ($failed ? "\033[31m" : "\033[32m") . "$passed passed, $failed failed\033[0m\n";
exit($failed ? 1 : 0);
