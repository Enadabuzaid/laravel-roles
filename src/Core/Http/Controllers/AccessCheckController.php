<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Controllers;

use Enadstack\LaravelRoles\Core\Http\Requests\CheckAccessRequest;
use Enadstack\LaravelRoles\Core\Queries\AccessCheckQuery;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AccessCheckController
{
    public function __invoke(CheckAccessRequest $request): JsonResponse
    {
        return ApiResponse::success(AccessCheckQuery::check($request->userId(), $request->permissionName()));
    }
}
