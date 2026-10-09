<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Queries\UsersOverviewQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserOverviewController
{
    use AuthorizesAccess;

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.view');

        return ApiResponse::success(UsersOverviewQuery::summary());
    }
}
