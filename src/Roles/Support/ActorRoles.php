<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The roles the current user holds, read with one query and memoized on the request.
 * Trashed roles are included in `held` (a trashed role still blocks self-restore); `live` and `is_owner` only count live ones.
 */
final class ActorRoles
{
    private const ATTRIBUTE = 'roles.actor_roles';

    /**
     * @return array{held: array<int, true>, live: list<int>, is_owner: bool}
     */
    public static function for(Request $request): array
    {
        /** @var array{held: array<int, true>, live: list<int>, is_owner: bool}|null $cached */
        $cached = $request->attributes->get(self::ATTRIBUTE);
        if ($cached !== null) {
            return $cached;
        }

        $result = ['held' => [], 'live' => [], 'is_owner' => false];
        $user = $request->user();

        if ($user !== null) {
            $tables = (array) config('permission.table_names');
            $roles = (string) ($tables['roles'] ?? 'roles');
            $pivot = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
            $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
            $guard = (string) config('roles.guard');
            $super = (string) config('roles.super_role');

            $rows = DB::table($pivot)
                ->join($roles, $roles.'.id', '=', $pivot.'.role_id')
                ->where($pivot.'.model_type', $user->getMorphClass())
                ->where($pivot.'.'.$morphKey, $user->getAuthIdentifier())
                ->where($roles.'.guard_name', $guard)
                ->get([$roles.'.id', $roles.'.name', $roles.'.deleted_at']);

            foreach ($rows as $row) {
                $result['held'][(int) $row->id] = true;

                if ($row->deleted_at === null) {
                    $result['live'][] = (int) $row->id;
                    $result['is_owner'] = $result['is_owner'] || ($super !== '' && $row->name === $super);
                }
            }
        }

        $request->attributes->set(self::ATTRIBUTE, $result);

        return $result;
    }
}
