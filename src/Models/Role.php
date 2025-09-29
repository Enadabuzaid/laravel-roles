<?php

namespace Enadabuzaid\LaravelRoles\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use SoftDeletes;

    protected $casts = [
        'label' => 'array',        // e.g. {"en":"Admin","ar":"مسؤول"}
        'description' => 'array',  // e.g. {"en":"Manage…","ar":"..."}
    ];

    public static function findByName(string $name, $guardName = null): self
    {
        $guardName ??= config('auth.defaults.guard');

        $query = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName);

        // Prefer tenant-specific record if team_scoped is enabled
        if (config('roles.tenancy.mode') === 'team_scoped') {
            $tenantId = app('permission.team_id', null);
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull(config('permission.team_foreign_key', 'team_id'))
                    ->orWhere(config('permission.team_foreign_key', 'team_id'), $tenantId);
            })->orderByRaw('CASE WHEN '.
                config('permission.team_foreign_key', 'team_id').
                ' IS NULL THEN 1 ELSE 0 END'); // prefer tenant-specific
        }

        $role = $query->first();

        if (! $role) {
            throw SpatieRole::getRoleClass()::getRoleNotFoundException($name, $guardName);
        }

        return $role;
    }
}