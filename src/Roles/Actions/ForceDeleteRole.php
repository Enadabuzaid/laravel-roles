<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class ForceDeleteRole extends Action
{
    public function handle(Role $role, ?string $confirmation): void
    {
        app(ActorContext::class)->assertKnownActor();

        $this->transaction(function () use ($role, $confirmation): void {
            $fresh = Role::withTrashed()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            if (! $fresh->trashed()) {
                throw new AccessException('Trash the role before deleting it permanently.', 'role.not_trashed', 409);
            }

            app(Guardrails::class)->assert(new Operation(
                OperationType::ForceDeleteRole,
                actor: app(ActorContext::class)->actor(),
                role: $fresh,
                confirmation: $confirmation,
            ));

            $old = $fresh->attributesToArray();
            $meta = ['id' => $fresh->getKey(), 'name' => $fresh->name];

            $fresh->permissions()->detach();
            DB::table((string) config('permission.table_names.model_has_roles'))
                ->where((string) (config('permission.column_names.role_pivot_key') ?: 'role_id'), $fresh->getKey())
                ->delete();
            $fresh->forceDelete();

            app(AuditRecorder::class)->record('roles', 'deleted', null, $old, null, $meta, Severity::Critical);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
