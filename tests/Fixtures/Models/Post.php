<?php

namespace Tests\Fixtures\Models;

use Simple\Database\Model;

class Post extends Model
{
    protected string $table = 'posts';
    protected ?array $guarded = [];
    protected array $casts = ['id' => 'int', 'user_id' => 'int', 'views' => 'int'];

    public function author(): ?User
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
