<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Controllers;

use Enadstack\LaravelRoles\Roles\Queries\RolesOverviewQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RoleOverviewController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(RolesOverviewQuery::summary());
    }
}
