<?php

namespace Simple\Tests\Feature\Database;

use Simple\Tests\Fixtures\Models\Post;
use Simple\Tests\Fixtures\Models\User;

class RelationsTest extends DatabaseTestCase
{
    public function test_has_many_as_method_and_property(): void
    {
        $ada = User::find(1);
        $this->assertSame(2, $ada->posts()->count());
        $this->assertCount(2, $ada->posts);
        $this->assertInstanceOf(Post::class, $ada->posts[0]);
    }

    public function test_has_many_or_where_cannot_return_other_owners_rows(): void
    {
        $titles = User::find(1)->posts()->where('views', 10)->orWhere('title', 'Bob 1')->pluck('title');
        $this->assertSame(['Ada 1'], $titles);
    }

    public function test_belongs_to(): void
    {
        $this->assertSame('Ada', Post::find(1)->author->name);
        $this->assertTrue(isset(Post::find(1)->author));
    }

    public function test_has_one_with_inferred_foreign_key(): void
    {
        $this->assertSame('Mathematician', User::find(1)->profile->bio);
        $this->assertNull(User::find(2)->profile);
    }

    public function test_loaded_relations_are_serialised_without_hidden_fields(): void
    {
        $post = Post::find(1);
        $post->author;

        $this->assertSame('Ada', $post->toArray()['author']['name']);
        $this->assertArrayNotHasKey('password', $post->toArray()['author']);
    }

    public function test_property_access_never_calls_non_relation_methods(): void
    {
        $this->assertNull(User::find(1)->shout);
        $this->assertNull(User::find(1)->save);
    }
}
