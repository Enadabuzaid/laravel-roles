<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Controllers;

use Enadstack\LaravelRoles\Models\Role;
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
}
