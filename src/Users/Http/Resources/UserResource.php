<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Resources;

use Enadstack\LaravelRoles\Roles\Http\Resources\RoleLiteResource;
use Enadstack\LaravelRoles\Users\Queries\UsersQuery;
use Enadstack\LaravelRoles\Users\Support\HostMfaColumn;
use Enadstack\LaravelRoles\Users\Support\TargetStanding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * A user row from UsersQuery (the `access_*` aliases carry the access state). `lock_reason` and `abilities`
 * come from the standing UsersQuery attached to the row and from request-memoized gate checks: no per-row queries.
 */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->user();
        $standing = self::standing($request, $user);
        $reason = $standing['reason'];
        $deletedAt = $user->getAttribute('deleted_at');
        $trashed = $deletedAt !== null;

        return [
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'status' => $trashed ? UsersQuery::TRASHED : (string) ($user->getAttribute('access_status') ?? UsersQuery::defaultStatus()),
            'status_reason' => $user->getAttribute('access_status_reason'),
            'last_login_at' => self::date($user->getAttribute('access_last_login_at')),
            'last_login_ip' => $user->getAttribute('access_last_login_ip'),
            'mfa_enabled' => self::mfaEnabled($user),
            'email_verified_at' => $user->getAttribute('email_verified_at'),
            'created_at' => $user->getAttribute('created_at'),
            'deleted_at' => $deletedAt,
            'roles' => $this->whenLoaded('roles', fn (): array => RoleLiteResource::collection($user->getRelation('roles'))->resolve()),
            'is_self' => $reason === 'self',
            'is_owner' => $standing['is_owner'],
            'locked' => $reason !== null,
            'lock_reason' => $reason,
            'abilities' => self::abilities($request, $reason, $trashed, $user->getAttribute('email_verified_at') === null),
        ];
    }

    private function user(): Model
    {
        $user = $this->resource;

        if (! $user instanceof Model) {
            throw new \LogicException('UserResource wraps a user model.');
        }

        return $user;
    }

    /** @return array{is_owner: bool, reason: 'self'|'owner'|'above'|null} */
    private static function standing(Request $request, Model $user): array
    {
        /** @var array{is_owner: bool, reason: 'self'|'owner'|'above'|null}|null $attached */
        $attached = $user->getAttribute('access_standing');
        if ($attached !== null) {
            return $attached;
        }

        $actor = $request->user();

        return $actor instanceof Model
            ? app(TargetStanding::class)->evaluate($actor, [$user])[$user->getKey()]
            : ['is_owner' => false, 'reason' => null];
    }

    private static function mfaEnabled(Model $user): bool
    {
        $column = app(HostMfaColumn::class)->column();

        return $user->getAttribute('access_mfa_enabled_at') !== null
            || ($column !== null && $user->getAttribute($column) !== null);
    }

    private static function date(mixed $value): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse($value);
    }

    /**
     * Permission checks resolve once per request; the per-row lock only narrows what the server would accept.
     * A trashed user only offers `view`, `restore` and `force_delete`; `edit` and `verify` mirror the edit endpoints.
     *
     * @param  'self'|'owner'|'above'|null  $reason
     * @return array{view: bool, update: bool, suspend: bool, delete: bool, assign: bool, restore: bool, force_delete: bool, edit: bool, verify: bool}
     */
    private static function abilities(Request $request, ?string $reason, bool $trashed, bool $unverified): array
    {
        /** @var array{view: bool, update: bool, suspend: bool, delete: bool, assign: bool}|null $cached */
        $cached = $request->attributes->get('users.abilities');

        if ($cached === null) {
            $gate = Gate::forUser($request->user());
            $cached = [
                'view' => $gate->allows('access.users.view'),
                'update' => $gate->allows('access.users.update'),
                'suspend' => $gate->allows('access.users.suspend'),
                'delete' => $gate->allows('access.users.delete'),
                'assign' => $gate->allows('access.roles.assign'),
            ];
            $request->attributes->set('users.abilities', $cached);
        }

        $edit = ! $trashed && $cached['update'] && $reason !== 'owner' && $reason !== 'above';

        return [
            'view' => $cached['view'],
            'update' => $edit,
            'suspend' => ! $trashed && $cached['suspend'] && $reason === null,
            'delete' => ! $trashed && $cached['delete'] && $reason === null,
            'assign' => ! $trashed && $cached['assign'] && $reason === null,
            'restore' => $trashed && $cached['delete'] && $reason === null,
            'force_delete' => $trashed && $cached['delete'] && $reason === null,
            'edit' => $edit,
            'verify' => $edit && $unverified,
        ];
    }
}
