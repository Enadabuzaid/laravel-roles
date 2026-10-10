<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

/**
 * Thrown by the `deleting` hook of HasAccess models when a delete outside the DeleteUser Action would remove the
 * last active owner. It is an AccessException: package API routes render it as the guardrail.last_owner envelope,
 * host code (a profile page, a console command) catches it like any exception.
 */
final class LastOwnerDeletionException extends AccessException
{
    public function __construct()
    {
        parent::__construct((string) trans('access::guardrail.last_owner'), 'guardrail.last_owner', 403);
    }
}
