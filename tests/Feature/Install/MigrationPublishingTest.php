<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\MigrationPublisher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

function publishedFiles(string $dir, string $name): array
{
    return glob($dir.'/*_'.$name.'.php') ?: [];
}

function cleanPublished(string $dir): void
{
    foreach (['extend_roles_table', 'extend_permissions_table', 'create_permission_tables'] as $name) {
        foreach (publishedFiles($dir, $name) as $file) {
            @unlink($file);
        }
    }
}

function tempMigrationDir(): string
{
    $dir = sys_get_temp_dir().'/roles-mig-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    return $dir;
}

it('publishes both migrations with timestamps, roles before permissions', function (): void {
    $dir = database_path('migrations');
    cleanPublished($dir);

    try {
        Artisan::call('vendor:publish', ['--tag' => 'roles-migrations']);

        $roles = publishedFiles($dir, 'extend_roles_table');
        $permissions = publishedFiles($dir, 'extend_permissions_table');

        expect($roles)->toHaveCount(1)->and($permissions)->toHaveCount(1);
        expect(basename($roles[0]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_extend_roles_table\.php$/');
        expect(basename($roles[0]) < basename($permissions[0]))->toBeTrue();
    } finally {
        cleanPublished($dir);
    }
});

it('stamps after an existing Spatie migration with a later timestamp', function (): void {
    $dir = tempMigrationDir();
    $spatie = $dir.'/2099_01_01_000000_create_permission_tables.php';
    file_put_contents($spatie, '<?php');

    try {
        (new MigrationPublisher(new Filesystem, $dir))->publish();

        $roles = basename(publishedFiles($dir, 'extend_roles_table')[0]);
        $permissions = basename(publishedFiles($dir, 'extend_permissions_table')[0]);

        expect($roles > basename($spatie))->toBeTrue();
        expect($permissions > $roles)->toBeTrue();
    } finally {
        cleanPublished($dir);
        @rmdir($dir);
    }
});

it('does not duplicate migrations when republished', function (): void {
    $dir = database_path('migrations');
    cleanPublished($dir);

    try {
        Artisan::call('vendor:publish', ['--tag' => 'roles-migrations']);
        Artisan::call('vendor:publish', ['--tag' => 'roles-migrations']);

        expect(publishedFiles($dir, 'extend_roles_table'))->toHaveCount(1);
        expect(publishedFiles($dir, 'extend_permissions_table'))->toHaveCount(1);
    } finally {
        cleanPublished($dir);
    }
});

it('reuses published files through the glob with fresh publisher instances', function (): void {
    $dir = tempMigrationDir();

    try {
        $first = (new MigrationPublisher(new Filesystem, $dir))->publish();
        $secondPublisher = new MigrationPublisher(new Filesystem, $dir);
        $targetsAgain = array_values($secondPublisher->targets());
        $second = $secondPublisher->publish();
        $third = (new MigrationPublisher(new Filesystem, $dir))->publish();

        expect($first)->toHaveCount(2)->and($second)->toBe([])->and($third)->toBe([])
            ->and($targetsAgain)->toBe($first)
            ->and(glob($dir.'/*.php'))->toHaveCount(2);
    } finally {
        cleanPublished($dir);
        @rmdir($dir);
    }
});

it('throws a clear exception when the Spatie tables are missing', function (): void {
    $original = config('permission.table_names');
    config([
        'permission.table_names.roles' => 'missing_roles',
        'permission.table_names.permissions' => 'missing_permissions',
    ]);

    try {
        $roles = include dirname(__DIR__, 3).'/database/migrations/extend_roles_table.php.stub';
        $permissions = include dirname(__DIR__, 3).'/database/migrations/extend_permissions_table.php.stub';

        expect(fn () => $roles->up())->toThrow(RuntimeException::class, 'run Spatie\'s permission migrations first');
        expect(fn () => $permissions->up())->toThrow(RuntimeException::class, 'the Spatie permission tables are missing');
    } finally {
        config(['permission.table_names' => $original]);
    }
});

it('skips the extension migrations when their columns already exist', function (): void {
    $roles = include dirname(__DIR__, 3).'/database/migrations/extend_roles_table.php.stub';
    $permissions = include dirname(__DIR__, 3).'/database/migrations/extend_permissions_table.php.stub';

    // The test schema already carries the extension columns (as an alpha.1 database would).
    expect(Schema::hasColumn('roles', 'label'))->toBeTrue()->and(Schema::hasColumn('permissions', 'module'))->toBeTrue();

    $roles->up();
    $roles->up();
    $permissions->up();
    $permissions->up();

    expect(Schema::hasColumn('roles', 'label'))->toBeTrue()->and(Schema::hasColumn('permissions', 'module'))->toBeTrue();
});

it('ignores look-alike files when resolving published migrations', function (): void {
    $dir = tempMigrationDir();
    file_put_contents($dir.'/backup_extend_roles_table.php', '<?php');
    file_put_contents($dir.'/notatimestamp_create_permission_tables.php', '<?php');

    try {
        $targets = (new MigrationPublisher(new Filesystem, $dir))->targets();

        foreach ($targets as $destination) {
            expect(basename($destination))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_extend_(roles|permissions)_table\.php$/');
        }
    } finally {
        array_map('unlink', glob($dir.'/*.php') ?: []);
        @rmdir($dir);
    }
});
