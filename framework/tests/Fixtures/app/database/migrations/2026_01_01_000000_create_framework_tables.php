<?php

use Simple\Database\Migration;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Schema;

/*
 * Every table the framework's features need: users (for auth), API tokens,
 * password resets, and the queue.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->datetime('email_verified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->json('abilities')->nullable();
            $t->datetime('last_used_at')->nullable();
            $t->datetime('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            $t->string('token', 64);
            $t->datetime('created_at');
        });

        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue', 100);
            $t->text('payload');
            $t->integer('attempts')->default(0);
            $t->bigInteger('reserved_at')->nullable();
            $t->bigInteger('available_at');
            $t->bigInteger('created_at');
            $t->index(['queue', 'available_at']);
        });

        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid', 36)->unique();
            $t->string('queue', 100);
            $t->text('payload');
            $t->text('exception');
            $t->datetime('failed_at');
        });
    }

    public function down(): void
    {
        foreach (['failed_jobs', 'jobs', 'password_reset_tokens', 'personal_access_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
