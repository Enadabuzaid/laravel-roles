<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

final class CreateRole extends Action
{
    /**
     * @param  array{name: string, label: array<string, string>, description?: array<string, string>|null, color?: string|null, is_default?: bool, sort_order?: int}  $data
     */
    public function handle(array $data): Role
    {
        app(ActorContext::class)->assertKnownActor();

        return $this->transaction(function () use ($data): Role {
            $guard = (string) config('roles.guard');

            if (Role::onlyTrashed()->where('name', $data['name'])->where('guard_name', $guard)->exists()) {
                throw new AccessException(
                    'A trashed role already uses this name; restore it or pick another name.',
                    'role.name_taken_trashed',
                    409,
                    ['name' => ['Restore the trashed role or choose a different name.']],
                );
            }

            if (($data['is_default'] ?? false) && $data['name'] === (string) config('roles.super_role')) {
                throw new AccessException("System roles can't be the default role.", 'role.default_not_allowed', 422, ['is_default' => ["System roles can't be the default role."]]);
            }

            $role = new Role;
            $role->forceFill([
                'name' => $data['name'],
                'guard_name' => $guard,
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? null,
                'is_system' => false,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'sort_order' => $data['sort_order'] ?? ((int) Role::withTrashed()->max('sort_order')) + 1,
            ]);
            $role->save();

            $meta = [];
            if ($role->is_default) {
                $previous = Role::query()->lockForUpdate()->whereKeyNot($role->getKey())->where('is_default', true)->get(['id', 'name']);
                if ($previous->isNotEmpty()) {
                    Role::query()->whereKey($previous->modelKeys())->update(['is_default' => false]);
                    $meta['default_cleared_from'] = $previous->map(fn (Role $other): array => ['id' => $other->getKey(), 'name' => $other->name])->all();
                }
            }

            app(AuditRecorder::class)->record('roles', 'created', $role, null, $role->attributesToArray(), $meta);

            return $role;
        });
    }
}
