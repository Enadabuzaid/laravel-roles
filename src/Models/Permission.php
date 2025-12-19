<?php

namespace Enadstack\LaravelRoles\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Illuminate\Support\Facades\Cache;
use Enadstack\LaravelRoles\Traits\HasTenantScope;

class Permission extends SpatiePermission
{
    use SoftDeletes;
    use HasTenantScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'status',
        'label',
        'description',
        'group',
        'group_label',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = parent::casts();

        if (config('roles.i18n.enabled', false)) {
            $casts['label'] = 'array';
            $casts['description'] = 'array';
            $casts['group_label'] = 'array';
        }

        return $casts;
    }

    protected static function booted(): void
    {
        $flush = function () {
            $store = Cache::getStore();
            if (method_exists($store, 'tags')) {
                Cache::tags(['laravel_roles'])->flush();
            } else {
                Cache::forget(config('roles.cache.keys.grouped_permissions', 'laravel_roles.grouped_permissions'));
                Cache::forget(config('roles.cache.keys.permission_matrix', 'laravel_roles.permission_matrix'));
            }
        };

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    public static function findByName(string $name, $guardName = null): self
    {
        $guardName ??= config('auth.defaults.guard');

        $query = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName);

        if (config('roles.tenancy.mode') === 'team_scoped') {
            $tenantId = app()->bound('permission.team_id') ? app('permission.team_id') : null;
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull(config('permission.team_foreign_key', 'team_id'))
                    ->orWhere(config('permission.team_foreign_key', 'team_id'), $tenantId);
            })->orderByRaw('CASE WHEN '.
                config('permission.team_foreign_key', 'team_id').
                ' IS NULL THEN 1 ELSE 0 END');
        }

        $perm = $query->first();

        if (! $perm) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $perm;
    }
}