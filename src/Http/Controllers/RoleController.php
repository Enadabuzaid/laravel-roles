<?php
// src/Http/Controllers/RoleController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Services\RoleService;
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
        ];
        $perPage = (int) $request->query('per_page', 20);
        
        return $this->roleService->list($filters, $perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'guard_name' => ['nullable','string'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
        ]);
        
        return $this->roleService->create($data);
    }

    public function show(Role $role)
    {
        return $role;
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
        ]);
        
        return $this->roleService->update($role, $data);
    }

    public function destroy(Role $role)
    {
        $this->roleService->delete($role);
        return response()->noContent();
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

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:roles,id'],
        ]);
        
        $results = $this->roleService->bulkDelete($data['ids']);
        
        return response()->json([
            'message' => 'Bulk delete completed',
            'results' => $results
        ]);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ]);
        
        $results = $this->roleService->bulkRestore($data['ids']);
        
        return response()->json([
            'message' => 'Bulk restore completed',
            'results' => $results
        ]);
    }

    public function recent(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        return $this->roleService->recent($limit);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->roleService->stats());
    }

    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['required', 'integer', 'exists:permissions,id'],
        ]);
        
        $role = $this->roleService->assignPermissions($role, $data['permission_ids']);
        
        return response()->json([
            'message' => 'Permissions assigned successfully',
            'role' => $role->load('permissions')
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
}