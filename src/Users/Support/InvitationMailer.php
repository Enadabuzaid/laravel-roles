<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Notifications\AccessInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use SensitiveParameter;
use Throwable;

/**
 * Sends the invitation mail once the surrounding transaction has committed, so a rolled-back write never
 * mails a dead token. A delivery failure is reported, never thrown: the invitation stays and `mailSent`
 * on the model stays false for the response to flag.
 */
final class InvitationMailer
{
    public static function afterCommit(AccessInvitation $invitation, #[SensitiveParameter] string $token, ?string $roleName): void
    {
        $email = $invitation->email;
        $hours = (int) config('roles.users.invitation_ttl_hours', 72);

        DB::afterCommit(static function () use ($invitation, $token, $email, $roleName, $hours): void {
            try {
                Notification::route('mail', $email)->notify(new AccessInvitationNotification($token, $email, $roleName, $hours));
                $invitation->mailSent = true;
            } catch (Throwable $e) {
                $invitation->mailSent = false;
                report($e);
            }
        });
    }

    public static function ttlHours(): int
    {
        return max(1, (int) config('roles.users.invitation_ttl_hours', 72));
    }
}
