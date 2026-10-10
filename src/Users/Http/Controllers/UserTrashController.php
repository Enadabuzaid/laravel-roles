<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Actions\ForceDeleteUser;
use Enadstack\LaravelRoles\Users\Actions\RestoreUser;
use Enadstack\LaravelRoles\Users\Http\Requests\ForceDeleteUserRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\RestoreUserRequest;
use Enadstack\LaravelRoles\Users\Http\Resources\UserResource;
use Enadstack\LaravelRoles\Users\Queries\UsersQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/** The users trash: restore and permanent delete (only routed when the host user model uses SoftDeletes). */
final class UserTrashController
{
    use AuthorizesAccess;

    public function restore(RestoreUserRequest $request, RestoreUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.delete');
        $user = $action->handle($request->targetUser());
        $actor = $request->user();

        return ApiResponse::success(
            UserResource::make(UsersQuery::row($user->getKey(), $actor instanceof Model ? $actor : null))->resolve($request),
        );
    }

    public function force(ForceDeleteUserRequest $request, ForceDeleteUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.delete');
        $action->handle($request->targetUser(), $request->confirmation());

        return ApiResponse::noContent();
    }
}
