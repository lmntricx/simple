<?php

namespace Simple\Database;

/**
 * Fills the database with data. Run with `php simple db:seed` (DatabaseSeeder)
 * or `php simple db:seed --class=UserSeeder`.
 */
abstract class Seeder
{
    abstract public function run(): void;

    /**
     * Run other seeders: $this->call(UserSeeder::class, PostSeeder::class).
     */
    public function call(string ...$seeders): void
    {
        foreach ($seeders as $seeder) {
            app($seeder)->run();
        }
    }
}
