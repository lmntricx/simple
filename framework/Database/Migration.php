<?php

namespace Simple\Database;

/**
 * Migration files in database/migrations return an anonymous subclass:
 *
 *   return new class extends Migration {
 *       public function up(): void { Schema::create(...); }
 *       public function down(): void { Schema::dropIfExists(...); }
 *   };
 */
abstract class Migration
{
    abstract public function up(): void;

    abstract public function down(): void;
}
