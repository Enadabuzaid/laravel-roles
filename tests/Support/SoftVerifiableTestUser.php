<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/** Soft deleting and MustVerifyEmail at once. */
class SoftVerifiableTestUser extends TestUser implements MustVerifyEmail
{
    use Notifiable;
    use SoftDeletes;
}
