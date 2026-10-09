<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Controllers;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\AssignRoleToUser;
use Enadstack\LaravelRoles\Roles\Actions\RemoveRoleFromUser;
use Enadstack\LaravelRoles\Roles\Actions\SyncUserRoles;
use Enadstack\LaravelRoles\Roles\Http\Requests\AssignRoleRequest;
use Enadstack\LaravelRoles\Roles\Http\Requests\RemoveRoleRequest;
use Enadstack\LaravelRoles\Roles\Http\Requests\SyncUserRolesRequest;
use Enadstack\LaravelRoles\Roles\Http\Resources\RoleLiteResource;
use Enadstack\LaravelRoles\Roles\Http\Resources\RoleMemberResource;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

final class RoleAssignmentController
{
    public function assign(AssignRoleRequest $request, AssignRoleToUser $action): JsonResponse
    {
        $user = $request->targetUser();
        $action->handle($user, $request->roleToAssign(), $request->confirmed());

        return $this->represent($user);
    }

    /**
     * The unused `$user` keeps the route parameters aligned with the method signature; the
     * target user is resolved by the request.
     */
    public function remove(RemoveRoleRequest $request, string $user, Role $role, RemoveRoleFromUser $action): JsonResponse
    {
        $user = $request->targetUser();
        $action->handle($user, $role);

        return $this->represent($user);
    }

    public function sync(SyncUserRolesRequest $request, SyncUserRoles $action): JsonResponse
    {
        $user = $request->targetUser();
        $action->handle($user, $request->rolesToSync(), $request->confirmed());

        return $this->represent($user);
    }

    private function represent(AccessUser&Model $user): JsonResponse
    {
        $user->unsetRelation('roles');
        $user->load('roles');

        return ApiResponse::success([
            'user' => RoleMemberResource::make($user)->resolve(),
            'roles' => RoleLiteResource::collection($user->getRelation('roles')->sortBy('name')->values())->resolve(),
        ]);
    }
}
