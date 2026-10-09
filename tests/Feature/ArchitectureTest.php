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

it('references the audit recorder in every mutating action', function () {
    $mutating = '/(->assignRole\(|->removeRole\(|->syncRoles\(|Role::create|Role::query\(\)->create|Permission::insert|->update\(|->delete\()/';
    $offenders = [];

    foreach ((new Finder)->files()->name('*.php')->in([dirname(__DIR__, 2).'/src/Roles/Actions', dirname(__DIR__, 2).'/src/Core/Actions']) as $file) {
        $code = $file->getContents();
        if (preg_match($mutating, $code) && ! str_contains($code, 'AuditRecorder')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('references the guardrails in every action that mutates user roles', function () {
    $offenders = [];

    foreach ((new Finder)->files()->name('*.php')->in(dirname(__DIR__, 2).'/src/Roles/Actions') as $file) {
        $code = $file->getContents();
        if (preg_match('/->(assignRole|removeRole|syncRoles)\(/', $code) && ! str_contains($code, 'Guardrails')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
