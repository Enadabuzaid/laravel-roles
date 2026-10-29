<?php

declare(strict_types=1);

it('publishes config and the permissions stub', function () {
    $config = config_path('roles.php');
    $stub = base_path('access/permissions.php');
    $spatieConfig = config_path('permission.php');
    $migrations = glob(database_path('migrations/*_create_permission_tables.php')) ?: [];
    $hadSpatieConfig = file_exists($spatieConfig);
    @unlink($config);
    @unlink($stub);

    try {
        $this->artisan('roles:install --teams=0 --id-type=int --no-interaction')->assertExitCode(0);

        expect(file_exists($config))->toBeTrue()->and(file_exists($stub))->toBeTrue()
            ->and(file_get_contents($config))->toContain("'teams' => false,")->toContain("'id_type' => 'int',");
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

it('rejects unsupported teams and id-type options with a clear error and publishes nothing', function (string $options, string $message) {
    $config = config_path('roles.php');
    @unlink($config);

    try {
        $this->artisan('roles:install --no-interaction '.$options)
            ->expectsOutputToContain($message)
            ->assertExitCode(1);

        expect(file_exists($config))->toBeFalse();
    } finally {
        @unlink($config);
    }
})->with([
    'bad team key' => ['--teams=1 --team-key=Tenant-Id', 'snake_case'],
    'uuid' => ['--id-type=uuid', 'not supported in 2.0-alpha'],
    'ulid' => ['--id-type=ulid', 'not supported in 2.0-alpha'],
    'garbage id type' => ['--id-type=banana', 'not supported in 2.0-alpha'],
]);

it('enables teams in both configs with a custom team key', function () {
    $config = config_path('roles.php');
    $spatieConfig = config_path('permission.php');
    $hadSpatieConfig = file_exists($spatieConfig);
    $originalSpatieConfig = $hadSpatieConfig ? file_get_contents($spatieConfig) : null;
    $migrations = glob(database_path('migrations/*_create_permission_tables.php')) ?: [];
    @unlink($config);

    try {
        $this->artisan('roles:install --teams=1 --team-key=tenant_id --no-interaction')->assertExitCode(0);

        expect(file_get_contents($config))->toContain("'teams' => true,")
            ->and(file_get_contents($spatieConfig))->toContain("'teams' => true")->toContain("'team_foreign_key' => 'tenant_id'");
    } finally {
        @unlink($config);
        @unlink(base_path('access/permissions.php'));
        if ($originalSpatieConfig === null) {
            @unlink($spatieConfig);
        } else {
            file_put_contents($spatieConfig, $originalSpatieConfig);
        }
        foreach (glob(database_path('migrations/*_create_permission_tables.php')) ?: [] as $file) {
            if (! in_array($file, $migrations, true)) {
                @unlink($file);
            }
        }
    }
});
