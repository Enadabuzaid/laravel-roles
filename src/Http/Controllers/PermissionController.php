<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Contracts\PermissionServiceContract;
use Enadstack\LaravelRoles\Contracts\PermissionMatrixServiceContract;
use Enadstack\LaravelRoles\Http\Requests\PermissionStoreRequest;
use Enadstack\LaravelRoles\Http\Requests\PermissionUpdateRequest;
use Enadstack\LaravelRoles\Http\Requests\BulkOperationRequest;
use Enadstack\LaravelRoles\Http\Resources\PermissionResource;
use Enadstack\LaravelRoles\Traits\ApiResponseTrait;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * PermissionController
 *
 * Handles permission CRUD operations through the PermissionService.
 * Never calls Spatie directly - all access goes through package services.
 *
 * @package Enadstack\LaravelRoles\Http\Controllers
 */
class PermissionController extends Controller
{
    use AuthorizesRequests, ApiResponseTrait;

    /**
     * Permission service instance.
     *
     * @var PermissionServiceContract
     */
    protected PermissionServiceContract $permissionService;

    /**
     * Permission matrix service instance.
     *
     * @var PermissionMatrixServiceContract
     */
    protected PermissionMatrixServiceContract $matrixService;

    /**
     * Create a new controller instance.
     *
     * @param PermissionServiceContract $permissionService
     * @param PermissionMatrixServiceContract $matrixService
     */
    public function __construct(
        PermissionServiceContract $permissionService,
        PermissionMatrixServiceContract $matrixService
    ) {
        $this->permissionService = $permissionService;
        $this->matrixService = $matrixService;
    }

    /**
     * List all permissions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Permission::class);

        $filters = [
            'search' => $request->query('q', $request->query('search')),
            'group' => $request->query('group'),
            'guard' => $request->query('guard', config('roles.guard', config('auth.defaults.guard'))),
            'status' => $request->query('status'),
            'sort' => in_array($request->query('sort'), ['id','name','group','created_at'], true) ? $request->query('sort') : 'id',
            'direction' => strtolower($request->query('dir', $request->query('direction', 'desc'))) === 'asc' ? 'asc' : 'desc',
            'only_deleted' => $request->boolean('only_deleted'),
            'with_deleted' => $request->boolean('with_deleted'),
            // Backward compatibility
            'with_trashed' => $request->boolean('with_trashed'),
            'only_trashed' => $request->boolean('only_trashed'),
        ];
        $perPage = (int) $request->query('per_page', 20);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 20;

        return $this->paginatedResponse(
            PermissionResource::collection($this->permissionService->list($filters, $perPage))
        );
    }

    /**
     * Store a new permission.
     *
     * @param PermissionStoreRequest $request
     * @return JsonResponse
     */
    public function store(PermissionStoreRequest $request)
    {
        return $this->createdResponse(
            new PermissionResource($this->permissionService->create($request->validated())),
            'Permission created successfully'
        );
    }

    /**
     * Show a specific permission.
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function show(Permission $permission)
    {
        $this->authorize('view', $permission);
        return $this->resourceResponse(new PermissionResource($permission));
    }

    /**
     * Update a permission.
     *
     * @param PermissionUpdateRequest $request
     * @param Permission $permission
     * @return JsonResponse
     */
    public function update(PermissionUpdateRequest $request, Permission $permission)
    {
        return $this->resourceResponse(
            new PermissionResource($this->permissionService->update($permission, $request->validated())),
            'Permission updated successfully'
        );
    }

    /**
     * Delete a permission (soft delete).
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function destroy(Permission $permission)
    {
        $this->authorize('delete', $permission);
        $this->permissionService->delete($permission);
        return $this->deletedResponse('Permission deleted successfully');
    }

    /**
     * Restore a soft-deleted permission.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorize('restore', Permission::class);
        $restored = $this->permissionService->restore($id);
        
        if (!$restored) {
            return $this->notFoundResponse('Permission not found or not deleted');
        }
        
        return $this->successResponse(null, 'Permission restored successfully');
    }

    /**
     * Force delete a permission (permanent).
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function forceDelete(Permission $permission): JsonResponse
    {
        $this->authorize('forceDelete', $permission);
        $this->permissionService->forceDelete($permission);
        return $this->successResponse(null, 'Permission permanently deleted');
    }

    /**
     * Bulk delete permissions.
     *
     * @param BulkOperationRequest $request
     * @return JsonResponse
     */
    public function bulkDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkDelete($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk delete completed');
    }

    /**
     * Bulk restore permissions.
     *
     * @param BulkOperationRequest $request
     * @return JsonResponse
     */
    public function bulkRestore(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkRestore($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk restore completed');
    }

    /**
     * Bulk force delete permissions.
     *
     * @param BulkOperationRequest $request
     * @return JsonResponse
     */
    public function bulkForceDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkForceDelete($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk force delete completed');
    }

    /**
     * Get permission statistics.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        return $this->successResponse($this->permissionService->stats());
    }

    /**
     * Get recent permissions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        return $this->successResponse(
            PermissionResource::collection($this->permissionService->recent($limit))
        );
    }

    /**
     * Get grouped permissions.
     *
     * @return JsonResponse
     */
    public function groups(): JsonResponse
    {
        return $this->successResponse($this->permissionService->getGroupedPermissions());
    }

    /**
     * Get permission matrix.
     *
     * @return JsonResponse
     */
    public function matrix(): JsonResponse
    {
        return $this->successResponse($this->matrixService->build());
    }

    /**
     * Change permission status.
     *
     * @param Request $request
     * @param Permission $permission
     * @return JsonResponse
     */
    public function changeStatus(Request $request, Permission $permission): JsonResponse
    {
        $this->authorize('update', $permission);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,deleted'],
        ]);

        $status = RolePermissionStatusEnum::from($data['status']);
        $permission = $this->permissionService->changeStatus($permission, $status);

        return $this->resourceResponse(
            new PermissionResource($permission),
            'Permission status updated successfully'
        );
    }

    /**
     * Activate permission.
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function activate(Permission $permission): JsonResponse
    {
        $this->authorize('update', $permission);

        $permission = $this->permissionService->changeStatus(
            $permission,
            RolePermissionStatusEnum::ACTIVE
        );

        return $this->resourceResponse(
            new PermissionResource($permission),
            'Permission activated successfully'
        );
    }

    /**
     * Deactivate permission.
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function deactivate(Permission $permission): JsonResponse
    {
        $this->authorize('update', $permission);

        $permission = $this->permissionService->changeStatus(
            $permission,
            RolePermissionStatusEnum::INACTIVE
        );

        return $this->resourceResponse(
            new PermissionResource($permission),
            'Permission deactivated successfully'
        );
    }

    /**
     * Bulk change status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkChangeStatus(Request $request): JsonResponse
    {
        $this->authorize('update', Permission::class);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:permissions,id'],
            'status' => ['required', 'string', 'in:active,inactive,deleted'],
        ]);

        $status = RolePermissionStatusEnum::from($data['status']);
        $results = $this->permissionService->bulkChangeStatus($data['ids'], $status);

        return $this->successResponse($results, 'Bulk status change completed');
    }
}
