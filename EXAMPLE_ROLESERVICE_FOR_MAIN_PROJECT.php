<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * RoleService - Consume Laravel Roles Package API
 *
 * Place this file in: app/Services/RoleService.php
 *
 * Usage:
 * $roleService = app(\App\Services\RoleService::class);
 * $stats = $roleService->getRoleStats();
 */
class RoleService
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $cacheTime;

    public function __construct()
    {
        // Base URL for the roles API
        $this->baseUrl = rtrim(config('app.url'), '/') . '/admin/acl';
        $this->timeout = config('services.roles_api.timeout', 30);
        $this->cacheTime = config('services.roles_api.cache_time', 300); // 5 minutes
    }

    /**
     * Get role statistics with growth data
     *
     * Returns: { total, active, deleted, with_permissions, without_permissions, growth: {...} }
     */
    public function getRoleStats(bool $fresh = false): array
    {
        $cacheKey = 'role_stats';

        if (!$fresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles-stats");

            if ($response->successful()) {
                $data = $response->json();

                // ✅ IMPORTANT: API returns { "success": true, "data": {...} }
                // Must check success flag and extract data
                if (isset($data['success']) && $data['success'] === true) {
                    $stats = $data['data'] ?? $this->getDefaultStats();
                    Cache::put($cacheKey, $stats, $this->cacheTime);
                    return $stats;
                }

                Log::warning('Role stats API returned unsuccessful response', [
                    'response' => $data,
                ]);

                return $this->getDefaultStats();
            }

            Log::warning('Role stats API returned non-successful HTTP status', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->getDefaultStats();

        } catch (\Exception $e) {
            Log::error('Failed to fetch role stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getDefaultStats();
        }
    }

    /**
     * Get all roles with pagination and filters
     */
    public function getRoles(array $filters = [], int $perPage = 20): array
    {
        try {
            $query = http_build_query(array_merge([
                'per_page' => $perPage,
            ], $filters));

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles?{$query}");

            if ($response->successful()) {
                $data = $response->json();

                // Paginated response structure:
                // { success: true, data: [...], meta: {...}, links: {...} }
                return [
                    'data' => $data['data'] ?? [],
                    'meta' => $data['meta'] ?? [],
                    'links' => $data['links'] ?? [],
                ];
            }

            Log::warning('Get roles API failed', [
                'status' => $response->status(),
            ]);

            return ['data' => [], 'meta' => [], 'links' => []];

        } catch (\Exception $e) {
            Log::error('Failed to fetch roles', [
                'error' => $e->getMessage(),
            ]);

            return ['data' => [], 'meta' => [], 'links' => []];
        }
    }

    /**
     * Get a specific role by ID
     */
    public function getRole(int $roleId): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles/{$roleId}");

            if ($response->successful()) {
                $data = $response->json();

                // Single resource response: { success: true, data: {...} }
                if (isset($data['success']) && $data['success'] === true) {
                    return $data['data'] ?? null;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to fetch role', [
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get recent roles
     */
    public function getRecentRoles(int $limit = 10): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/roles-recent?limit={$limit}");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true) {
                    return $data['data'] ?? [];
                }
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to fetch recent roles', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get permission statistics
     */
    public function getPermissionStats(bool $fresh = false): array
    {
        $cacheKey = 'permission_stats';

        if (!$fresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/permissions-stats");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true) {
                    $stats = $data['data'] ?? $this->getDefaultPermissionStats();
                    Cache::put($cacheKey, $stats, $this->cacheTime);
                    return $stats;
                }
            }

            return $this->getDefaultPermissionStats();

        } catch (\Exception $e) {
            Log::error('Failed to fetch permission stats', [
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultPermissionStats();
        }
    }

    /**
     * Default stats structure when API fails
     */
    protected function getDefaultStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'deleted' => 0,
            'with_permissions' => 0,
            'without_permissions' => 0,
            'growth' => [
                'last_7_days' => $this->getDefaultGrowth(),
                'last_30_days' => $this->getDefaultGrowth(),
                'last_3_months' => $this->getDefaultGrowth(),
                'last_6_months' => $this->getDefaultGrowth(),
                'last_year' => $this->getDefaultGrowth(),
            ],
        ];
    }

    /**
     * Default permission stats structure
     */
    protected function getDefaultPermissionStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'deleted' => 0,
            'assigned' => 0,
            'unassigned' => 0,
            'by_group' => [],
            'growth' => [
                'last_7_days' => $this->getDefaultGrowth(),
                'last_30_days' => $this->getDefaultGrowth(),
                'last_3_months' => $this->getDefaultGrowth(),
                'last_6_months' => $this->getDefaultGrowth(),
                'last_year' => $this->getDefaultGrowth(),
            ],
        ];
    }

    /**
     * Default growth structure
     */
    protected function getDefaultGrowth(): array
    {
        return [
            'current' => 0,
            'previous' => 0,
            'difference' => 0,
            'percentage' => 0,
            'trend' => 'stable',
        ];
    }
}

