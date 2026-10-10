<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;

/** A host user model implementing MustVerifyEmail. */
class VerifiableTestUser extends TestUser implements MustVerifyEmail
{
    use Notifiable;
}
