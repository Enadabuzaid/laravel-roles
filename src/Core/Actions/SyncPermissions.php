<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Core\Registry\PermissionDefinition;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class SyncPermissions extends Action
{
    public function __construct(private readonly PermissionRegistry $registry) {}

    public function handle(bool $dryRun = false, bool $prune = false): SyncResult
    {
        app(ActorContext::class)->assertKnownActor();

        $guard = (string) config('roles.guard');
        /** @var array<string, PermissionDefinition> $definitions */
        $definitions = collect($this->registry->definitions())->keyBy('name')->all();

        $existing = Permission::query()->where('guard_name', $guard)->get()->keyBy('name');

        $added = [];
        $updated = [];
        $restored = [];
        /** @var array<string, Permission> $toUpdate */
        $toUpdate = [];

        foreach ($definitions as $name => $definition) {
            $row = $existing[$name] ?? null;

            if ($row === null) {
                $added[] = $name;

                continue;
            }

            if ($row->deprecated_at !== null) {
                $restored[] = $name;
                $toUpdate[$name] = $row;
                if ($this->differs($row, $definition)) {
                    $updated[] = $name;
                }

                continue;
            }

            if ($this->differs($row, $definition)) {
                $updated[] = $name;
                $toUpdate[$name] = $row;
            }
        }

        $deprecated = [];
        foreach ($existing as $name => $row) {
            if ($row->source === Permission::SOURCE_REGISTRY && $row->deprecated_at === null && ! isset($definitions[$name])) {
                $deprecated[] = $name;
            }
        }

        $prunedIds = [];
        $pruned = [];
        if ($prune) {
            $candidates = $existing->filter(fn (Permission $row): bool => $row->source === Permission::SOURCE_REGISTRY
                && $row->deprecated_at !== null
                && ! in_array($row->name, $restored, true));
            $held = $this->heldIds($candidates->map(fn (Permission $row): mixed => $row->getKey())->values()->all());

            foreach ($candidates as $name => $row) {
                if (! in_array($row->getKey(), $held, true)) {
                    $prunedIds[] = $row->getKey();
                    $pruned[] = (string) $name;
                }
            }
        }

        $result = new SyncResult($added, $updated, $restored, $deprecated, $pruned);

        if ($dryRun || ! $result->hasChanges()) {
            return $result;
        }

        $this->transaction(function () use ($definitions, $added, $toUpdate, $deprecated, $prunedIds, $guard, $result): void {
            $now = now();

            $rows = [];
            foreach ($added as $name) {
                $definition = $definitions[$name];
                $rows[] = [
                    'name' => $name,
                    'guard_name' => $guard,
                    'module' => $definition->module,
                    'label' => json_encode($definition->label, JSON_UNESCAPED_UNICODE),
                    'description' => $definition->description === null ? null : json_encode($definition->description, JSON_UNESCAPED_UNICODE),
                    'risk' => $definition->risk,
                    'sort_order' => $definition->sortOrder,
                    'source' => Permission::SOURCE_REGISTRY,
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                Permission::insert($rows);
            }

            foreach ($toUpdate as $name => $row) {
                $definition = $definitions[$name];
                $row->update([
                    'module' => $definition->module,
                    'label' => $definition->label,
                    'description' => $definition->description,
                    'risk' => $definition->risk,
                    'sort_order' => $definition->sortOrder,
                    'source' => Permission::SOURCE_REGISTRY,
                    'deprecated_at' => null,
                    'last_synced_at' => $now,
                ]);
            }

            if ($deprecated !== []) {
                Permission::query()->whereIn('name', $deprecated)->where('guard_name', $guard)->update(['deprecated_at' => $now]);
            }

            if ($prunedIds !== []) {
                Permission::query()->whereIn((new Permission)->getKeyName(), $prunedIds)->delete();
            }

            $this->audit($result);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $result;
    }

    private function audit(SyncResult $result): void
    {
        $lists = ['added' => $result->added, 'updated' => $result->updated, 'restored' => $result->restored, 'deprecated' => $result->deprecated, 'pruned' => $result->pruned];
        $values = ['counts' => array_map('count', $lists)];
        foreach ($lists as $change => $names) {
            $values[$change] = array_slice($names, 0, 200);
        }

        app(AuditRecorder::class)->record(
            'permissions',
            'synced',
            null,
            null,
            $values,
            [],
            $result->deprecated !== [] || $result->pruned !== [] ? Severity::Warning : Severity::Info,
        );
    }

    private function differs(Permission $row, PermissionDefinition $definition): bool
    {
        // Order-insensitive comparison (ksort): Postgres jsonb does not preserve object key order.
        return ! $this->sameTranslations($row->label, $definition->label)
            || ! $this->sameTranslations($row->description, $definition->description)
            || $row->risk !== $definition->risk
            || $row->module !== $definition->module
            || (int) $row->sort_order !== $definition->sortOrder
            || $row->source !== Permission::SOURCE_REGISTRY;
    }

    /**
     * @param  array<string, string>|null  $stored
     * @param  array<string, string>|null  $wanted
     */
    private function sameTranslations(?array $stored, ?array $wanted): bool
    {
        if ($stored === null || $wanted === null) {
            return $stored === $wanted;
        }

        ksort($stored);
        ksort($wanted);

        return $stored === $wanted;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<mixed>
     */
    private function heldIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $tables = (array) config('permission.table_names');
        $key = (string) (config('permission.column_names.permission_pivot_key') ?? 'permission_id');

        $held = [];
        foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
            foreach (DB::table((string) $tables[$table])->whereIn($key, $ids)->pluck($key) as $id) {
                $held[] = $id;
            }
        }

        return $held;
    }
}
