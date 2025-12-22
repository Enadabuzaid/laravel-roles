<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

/**
 * PermissionServiceContract
 *
 * Defines the interface for permission management operations.
 * All permission access must go through this interface, never directly via Spatie.
 *
 * @package Enadstack\LaravelRoles\Contracts
 */
interface PermissionServiceContract
{
    /**
     * Get a paginated list of permissions.
     *
     * @param array $filters Filter options
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Find a permission by ID.
     *
     * @param int $id
     * @return Permission|null
     */
    public function find(int $id): ?Permission;

    /**
     * Find a permission by name.
     *
     * @param string $name
     * @param string|null $guardName
     * @return Permission|null
     */
    public function findByName(string $name, ?string $guardName = null): ?Permission;

    /**
     * Create a new permission.
     *
     * @param array $data
     * @return Permission
     */
    public function create(array $data): Permission;

    /**
     * Update an existing permission.
     *
     * @param Permission $permission
     * @param array $data
     * @return Permission
     */
    public function update(Permission $permission, array $data): Permission;

    /**
     * Soft delete a permission.
     *
     * @param Permission $permission
     * @return bool
     */
    public function delete(Permission $permission): bool;

    /**
     * Force delete a permission.
     *
     * @param Permission $permission
     * @return bool
     */
    public function forceDelete(Permission $permission): bool;

    /**
     * Restore a soft-deleted permission.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Get recently created permissions.
     *
     * @param int $limit
     * @return Collection
     */
    public function recent(int $limit = 10): Collection;

    /**
     * Get permission statistics.
     *
     * @return array
     */
    public function stats(): array;

    /**
     * Get permissions grouped by group (cached).
     *
     * @return SupportCollection
     */
    public function getGroupedPermissions(): SupportCollection;

    /**
     * Get the permission matrix (roles x permissions).
     *
     * @return array
     */
    public function getPermissionMatrix(): array;

    /**
     * Change permission status.
     *
     * @param Permission $permission
     * @param RolePermissionStatusEnum $status
     * @return Permission
     */
    public function changeStatus(Permission $permission, RolePermissionStatusEnum $status): Permission;

    /**
     * Resolve permission label for the given locale.
     *
     * @param Permission $permission
     * @param string|null $locale
     * @return string|null
     */
    public function resolveLabel(Permission $permission, ?string $locale = null): ?string;

    /**
     * Resolve permission description for the given locale.
     *
     * @param Permission $permission
     * @param string|null $locale
     * @return string|null
     */
    public function resolveDescription(Permission $permission, ?string $locale = null): ?string;

    /**
     * Bulk delete permissions.
     *
     * @param array $ids
     * @return array{success: array, failed: array}
     */
    public function bulkDelete(array $ids): array;

    /**
     * Bulk restore permissions.
     *
     * @param array $ids
     * @return array{success: array, failed: array}
     */
    public function bulkRestore(array $ids): array;

    /**
     * Bulk force delete permissions.
     *
     * @param array $ids
     * @return array{success: array, failed: array}
     */
    public function bulkForceDelete(array $ids): array;
}
