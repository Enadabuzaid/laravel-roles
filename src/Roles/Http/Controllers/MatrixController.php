<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\UpdateMatrix;
use Enadstack\LaravelRoles\Roles\Actions\UpdateRolePermissions;
use Enadstack\LaravelRoles\Roles\Http\Requests\UpdateMatrixRequest;
use Enadstack\LaravelRoles\Roles\Http\Requests\UpdateRolePermissionsRequest;
use Enadstack\LaravelRoles\Roles\Http\Resources\MatrixResource;
use Enadstack\LaravelRoles\Roles\Http\Resources\RoleResource;
use Enadstack\LaravelRoles\Roles\Queries\MatrixQuery;
use Enadstack\LaravelRoles\Roles\Queries\RolesQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MatrixController
{
    use AuthorizesAccess;

    public function show(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'viewAny', 'permission');

        $matrix = MatrixQuery::get($request->user(), Gate::forUser($request->user())->allows('update', Role::class));

        return ApiResponse::success(MatrixResource::make($matrix)->resolve());
    }

    public function update(UpdateMatrixRequest $request, UpdateMatrix $action): JsonResponse
    {
        $result = $action->handle($request->changes(), $request->confirmed());

        return ApiResponse::success(['roles' => (object) $result['roles'], 'summary' => $result['summary']]);
    }

    public function updateRole(UpdateRolePermissionsRequest $request, Role $role, UpdateRolePermissions $action): JsonResponse
    {
        $result = $action->handle($role, $request->grant(), $request->revoke(), $request->confirmed());

        return ApiResponse::success([
            'role' => RoleResource::make(RolesQuery::detail($role->refresh()))->resolve(),
            'added' => $result['added'],
            'removed' => $result['removed'],
        ]);
    }
}
