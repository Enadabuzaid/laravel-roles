<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

final class InvitationErrors
{
    /** One response for every way a token can be unusable (unknown, used, revoked, expired, wrong email). */
    public static function invalid(): AccessException
    {
        return new AccessException((string) trans('access::errors.invitation_invalid'), 'invitation.invalid', 410);
    }

    public static function userExists(): AccessException
    {
        return new AccessException((string) trans('access::errors.invitation_user_exists'), 'invitation.user_exists', 409);
    }

    public static function pendingExists(): AccessException
    {
        return new AccessException((string) trans('access::errors.invitation_pending_exists'), 'invitation.pending_exists', 409);
    }

    public static function notPending(): AccessException
    {
        return new AccessException((string) trans('access::errors.invitation_not_pending'), 'invitation.not_pending', 409);
    }
}
