<?php

namespace Enadstack\LaravelRoles\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use SoftDeletes;

    protected $casts = [
        'label'        => 'array', // {"en":"Create user","ar":"إضافة مستخدم"}
        'description'  => 'array',
        'group_label'  => 'array', // {"en":"Users","ar":"المستخدمون"}
    ];

    public static function findByName(string $name, $guardName = null): self
    {
        $guardName ??= config('auth.defaults.guard');

        $query = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName);

        if (config('roles.tenancy.mode') === 'team_scoped') {
            $tenantId = app('permission.team_id', null);
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull(config('permission.team_foreign_key', 'team_id'))
                    ->orWhere(config('permission.team_foreign_key', 'team_id'), $tenantId);
            })->orderByRaw('CASE WHEN '.
                config('permission.team_foreign_key', 'team_id').
                ' IS NULL THEN 1 ELSE 0 END');
        }

        $perm = $query->first();

        if (! $perm) {
            throw SpatiePermission::getPermissionNotFoundException($name, $guardName);
        }

        return $perm;
    }
}