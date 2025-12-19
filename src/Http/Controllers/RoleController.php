<?php
// src/Http/Controllers/RoleController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Services\RoleService;
use Enadstack\LaravelRoles\Http\Requests\RoleStoreRequest;
use Enadstack\LaravelRoles\Http\Requests\RoleUpdateRequest;
use Enadstack\LaravelRoles\Http\Requests\AssignPermissionsRequest;
use Enadstack\LaravelRoles\Http\Requests\BulkOperationRequest;
use Enadstack\LaravelRoles\Http\Resources\RoleResource;
use Enadstack\LaravelRoles\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    use AuthorizesRequests, ApiResponseTrait;

    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $filters = [
            'search' => $request->query('search'),
            'guard' => $request->query('guard'),
            'sort' => $request->query('sort', 'id'),
            'direction' => $request->query('direction', 'desc'),
            'with_trashed' => $request->boolean('with_trashed'),
            'only_trashed' => $request->boolean('only_trashed'),
        ];
        $perPage = (int) $request->query('per_page', 20);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 20; // Bound per_page

        return $this->paginatedResponse(
            RoleResource::collection($this->roleService->list($filters, $perPage))
        );
    }

    public function store(RoleStoreRequest $request)
    {
        // Authorization handled in RoleStoreRequest
        return $this->createdResponse(
            new RoleResource($this->roleService->create($request->validated())),
            'Role created successfully'
        );
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);
        return $this->resourceResponse(new RoleResource($role));
    }

    public function update(RoleUpdateRequest $request, Role $role)
    {
        // Authorization handled in RoleUpdateRequest
        return $this->resourceResponse(
            new RoleResource($this->roleService->update($role, $request->validated())),
            'Role updated successfully'
        );
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);
        $this->roleService->delete($role);
        return $this->deletedResponse('Role deleted successfully');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorize('restore', Role::class);
        $restored = $this->roleService->restore($id);
        
        if (!$restored) {
            return $this->notFoundResponse('Role not found or not deleted');
        }
        
        return $this->successResponse(null, 'Role restored successfully');
    }

    public function forceDelete(Role $role): JsonResponse
    {
        $this->authorize('forceDelete', $role);
        $this->roleService->forceDelete($role);
        return $this->successResponse(null, 'Role permanently deleted');
    }

    public function bulkDelete(BulkOperationRequest $request): JsonResponse
    {
        // Authorization handled in BulkOperationRequest
        $results = $this->roleService->bulkDelete($request->validated()['ids']);

        return $this->successResponse($results, 'Bulk delete completed');
    }

    public function bulkRestore(BulkOperationRequest $request): JsonResponse
    {
        // Authorization handled in BulkOperationRequest
        $results = $this->roleService->bulkRestore($request->validated()['ids']);

        return $this->successResponse($results, 'Bulk restore completed');
    }

    public function bulkForceDelete(BulkOperationRequest $request): JsonResponse
    {
        // Authorization handled in BulkOperationRequest
        $results = $this->roleService->bulkForceDelete($request->validated()['ids']);

        return $this->successResponse($results, 'Bulk force delete completed');
    }

    public function recent(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10; // Bound limit

        return $this->successResponse(
            RoleResource::collection($this->roleService->recent($limit))
        );
    }

    public function stats(): JsonResponse
    {
        return $this->successResponse($this->roleService->stats());
    }

    public function assignPermissions(AssignPermissionsRequest $request, Role $role): JsonResponse
    {
        // Authorization handled in AssignPermissionsRequest
        $role = $this->roleService->assignPermissions($role, $request->validated()['permission_ids']);

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Permissions assigned successfully'
        );
    }

    public function permissions(int $id)
    {
        $role = $this->roleService->getRoleWithPermissions($id);
        
        if (!$role) {
            return $this->notFoundResponse('Role not found');
        }
        
        $this->authorize('view', $role);

        return $this->successResponse($role->permissions);
    }

    public function permissionsGroupedByRole()
    {
        $this->authorize('viewAny', Role::class);
        return $this->successResponse($this->roleService->getPermissionsGroupedByRole());
    }

    public function addPermission(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $role = $this->roleService->addPermission($role, $data['permission_id']);

        return $this->resourceResponse(
            new RoleResource($role),
            'Permission attached successfully'
        );
    }

    public function removePermission(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $role = $this->roleService->removePermission($role, $data['permission_id']);

        return $this->resourceResponse(
            new RoleResource($role),
            'Permission detached successfully'
        );
    }

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
     * Change role status
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
     * Activate role
     */
    public function activate(Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $this->roleService->activate($role);

        return $this->resourceResponse(
            new RoleResource($role),
            'Role activated successfully'
        );
    }

    /**
     * Deactivate role
     */
    public function deactivate(Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $this->roleService->deactivate($role);

        return $this->resourceResponse(
            new RoleResource($role),
            'Role deactivated successfully'
        );
    }

    /**
     * Bulk change status
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
        $results = $this->roleService->bulkChangeStatus($data['ids'], $status);

        return $this->successResponse($results, 'Bulk status change completed');
    }
}
