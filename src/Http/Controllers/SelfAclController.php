<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Traits\ApiResponseTrait;

/**
 * SelfAclController
 *
 * Handles endpoints for the current authenticated user's ACL information.
 * Returns roles, permissions, and tenant scope info.
 *
 * @package Enadstack\LaravelRoles\Http\Controllers
 */
class SelfAclController extends Controller
{
    use ApiResponseTrait;

    /**
     * Tenant context instance.
     *
     * @var TenantContextContract
     */
    protected TenantContextContract $tenantContext;

    /**
     * Guard resolver instance.
     *
     * @var GuardResolverContract
     */
    protected GuardResolverContract $guardResolver;

    /**
     * Create a new controller instance.
     *
     * @param TenantContextContract $tenantContext
     * @param GuardResolverContract $guardResolver
     */
    public function __construct(
        TenantContextContract $tenantContext,
        GuardResolverContract $guardResolver
    ) {
        $this->tenantContext = $tenantContext;
        $this->guardResolver = $guardResolver;
    }

    /**
     * Get the current user's complete ACL information.
     *
     * Returns:
     * - roles: Array of role names and IDs
     * - permissions: Array of permission names and IDs
     * - tenant: Tenant scope information
     * - guard: Current guard name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function acl(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $guard = $this->guardResolver->guard();

        // Get roles and permissions using Spatie's methods
        $roles = $this->getUserRoles($user, $guard);
        $permissions = $this->getUserPermissions($user, $guard);

        return $this->successResponse([
            'roles' => $roles,
            'permissions' => $permissions,
            'all_permissions' => $this->getAllPermissionsFlat($user, $guard),
            'tenant' => [
                'mode' => $this->tenantContext->mode(),
                'tenant_id' => $this->tenantContext->tenantId(),
                'scope_key' => $this->tenantContext->scopeKey(),
            ],
            'guard' => $guard,
        ]);
    }

    /**
     * Get the current user's roles.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function roles(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $guard = $this->guardResolver->guard();
        $roles = $this->getUserRoles($user, $guard);

        return $this->successResponse($roles);
    }

    /**
     * Get the current user's permissions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $guard = $this->guardResolver->guard();
        $permissions = $this->getUserPermissions($user, $guard);

        return $this->successResponse($permissions);
    }

    /**
     * Get the current user's abilities (all permissions as flat array of names).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function abilities(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $guard = $this->guardResolver->guard();
        $abilities = $this->getAllPermissionsFlat($user, $guard);

        return $this->successResponse($abilities);
    }

    /**
     * Get user roles.
     *
     * @param mixed $user
     * @param string $guard
     * @return array
     */
    protected function getUserRoles(mixed $user, string $guard): array
    {
        if (!method_exists($user, 'roles')) {
            return [];
        }

        return $user->roles
            ->where('guard_name', $guard)
            ->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->resolveLabel($role->label),
                'description' => $this->resolveLabel($role->description),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get user direct permissions.
     *
     * @param mixed $user
     * @param string $guard
     * @return array
     */
    protected function getUserPermissions(mixed $user, string $guard): array
    {
        if (!method_exists($user, 'permissions')) {
            return [];
        }

        // Direct permissions
        $directPermissions = $user->permissions
            ->where('guard_name', $guard)
            ->map(fn($perm) => [
                'id' => $perm->id,
                'name' => $perm->name,
                'label' => $this->resolveLabel($perm->label),
                'group' => $perm->group ?? null,
                'via' => 'direct',
            ])
            ->values();

        // Role permissions
        $rolePermissions = collect();
        if (method_exists($user, 'roles')) {
            foreach ($user->roles->where('guard_name', $guard) as $role) {
                foreach ($role->permissions as $perm) {
                    if ($perm->guard_name === $guard) {
                        $rolePermissions->push([
                            'id' => $perm->id,
                            'name' => $perm->name,
                            'label' => $this->resolveLabel($perm->label),
                            'group' => $perm->group ?? null,
                            'via' => 'role:' . $role->name,
                        ]);
                    }
                }
            }
        }

        return $directPermissions
            ->merge($rolePermissions)
            ->unique('id')
            ->values()
            ->toArray();
    }

    /**
     * Get all permissions as flat array of names.
     *
     * @param mixed $user
     * @param string $guard
     * @return array
     */
    protected function getAllPermissionsFlat(mixed $user, string $guard): array
    {
        if (!method_exists($user, 'getAllPermissions')) {
            return [];
        }

        return $user->getAllPermissions()
            ->where('guard_name', $guard)
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Resolve label (handles i18n arrays).
     *
     * @param mixed $label
     * @return string|null
     */
    protected function resolveLabel(mixed $label): ?string
    {
        if ($label === null) {
            return null;
        }

        if (is_array($label)) {
            $locale = app()->getLocale();
            $fallback = config('roles.i18n.fallback', 'en');

            return $label[$locale] ?? $label[$fallback] ?? reset($label) ?? null;
        }

        return (string) $label;
    }
}
