<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Roles\Queries\RolesOverviewQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleOverviewController
{
    use AuthorizesAccess;

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'viewAny', 'role');

        return ApiResponse::success(RolesOverviewQuery::summary());
    }
}
