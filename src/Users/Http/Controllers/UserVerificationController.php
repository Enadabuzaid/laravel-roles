<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Actions\ResendVerification;
use Enadstack\LaravelRoles\Users\Actions\VerifyUserEmail;
use Enadstack\LaravelRoles\Users\Http\Requests\ResendVerificationRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\UserLifecycleRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\VerifyUserEmailRequest;
use Enadstack\LaravelRoles\Users\Http\Resources\UserResource;
use Enadstack\LaravelRoles\Users\Queries\UsersQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

final class UserVerificationController
{
    use AuthorizesAccess;

    public function verify(VerifyUserEmailRequest $request, VerifyUserEmail $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.update');
        $user = $request->targetUser();
        $action->handle($user);

        return $this->represent($request, $user);
    }

    public function resend(ResendVerificationRequest $request, ResendVerification $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.update');
        $user = $request->targetUser();
        $sent = $action->handle($user);

        return $this->represent($request, $user, ['verification_sent' => $sent]);
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
