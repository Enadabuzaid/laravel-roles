<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Support\MigrationPublisher;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Spatie\Permission\PermissionServiceProvider;

class InstallCommand extends Command
{
    protected $signature = 'roles:install {--teams= : Enable teams (1|0)} {--id-type= : int, uuid or ulid} {--force : Overwrite existing files}';

    protected $description = 'Publish the config, permissions stub and Spatie assets.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $teamsOption = $this->option('teams');
        $idOption = $this->option('id-type');

        $teams = $teamsOption !== null
            ? filter_var($teamsOption, FILTER_VALIDATE_BOOLEAN)
            : ($this->input->isInteractive() ? $this->confirm('Enable teams (multi-tenant roles)?', false) : false);
        $idType = $idOption !== null
            ? (is_string($idOption) ? $idOption : '')
            : ($this->input->isInteractive() ? $this->askIdType() : 'int');

        if (! in_array($idType, ['int', 'uuid', 'ulid'], true)) {
            $this->components->error('--id-type must be int, uuid or ulid.');

            return self::FAILURE;
        }

        $configExisted = file_exists(config_path('roles.php'));

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

        if (! $configExisted || $force) {
            $this->applyOptions($teams, $idType);
        } elseif ($teamsOption !== null || $idOption !== null) {
            $this->components->warn('config/roles.php already exists: --teams / --id-type were not applied. Re-run with --force or edit the file.');
        }

        $this->components->info('Installed. Next steps:');
        foreach (['php artisan migrate', 'php artisan roles:sync', 'php artisan roles:seed', 'php artisan roles:owner you@example.com'] as $step) {
            $this->line("  {$step}");
        }

        return self::SUCCESS;
    }

    private function askIdType(): string
    {
        $choice = $this->choice('User key type?', ['int', 'uuid', 'ulid'], 0);

        return is_string($choice) ? $choice : 'int';
    }

    private function applyOptions(bool $teams, string $idType): void
    {
        $path = config_path('roles.php');
        $contents = file_get_contents($path);

        if ($contents === false) {
            return;
        }

        $contents = str_replace(
            ["'teams' => false,", "'id_type' => 'int',"],
            ["'teams' => ".($teams ? 'true' : 'false').',', "'id_type' => '{$idType}',"],
            $contents,
        );

        file_put_contents($path, $contents);
    }
}
