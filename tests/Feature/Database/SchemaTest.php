<?php

namespace Tests\Feature\Database;

use RuntimeException;
use Simple\Database\Connection;
use Simple\Database\Migrator;
use Simple\Database\QueryBuilder;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Schema;
use Tests\Fixtures\Models\Post;

class SchemaTest extends DatabaseTestCase
{
    public function test_alter_table_adds_and_drops_columns_indexes_and_foreign_keys(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->string('slug')->nullable();
            $t->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->index('slug');
        });

        Post::where('id', 1)->update(['slug' => 'ada-1', 'editor_id' => 2]);
        $this->assertSame('ada-1', Post::find(1)->slug);

        Schema::table('posts', function (Blueprint $t) {
            $t->dropIndex(['slug']);
            $t->dropColumn('slug');
        });

        $this->assertArrayNotHasKey('slug', Post::find(1)->toArray());
    }

    public function test_has_table_and_drop_if_exists(): void
    {
        Schema::create('tmp', fn (Blueprint $t) => $t->string('x'));
        $this->assertTrue(Schema::hasTable('tmp'));

        Schema::dropIfExists('tmp');
        $this->assertFalse(Schema::hasTable('tmp'));
    }

    public function test_drop_all_tables(): void
    {
        Schema::dropAllTables();
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('posts'));
    }

    public function test_insert_into_a_table_without_an_auto_increment_id(): void
    {
        Schema::create('tags', fn (Blueprint $t) => $t->string('slug'));
        (new QueryBuilder(Connection::get(), 'tags'))->insert(['slug' => 'php']);
        $this->assertSame(1, (new QueryBuilder(Connection::get(), 'tags'))->count());
    }

    public function test_transaction_commits(): void
    {
        Connection::transaction(fn () => Post::create(['user_id' => 1, 'title' => 'tx']));
        $this->assertSame(1, Post::where('title', 'tx')->count());
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        try {
            Connection::transaction(function () {
                Post::create(['user_id' => 1, 'title' => 'rollback-me']);
                throw new RuntimeException('boom');
            });
            $this->fail('expected exception');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(0, Post::where('title', 'rollback-me')->count());
    }

    public function test_migrate_rollback_and_status(): void
    {
        $dir = $this->migrationDir([
            '2026_01_01_000000_create_a' => "Schema::create('a', fn (Blueprint \$t) => \$t->id());",
        ], ['2026_01_01_000000_create_a' => "Schema::dropIfExists('a');"]);

        $migrator = new Migrator($dir);
        $this->assertSame(['2026_01_01_000000_create_a'], $migrator->migrate());
        $this->assertSame([], $migrator->migrate(), 'second run');
        $this->assertSame(['2026_01_01_000000_create_a' => 1], $migrator->status());

        $this->assertSame(['2026_01_01_000000_create_a'], $migrator->rollback());
        $this->assertFalse(Schema::hasTable('a'));

        $this->removeDir($dir);
    }

    public function test_failed_migration_is_rolled_back_where_supported(): void
    {
        if ($this->driver() === 'mysql') {
            $this->markTestSkipped('MySQL commits schema changes immediately; migrations cannot be rolled back.');
        }

        $dir = $this->migrationDir([
            '2026_01_02_000000_broken' => "Schema::create('b', fn (Blueprint \$t) => \$t->id()); throw new RuntimeException('fail');",
        ]);

        $migrator = new Migrator($dir);
        try {
            $migrator->migrate();
            $this->fail('expected exception');
        } catch (RuntimeException) {
        }

        $this->assertFalse(Schema::hasTable('b'));
        $this->assertNull($migrator->status()['2026_01_02_000000_broken']);

        $this->removeDir($dir);
    }

    private function migrationDir(array $up, array $down = []): string
    {
        $dir = sys_get_temp_dir() . '/simple_migrations_' . bin2hex(random_bytes(6));
        mkdir($dir);

        foreach ($up as $name => $body) {
            file_put_contents("$dir/$name.php", "<?php\nuse Simple\\Database\\Migration; use Simple\\Database\\Schema\\{Schema, Blueprint};\n"
                . "return new class extends Migration {\n"
                . "    public function up(): void { $body }\n"
                . '    public function down(): void { ' . ($down[$name] ?? '') . " }\n"
                . "};\n");
        }

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        array_map('unlink', glob("$dir/*") ?: []);
        rmdir($dir);
    }
}
