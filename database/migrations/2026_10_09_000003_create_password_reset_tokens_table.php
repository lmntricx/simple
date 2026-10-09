<?php

use Simple\Database\Migration;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('token', 64);
            $table->datetime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
