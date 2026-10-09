<?php

namespace Tests\Feature\Database;

use LogicException;
use Simple\Http\HttpException;
use Tests\Fixtures\Models\BlogPost;
use Tests\Fixtures\Models\Category;
use Tests\Fixtures\Models\Post;
use Tests\Fixtures\Models\User;

class ModelTest extends DatabaseTestCase
{
    public function test_create_sets_id_and_exists(): void
    {
        $this->assertSame(1, $this->ada->id);
        $this->assertTrue($this->ada->exists);
    }

    public function test_find_returns_model_or_null(): void
    {
        $this->assertSame('Ada', User::find(1)->name);
        $this->assertSame('Ada', User::find('1')->name);
        $this->assertNull(User::find(999));
        $this->assertNull(User::find(null));
        $this->assertNull(User::find(''));
    }

    public function test_find_with_non_numeric_id_returns_null_instead_of_erroring(): void
    {
        $this->assertNull(User::find('abc'));
        $this->assertNull(User::find('1 OR 1=1'));
        $this->assertNull(User::find('-1'));
    }

    public function test_find_or_fail_throws_404(): void
    {
        $this->expectException(HttpException::class);
        User::findOrFail(999);
    }

    public function test_all_returns_models(): void
    {
        $all = User::all();
        $this->assertCount(3, $all);
        $this->assertInstanceOf(User::class, $all[0]);
    }

    public function test_table_name_is_inferred(): void
    {
        $this->assertSame('categories', (new Category())->getTable());
        $this->assertSame('blog_posts', (new BlogPost())->getTable());
    }

    public function test_mass_assignment_respects_fillable(): void
    {
        $user = new User(['name' => 'X', 'id' => 500, 'is_admin' => 1]);
        $this->assertNull($user->id);
        $this->assertNull($user->is_admin);
    }

    public function test_mutator_hashes_password_and_hidden_fields_are_not_serialised(): void
    {
        $user = User::find(1);
        $this->assertTrue(password_verify('secret', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertStringNotContainsString('password', json_encode($user));
    }

    public function test_casts_round_trip(): void
    {
        $this->assertFalse(User::find(2)->active);
        $this->assertTrue(User::find(1)->active);
        $this->assertSame(2, User::find(2)->id);
        $this->assertSame(['theme' => 'dark'], User::find(1)->settings);
    }

    public function test_timestamps_are_set(): void
    {
        $user = User::find(3);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);

        $user->update(['name' => 'Cy2']);
        $this->assertSame('Cy2', User::find(3)->name);
    }

    public function test_save_only_writes_dirty_columns(): void
    {
        $user = User::find(3);
        $this->assertSame([], $user->getDirty());

        $user->name = 'Cy3';
        $this->assertSame(['name' => 'Cy3'], $user->getDirty());

        $user->save();
        $this->assertSame([], $user->getDirty());
    }

    public function test_setting_a_cast_attribute_to_its_current_value_is_not_dirty(): void
    {
        $user = User::find(1);
        $user->active = true;
        $user->settings = ['theme' => 'dark'];
        $this->assertSame([], $user->getDirty());
    }

    public function test_saving_an_existing_model_without_a_key_throws(): void
    {
        $user = new User(['name' => 'ghost']);
        $user->exists = true;

        $this->expectException(LogicException::class);
        $user->save();
    }

    public function test_refresh_reloads_from_the_database(): void
    {
        $user = User::find(1);
        User::query()->where('id', 1)->update(['name' => 'Ada L']);
        $this->assertSame('Ada L', $user->refresh()->name);
    }

    public function test_delete_removes_the_row_and_cascades(): void
    {
        $user = User::find(1);
        $this->assertTrue($user->delete());
        $this->assertNull(User::find(1));
        $this->assertSame(0, Post::where('user_id', 1)->count());
        $this->assertFalse($user->delete(), 'deleting twice');
    }
}
