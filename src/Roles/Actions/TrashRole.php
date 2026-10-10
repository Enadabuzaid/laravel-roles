<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Support\InvitationSweeper;
use Spatie\Permission\PermissionRegistrar;

final class TrashRole extends Action
{
    public function handle(Role $role, ?string $confirmation = null): Role
    {
        app(ActorContext::class)->assertKnownActor();

        $this->transaction(function () use ($role, $confirmation): void {
            app(OwnerCounter::class)->lockSuperRole(); // documented lock order: the super role first
            $fresh = Role::query()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            app(Guardrails::class)->assert(new Operation(
                OperationType::DeleteRole,
                actor: app(ActorContext::class)->actor(),
                role: $fresh,
                confirmation: $confirmation,
            ));

            $members = $fresh->users()->count();
            $inviters = $members > 0 ? app(InvitationSweeper::class)->invitersHolding($fresh) : [];
            $fresh->delete();
            app(InvitationSweeper::class)->revokeUnauthorizedFor($inviters);
            app(InvitationSweeper::class)->revokeForRole($fresh);

            app(AuditRecorder::class)->record(
                'roles',
                'trashed',
                $fresh,
                null,
                null,
                ['members_count' => $members],
                $members > 0 ? Severity::Warning : Severity::Info,
            );
            $role->setRawAttributes($fresh->getAttributes(), true);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
