<?php

namespace Database\Factories;

use App\Models\User;
use Simple\Database\Factory;

class UserFactory extends Factory
{
    protected string $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->fake->name(),
            'email' => $this->fake->email(),
            'password' => 'password',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
