<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Enadstack\LaravelRoles\Contracts\PermissionMatrixServiceContract;
use Enadstack\LaravelRoles\Traits\ApiResponseTrait;

/**
 * PermissionMatrixController
 *
 * Handles permission matrix API endpoints.
 * Uses PermissionMatrixService for efficient, cached matrix building.
 *
 * @package Enadstack\LaravelRoles\Http\Controllers
 */
class PermissionMatrixController extends Controller
{
    use AuthorizesRequests, ApiResponseTrait;

    /**
     * Matrix service instance.
     *
     * @var PermissionMatrixServiceContract
     */
    protected PermissionMatrixServiceContract $matrixService;

    /**
     * Create a new controller instance.
     *
     * @param PermissionMatrixServiceContract $matrixService
     */
    public function __construct(PermissionMatrixServiceContract $matrixService)
    {
        $this->matrixService = $matrixService;
    }

    /**
     * Get the permission matrix.
     *
     * Returns the Role × Permission matrix with efficient caching.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $guard = $request->query('guard');

        $matrix = $guard
            ? $this->matrixService->forGuard($guard)
            : $this->matrixService->build();

        return $this->successResponse($matrix);
    }

    /**
     * Get the grouped permission matrix.
     *
     * Returns the matrix grouped by permission group.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function grouped(Request $request): JsonResponse
    {
        $matrix = $this->matrixService->buildGrouped();

        return $this->successResponse($matrix);
    }

    /**
     * Get permissions for a specific role.
     *
     * @param Request $request
     * @param int $roleId
     * @return JsonResponse
     */
    public function permissionsForRole(Request $request, int $roleId): JsonResponse
    {
        $permissions = $this->matrixService->permissionsForRole($roleId);

        return $this->successResponse($permissions);
    }

    /**
     * Get roles with a specific permission.
     *
     * @param Request $request
     * @param int $permissionId
     * @return JsonResponse
     */
    public function rolesWithPermission(Request $request, int $permissionId): JsonResponse
    {
        $roles = $this->matrixService->rolesWithPermission($permissionId);

        return $this->successResponse($roles);
    }

    /**
     * Get cache statistics.
     *
     * @return JsonResponse
     */
    public function cacheStats(): JsonResponse
    {
        $stats = $this->matrixService->cacheStats();

        return $this->successResponse($stats);
    }

    /**
     * Invalidate the matrix cache.
     *
     * @return JsonResponse
     */
    public function invalidate(): JsonResponse
    {
        $this->matrixService->invalidate();

        return $this->successResponse(null, 'Matrix cache invalidated');
    }
}
