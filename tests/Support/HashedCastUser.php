<?php

declare(strict_types=1);

namespace Tests\Support;

/** A host user model that hashes the password through the `hashed` cast. */
class HashedCastUser extends TestUser
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime'];
    }
}
