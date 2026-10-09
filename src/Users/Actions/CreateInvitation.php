<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\RoleChangeSeverity;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Support\InvitationErrors;
use Enadstack\LaravelRoles\Users\Support\InvitationMailer;
use Enadstack\LaravelRoles\Users\Support\InvitationToken;
use Enadstack\LaravelRoles\Users\Support\UserLookup;

/**
 * Invites an email to a role. The role goes through the same guardrails as an assignment (a null-target
 * AssignRole operation: escalation, high-risk confirmation, Owner role needs an Owner actor); nothing is
 * assigned until the invitation is accepted. Only the token hash is stored.
 */
final class CreateInvitation extends Action
{
    public function handle(string $email, Role $role, bool $confirmed = false): AccessInvitation
    {
        app(ActorContext::class)->assertKnownActor();

        $email = UserLookup::normalize($email);
        $token = InvitationToken::make();

        return $this->transaction(function () use ($email, $role, $confirmed, $token): AccessInvitation {
            app(OwnerCounter::class)->lockSuperRole(); // serializes concurrent creates, so one pending invitation per email

            app(Guardrails::class)->assert(new Operation(
                OperationType::AssignRole,
                actor: app(ActorContext::class)->actor(),
                added: [$role],
                confirmed: $confirmed,
            ));

            if (UserLookup::emailTaken($email)) {
                throw InvitationErrors::userExists();
            }

            if (AccessInvitation::query()->pending()->where('email', $email)->lockForUpdate()->exists()) {
                throw InvitationErrors::pendingExists();
            }

            $actor = app(ActorContext::class)->actor();
            $invitation = AccessInvitation::query()->create([
                'email' => $email,
                'role_id' => $role->getKey(),
                'token_hash' => InvitationToken::hash($token),
                'invited_by' => $actor?->getKey(),
                'expires_at' => now()->addHours(InvitationMailer::ttlHours()),
            ]);

            app(AuditRecorder::class)->record(
                'invitations',
                'created',
                $invitation,
                null,
                ['status' => 'pending', 'expires_at' => $invitation->expires_at->toIso8601String()],
                ['email' => $email, 'role' => $role->name],
                RoleChangeSeverity::for([$role], []),
            );

            InvitationMailer::afterCommit($invitation, $token, $role->name);

            return $invitation;
        });
    }
}
