<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Support\MigrationPublisher;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Spatie\Permission\PermissionServiceProvider;

class InstallCommand extends Command
{
    protected $signature = 'roles:install {--teams= : Enable Spatie teams (1) or keep them off (0, default)} {--team-key=team_id : Team foreign key column used when --teams=1} {--id-type= : Not supported yet in 2.0-alpha (must be int)} {--force : Overwrite existing files}';

    protected $description = 'Publish the config, permissions stub and Spatie assets.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $teamsOption = $this->option('teams');
        $idOption = $this->option('id-type');

        $teams = $teamsOption !== null && filter_var($teamsOption, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        $teamKey = is_string($this->option('team-key')) ? $this->option('team-key') : 'team_id';

        if ($teams && preg_match('/^[a-z][a-z0-9_]*$/', $teamKey) !== 1) {
            $this->components->error('--team-key must be a snake_case column name, e.g. tenant_id.');

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

        if ($teams) {
            $this->enableTeams($teamKey);
        }

        $migrations = database_path('migrations');
        $files = new Filesystem;

        if ($files->glob($migrations.'/*_create_permission_tables.php') === []) {
            $this->callSilently('vendor:publish', ['--provider' => PermissionServiceProvider::class, '--tag' => 'permission-migrations']);
        }

        (new MigrationPublisher($files, $migrations))->publish();

        if ($teams) {
            $this->components->info("Teams enabled (team key [{$teamKey}]). Call TeamContext::set(\$tenantId) or add the 'access.team' middleware after your tenant middleware.");
        }

        $this->components->info('Installed. Next steps:');
        foreach (['php artisan migrate', 'php artisan roles:sync', 'php artisan roles:seed', 'php artisan roles:owner you@example.com'] as $step) {
            $this->line("  {$step}");
        }

        $this->line("  Add the 'access.active' middleware alias to your web group (bootstrap/app.php: \$middleware->appendToGroup('web', 'access.active')) so suspended users are signed out mid-session.");

        return self::SUCCESS;
    }

    private function enableTeams(string $teamKey): void
    {
        $this->patchConfig(config_path('permission.php'), [
            "/'teams'\s*=>\s*(false|true)/" => "'teams' => true",
            "/'team_foreign_key'\s*=>\s*'[^']*'/" => "'team_foreign_key' => '{$teamKey}'",
        ]);
        $this->patchConfig(config_path('roles.php'), ["/'teams'\s*=>\s*(false|true)/" => "'teams' => true"]);
    }

    /** @param  array<string, string>  $replacements */
    private function patchConfig(string $path, array $replacements): void
    {
        if (! file_exists($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        foreach ($replacements as $pattern => $replacement) {
            $contents = (string) preg_replace($pattern, $replacement, $contents, 1);
        }

        file_put_contents($path, $contents);
    }
}
