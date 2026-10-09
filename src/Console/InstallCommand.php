<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Support\MigrationPublisher;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Spatie\Permission\PermissionServiceProvider;

class InstallCommand extends Command
{
    protected $signature = 'roles:install {--teams= : Not supported yet in 2.0-alpha (must be 0)} {--id-type= : Not supported yet in 2.0-alpha (must be int)} {--force : Overwrite existing files}';

    protected $description = 'Publish the config, permissions stub and Spatie assets.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $teamsOption = $this->option('teams');
        $idOption = $this->option('id-type');

        if ($teamsOption !== null && filter_var($teamsOption, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false) {
            $this->components->error('Teams are not supported in 2.0-alpha (planned for a later phase). Run roles:install without --teams.');

            return self::FAILURE;
        }

        if ($idOption !== null && $idOption !== 'int') {
            $this->components->error('--id-type other than [int] is not supported in 2.0-alpha (uuid and ulid are planned for a later phase).');

            return self::FAILURE;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'roles-config', '--force' => $force]);
        $this->callSilently('vendor:publish', ['--tag' => 'roles-permissions', '--force' => $force]);

        if (! file_exists(config_path('permission.php'))) {
            $this->callSilently('vendor:publish', ['--provider' => PermissionServiceProvider::class, '--tag' => 'permission-config']);
        }

        $migrations = database_path('migrations');
        $files = new Filesystem;

        if ($files->glob($migrations.'/*_create_permission_tables.php') === []) {
            $this->callSilently('vendor:publish', ['--provider' => PermissionServiceProvider::class, '--tag' => 'permission-migrations']);
        }

        (new MigrationPublisher($files, $migrations))->publish();

        $this->components->info('Installed. Next steps:');
        foreach (['php artisan migrate', 'php artisan roles:sync', 'php artisan roles:seed', 'php artisan roles:owner you@example.com'] as $step) {
            $this->line("  {$step}");
        }

        return self::SUCCESS;
    }
}
