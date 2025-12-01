<?php

namespace Enadstack\LaravelRoles\Listeners;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Cache;

class ClearPermissionCache
{
    public function handle($event): void
    {
        // Clear Spatie's permission cache
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore if permission registrar not bound
        }

        // Clear package cache tags if supported
        if (config('roles.cache.enabled', true)) {
            $tenant = app()->bound('permission.team_id') ? app('permission.team_id') : null;

            try {
                if (Cache::supportsTags()) {
                    $tags = ['laravel_roles'];
                    if ($tenant) {
                        $tags[] = "tenant:{$tenant}";
                    }
                    Cache::tags($tags)->flush();
                } else {
                    // Fallback: forget a few known keys
                    $keys = array_values(config('roles.cache.keys', []));
                    foreach ($keys as $key) {
                        if ($tenant) {
                            Cache::forget("{$key}:tenant:{$tenant}");
                        }

                        Cache::forget($key);
                    }
                }
            } catch (\Throwable $e) {
                // don't let cache issues break app
            }
        }
    }
}

