<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Rotates the token (the old one dies) and restarts the expiry of an invitation that is not accepted or
 * revoked. Lock order: the super role, then the invitation row (the same as acceptance).
 */
final class ResendInvitation extends Action
{
    public function handle(AccessInvitation $invitation, bool $confirmed = false): AccessInvitation
    {
        app(ActorContext::class)->assertKnownActor();
        $token = InvitationToken::make();

        return $this->transaction(function () use ($invitation, $confirmed, $token): AccessInvitation {
            app(OwnerCounter::class)->lockSuperRole(); // global lock order: the super role first

            $locked = AccessInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException)->setModel(AccessInvitation::class, [$invitation->getKey()]);

            if ($locked->accepted_at !== null || $locked->revoked_at !== null) {
                throw InvitationErrors::notPending();
            }

            $role = $locked->role_id === null ? null : Role::query()->where('guard_name', (string) config('roles.guard'))->find($locked->role_id);

            if ($role !== null) {
                app(Guardrails::class)->assert(new Operation(
                    OperationType::AssignRole,
                    actor: app(ActorContext::class)->actor(),
                    added: [$role],
                    confirmed: $confirmed,
                ));
            }

            if (UserLookup::emailTaken($locked->email)) {
                throw InvitationErrors::userExists();
            }

            if (AccessInvitation::query()->pending()->where('email', $locked->email)->whereKeyNot($locked->getKey())->lockForUpdate()->exists()) {
                throw InvitationErrors::pendingExists();
            }

            $locked->update([
                'token_hash' => InvitationToken::hash($token),
                'expires_at' => now()->addHours(InvitationMailer::ttlHours()),
            ]);

            app(AuditRecorder::class)->record(
                'invitations',
                'resent',
                $locked,
                null,
                ['status' => 'pending', 'expires_at' => $locked->expires_at->toIso8601String()],
                ['email' => $locked->email, 'role' => $role?->name],
                Severity::Info,
            );

            InvitationMailer::afterCommit($locked, $token, $role?->name);

            return $locked;
        });
    }
}
