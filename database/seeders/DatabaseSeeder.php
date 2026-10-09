<?php

namespace Database\Seeders;

use App\Models\User;
use Simple\Database\Seeder;

/**
 * php simple db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // A known account for logging in locally: test@example.com / password
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        User::factory()->count(10)->create();
    }
}
