<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Resources;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Http\Resources\RoleLiteResource;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** Never exposes the token or its hash. */
final class InvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;

        if (! $invitation instanceof AccessInvitation) {
            throw new \LogicException('InvitationResource wraps an AccessInvitation.');
        }

        $role = $invitation->role;

        return [
            'id' => $invitation->getKey(),
            'email' => $invitation->email,
            'role' => $role instanceof Role ? RoleLiteResource::make($role)->resolve($request) : null,
            'status' => $invitation->status(),
            'expires_at' => $invitation->expires_at,
            'accepted_at' => $invitation->accepted_at,
            'revoked_at' => $invitation->revoked_at,
            'invited_by' => $this->inviter($request, $invitation),
            'created_at' => $invitation->created_at,
        ];
    }

    /** @return array{id: int|string, name?: string}|null the name only for viewers who may see users */
    private function inviter(Request $request, AccessInvitation $invitation): ?array
    {
        if ($invitation->invited_by === null) {
            return null;
        }

        $canViewUsers = $request->attributes->get('invitations.can_view_users');

        if (! is_bool($canViewUsers)) {
            $canViewUsers = Gate::forUser($request->user())->allows('access.users.view');
            $request->attributes->set('invitations.can_view_users', $canViewUsers);
        }

        $inviter = $invitation->inviter;

        return $canViewUsers && $inviter !== null
            ? ['id' => $invitation->invited_by, 'name' => (string) $inviter->getAttribute('name')]
            : ['id' => $invitation->invited_by];
    }
}
