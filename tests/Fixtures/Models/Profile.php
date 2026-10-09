<?php

namespace Tests\Fixtures\Models;

use Simple\Database\Model;

class Profile extends Model
{
    protected string $table = 'profiles';
    protected ?array $guarded = [];
    protected bool $timestamps = false;
}
