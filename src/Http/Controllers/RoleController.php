<?php
// src/Http/Controllers/RoleController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
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
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->query('search'),
            'guard' => $request->query('guard'),
            'sort' => $request->query('sort', 'id'),
            'direction' => $request->query('direction', 'desc'),
            'with_trashed' => $request->boolean('with_trashed'),
            'only_trashed' => $request->boolean('only_trashed'),
        ];
        $perPage = (int) $request->query('per_page', 20);
        
        return RoleResource::collection($this->roleService->list($filters, $perPage));
    }

    public function store(RoleStoreRequest $request)
    {
        return new RoleResource($this->roleService->create($request->validated()));
    }

    public function show(Role $role)
    {
        return new RoleResource($role);
    }

    public function update(RoleUpdateRequest $request, Role $role)
    {
        return new RoleResource($this->roleService->update($role, $request->validated()));
    }

    public function destroy(Role $role)
    {
        $this->roleService->delete($role);
        return response()->json(['message' => 'Role deleted successfully'], 200);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $restored = $this->roleService->restore($id);
        
        if (!$restored) {
            return response()->json(['message' => 'Role not found or not deleted'], 404);
        }
        
        return response()->json(['message' => 'Role restored successfully']);
    }

    public function forceDelete(Role $role): JsonResponse
    {
        $this->roleService->forceDelete($role);
        return response()->json(['message' => 'Role permanently deleted']);
    }

    public function bulkDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->roleService->bulkDelete($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk delete completed',
            'results' => $results
        ]);
    }

    public function bulkRestore(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->roleService->bulkRestore($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk restore completed',
            'results' => $results
        ]);
    }

    public function bulkForceDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->roleService->bulkForceDelete($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk force delete completed',
            'results' => $results,
        ]);
    }

    public function recent(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        return RoleResource::collection($this->roleService->recent($limit));
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->roleService->stats());
    }

    public function assignPermissions(AssignPermissionsRequest $request, Role $role): JsonResponse
    {
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
        
        return response()->json($role->permissions);
    }

    public function permissionsGroupedByRole()
    {
        return response()->json($this->roleService->getPermissionsGroupedByRole());
    }

    public function addPermission(Request $request, Role $role): JsonResponse
    {
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'guard_name' => ['nullable', 'string'],
        ]);

        $new = $this->roleService->cloneWithPermissions($role, $data['name'], $data);

        return response()->json([
            'message' => 'Role cloned successfully',
            'role' => new RoleResource($new),
        ], 201);
    }
}
