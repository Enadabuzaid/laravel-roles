<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Spatie\Permission\PermissionRegistrar;

final class UpdateRole extends Action
{
    private const UPDATABLE = ['name', 'label', 'description', 'color', 'is_default', 'sort_order'];

    /** @param  array<string, mixed>  $data */
    public function handle(Role $role, array $data): Role
    {
        app(ActorContext::class)->assertKnownActor();

        $changed = $this->transaction(function () use ($role, $data): bool {
            $fresh = Role::query()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();
            $fresh->fill(array_intersect_key($data, array_flip(self::UPDATABLE)));

            if (! $fresh->isDirty()) {
                $this->adopt($role, $fresh);

                return false;
            }

            if ($fresh->isDirty('name')) {
                app(Guardrails::class)->assert(new Operation(
                    OperationType::RenameRole,
                    actor: app(ActorContext::class)->actor(),
                    role: $fresh->replicate()->setRawAttributes($fresh->getOriginal()),
                    newName: (string) $fresh->name,
                ));
            }

            $dirty = array_keys($fresh->getDirty());
            $old = array_intersect_key($fresh->getOriginal(), array_flip($dirty));
            $fresh->save();

            if ($fresh->is_default && in_array('is_default', $dirty, true)) {
                Role::query()->whereKeyNot($fresh->getKey())->where('is_default', true)->update(['is_default' => false]);
            }

            $new = array_intersect_key($fresh->attributesToArray(), array_flip(array_diff($dirty, ['updated_at'])));
            app(AuditRecorder::class)->record('roles', 'updated', $fresh, array_intersect_key($old, $new), $new);
            $this->adopt($role, $fresh);

            return true;
        });

        if ($changed) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $role;
    }

    private function adopt(Role $target, Role $source): void
    {
        $target->setRawAttributes($source->getAttributes(), true);
    }
}
