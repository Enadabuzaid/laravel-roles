<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Contracts\RoleServiceContract;
use Enadstack\LaravelRoles\Contracts\RolePermissionSyncServiceContract;
use Enadstack\LaravelRoles\Http\Requests\RoleStoreRequest;
use Enadstack\LaravelRoles\Http\Requests\RoleUpdateRequest;
use Enadstack\LaravelRoles\Http\Requests\AssignPermissionsRequest;
use Enadstack\LaravelRoles\Http\Requests\BulkOperationRequest;
use Enadstack\LaravelRoles\Http\Resources\RoleResource;
use Enadstack\LaravelRoles\Traits\ApiResponseTrait;
use Enadstack\LaravelRoles\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * RoleController
 *
 * Handles role CRUD operations through the RoleService.
 * Never calls Spatie directly - all access goes through package services.
 *
 * @package Enadstack\LaravelRoles\Http\Controllers
 */
class RoleController extends Controller
{
    use AuthorizesRequests, ApiResponseTrait;

    /**
     * Role service instance.
     *
     * @var RoleServiceContract
     */
    protected RoleServiceContract $roleService;

    /**
     * Role permission sync service instance.
     *
     * @var RolePermissionSyncServiceContract
     */
    protected RolePermissionSyncServiceContract $syncService;

    /**
     * Create a new controller instance.
     *
     * @param RoleServiceContract $roleService
     * @param RolePermissionSyncServiceContract $syncService
     */
    public function __construct(
        RoleServiceContract $roleService,
        RolePermissionSyncServiceContract $syncService
    ) {
        $this->roleService = $roleService;
        $this->syncService = $syncService;
    }

    /**
     * List all roles.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $filters = [
            'search' => $request->query('search'),
            'guard' => $request->query('guard'),
            'status' => $request->query('status'),
            'sort' => $request->query('sort', 'id'),
            'direction' => $request->query('direction', 'desc'),
            'only_deleted' => $request->boolean('only_deleted'),
            'with_deleted' => $request->boolean('with_deleted'),
            // Backward compatibility
            'with_trashed' => $request->boolean('with_trashed'),
            'only_trashed' => $request->boolean('only_trashed'),
        ];
        $perPage = (int) $request->query('per_page', 20);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 20;

        return $this->paginatedResponse(
            RoleResource::collection($this->roleService->list($filters, $perPage))
        );
    }

    /**
     * Store a new role.
     *
     * @param RoleStoreRequest $request
     * @return JsonResponse
     */
    public function store(RoleStoreRequest $request)
    {
        return $this->createdResponse(
            new RoleResource($this->roleService->create($request->validated())),
            'Role created successfully'
        );
    }

    /**
     * Show a specific role.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);
        return $this->resourceResponse(new RoleResource($role));
    }

    /**
     * Update a role.
     *
     * @param RoleUpdateRequest $request
     * @param Role $role
     * @return JsonResponse
     */
    public function update(RoleUpdateRequest $request, Role $role)
    {
        return $this->resourceResponse(
            new RoleResource($this->roleService->update($role, $request->validated())),
            'Role updated successfully'
        );
    }

    /**
     * Delete a role (soft delete).
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);
        $this->roleService->delete($role);
        return $this->deletedResponse('Role deleted successfully');
    }

    /**
     * Restore a soft-deleted role.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorize('restore', Role::class);
        $restored = $this->roleService->restore($id);
        
        if (!$restored) {
            return $this->notFoundResponse('Role not found or not deleted');
        }
        
        return $this->successResponse(null, 'Role restored successfully');
    }

    /**
     * Force delete a role (permanent).
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function forceDelete(Role $role): JsonResponse
    {
        $this->authorize('forceDelete', $role);
        $this->roleService->forceDelete($role);
        return $this->successResponse(null, 'Role permanently deleted');
    }

    /**
     * Bulk delete roles.
     *
     * @param BulkOperationRequest $request
     * @return JsonResponse
     */
    public function bulkDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->roleService->bulkDelete($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk delete completed');
    }

    /**
     * Bulk restore roles.
     *
     * @param BulkOperationRequest $request
     * @return JsonResponse
     */
    public function bulkRestore(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->roleService->bulkRestore($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk restore completed');
    }

    /**
     * Bulk force delete roles.
     *
     * @param BulkOperationRequest $request
     * @return JsonResponse
     */
    public function bulkForceDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->roleService->bulkForceDelete($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk force delete completed');
    }

    /**
     * Get recent roles.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        return $this->successResponse(
            RoleResource::collection($this->roleService->recent($limit))
        );
    }

    /**
     * Get role statistics.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        return $this->successResponse($this->roleService->stats());
    }

    /**
     * Assign permissions to role (sync).
     *
     * @param AssignPermissionsRequest $request
     * @param Role $role
     * @return JsonResponse
     */
    public function assignPermissions(AssignPermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->syncService->assignPermissions($role, $request->validated()['permission_ids']);

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Permissions assigned successfully'
        );
    }

