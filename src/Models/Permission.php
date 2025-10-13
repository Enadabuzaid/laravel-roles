<?php

namespace Enadstack\LaravelRoles\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (config('roles.i18n.enabled')) {
            $this->casts = [
                'label'        => 'array',
                'description'  => 'array',
                'group_label'  => 'array',
            ];
        } else {
            $this->casts = [
                'description'  => 'string',
            ];
        }
    }

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