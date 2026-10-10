<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Eloquent\SoftDeletes;

/** A host user model that soft deletes (needs the `deleted_at` column of the test migration). */
class SoftTestUser extends TestUser
{
    use SoftDeletes;
}
