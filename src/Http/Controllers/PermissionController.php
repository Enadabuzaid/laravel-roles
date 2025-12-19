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
use Enadstack\LaravelRoles\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    use ApiResponseTrait;

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

        return $this->paginatedResponse(
            PermissionResource::collection($this->permissionService->list($filters, $perPage))
        );
    }

    public function store(PermissionStoreRequest $request)
    {
        return $this->createdResponse(
            new PermissionResource($this->permissionService->create($request->validated())),
            'Permission created successfully'
        );
    }

    public function show(Permission $permission)
    {
        return $this->resourceResponse(new PermissionResource($permission));
    }

    public function update(PermissionUpdateRequest $request, Permission $permission)
    {
        return $this->resourceResponse(
            new PermissionResource($this->permissionService->update($permission, $request->validated())),
            'Permission updated successfully'
        );
    }

    public function destroy(Permission $permission)
    {
        $this->permissionService->delete($permission);
        return $this->deletedResponse('Permission deleted successfully');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $restored = $this->permissionService->restore($id);
        
        if (!$restored) {
            return $this->notFoundResponse('Permission not found or not deleted');
        }
        
        return $this->successResponse(null, 'Permission restored successfully');
    }

    public function forceDelete(Permission $permission): JsonResponse
    {
        $this->permissionService->forceDelete($permission);
        return $this->successResponse(null, 'Permission permanently deleted');
    }

    public function bulkForceDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkForceDelete($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk force delete completed');
    }

    public function bulkDelete(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkDelete($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk delete completed');
    }

    public function bulkRestore(BulkOperationRequest $request): JsonResponse
    {
        $results = $this->permissionService->bulkRestore($request->validated()['ids']);
        return $this->successResponse($results, 'Bulk restore completed');
    }

    public function stats(): JsonResponse
    {
        return $this->successResponse($this->permissionService->stats());
    }

    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        return $this->successResponse(
            PermissionResource::collection($this->permissionService->recent($limit))
        );
    }

    public function groups(): JsonResponse
    {
        return $this->successResponse($this->permissionService->getGroupedPermissions());
    }

    public function matrix(): JsonResponse
    {
        return $this->successResponse($this->permissionService->getPermissionMatrix());
    }
}

