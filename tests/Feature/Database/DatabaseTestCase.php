<?php

namespace Tests\Feature\Database;

use Simple\Database\Connection;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Schema;
use Simple\Testing\TestCase;
use Tests\Fixtures\Models\Post;
use Tests\Fixtures\Models\Profile;
use Tests\Fixtures\Models\User;

/**
 * ORM tests run on their own schema instead of the app's migrations.
 * Every test starts with: Ada (2 posts, a profile), Bob (inactive, 1 post), Cy.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected bool $migrate = false;

    protected User $ada;
    protected User $bob;
    protected User $cy;

    protected function setUp(): void
    {
        parent::setUp();

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
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('bio');
        });

        $this->ada = User::create(['name' => 'Ada', 'email' => 'ada@x.io', 'password' => 'secret', 'settings' => ['theme' => 'dark']]);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@x.io', 'active' => false]);
        $this->cy = User::create(['name' => 'Cy', 'email' => 'cy@x.io']);

        Post::create(['user_id' => $this->ada->id, 'title' => 'Ada 1', 'views' => 10]);
        Post::create(['user_id' => $this->ada->id, 'title' => 'Ada 2', 'views' => 50]);
        Post::create(['user_id' => $this->bob->id, 'title' => 'Bob 1', 'views' => 99]);

        Profile::create(['user_id' => $this->ada->id, 'bio' => 'Mathematician']);
    }

    protected function driver(): string
    {
        return Connection::driver(Connection::get());
    }

    /** Quote an identifier the way the current driver does. */
    protected function q(string $name): string
    {
        return $this->driver() === 'mysql' ? "`$name`" : "\"$name\"";
    }
}
