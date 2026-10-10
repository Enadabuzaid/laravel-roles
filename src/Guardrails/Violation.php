<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

final class Violation
{
    public static function lastOwner(): AccessException
    {
        return self::make('last_owner');
    }

    public static function selfRoleChange(): AccessException
    {
        return self::make('self_role_change');
    }

    public static function selfDestructive(): AccessException
    {
        return self::make('self_destructive');
    }

    public static function targetAboveActor(): AccessException
    {
        return self::make('target_above_actor');
    }

    public static function escalation(): AccessException
    {
        return self::make('escalation');
    }

    public static function highRiskConfirmRequired(): AccessException
    {
        return self::make('high_risk_confirm_required');
    }

    public static function ownerRoleLocked(): AccessException
    {
        return self::make('owner_role_locked');
    }

    public static function systemRole(): AccessException
    {
        return self::make('system_role');
    }

    public static function confirmationMismatch(): AccessException
    {
        return self::make('confirmation_mismatch');
    }

    /** The message is the translated `access::guardrail.<key>` of the request locale. */
    private static function make(string $key): AccessException
    {
        return new AccessException((string) trans('access::guardrail.'.$key), 'guardrail.'.$key, 403);
    }
}
