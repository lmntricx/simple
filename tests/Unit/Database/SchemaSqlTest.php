<?php

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Column;

/**
 * The SQL generated for each driver, without needing a database.
 */
class SchemaSqlTest extends TestCase
{
    public function test_mysql_create_table(): void
    {
        $b = new Blueprint('posts');
        $b->id();
        $b->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $b->boolean('published')->default(false);
        $sql = $b->toSql('mysql')[0];

        $this->assertStringContainsString('`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringContainsString('`user_id` BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringContainsString('`published` TINYINT(1) NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $sql);
    }

    public function test_pgsql_create_table(): void
    {
        $b = new Blueprint('posts');
        $b->id();
        $b->boolean('published')->default(false);
        $b->boolean('visible')->default(1);
        $b->json('meta');
        $sql = $b->toSql('pgsql')[0];

        $this->assertStringContainsString('"id" BIGSERIAL PRIMARY KEY', $sql);
        $this->assertStringContainsString('"published" BOOLEAN NOT NULL DEFAULT FALSE', $sql);
        $this->assertStringContainsString('"visible" BOOLEAN NOT NULL DEFAULT TRUE', $sql);
        $this->assertStringContainsString('"meta" JSONB NOT NULL', $sql);
    }

    public function test_mysql_alter_adds_foreign_key_separately(): void
    {
        $b = new Blueprint('posts', altering: true);
        $b->foreignId('editor_id')->nullable()->constrained('users');

        $this->assertSame([
            'ALTER TABLE `posts` ADD COLUMN `editor_id` BIGINT UNSIGNED NULL',
            'ALTER TABLE `posts` ADD FOREIGN KEY (`editor_id`) REFERENCES `users` (`id`)',
        ], $b->toSql('mysql'));
    }

    public function test_sqlite_alter_adds_foreign_key_inline(): void
    {
        $b = new Blueprint('posts', altering: true);
        $b->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();

        $this->assertSame(
            ['ALTER TABLE "posts" ADD COLUMN "editor_id" INTEGER NULL REFERENCES "users" ("id") ON DELETE SET NULL'],
            $b->toSql('sqlite'),
        );
    }

    public function test_string_defaults_are_escaped(): void
    {
        $this->assertSame(
            "\"x\" VARCHAR(255) NOT NULL DEFAULT 'it''s'",
            (new Column('x', 'string', ['length' => 255]))->default("it's")->toSql('sqlite'),
        );
    }
}
