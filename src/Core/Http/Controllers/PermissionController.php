<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Controllers;

use Enadstack\LaravelRoles\Core\Actions\CreatePermission;
use Enadstack\LaravelRoles\Core\Actions\DeletePermission;
use Enadstack\LaravelRoles\Core\Actions\UpdatePermission;
use Enadstack\LaravelRoles\Core\Http\Requests\DestroyPermissionRequest;
use Enadstack\LaravelRoles\Core\Http\Requests\StorePermissionRequest;
use Enadstack\LaravelRoles\Core\Http\Requests\UpdatePermissionRequest;
use Enadstack\LaravelRoles\Core\Http\Resources\PermissionResource;
use Enadstack\LaravelRoles\Core\Queries\PermissionsQuery;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PermissionController
{
    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, PermissionsQuery::definition());

        return ApiResponse::list($query, PermissionsQuery::paginate($query), PermissionResource::class);
    }

    public function show(Permission $permission): JsonResponse
    {
        return ApiResponse::success($this->represent($permission));
    }

    public function store(StorePermissionRequest $request, CreatePermission $action): JsonResponse
    {
        /** @var array{name: string, module: string, label: array<string, string>} $data */
        $data = $request->permissionData();

        return ApiResponse::created($this->represent($action->handle($data)));
    }

    public function update(UpdatePermissionRequest $request, Permission $permission, UpdatePermission $action): JsonResponse
    {
        return ApiResponse::success($this->represent($action->handle($permission, $request->permissionData())));
    }

    public function destroy(DestroyPermissionRequest $request, Permission $permission, DeletePermission $action): JsonResponse
    {
        $action->handle($permission, $request->confirmation());

        return ApiResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function represent(Permission $permission): array
    {
        return PermissionResource::make(PermissionsQuery::detail($permission))->resolve();
    }
}
