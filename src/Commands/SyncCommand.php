<?php

namespace Enadstack\LaravelRoles\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Enadstack\LaravelRoles\Database\Seeders\RolesSeeder;
use Throwable;

class SyncCommand extends Command
{
    protected $signature = 'roles:sync
        {--guard= : Override guard (default from config)}
        {--team-id= : When team_scoped, run sync against a specific tenant/team id}
        {--no-map : Seed roles/permissions but skip mapping}
        {--prune : Remove DB permissions not present in config}
        {--dry-run : Show what would change without writing}
    ';

    protected $description = 'Sync roles & permissions from config (idempotent).';

    public function handle(): int
    {
        $guard = $this->option('guard') ?: config('roles.guard', 'web');
        $dry   = (bool) $this->option('dry-run');

        // Team/Tenancy context (Spatie teams)
        $teamId = $this->option('team-id');
        $isTeamScoped = config('roles.tenancy.mode') === 'team_scoped';
        if ($isTeamScoped && $teamId !== null) {
            app()->instance('permission.team_id', $teamId);
            $this->components->info("Syncing under team_id={$teamId}");
        }

        // 1) Seed/create/update (idempotent) using your existing logic
        if ($dry) {
            $this->components->info('Dry-run: would execute RolesSeeder (create/ensure roles & permissions).');
        } else {
            $this->callSilent('db:seed', [
                '--class' => RolesSeeder::class,
            ]);
        }

        // 2) Optionally re-run mapping only (if you want to isolate mapping when --no-map is used)
        if ($this->option('no-map')) {
            $this->components->info('Skipped mapping (role->permissions) because --no-map set.');
        } else {
            // Reapply mapping from config (idempotent)
            $map = (array) config('roles.seed.map', []);
            if (! empty($map)) {
                $this->components->info('Applying role->permission map from config…');
                // use the same logic as seeder’s mapping section:
                (new RolesSeeder())->run(); // already does mapping; fine to call once
            }
        }

        // 3) Optional prune: remove permissions that aren’t in config
        if ($this->option('prune')) {
            $this->prune($guard, $dry);
        }

        if (! $dry) {
            $this->callSilent('permission:cache-reset');
        }

        $this->components->info($dry ? 'Dry-run complete.' : 'Sync complete ✅');
        return self::SUCCESS;
    }

    protected function prune(string $guard, bool $dry): void
    {
        $configured = $this->computeConfiguredPermissions($guard);
        $existing   = Permission::where('guard_name', $guard)->pluck('name')->all();

        $toDelete = array_values(array_diff($existing, $configured));

        if (empty($toDelete)) {
            $this->components->info('Prune: nothing to remove.');
            return;
        }

        $this->warn('Prune will remove these permissions (and detach from roles):');
        foreach ($toDelete as $p) {
            $this->line("  - {$p}");
        }

        if ($dry) {
            $this->components->info('Dry-run: skipping actual deletion.');
            return;
        }

        foreach ($toDelete as $name) {
            $permissionClass = config('permission.models.permission', Permission::class);
            $perm = $permissionClass::where('guard_name', $guard)->where('name', $name)->first();
            if (! $perm) continue;

            // Try to delete with relationship detach first
            try {
                if (method_exists($perm, 'roles')) {
                    $perm->roles()->detach();
                }
                $perm->delete();
            } catch (Throwable $e) {
                // If normal delete fails (relationship issues), try force delete
                try {
                    $perm->forceDelete();
                } catch (Throwable $e2) {
                    // Last resort: direct DB delete
                    try {
                        DB::table('permissions')
                            ->where('id', $perm->id)
                            ->delete();
                    } catch (Throwable $e3) {
                        $this->warn("Could not delete permission '{$name}': {$e3->getMessage()}");
                        continue;
                    }
                }
            }
        }

        $this->components->info('Prune complete.');
    }

    protected function computeConfiguredPermissions(string $guard): array
    {
        $names = [];

        // flat permissions
        foreach ((array) config('roles.seed.permissions', []) as $p) {
            $names[] = $p;
        }

        // grouped permissions "<group>.<action>"
        foreach ((array) config('roles.seed.permission_groups', []) as $group => $actions) {
            foreach ((array) $actions as $action) {
                $names[] = "{$group}.{$action}";
            }
        }

        return array_values(array_unique($names));
    }
}