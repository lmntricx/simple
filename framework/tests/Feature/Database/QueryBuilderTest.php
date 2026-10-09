<?php

namespace Simple\Tests\Feature\Database;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Simple\Database\Connection;
use Simple\Database\QueryBuilder;
use Simple\Tests\Fixtures\Models\Post;
use Simple\Tests\Fixtures\Models\User;

class QueryBuilderTest extends DatabaseTestCase
{
    private function titles(array $posts): array
    {
        return array_map(fn (Post $p) => $p->title, $posts);
    }

    public function test_multiple_wheres_are_anded(): void
    {
        $this->assertSame(1, User::where('name', 'Ada')->where('email', 'ada@x.io')->count());
        $this->assertSame(0, User::where('name', 'Ada')->where('email', 'bob@x.io')->count());
    }

    public function test_operators(): void
    {
        $this->assertSame(2, Post::where('views', '>=', 50)->count());
        $this->assertSame(2, Post::where('title', 'like', '%1')->count());
        $this->assertSame(2, Post::where('views', '!=', 50)->count());
        $this->assertSame(1, Post::where('views', '<', 50)->count());
    }

    public function test_null_comparisons(): void
    {
        $this->assertSame(2, User::where('password', null)->count());
        $this->assertSame(1, User::where('password', '!=', null)->count());
        $this->assertSame(2, User::whereNull('password')->count());
        $this->assertSame(1, User::whereNotNull('password')->count());
    }

    public function test_where_in_and_not_in(): void
    {
        $this->assertSame(2, User::whereIn('id', [1, 2])->count());
        $this->assertSame(0, User::whereIn('id', [])->count());
        $this->assertSame(1, User::whereNotIn('id', [1, 2])->count());
        $this->assertSame(3, User::whereNotIn('id', [])->count());
    }

    public function test_nested_where_groups(): void
    {
        $query = Post::where('views', '>', 20)->where(fn ($q) => $q->where('title', 'Ada 1')->orWhere('title', 'Ada 2'));

        $this->assertSame(1, $query->count());
        $this->assertStringContainsString("AND ({$this->q('title')} = ? OR {$this->q('title')} = ?)", $query->toSql());
    }

    public function test_order_limit_offset_first(): void
    {
        $this->assertSame('Bob 1', Post::query()->orderBy('views', 'desc')->first()->title);
        $this->assertSame(['Ada 2'], $this->titles(Post::query()->orderBy('views')->limit(1)->offset(1)->get()));
        $this->assertSame(['Ada 2', 'Bob 1'], $this->titles(Post::query()->orderBy('views')->offset(1)->get()));
    }

    public function test_first_and_count_do_not_mutate_the_builder(): void
    {
        $query = Post::query()->orderBy('views');
        $query->first();
        $query->count();
        $this->assertCount(3, $query->get());
    }

    public function test_paginate(): void
    {
        $page = Post::query()->orderBy('id')->paginate(2, 2);
        $this->assertCount(1, $page['data']);
        $this->assertSame(['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $page['meta']);
    }

    public function test_paginate_with_zero_per_page_does_not_divide_by_zero(): void
    {
        $this->assertSame(1, Post::query()->paginate(0)['meta']['per_page']);
    }

    public function test_pluck_and_exists(): void
    {
        $this->assertSame(['Ada 1', 'Ada 2', 'Bob 1'], Post::query()->orderBy('id')->pluck('title'));
        $this->assertTrue(Post::where('views', 99)->exists());
        $this->assertFalse(Post::where('views', 12345)->exists());
    }

    public function test_select_with_alias(): void
    {
        $this->assertSame('Ada', User::query()->select('name as n')->orderBy('id')->first()->n);
    }

    public function test_join_does_not_let_joined_columns_overwrite_the_model(): void
    {
        $post = Post::query()->join('users', 'users.id', '=', 'posts.user_id')->where('posts.id', 3)->first();
        $this->assertSame(3, $post->id);
        $this->assertNull($post->email);
    }

    public function test_plain_query_builder_returns_arrays(): void
    {
        $row = (new QueryBuilder(Connection::get(), 'users'))->where('id', 1)->first();
        $this->assertIsArray($row);
    }

    public static function maliciousIdentifiers(): array
    {
        return [
            'orderBy injection' => [fn () => User::query()->orderBy('name; DROP TABLE users')],
            'where column injection' => [fn () => User::where('id = 1 OR 1', 1)],
            'direction injection' => [fn () => User::query()->orderBy('name', 'DESC; --')],
            'operator injection' => [fn () => User::where('id', '; DROP', 1)],
            'select injection' => [fn () => User::query()->select('* FROM users; --')->get()],
        ];
    }

    #[DataProvider('maliciousIdentifiers')]
    public function test_identifiers_and_operators_are_validated(\Closure $query): void
    {
        $this->expectException(InvalidArgumentException::class);
        $query();
    }

    public function test_values_are_bound_not_interpolated(): void
    {
        $this->assertSame(0, User::where('name', "' OR '1'='1")->count());
        $this->assertSame(3, User::query()->count(), 'table intact');
    }

    public function test_array_value_in_where_gives_a_clear_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whereIn');
        User::where('id', [1, 2])->get();
    }

    public function test_update_and_delete_without_where_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder(Connection::get(), 'posts'))->update(['views' => 0]);
    }

    public function test_delete_without_where_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder(Connection::get(), 'posts'))->delete();
    }

    public function test_update_returns_affected_rows(): void
    {
        $this->assertSame(1, Post::where('id', 1)->update(['views' => 11]));
    }
}
