<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

/**
 * Invitation tokens: 64 random bytes in base64url; only the SHA-256 hash is ever stored or compared.
 */
final class InvitationToken
{
    public static function make(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Timing-safe comparison of the stored hash with the hash of a presented token. */
    public static function matches(string $storedHash, string $token): bool
    {
        return hash_equals($storedHash, self::hash($token));
    }
}
