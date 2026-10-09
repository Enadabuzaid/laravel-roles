<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Core\Actions\SyncPermissions;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistryException;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'roles:sync {--dry-run : Show the diff without writing} {--prune : Delete deprecated permissions nobody holds}';

    protected $description = 'Sync the permissions registry into the database.';

    public function handle(SyncPermissions $sync): int
    {
        try {
            $result = $sync->handle((bool) $this->option('dry-run'), (bool) $this->option('prune'));
        } catch (PermissionRegistryException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result->hasChanges()) {
            $this->components->info('Already in sync.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach (['added', 'updated', 'restored', 'deprecated', 'pruned'] as $change) {
            foreach ($result->{$change} as $name) {
                $rows[] = [$change, $name];
            }
        }
        $this->table(['Change', 'Permission'], $rows);

        $this->components->info($this->option('dry-run') ? 'Dry run — nothing was written.' : 'Permissions synced.');

        return self::SUCCESS;
    }
}
