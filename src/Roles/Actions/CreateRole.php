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

            if ($role->is_default) {
                Role::query()->whereKeyNot($role->getKey())->where('is_default', true)->update(['is_default' => false]);
            }

            app(AuditRecorder::class)->record('roles', 'created', $role, null, $role->attributesToArray());

            return $role;
        });
    }
}
