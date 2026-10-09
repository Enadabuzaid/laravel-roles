<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Actions\AcceptInvitation;
use Enadstack\LaravelRoles\Users\Http\Requests\AcceptInvitationRequest;
use Illuminate\Http\JsonResponse;

/** The single public route of the package (`access:public`, throttled). The host app signs the user in itself. */
final class AcceptInvitationController
{
    public function __invoke(AcceptInvitationRequest $request, AcceptInvitation $action): JsonResponse
    {
        $action->handle($request->token(), $request->email(), $request->profile());

        return ApiResponse::success(['accepted' => true]);
    }
}
