<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\ListQuery;
use Enadstack\LaravelRoles\Users\Actions\CreateInvitation;
use Enadstack\LaravelRoles\Users\Actions\ResendInvitation;
use Enadstack\LaravelRoles\Users\Actions\RevokeInvitation;
use Enadstack\LaravelRoles\Users\Http\Requests\ListInvitationsRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\ResendInvitationRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\RevokeInvitationRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\StoreInvitationRequest;
use Enadstack\LaravelRoles\Users\Http\Resources\InvitationResource;
use Enadstack\LaravelRoles\Users\Queries\InvitationsQuery;
use Illuminate\Http\JsonResponse;

final class InvitationController
{
    use AuthorizesAccess;

    public function index(ListInvitationsRequest $request): JsonResponse
    {
        $this->authorizePermission($request, 'access.invitations.view');

        $query = ListQuery::fromRequest($request, InvitationsQuery::definition());

        return ApiResponse::list($query, InvitationsQuery::paginate($query), InvitationResource::class, ['counts' => InvitationsQuery::counts()]);
    }

    public function store(StoreInvitationRequest $request, CreateInvitation $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.invitations.create');

        $invitation = $action->handle($request->email(), $request->roleToInvite(), $request->confirmed());

        return ApiResponse::created(
            InvitationResource::make(InvitationsQuery::row($invitation->getKey()))->resolve($request),
            meta: ['mail_sent' => $invitation->mailSent],
        );
    }

    public function resend(ResendInvitationRequest $request, ResendInvitation $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.invitations.create');

        $invitation = $action->handle($request->invitation(), $request->confirmed());

        return ApiResponse::success(
            InvitationResource::make(InvitationsQuery::row($invitation->getKey()))->resolve($request),
            meta: ['mail_sent' => $invitation->mailSent],
        );
    }

    public function destroy(RevokeInvitationRequest $request, RevokeInvitation $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.invitations.revoke');

        $invitation = $request->invitation();
        $action->handle($invitation);

        return ApiResponse::success(InvitationResource::make(InvitationsQuery::row($invitation->getKey()))->resolve($request));
    }
}
