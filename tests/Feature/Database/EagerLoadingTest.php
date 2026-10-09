<?php

namespace Tests\Feature\Database;

use InvalidArgumentException;
use Simple\Database\Connection;
use Tests\Fixtures\Models\Post;
use Tests\Fixtures\Models\User;

class EagerLoadingTest extends DatabaseTestCase
{
    /**
     * Run $callback and return how many queries it made.
     */
    private function countQueries(\Closure $callback): int
    {
        Connection::enableQueryLog();
        try {
            $callback();
            return count(Connection::getQueryLog());
        } finally {
            Connection::disableQueryLog();
        }
    }

    public function test_lazy_loading_makes_one_query_per_model(): void
    {
        $queries = $this->countQueries(function () {
            foreach (Post::all() as $post) {
                $post->author->name;
            }
        });

        $this->assertSame(4, $queries, '1 for posts + 1 per post (N+1)');
    }

    public function test_with_loads_belongs_to_in_one_query(): void
    {
        $queries = $this->countQueries(function () {
            $posts = Post::with('author')->orderBy('id')->get();
            $this->assertSame(['Ada', 'Ada', 'Bob'], array_map(fn ($p) => $p->author->name, $posts));
        });

        $this->assertSame(2, $queries);
    }

    public function test_with_loads_has_many_and_has_one(): void
    {
        $queries = $this->countQueries(function () {
            $users = User::with('posts', 'profile')->orderBy('id')->get();

            $this->assertSame([2, 1, 0], array_map(fn ($u) => count($u->posts), $users));
            $this->assertSame('Mathematician', $users[0]->profile->bio);
            $this->assertNull($users[1]->profile);
            $this->assertSame([], $users[2]->posts);
        });

        $this->assertSame(3, $queries);
    }

    public function test_nested_relations(): void
    {
        $queries = $this->countQueries(function () {
            $users = User::with('posts.author')->orderBy('id')->get();
            $this->assertSame('Ada', $users[0]->posts[1]->author->name);
        });

        $this->assertSame(3, $queries);
    }

    public function test_eager_and_lazy_loading_give_the_same_data(): void
    {
        $eager = array_map(fn ($u) => $u->toArray(), User::with('posts', 'profile')->orderBy('id')->get());

        $lazy = array_map(function (User $u) {
            $u->posts;
            $u->profile;
            return $u->toArray();
        }, User::query()->orderBy('id')->get());

        $this->assertEquals($lazy, $eager);
    }

    public function test_load_on_existing_models(): void
    {
        $post = Post::find(3);

        $queries = $this->countQueries(fn () => $post->load('author'));
        $this->assertSame(1, $queries);
        $this->assertTrue($post->relationLoaded('author'));

        $this->assertSame(0, $this->countQueries(fn () => $post->author->name), 'already loaded');
    }

    public function test_with_works_with_first_find_and_paginate(): void
    {
        $this->assertTrue(Post::with('author')->first()->relationLoaded('author'));
        $this->assertTrue(Post::with('author')->paginate(2)['data'][1]->relationLoaded('author'));
    }

    public function test_null_foreign_keys_and_empty_results(): void
    {
        $this->assertSame([], Post::with('author')->where('id', 999)->get());

        $queries = $this->countQueries(fn () => User::with('posts')->where('id', 999)->get());
        $this->assertSame(1, $queries, 'no relation query when there are no parents');
    }

    public function test_relation_constraints_still_apply_when_chaining(): void
    {
        $ada = User::find(1);
        $this->assertSame(['Ada 2'], $ada->posts()->where('views', '>', 20)->pluck('title'));
        $this->assertSame(['Ada 1'], $ada->posts()->where('views', 10)->orWhere('title', 'Bob 1')->pluck('title'));
        $this->assertSame(1, $ada->posts()->where('views', 50)->count());
    }

    public function test_unknown_relation_is_an_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[shout] is not a relationship');
        User::with('shout')->get();
    }
}
