<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

arch('every src file declares strict types')->expect('Enadstack\LaravelRoles')->toUseStrictTypes();

it('never calls spatie role/permission mutators outside actions', function () {
    $forbidden = '/->(assignRole|removeRole|syncRoles|givePermissionTo|revokePermissionTo|syncPermissions)\(/';
    $offenders = [];

    foreach ((new Finder)->files()->name('*.php')->in(dirname(__DIR__, 2).'/src')->notPath(['Roles/Actions', 'Core/Actions']) as $file) {
        if (preg_match($forbidden, $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps controllers free of queries once they exist', function () {
    $offenders = [];
    $dir = dirname(__DIR__, 2).'/src';

    foreach ((new Finder)->files()->name('*Controller.php')->in($dir) as $file) {
        if (preg_match('/(::query\(|DB::|->where\()/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
