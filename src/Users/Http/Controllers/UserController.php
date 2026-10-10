<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\ListQuery;
use Enadstack\LaravelRoles\Users\Actions\UpdateUser;
use Enadstack\LaravelRoles\Users\Http\Requests\UpdateUserRequest;
use Enadstack\LaravelRoles\Users\Http\Resources\UserProfileResource;
use Enadstack\LaravelRoles\Users\Http\Resources\UserResource;
use Enadstack\LaravelRoles\Users\Queries\UserProfileQuery;
use Enadstack\LaravelRoles\Users\Queries\UsersQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController
{
    use AuthorizesAccess;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.view');

        $query = ListQuery::fromRequest($request, UsersQuery::definition());
        $actor = $request->user();

        return ApiResponse::list($query, UsersQuery::paginate($query, $actor instanceof Model ? $actor : null), UserResource::class);
    }

    public function update(UpdateUserRequest $request, UpdateUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.update');

        $user = $request->targetUser();
        $result = $action->handle($user, $request->changes());
        $actor = $request->user();

        return ApiResponse::success(
            UserResource::make(UsersQuery::row($user->getKey(), $actor instanceof Model ? $actor : null))->resolve($request),
            meta: ['verification_sent' => $result['verification_sent']],
        );
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.view');

        $actor = $request->user();

        return ApiResponse::success(UserProfileResource::make(UserProfileQuery::detail($user, $actor instanceof Model ? $actor : null))->resolve($request));
    }
}
