<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestUser;

it('installs, migrates, syncs, seeds and bootstraps an owner into a healthy state', function (): void {
    $config = config_path('roles.php');
    $stub = base_path('access/permissions.php');
    $spatieConfig = config_path('permission.php');
    $migrationsDir = database_path('migrations');
    $hadConfig = file_exists($config);
    $hadStub = file_exists($stub);
    $hadSpatieConfig = file_exists($spatieConfig);
    $existing = glob($migrationsDir.'/*.php') ?: [];
    $published = [];

    try {
        // Start from the bare Spatie schema: drop what the shim migrations added so the published stubs do the work.
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        DB::table('migrations')->whereIn('migration', [
            '0000_00_00_000000_create_spatie_permission_tables',
            '0000_00_00_000001_extend_roles_table',
            '0000_00_00_000002_extend_permissions_table',
        ])->delete();

        $this->artisan('roles:install --no-interaction')->assertExitCode(0);

        $published = array_values(array_diff(glob($migrationsDir.'/*.php') ?: [], $existing));
        expect($published)->toHaveCount(3);

        Artisan::call('migrate', ['--path' => $published, '--realpath' => true]);

        expect(Schema::hasColumn('roles', 'label'))->toBeTrue()
            ->and(Schema::hasColumn('permissions', 'module'))->toBeTrue();

        config(['roles.permissions_file' => $stub]);
        app()->forgetInstance(PermissionRegistry::class);
        TestUser::create(['name' => 'Boss', 'email' => 'boss@example.test', 'password' => 'x']);

        $this->artisan('roles:sync')->assertExitCode(0);
        $this->artisan('roles:seed')->assertExitCode(0);
        $this->artisan('roles:owner boss@example.test')->assertExitCode(0);
        $this->artisan('roles:doctor')->assertExitCode(0);
    } finally {
        foreach ($published as $file) {
            @unlink($file);
        }
        foreach (glob($migrationsDir.'/*.php') ?: [] as $file) {
            if (! in_array($file, $existing, true)) {
                @unlink($file);
            }
        }
        if (! $hadConfig) {
            @unlink($config);
        }
        if (! $hadStub) {
            @unlink($stub);
            @rmdir(dirname($stub));
        }
        if (! $hadSpatieConfig) {
            @unlink($spatieConfig);
        }
    }
});
