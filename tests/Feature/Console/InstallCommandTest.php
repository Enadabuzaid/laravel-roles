<?php

declare(strict_types=1);

it('publishes config and the permissions stub and applies options', function () {
    $config = config_path('roles.php');
    $stub = base_path('access/permissions.php');
    $spatieConfig = config_path('permission.php');
    $migrations = glob(database_path('migrations/*_create_permission_tables.php')) ?: [];
    $hadSpatieConfig = file_exists($spatieConfig);
    @unlink($config);
    @unlink($stub);

    try {
        $this->artisan('roles:install --teams=1 --id-type=uuid --no-interaction')->assertExitCode(0);

        expect(file_exists($config))->toBeTrue()->and(file_exists($stub))->toBeTrue()
            ->and(file_get_contents($config))->toContain("'teams' => true,")->toContain("'id_type' => 'uuid',");
    } finally {
        @unlink($config);
        @unlink($stub);
        if (! $hadSpatieConfig) {
            @unlink($spatieConfig);
        }
        foreach (glob(database_path('migrations/*_create_permission_tables.php')) ?: [] as $file) {
            if (! in_array($file, $migrations, true)) {
                @unlink($file);
            }
        }
    }
});
