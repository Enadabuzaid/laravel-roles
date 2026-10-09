<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Controllers;

use Enadstack\LaravelRoles\Core\Queries\PermissionsOverviewQuery;
use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PermissionOverviewController
{
    use AuthorizesAccess;

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeAbility($request, 'viewAny', 'permission');

        return ApiResponse::success(PermissionsOverviewQuery::summary());
    }
}
