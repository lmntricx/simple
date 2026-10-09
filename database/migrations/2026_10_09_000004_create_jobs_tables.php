<?php

use Simple\Database\Migration;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100);
            $table->text('payload');
            $table->integer('attempts')->default(0);
            // Unix timestamps, so they mean the same thing on every server and database.
            $table->bigInteger('reserved_at')->nullable();
            $table->bigInteger('available_at');
            $table->bigInteger('created_at');
            $table->index(['queue', 'available_at']);
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('queue', 100);
            $table->text('payload');
            $table->text('exception');
            $table->datetime('failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
};
