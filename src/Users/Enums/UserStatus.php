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

    /**
     * Whether an owner in this status counts towards `min_owners`. Locked is transient and self-healing, so a locked
     * owner still counts; suspended, deactivated and not-yet-activated owners do not.
     */
    public function countsAsActiveOwner(): bool
    {
        return ! in_array($this, [self::Suspended, self::Deactivated, self::Invited, self::PendingVerification], true);
    }
}
