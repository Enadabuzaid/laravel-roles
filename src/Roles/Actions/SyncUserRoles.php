<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\RoleChangeSeverity;
use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class SyncUserRoles extends Action
{
    /**
     * @param  list<string|Role>  $roles
     */
    public function handle(AccessUser&Model $user, array $roles, bool $confirmed = false): void
    {
        $this->transaction(function () use ($user, $roles, $confirmed): void {
            $guard = (string) config('roles.guard');
            /** @var array<string, Role> $wanted */
            $wanted = [];
            foreach ($roles as $role) {
                /** @var Role $model */
                $model = $role instanceof Role ? $role : Role::findByName($role, $guard);
                $wanted[$model->name] = $model;
            }

            app(OwnerCounter::class)->lockSuperRole();
            // Fresh snapshot: a relation loaded earlier may be stale relative to the locked DB state.
            $user->unsetRelation('roles');
            $before = $user->getRoleNames()->all();
            /** @var array<string, Role> $current */
            $current = Role::query()->where('guard_name', $guard)->whereIn('name', $before)->get()->keyBy('name')->all();

            $added = array_values(array_diff_key($wanted, $current));
            $removed = array_values(array_diff_key($current, $wanted));

            app(Guardrails::class)->assert(new Operation(
                OperationType::SyncRoles,
                actor: app(ActorContext::class)->actor(),
                target: $user,
                added: $added,
                removed: $removed,
                confirmed: $confirmed,
            ));

            $user->syncRoles(array_values($wanted));
            $user->unsetRelation('roles');
            $after = $user->getRoleNames()->all();

            if ($before !== $after) {
                app(AuditRecorder::class)->record(
                    'roles',
                    'synced',
                    $user,
                    ['roles' => $before],
                    ['roles' => $after],
                    ['added' => array_map(fn (Role $r): string => $r->name, $added), 'removed' => array_map(fn (Role $r): string => $r->name, $removed)],
                    RoleChangeSeverity::for($added, $removed),
                );
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
