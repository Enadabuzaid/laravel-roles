<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

use Enadstack\LaravelRoles\Models\Role;

/**
 * RolePermissionSyncServiceContract
 *
 * Defines the interface for syncing permissions to roles.
 * Supports diff-based sync and wildcard expansion.
 *
 * @package Enadstack\LaravelRoles\Contracts
 */
interface RolePermissionSyncServiceContract
{
    /**
     * Assign permissions to a role (sync).
     *
     * @param Role $role
     * @param array<int|string> $permissionIds Permission IDs or names
     * @return Role
     */
    public function assignPermissions(Role $role, array $permissionIds): Role;

    /**
     * Perform a diff-based sync on role permissions.
     *
     * @param Role $role
     * @param array<string> $grant Permission names to grant
     * @param array<string> $revoke Permission names to revoke
     * @return array{granted: array, revoked: array, skipped: array}
     */
    public function diffSync(Role $role, array $grant = [], array $revoke = []): array;

    /**
     * Expand wildcard permissions.
     *
     * Supports patterns like:
     * - '*' -> all permissions
     * - 'users.*' -> all user permissions
     * - 'users.create' -> specific permission
     *
     * @param array<string> $patterns Permission patterns
     * @param string|null $guard
     * @return array<string> Expanded permission names
     */
    public function expandWildcards(array $patterns, ?string $guard = null): array;

    /**
     * Add a single permission to a role.
     *
     * @param Role $role
     * @param int|string $permission Permission ID or name
     * @return Role
     */
    public function addPermission(Role $role, int|string $permission): Role;

    /**
     * Remove a single permission from a role.
     *
     * @param Role $role
     * @param int|string $permission Permission ID or name
     * @return Role
     */
    public function removePermission(Role $role, int|string $permission): Role;

    /**
     * Sync all roles from config mapping.
     *
     * @param bool $prune Remove permissions not in config
     * @return array{synced: array, errors: array}
     */
    public function syncFromConfig(bool $prune = false): array;

    /**
     * Check if a pattern matches a permission name.
     *
     * @param string $pattern
     * @param string $permissionName
     * @return bool
     */
    public function matchesPattern(string $pattern, string $permissionName): bool;
}
