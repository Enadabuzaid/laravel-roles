<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Core\Support\PermissionHolders;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Spatie\Permission\PermissionRegistrar;

final class UpdatePermission extends Action
{
    private const UPDATABLE = ['module', 'label', 'description', 'risk', 'sort_order'];

    /** @param  array<string, mixed>  $data */
    public function handle(Permission $permission, array $data): Permission
    {
        app(ActorContext::class)->assertKnownActor();

        $changed = $this->transaction(function () use ($permission, $data): bool {
            $fresh = Permission::query()->lockForUpdate()->whereKey($permission->getKey())->firstOrFail();

            if ($fresh->source !== Permission::SOURCE_MANUAL) {
                throw new AccessException('Registry permissions are managed by the registry file; edit it and sync.', 'permission.registry_managed', 409);
            }

            $fresh->fill(array_intersect_key($data, array_flip(self::UPDATABLE)));
            $dirty = array_keys($fresh->getDirty());

            $downgrade = $fresh->isDirty('risk') && $fresh->getOriginal('risk') === Permission::RISK_HIGH && $fresh->risk !== Permission::RISK_HIGH;

            if ($downgrade) {
                $holders = PermissionHolders::count($fresh);
                if ($holders > 0) {
                    throw new AccessException(
                        "The permission is held by {$holders} role or user grant(s); revoke it before lowering its risk from high.",
                        'permission.in_use',
                        409,
                        ['holders' => [$holders]],
                    );
                }
            }

            if ($dirty !== []) {
                $old = array_intersect_key($fresh->getOriginal(), array_flip($dirty));
                $fresh->save();
                $new = array_intersect_key($fresh->attributesToArray(), array_flip(array_diff($dirty, ['updated_at'])));
                app(AuditRecorder::class)->record('permissions', 'updated', $fresh, array_intersect_key($old, $new), $new, [], $downgrade ? Severity::Warning : Severity::Info);
            }

            $permission->setRawAttributes($fresh->getAttributes(), true);

            return $dirty !== [];
        });

        if ($changed) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $permission;
    }
}
