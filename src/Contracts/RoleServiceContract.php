<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * RoleServiceContract
 *
 * Defines the interface for role management operations.
 * All role access must go through this interface, never directly via Spatie.
 *
 * @package Enadstack\LaravelRoles\Contracts
 */
interface RoleServiceContract
{
    /**
     * Get a paginated list of roles.
     *
     * @param array $filters Filter options (search, guard, status, trash filters, sorting)
     * @param int $perPage Number of items per page
     * @return LengthAwarePaginator
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Find a role by ID.
     *
     * @param int $id
     * @return Role|null
     */
    public function find(int $id): ?Role;

    /**
     * Find a role by name.
     *
     * @param string $name
     * @param string|null $guardName
     * @return Role|null
     */
    public function findByName(string $name, ?string $guardName = null): ?Role;

    /**
     * Create a new role.
     *
     * @param array $data Role data
     * @return Role
     */
    public function create(array $data): Role;

    /**
     * Update an existing role.
     *
     * @param Role $role
     * @param array $data
     * @return Role
     */
    public function update(Role $role, array $data): Role;

    /**
     * Soft delete a role.
     *
     * @param Role $role
     * @return bool
     */
    public function delete(Role $role): bool;

    /**
     * Force delete a role (permanent).
     *
     * @param Role $role
     * @return bool
     */
    public function forceDelete(Role $role): bool;

    /**
     * Restore a soft-deleted role.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Get recently created roles.
     *
     * @param int $limit
     * @return EloquentCollection
     */
    public function recent(int $limit = 10): EloquentCollection;

    /**
     * Get role statistics.
     *
     * @return array
     */
    public function stats(): array;

    /**
     * Change role status.
     *
     * @param Role $role
     * @param RolePermissionStatusEnum $status
     * @return Role
     */
    public function changeStatus(Role $role, RolePermissionStatusEnum $status): Role;

    /**
     * Get a role with its permissions loaded.
     *
     * @param int $id
     * @return Role|null
     */
    public function getRoleWithPermissions(int $id): ?Role;

    /**
     * Get all permissions grouped by role.
     *
     * @return SupportCollection
     */
    public function getPermissionsGroupedByRole(): SupportCollection;

    /**
     * Clone a role with its permissions.
     *
     * @param Role $role
     * @param string $name
     * @param array $attributes
     * @return Role
     */
    public function cloneWithPermissions(Role $role, string $name, array $attributes = []): Role;

    /**
     * Bulk delete roles.
     *
     * @param array $ids
     * @return array{success: array, failed: array}
     */
    public function bulkDelete(array $ids): array;

    /**
     * Bulk restore roles.
     *
     * @param array $ids
     * @return array{success: array, failed: array}
     */
    public function bulkRestore(array $ids): array;

    /**
     * Bulk force delete roles.
     *
     * @param array $ids
     * @return array{success: array, failed: array}
     */
    public function bulkForceDelete(array $ids): array;
}
