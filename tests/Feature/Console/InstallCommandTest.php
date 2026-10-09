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
    'teams 1' => ['--teams=1', 'not supported in 2.0-alpha'],
    'teams true' => ['--teams=true', 'not supported in 2.0-alpha'],
    'uuid' => ['--id-type=uuid', 'not supported in 2.0-alpha'],
    'ulid' => ['--id-type=ulid', 'not supported in 2.0-alpha'],
    'garbage id type' => ['--id-type=banana', 'not supported in 2.0-alpha'],
]);
