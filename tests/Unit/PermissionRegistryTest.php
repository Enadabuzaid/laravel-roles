<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistryException;

function registryFile(string $name): string
{
    return dirname(__DIR__).'/Support/registry/'.$name.'.php';
}

it('normalizes strings, translations, descriptions and risk', function () {
    $defs = collect((new PermissionRegistry(registryFile('valid'), []))->definitions())->keyBy('name');

    expect($defs['orders.view']->label)->toBe(['en' => 'View orders'])
        ->and($defs['orders.update']->label)->toBe(['en' => 'Edit orders', 'ar' => 'تعديل الطلبات'])
        ->and($defs['orders.refund']->risk)->toBe('high')
        ->and($defs['orders.refund']->description)->toBe(['en' => 'Money back'])
        ->and($defs['orders.view']->risk)->toBe('low')
        ->and($defs['orders.view']->module)->toBe('orders')
        ->and($defs['orders.refund']->sortOrder)->toBe(3);
});

it('adds package permissions only for enabled modules', function () {
    $names = collect((new PermissionRegistry(null, ['users', 'audit']))->definitions())->pluck('name')->all();

    expect($names)->toContain('access.users.view', 'access.audit.view')
        ->and($names)->not->toContain('access.roles.view');
});

it('fails loudly on a missing, malformed, duplicate or invalid file', function (string $file) {
    (new PermissionRegistry($file, []))->definitions();
})->with([
    'missing' => fn () => registryFile('does-not-exist'),
    'not array' => fn () => registryFile('not-array'),
    'duplicate' => fn () => registryFile('duplicate'),
    'bad risk' => fn () => registryFile('bad-risk'),
    'module not array' => fn () => registryFile('bad-module'),
    'array spec without label key' => fn () => registryFile('no-label-key'),
    'bare map with non-string value' => fn () => registryFile('bare-map-bad'),
    'empty array spec' => fn () => registryFile('empty-spec'),
    'list-shaped spec' => fn () => registryFile('list-spec'),
    'empty string in bare map' => fn () => registryFile('empty-string'),
    'non-string label' => fn () => registryFile('bad-label'),
    'non-string risk' => fn () => registryFile('bad-risk-type'),
])->throws(PermissionRegistryException::class);

it('ships arabic labels for package permissions', function () {
    $defs = collect((new PermissionRegistry(null, ['users']))->definitions())->keyBy('name');

    expect($defs['access.users.suspend']->label)->toHaveKeys(['en', 'ar'])->and($defs['access.users.suspend']->risk)->toBe('high');
});

it('treats a bare locale map as the label', function () {
    $bare = (new PermissionRegistry(registryFile('bare-map'), []))->definitions()[0];
    $keyed = collect((new PermissionRegistry(registryFile('valid'), []))->definitions())->keyBy('name')['orders.update'];

    expect($bare->label)->toBe(['en' => 'View orders', 'ar' => 'عرض الطلبات'])
        ->and($bare->risk)->toBe('low')->and($bare->description)->toBeNull()
        ->and([$bare->module, $bare->sortOrder])->toBe(['orders', 1])
        ->and($keyed->label)->toHaveKeys(['en', 'ar']);
});

it('ships a stub that the registry accepts', function () {
    expect((new PermissionRegistry(dirname(__DIR__, 2).'/stubs/permissions.php', []))->definitions())->toBe([]);
});

it('normalizes the bare locale map and the label-keyed form identically', function () {
    [$one, $two] = (new PermissionRegistry(registryFile('equivalence'), []))->definitions();

    expect($one->label)->toEqual($two->label)
        ->and($one->description)->toEqual($two->description)
        ->and($one->risk)->toEqual($two->risk)
        ->and([$one->sortOrder, $two->sortOrder])->toBe([1, 2]);
});
