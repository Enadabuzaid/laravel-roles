<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Core\Support\PermissionHolders;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Spatie\Permission\PermissionRegistrar;

final class DeletePermission extends Action
{
    public function handle(Permission $permission, ?string $confirmation): void
    {
        app(ActorContext::class)->assertKnownActor();

        $this->transaction(function () use ($permission, $confirmation): void {
            $fresh = Permission::query()->lockForUpdate()->whereKey($permission->getKey())->firstOrFail();

            if ($fresh->source !== Permission::SOURCE_MANUAL) {
                throw new AccessException('Registry permissions cannot be deleted here; remove it from the registry and sync with prune.', 'permission.registry_managed', 409);
            }

            $holders = PermissionHolders::count($fresh);
            if ($holders > 0) {
                throw new AccessException(
                    "The permission is held by {$holders} role or user grant(s); revoke it first.",
                    'permission.in_use',
                    409,
                    ['holders' => [$holders]],
                );
            }

            app(Guardrails::class)->assert(new Operation(
                OperationType::DeletePermission,
                actor: app(ActorContext::class)->actor(),
                target: $fresh,
                confirmation: $confirmation,
            ));

            $old = $fresh->attributesToArray();
            $fresh->delete();

            app(AuditRecorder::class)->record('permissions', 'deleted', null, $old, null, ['id' => $fresh->getKey(), 'name' => $fresh->name], Severity::Warning);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
