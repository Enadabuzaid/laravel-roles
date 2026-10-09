<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Controllers;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\CreateRole;
use Enadstack\LaravelRoles\Roles\Actions\ForceDeleteRole;
use Enadstack\LaravelRoles\Roles\Actions\RestoreRole;
use Enadstack\LaravelRoles\Roles\Actions\TrashRole;
use Enadstack\LaravelRoles\Roles\Actions\UpdateRole;
use Enadstack\LaravelRoles\Roles\Http\Requests\DestroyRoleRequest;
use Enadstack\LaravelRoles\Roles\Http\Requests\RestoreRoleRequest;
use Enadstack\LaravelRoles\Roles\Http\Requests\StoreRoleRequest;
use Enadstack\LaravelRoles\Roles\Http\Requests\UpdateRoleRequest;
use Enadstack\LaravelRoles\Roles\Http\Resources\RoleResource;
use Enadstack\LaravelRoles\Roles\Queries\RolesQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleController
{
    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, RolesQuery::definition());

        return ApiResponse::list($query, RolesQuery::paginate($query), RoleResource::class);
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(RoleResource::make(RolesQuery::detail($role))->resolve());
    }

    public function store(StoreRoleRequest $request, CreateRole $action): JsonResponse
    {
        /** @var array{name: string, label: array<string, string>} $data */
        $data = $request->roleData();

        return ApiResponse::created($this->represent($action->handle($data, $request->permissionReferences(), $request->confirmed())));
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $action): JsonResponse
    {
        return ApiResponse::success($this->represent($action->handle($role, $request->roleData())));
    }

    public function destroy(DestroyRoleRequest $request, Role $role, TrashRole $action): JsonResponse
    {
        return ApiResponse::success($this->represent($action->handle($role, $request->confirmation())));
    }

    public function restore(RestoreRoleRequest $request, Role $role, RestoreRole $action): JsonResponse
    {
        return ApiResponse::success($this->represent($action->handle($role, $request->confirmed())));
    }

    public function force(DestroyRoleRequest $request, Role $role, ForceDeleteRole $action): JsonResponse
    {
        $action->handle($role, $request->confirmation());

        return ApiResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function represent(Role $role): array
    {
        return RoleResource::make(RolesQuery::detail($role))->resolve();
    }
}
