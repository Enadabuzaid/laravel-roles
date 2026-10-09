<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Http\Resources\RoleMemberResource;
use Enadstack\LaravelRoles\Roles\Queries\RoleMembersQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleMemberController
{
    use AuthorizesAccess;

    public function __invoke(Request $request, Role $role): JsonResponse
    {
        $this->authorizeAbility($request, 'view', 'role');
        $this->authorizePermission($request, 'access.users.view');

        $query = ListQuery::fromRequest($request, RoleMembersQuery::definition());

        return ApiResponse::list($query, RoleMembersQuery::paginate($role, $query), RoleMemberResource::class);
    }
}
