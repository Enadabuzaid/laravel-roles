<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

final class Violation
{
    public static function lastOwner(): AccessException
    {
        return self::make('guardrail.last_owner', 'At least one Owner must remain.');
    }

    public static function selfRoleChange(): AccessException
    {
        return self::make('guardrail.self_role_change', "You can't change your own role.");
    }

    public static function selfDestructive(): AccessException
    {
        return self::make('guardrail.self_destructive', "You can't suspend, deactivate or delete yourself.");
    }

    public static function escalation(): AccessException
    {
        return self::make('guardrail.escalation', "You can't grant access higher than your own.");
    }

    public static function highRiskConfirmRequired(): AccessException
    {
        return self::make('guardrail.high_risk_confirm_required', 'This grants high-risk access. Confirm to continue.');
    }

    public static function systemRole(): AccessException
    {
        return self::make('guardrail.system_role', "System roles can't be deleted or renamed.");
    }

    public static function confirmationMismatch(): AccessException
    {
        return self::make('guardrail.confirmation_mismatch', 'Type the exact name to confirm.');
    }

    private static function make(string $code, string $message): AccessException
    {
        return new AccessException($message, $code, 403);
    }
}
