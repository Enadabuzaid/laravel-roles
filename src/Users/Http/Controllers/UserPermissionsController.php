<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Http\Resources\UserPermissionsResource;
use Enadstack\LaravelRoles\Users\Queries\UserPermissionsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserPermissionsController
{
    use AuthorizesAccess;

    public function __invoke(Request $request, string $user): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.view');

        $result = UserPermissionsQuery::forUserId($user);

        return ApiResponse::success(
            UserPermissionsResource::make($result['entries'])->resolve($request),
            meta: ['total' => count($result['entries']), 'is_owner' => $result['is_owner']],
        );
    }
}
