<?php

namespace Enadstack\LaravelRoles\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Enadstack\LaravelRoles\Database\Seeders\RolesSeeder as PackageRolesSeeder;

use function Laravel\Prompts\select;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'roles:install
        {--with-seeds : Seed initial roles/permissions from config/roles.php}
        {--team-key=  : Team/Tenant foreign key when using same-DB (default: team_id)}';

    protected $description = 'Install laravel-roles: configure languages and tenancy';

    public function handle(): int
    {
        $fs = new Filesystem;
        $seedTenantId = null;

        // 1) Publish vendor files
        $this->components->info('Publishing Spatie Permission config & migrations…');
        $this->callSilently('vendor:publish', ['--provider' => 'Spatie\\Permission\\PermissionServiceProvider']);


        $this->components->info('Publishing roles.php config…');
        // Use the correct publish tag as defined in the service provider
        $this->callSilent('vendor:publish', ['--tag' => 'roles-config']);

        $rolesConfig = config('roles', []) ?: [];

        /* -------------------------------------------------------------
         | LANGUAGES
         |--------------------------------------------------------------
         */
        $this->components->info('Configure languages');
        $isMulti = confirm('Enable multiple languages (i18n)?', true);

        if ($isMulti) {
            $locales = multiselect(
                label: 'Select languages/locales (space to toggle, enter to confirm)',
                options: [
                    'en' => 'English (en)',
                    'ar' => 'Arabic (ar)',
                ],
                default: ['en', 'ar']
            );

            // allow adding more (fr, de, tr, …)
            while (confirm('Add another locale?', false)) {
                $new = Str::lower(trim(text(
                    label: 'Locale code (e.g., fr, de, tr)',
                    placeholder: 'fr'
                )));
                if ($new && !in_array($new, $locales, true)) {
                    $locales[] = $new;
                    $this->components->info("Added locale: {$new}");
                }
            }

            if (!$locales) {
                $locales = ['en', 'ar'];
            }
        } else {
            // single language mode: default to English only
            $locales = ['en'];
        }

        if ($isMulti) {
            $default = select('Default locale', array_combine($locales, $locales), in_array('en', $locales, true) ? 'en' : $locales[0]);
            $fallback = select('Fallback locale', array_combine($locales, $locales), in_array('en', $locales, true) ? 'en' : $locales[0]);
        } else {
            $default = 'en';
            $fallback = 'en';
        }

        $rolesConfig = Arr::set($rolesConfig, 'i18n.enabled', $isMulti);
        $rolesConfig = Arr::set($rolesConfig, 'i18n.locales', array_values($locales));
        $rolesConfig = Arr::set($rolesConfig, 'i18n.default', $default);
        $rolesConfig = Arr::set($rolesConfig, 'i18n.fallback', $fallback);

        /* -------------------------------------------------------------
         | TENANCY
         |--------------------------------------------------------------
         */
        $this->components->info('Configure tenancy');
        $mode = select(
            label: 'Choose tenancy mode',
            options: [
                'single' => 'Single project (no multi-tenancy)',
                'team_scoped' => 'Same DB, scope by tenant column (tenant_id/provider_id)',
                'multi_database' => 'Multi-database (each provider has its own DB via tenancy package)',
            ],
            default: 'single'
        );

        if ($mode === 'single') {
            $this->enableTeams(false);
            $rolesConfig = Arr::set($rolesConfig, 'tenancy.mode', 'single');
            $rolesConfig = Arr::set($rolesConfig, 'tenancy.provider', null);
        } elseif ($mode === 'team_scoped') {
            $fk = $this->option('team-key') ?: text(
                label: 'Tenant foreign key column (e.g., tenant_id / provider_id)',
                default: 'team_id',
                placeholder: 'tenant_id / provider_id / team_id'
            );

            $this->enableTeams(true, $fk);

            $rolesConfig = Arr::set($rolesConfig, 'tenancy.mode', 'team_scoped');
            $rolesConfig = Arr::set($rolesConfig, 'tenancy.team_foreign_key', $fk);
            $rolesConfig = Arr::set($rolesConfig, 'tenancy.provider', null);

            if (confirm('Seed roles/permissions for a specific tenant_id (same-table) now?', false)) {
                $seedTenantId = text('Enter tenant_id (team id) to seed into');
            }

            $this->line('Remember to set tenant per request:');
            $this->line('  app()->instance("permission.team_id", $tenantId);');
        } else { // multi_database
            $this->enableTeams(false);

            // Determine tenancy provider safely
            $stanclClass = '\\Stancl\\Tenancy\\TenancyServiceProvider';
            $provider = class_exists($stanclClass)
                ? 'stancl/tenancy'
                : select(
                    label: 'Tenancy provider (install in the app if needed)',
                    options: [
                        'stancl/tenancy' => 'stancl/tenancy (recommended)',
                        'custom' => 'Other/custom',
                    ],
                    default: 'stancl/tenancy'
                );

            $rolesConfig = Arr::set($rolesConfig, 'tenancy.mode', 'multi_database');
            $rolesConfig = Arr::set($rolesConfig, 'tenancy.provider', $provider);

            $this->components->warn('Multi-database: run Spatie migrations on each tenant DB.');
            if ($provider === 'stancl/tenancy') {
                $this->line('Move Spatie migrations to database/migrations/tenant and run:');
                $this->line('  php artisan tenants:artisan "migrate --force"');
            }
        }

        // 3) Persist roles.php
        $this->writeConfigRoles($fs, $rolesConfig);

        // 4) Migrate base DB
        $this->components->info('Running base migrations…');
        $this->call('migrate');

        // 5) Optional seeding
        if (confirm('Seed initial roles & permissions now?', (bool)$this->option('with-seeds'))) {
            if ($seedTenantId !== null) {
                // Set current team/tenant context for Spatie "teams" so seed data is scoped to that tenant
                app()->instance('permission.team_id', $seedTenantId);
                $this->line("Seeding into tenant_id: {$seedTenantId}");
            }
            $this->components->info('Seeding roles & permissions…');
            $this->call('db:seed', ['--class' => PackageRolesSeeder::class]);
        }

        $this->components->info('laravel-roles installed ✅');
        return self::SUCCESS;
    }

    /**
     * Toggle Spatie "teams" and (optionally) set team_foreign_key in config/permission.php
     */
    protected function enableTeams(bool $enabled, ?string $teamKey = null): void
    {
        $path = config_path('permission.php');
        if (!file_exists($path)) {
            $this->components->warn('config/permission.php not found (publish may have failed).');
            return;
        }

        $content = file_get_contents($path);

        // Set teams flag
        $content = Str::of($content)
            ->replace("'teams' => false", "'teams' => " . ($enabled ? 'true' : 'false'))
            ->replace("'teams' => true", "'teams' => " . ($enabled ? 'true' : 'false'))
            ->toString();

        // Set team_foreign_key if provided
        if ($teamKey) {
            $content = Str::of($content)
                ->replace("'team_foreign_key' => 'team_id'", "'team_foreign_key' => '{$teamKey}'")
                ->toString();
        }

        file_put_contents($path, $content);
    }

    /**
     * Write back config/roles.php with updated array.
     */
    protected function writeConfigRoles(Filesystem $fs, array $config): void
    {
        $target = config_path('roles.php');
        $export = var_export($config, true);
        $php = <<<PHP
<?php

return {$export};

PHP;
        $fs->put($target, $php);
    }
}

