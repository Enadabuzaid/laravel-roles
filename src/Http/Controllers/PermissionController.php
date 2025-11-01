<?php
// src/Http/Controllers/PermissionController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->query('q', $request->query('search')),
            'group' => $request->query('group'),
            'guard' => $request->query('guard', config('roles.guard', config('auth.defaults.guard'))),
            'sort' => in_array($request->query('sort'), ['id','name','group','created_at'], true) ? $request->query('sort') : 'id',
            'direction' => strtolower($request->query('dir', $request->query('direction', 'desc'))) === 'asc' ? 'asc' : 'desc',
        ];
        $perPage = (int) $request->query('per_page', 20);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 20;

        return $this->permissionService->list($filters, $perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'guard_name' => ['nullable','string'],
            'group' => ['nullable','string','max:255'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
            'group_label' => ['nullable','array'],
        ]);
        
        return $this->permissionService->create($data);
    }

    public function show(Permission $permission)
    {
        return $permission;
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'group' => ['nullable','string','max:255'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
            'group_label' => ['nullable','array'],
        ]);
        
        return $this->permissionService->update($permission, $data);
    }

    public function destroy(Permission $permission)
    {
        $this->permissionService->delete($permission);
        return response()->noContent();
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $restored = $this->permissionService->restore($id);
        
        if (!$restored) {
            return response()->json(['message' => 'Permission not found or not deleted'], 404);
        }
        
        return response()->json(['message' => 'Permission restored successfully']);
    }

    public function forceDelete(Permission $permission): JsonResponse
    {
        $this->permissionService->forceDelete($permission);
        return response()->json(['message' => 'Permission permanently deleted']);
    }

    public function recent(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        return $this->permissionService->recent($limit);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->permissionService->stats());
    }

    // helpful for UI: return groups with their permissions
    public function groups()
    {
        return $this->permissionService->getGroupedPermissions();
    }

    public function matrix(): JsonResponse
    {
        return response()->json($this->permissionService->getPermissionMatrix());
    }
}