<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Queries;

use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/** Invitations with their role and inviter eager loaded; the status is derived from the timestamps. */
final class InvitationsQuery
{
    public const STATUSES = ['pending', 'accepted', 'expired', 'revoked'];

    public static function definition(): ListDefinition
    {
        return new ListDefinition(
            filters: [
                'status' => static function (Builder $query, mixed $value): void {
                    $statuses = array_values(array_intersect(self::STATUSES, array_map('strval', (array) $value)));

                    if ($statuses === []) {
                        return;
                    }

                    $query->where(static function (Builder $group) use ($statuses): void {
                        foreach ($statuses as $status) {
                            $group->orWhere(static function (Builder $one) use ($status): void {
                                /** @var Builder<AccessInvitation> $one */
                                $one->{$status}();
                            });
                        }
                    });
                },
            ],
            sorts: ['created' => 'created_at', 'expires' => 'expires_at'],
            searchable: ['email'],
            defaultSort: '-created',
        );
    }

    /** @return LengthAwarePaginator<int, Model> */
    public static function paginate(ListQuery $query): LengthAwarePaginator
    {
        /** @var Builder<Model> $builder */
        $builder = AccessInvitation::query()->with(['role', 'inviter:id,name']);

        return $query->paginate($query->apply($builder));
    }

    /** @return array{pending: int, accepted: int, expired: int, revoked: int} */
    public static function counts(): array
    {
        return [
            'pending' => AccessInvitation::query()->pending()->count(),
            'accepted' => AccessInvitation::query()->accepted()->count(),
            'expired' => AccessInvitation::query()->expired()->count(),
            'revoked' => AccessInvitation::query()->revoked()->count(),
        ];
    }

    public static function row(int|string $id): AccessInvitation
    {
        return AccessInvitation::query()->with(['role', 'inviter:id,name'])->findOrFail($id);
    }
}
