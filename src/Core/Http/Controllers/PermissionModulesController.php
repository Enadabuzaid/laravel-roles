<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Controllers;

use Enadstack\LaravelRoles\Core\Queries\PermissionsQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PermissionModulesController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(PermissionsQuery::modules()->values()->all());
    }
}
