<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Actions\DeactivateUser;
use Enadstack\LaravelRoles\Users\Actions\DeleteUser;
use Enadstack\LaravelRoles\Users\Actions\ForcePasswordReset;
use Enadstack\LaravelRoles\Users\Actions\ReactivateUser;
use Enadstack\LaravelRoles\Users\Actions\SuspendUser;
use Enadstack\LaravelRoles\Users\Actions\UnlockUser;
use Enadstack\LaravelRoles\Users\Http\Requests\DeactivateUserRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\DeleteUserRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\ForcePasswordResetRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\ReactivateUserRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\SuspendUserRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\UnlockUserRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\UserLifecycleRequest;
use Enadstack\LaravelRoles\Users\Http\Resources\UserResource;
use Enadstack\LaravelRoles\Users\Queries\UsersQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

final class UserLifecycleController
{
    use AuthorizesAccess;

    public function suspend(SuspendUserRequest $request, SuspendUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.suspend');
        $user = $request->targetUser();
        $action->handle($user, $request->reason());

        return $this->represent($request, $user);
    }

    public function reactivate(ReactivateUserRequest $request, ReactivateUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.suspend');
        $user = $request->targetUser();
        $action->handle($user);

        return $this->represent($request, $user);
    }

    public function deactivate(DeactivateUserRequest $request, DeactivateUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.suspend');
        $user = $request->targetUser();
        $action->handle($user, $request->reason());

        return $this->represent($request, $user);
    }

    public function unlock(UnlockUserRequest $request, UnlockUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.update');
        $user = $request->targetUser();
        $action->handle($user);

        return $this->represent($request, $user);
    }

    public function forcePasswordReset(ForcePasswordResetRequest $request, ForcePasswordReset $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.update');
        $user = $request->targetUser();
        $sent = $action->handle($user);

        return $this->represent($request, $user, ['link_sent' => $sent]);
    }

    public function destroy(DeleteUserRequest $request, DeleteUser $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.delete');
        $action->handle($request->targetUser(), $request->confirmation());

        return ApiResponse::noContent();
    }

    /** @param  array<string, mixed>  $meta */
    private function represent(UserLifecycleRequest $request, Model $user, array $meta = []): JsonResponse
    {
        $actor = $request->user();

        return ApiResponse::success(
            UserResource::make(UsersQuery::row($user->getKey(), $actor instanceof Model ? $actor : null))->resolve($request),
            meta: $meta,
        );
    }
}
