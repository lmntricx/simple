<?php

namespace Simple\Tests\Fixtures\Models;

use Simple\Database\Model;
use Simple\Database\Relations\BelongsTo;

class Post extends Model
{
    protected string $table = 'posts';
    protected ?array $guarded = [];
    protected array $casts = ['id' => 'int', 'user_id' => 'int', 'views' => 'int'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
