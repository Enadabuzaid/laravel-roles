<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Support\InvitationErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Revokes an invitation: its token stops working. Already revoked is a no-op; an accepted invitation can't be revoked. */
final class RevokeInvitation extends Action
{
    /** @return bool whether anything changed */
    public function handle(AccessInvitation $invitation): bool
    {
        app(ActorContext::class)->assertKnownActor();

        return $this->transaction(function () use ($invitation): bool {
            $locked = AccessInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException)->setModel(AccessInvitation::class, [$invitation->getKey()]);

            if ($locked->accepted_at !== null) {
                throw InvitationErrors::notPending();
            }

            if ($locked->revoked_at !== null) {
                $invitation->setRawAttributes($locked->getAttributes(), true);

                return false;
            }

            $before = $locked->status();
            $locked->update(['revoked_at' => now()]);
            $invitation->setRawAttributes($locked->getAttributes(), true);

            $role = $locked->role_id === null ? null : Role::withTrashed()->find($locked->role_id);

            app(AuditRecorder::class)->record(
                'invitations',
                'revoked',
                $locked,
                ['status' => $before],
                ['status' => 'revoked'],
                ['email' => $locked->email, 'role' => $role?->name],
                Severity::Info,
            );

            return true;
        });
    }
}
