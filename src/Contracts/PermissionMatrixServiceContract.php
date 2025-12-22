<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

/**
 * PermissionMatrixServiceContract
 *
 * Defines the interface for building the Role × Permission matrix.
 * Must use efficient queries (no N+1) and contextual caching.
 *
 * @package Enadstack\LaravelRoles\Contracts
 */
interface PermissionMatrixServiceContract
{
    /**
     * Build the complete Role × Permission matrix.
     *
     * The matrix shows which roles have which permissions.
     * Uses maximum 3 queries and is cached with tenant/guard/locale awareness.
     *
     * @return array{
     *     roles: array<array{id: int, name: string, label: string|null}>,
     *     permissions: array<array{id: int, name: string, label: string|null, group: string|null}>,
     *     matrix: array<int, array<int, bool>>
     * }
     */
    public function build(): array;

    /**
     * Get the matrix for a specific guard.
     *
     * @param string $guard
     * @return array
     */
    public function forGuard(string $guard): array;

    /**
     * Get the matrix with permission grouping.
     *
     * @return array{
     *     roles: array,
     *     groups: array<string, array{
     *         label: string|null,
     *         permissions: array
     *     }>,
     *     matrix: array
     * }
     */
    public function buildGrouped(): array;

    /**
     * Get permissions for a specific role in the matrix.
     *
     * @param int $roleId
     * @return array<array{id: int, name: string, has_permission: bool}>
     */
    public function permissionsForRole(int $roleId): array;

    /**
     * Get roles that have a specific permission.
     *
     * @param int $permissionId
     * @return array<array{id: int, name: string}>
     */
    public function rolesWithPermission(int $permissionId): array;

    /**
     * Invalidate the cached matrix.
     *
     * @return void
     */
    public function invalidate(): void;

    /**
     * Get cache statistics.
     *
     * @return array{
     *     cache_enabled: bool,
     *     cache_key: string,
     *     ttl: int,
     *     is_cached: bool
     * }
     */
    public function cacheStats(): array;
}
