<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Factories\UserFactory;
use Simple\Console\Kernel;
use Simple\Database\Factory;
use Simple\Database\Schema\Schema;
use Tests\TestCase;

class FactoryAndSeederTest extends TestCase
{
    private function command(string ...$args): array
    {
        $out = fopen('php://memory', 'w+');
        $code = (new Kernel($this->app, $out))->handle(['simple', ...$args]);
        rewind($out);

        return [$code, stream_get_contents($out)];
    }

    public function test_create_one_and_many(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->exists);

        $users = User::factory()->count(3)->create();
        $this->assertCount(3, $users);
        $this->assertSame(4, User::query()->count());
    }

    public function test_emails_are_unique(): void
    {
        $emails = array_map(fn ($u) => $u->email, User::factory()->count(25)->create());
        $this->assertCount(25, array_unique($emails));
    }

    public function test_mutators_run_so_passwords_are_hashed(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->verifyPassword('password'));
        $this->assertNotSame('password', $user->getAttribute('password'));
    }

    public function test_overrides_and_states(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Ada']);
        $this->assertSame('Ada', $user->name);
        $this->assertNull(User::find($user->id)->email_verified_at);

        $this->assertNotNull(User::factory()->create()->email_verified_at, 'verified by default');
    }

    public function test_closure_states_receive_attributes(): void
    {
        $user = User::factory()->state(fn (array $attrs) => ['name' => strtoupper($attrs['name'])])->make();
        $this->assertSame(strtoupper($user->name), $user->name);
    }

    public function test_make_does_not_save(): void
    {
        $user = User::factory()->make();
        $this->assertFalse($user->exists);
        $this->assertSame(0, User::query()->count());
    }

    public function test_factories_create_related_models(): void
    {
        $factory = new class extends Factory {
            protected string $model = \Simple\Auth\PersonalAccessToken::class;

            public function definition(): array
            {
                return ['user_id' => UserFactory::new(), 'name' => 'cli', 'token' => hash('sha256', 'x' . $this->fake->uuid())];
            }
        };

        $token = $factory->create();
        $this->assertNotNull(User::find($token->user_id));
    }

    public function test_db_seed_runs_the_database_seeder(): void
    {
        [$code, $output] = $this->command('db:seed');

        $this->assertSame(0, $code, $output);
        $this->assertSame(11, User::query()->count());
        $this->assertTrue(User::firstWhere('email', 'test@example.com')->verifyPassword('password'));
    }

    public function test_db_seed_with_unknown_class_fails(): void
    {
        [$code, $output] = $this->command('db:seed', '--class=NopeSeeder');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('not found', $output);
    }

    public function test_db_fresh_rebuilds_and_optionally_seeds(): void
    {
        User::factory()->count(3)->create();
        Schema::create('stray_table', fn ($t) => $t->id());

        [$code] = $this->command('db:fresh');
        $this->assertSame(0, $code);
        $this->assertSame(0, User::query()->count());
        $this->assertFalse(Schema::hasTable('stray_table'));

        $this->command('db:fresh', '--seed');
        $this->assertSame(11, User::query()->count());
    }

    public function test_db_fresh_refuses_in_production_without_force(): void
    {
        User::factory()->create();
        $this->app->config->set('app.env', 'production');

        [$code, $output] = $this->command('db:fresh');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Refusing', $output);
        $this->assertSame(1, User::query()->count(), 'data untouched');
    }

    public function test_fake_data_is_repeatable_with_a_seed(): void
    {
        $first = [fake()->seed(42)->name(), fake()->sentence()];
        $second = [fake()->seed(42)->name(), fake()->sentence()];
        $this->assertSame($first, $second);
    }
}
