<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A misconfigured host model: does not implement AccessUser. */
class NonAccessUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
