<?php

declare(strict_types=1);

namespace Tests\Support;

use Enadstack\LaravelRoles\Concerns\HasAccess;
use Enadstack\LaravelRoles\Contracts\AccessUser;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable implements AccessUser
{
    use HasAccess;

    protected $table = 'users';

    protected $guarded = [];
}
