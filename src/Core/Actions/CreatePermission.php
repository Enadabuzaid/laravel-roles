<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Spatie\Permission\PermissionRegistrar;

final class CreatePermission extends Action
{
    /**
     * @param  array{name: string, module: string, label: array<string, string>, description?: array<string, string>|null, risk?: string, sort_order?: int}  $data
     */
    public function handle(array $data): Permission
    {
        app(ActorContext::class)->assertKnownActor();

        $permission = $this->transaction(function () use ($data): Permission {
            $permission = new Permission;
            $permission->forceFill([
                'name' => $data['name'],
                'guard_name' => (string) config('roles.guard'),
                'module' => $data['module'],
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'risk' => $data['risk'] ?? Permission::RISK_LOW,
                'sort_order' => $data['sort_order'] ?? ((int) Permission::query()->max('sort_order')) + 1,
                'source' => Permission::SOURCE_MANUAL,
            ]);
            $permission->save();

            app(AuditRecorder::class)->record('permissions', 'created', $permission, null, $permission->attributesToArray());

            return $permission;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }
}
