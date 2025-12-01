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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    use AuthorizesRequests;

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

        return RoleResource::collection($this->roleService->list($filters, $perPage));
    }

    public function store(RoleStoreRequest $request)
    {
        // Authorization handled in RoleStoreRequest
        return new RoleResource($this->roleService->create($request->validated()));
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);
        return new RoleResource($role);
    }

    public function update(RoleUpdateRequest $request, Role $role)
    {
        // Authorization handled in RoleUpdateRequest
        return new RoleResource($this->roleService->update($role, $request->validated()));
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);
        $this->roleService->delete($role);
        return response()->json(['message' => 'Role deleted successfully'], 200);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorize('restore', Role::class);
        $restored = $this->roleService->restore($id);
        
        if (!$restored) {
            return response()->json(['message' => 'Role not found or not deleted'], 404);
        }
        
        return response()->json(['message' => 'Role restored successfully']);
    }

    public function forceDelete(Role $role): JsonResponse
    {
        $this->authorize('forceDelete', $role);
        $this->roleService->forceDelete($role);
        return response()->json(['message' => 'Role permanently deleted']);
    }

    public function bulkDelete(BulkOperationRequest $request): JsonResponse
    {
        // Authorization handled in BulkOperationRequest
        $results = $this->roleService->bulkDelete($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk delete completed',
            'results' => $results
        ]);
    }

    public function bulkRestore(BulkOperationRequest $request): JsonResponse
    {
        // Authorization handled in BulkOperationRequest
        $results = $this->roleService->bulkRestore($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk restore completed',
            'results' => $results
        ]);
    }

    public function bulkForceDelete(BulkOperationRequest $request): JsonResponse
    {
        // Authorization handled in BulkOperationRequest
        $results = $this->roleService->bulkForceDelete($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk force delete completed',
            'results' => $results,
        ]);
    }

    public function recent(Request $request)
    {
        $this->authorize('viewAny', Role::class);
        $limit = (int) $request->query('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10; // Bound limit
        return RoleResource::collection($this->roleService->recent($limit));
    }

    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);
        return response()->json($this->roleService->stats());
    }

    public function assignPermissions(AssignPermissionsRequest $request, Role $role): JsonResponse
    {
        // Authorization handled in AssignPermissionsRequest
        $role = $this->roleService->assignPermissions($role, $request->validated()['permission_ids']);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'role' => new RoleResource($role->load('permissions'))
        ]);
    }

    public function permissions(int $id)
    {
        $role = $this->roleService->getRoleWithPermissions($id);
        
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }
        
        $this->authorize('view', $role);

        return response()->json($role->permissions);
    }

    public function permissionsGroupedByRole()
    {
        $this->authorize('viewAny', Role::class);
        return response()->json($this->roleService->getPermissionsGroupedByRole());
    }

    public function addPermission(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $role = $this->roleService->addPermission($role, $data['permission_id']);

        return response()->json([
            'message' => 'Permission attached successfully',
            'role' => new RoleResource($role),
        ]);
    }

    public function removePermission(Request $request, Role $role): JsonResponse
    {
        $this->authorize('assignPermissions', $role);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $role = $this->roleService->removePermission($role, $data['permission_id']);

        return response()->json([
            'message' => 'Permission detached successfully',
            'role' => new RoleResource($role),
        ]);
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

        return response()->json([
            'message' => 'Role cloned successfully',
            'role' => new RoleResource($new),
        ], 201);
    }
}
