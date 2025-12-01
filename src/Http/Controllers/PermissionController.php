<?php
// src/Http/Controllers/PermissionController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Services\PermissionService;
use Enadstack\LaravelRoles\Http\Requests\PermissionStoreRequest;
use Enadstack\LaravelRoles\Http\Requests\PermissionUpdateRequest;
use Enadstack\LaravelRoles\Http\Requests\BulkOperationRequest;
use Enadstack\LaravelRoles\Http\Resources\PermissionResource;
use Enadstack\LaravelRoles\Http\Resources\PermissionMatrixResource;
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
            'with_trashed' => $request->boolean('with_trashed'),
            'only_trashed' => $request->boolean('only_trashed'),
        ];
        $perPage = (int) $request->query('per_page', 20);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 20;

        return PermissionResource::collection($this->permissionService->list($filters, $perPage));
    }

    public function store(PermissionStoreRequest $request)
    {
        return new PermissionResource($this->permissionService->create($request->validated()));
    }

    public function show(Permission $permission)
    {
        return new PermissionResource($permission);
    }

    public function update(PermissionUpdateRequest $request, Permission $permission)
    {
        return new PermissionResource($this->permissionService->update($permission, $request->validated()));
    }

    public function destroy(Permission $permission)
    {
        $this->permissionService->delete($permission);
        return response()->json(['message' => 'Permission deleted successfully'], 200);
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

    public function bulkForceDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkForceDelete($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk force delete completed',
            'results' => $results,
        ]);
    }

    public function bulkDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkDelete($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk delete completed',
            'results' => $results,
        ]);
    }

    public function bulkRestore(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkRestore($request->validated()['ids']);

        return response()->json([
            'message' => 'Bulk restore completed',
            'results' => $results,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->permissionService->stats());
    }

    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        return response()->json([
            'data' => PermissionResource::collection($this->permissionService->recent($limit))
        ]);
    }

    public function groups(): JsonResponse
    {
        return response()->json($this->permissionService->getGroupedPermissions());
    }

    public function matrix(): JsonResponse
    {
        return response()->json($this->permissionService->getPermissionMatrix());
    }
}