    /**
     * Diff-based permission sync.
     *
     * Accepts grant and revoke arrays for fine-grained permission management.
     * Supports wildcards like 'users.*' or '*'.
     *
     * Payload format:
     * {
     *   "grant": ["roles.list", "users.*"],
     *   "revoke": ["users.delete"]
     * }
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function diffPermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'grant' => ['sometimes', 'array'],
            'grant.*' => ['string'],
            'revoke' => ['sometimes', 'array'],
            'revoke.*' => ['string'],
        ]);

        $result = $this->syncService->diffSync(
            $role,
            $data['grant'] ?? [],
            $data['revoke'] ?? []
        );

        return $this->successResponse([
            'result' => $result,
            'role' => new RoleResource($role->fresh()->load('permissions')),
        ], 'Permission diff applied successfully');
    }

    /**
     * Get role's permissions.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function permissions(int $id)
    {
        $role = $this->roleService->getRoleWithPermissions($id);
        
        if (!$role) {
            return $this->notFoundResponse('Role not found');
        }
        
        $this->authorize('view', $role);

        return $this->successResponse($role->permissions);
    }

    /**
     * Get all permissions grouped by role.
     *
     * @return JsonResponse
     */
    public function permissionsGroupedByRole()
    {
        $this->authorize('viewAny', Role::class);
        return $this->successResponse($this->roleService->getPermissionsGroupedByRole());
    }

    /**
     * Add a single permission to role.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function addPermission(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $role = $this->syncService->addPermission($role, $data['permission_id']);

        return $this->resourceResponse(
            new RoleResource($role),
            'Permission attached successfully'
        );
    }

    /**
     * Remove a single permission from role.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function removePermission(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $role = $this->syncService->removePermission($role, $data['permission_id']);

        return $this->resourceResponse(
            new RoleResource($role),
            'Permission detached successfully'
        );
    }

    /**
     * Clone a role with its permissions.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function clone(Request $request, Role $role): JsonResponse
    {
        $this->authorize('clone', $role);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/', 'unique:roles,name'],
            'label' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'guard_name' => ['nullable', 'string', 'in:web,api,admin'],
        ]);

        $new = $this->roleService->cloneWithPermissions($role, $data['name'], $data);

        return $this->createdResponse(
            new RoleResource($new),
            'Role cloned successfully'
        );
    }

    /**
     * Change role status.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function changeStatus(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,deleted'],
        ]);

        $status = \Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum::from($data['status']);
        $role = $this->roleService->changeStatus($role, $status);

        return $this->resourceResponse(
            new RoleResource($role),
            'Role status updated successfully'
        );
    }

    /**
     * Activate role.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function activate(Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $this->roleService->changeStatus(
            $role,
            \Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum::ACTIVE
        );

        return $this->resourceResponse(
            new RoleResource($role),
            'Role activated successfully'
        );
    }

    /**
     * Deactivate role.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function deactivate(Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $this->roleService->changeStatus(
            $role,
            \Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum::INACTIVE
        );

        return $this->resourceResponse(
            new RoleResource($role),
            'Role deactivated successfully'
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
        $this->authorize('update', Role::class);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:roles,id'],
            'status' => ['required', 'string', 'in:active,inactive,deleted'],
        ]);

        $status = \Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum::from($data['status']);
        
        // Use RoleService for bulk status change
        $service = app(RoleService::class);
        $results = $service->bulkChangeStatus($data['ids'], $status);

        return $this->successResponse($results, 'Bulk status change completed');
    }

    /**
     * Sync permissions by name (replaces all permissions).
     * Used by the Permission Matrix frontend.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function syncPermissionsByName(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role->syncPermissions($data['permissions']);

        return $this->resourceResponse(
            new RoleResource($role->fresh()->load('permissions')),
            'Permissions synced successfully'
        );
    }

    /**
     * Add single permission by name.
     * Used by the Permission Matrix frontend.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function grantPermissionByName(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permission' => ['required', 'string', 'exists:permissions,name'],
        ]);

        $role->givePermissionTo($data['permission']);

        return $this->resourceResponse(
            new RoleResource($role->fresh()->load('permissions')),
            'Permission granted successfully'
        );
    }

    /**
     * Revoke single permission by name.
     * Used by the Permission Matrix frontend.
     *
     * @param Role $role
     * @param string $permission
     * @return JsonResponse
     */
    public function revokePermissionByName(Role $role, string $permission): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $role->revokePermissionTo($permission);

        return $this->resourceResponse(
            new RoleResource($role->fresh()->load('permissions')),
            'Permission revoked successfully'
        );
    }
}
