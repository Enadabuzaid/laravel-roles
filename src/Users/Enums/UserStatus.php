<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Enums;

enum UserStatus: string
{
    case Invited = 'invited';
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Locked = 'locked';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    /**
     * Whether the status alone allows signing in. A locked user may sign in again once
     * `locked_until` has passed; use UserAccessState::canSignInNow() for that.
     */
    public function canSignIn(): bool
    {
        return $this === self::Active;
    }

    /** A final status needs an explicit administrative action to leave. */
    public function isFinal(): bool
    {
        return $this === self::Deactivated;
    }
}
