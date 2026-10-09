<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Resources;

use Enadstack\LaravelRoles\Roles\Http\Resources\RoleLiteResource;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Queries\UsersQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A user from UserProfileQuery::detail(): the list fields plus the full access state, roles with permission counts and the effective permission count. */
final class UserProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        if (! $user instanceof Model) {
            throw new \LogicException('UserProfileResource wraps a user model.');
        }

        $state = $user->getRelation('accessState');

        return [
            ...UserResource::make($user)->resolve($request),
            'state' => [
                'status' => $state instanceof UserAccessState ? $state->status->value : UsersQuery::defaultStatus(),
                'reason' => $state instanceof UserAccessState ? $state->status_reason : null,
                'activated_at' => $state instanceof UserAccessState ? $state->activated_at : null,
                'last_login_at' => $state instanceof UserAccessState ? $state->last_login_at : null,
                'last_login_ip' => $state instanceof UserAccessState ? $state->last_login_ip : null,
                'failed_login_count' => $state instanceof UserAccessState ? $state->failed_login_count : 0,
                'locked_until' => $state instanceof UserAccessState ? $state->locked_until : null,
                'mfa_enabled_at' => $state instanceof UserAccessState ? $state->mfa_enabled_at : null,
                'mfa_method' => $state instanceof UserAccessState ? $state->mfa_method : null,
                'password_changed_at' => $state instanceof UserAccessState ? $state->password_changed_at : null,
            ],
            'roles' => $user->getRelation('roles')->map(fn (Model $role): array => [
                ...RoleLiteResource::make($role)->resolve($request),
                'permissions_count' => (int) $role->getAttribute('permissions_count'),
            ])->values()->all(),
            'effective_permissions_count' => (int) $user->getAttribute('effective_permissions_count'),
        ];
    }
}
