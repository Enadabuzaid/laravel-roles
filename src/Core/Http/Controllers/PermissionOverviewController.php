<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Controllers;

use Enadstack\LaravelRoles\Core\Queries\PermissionsOverviewQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PermissionOverviewController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(PermissionsOverviewQuery::summary());
    }
}
