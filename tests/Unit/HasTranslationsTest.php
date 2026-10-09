<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;

it('returns the requested locale', function () {
    $role = new Role(['name' => 'admin', 'label' => ['en' => 'Admin', 'ar' => 'مدير']]);

    expect($role->translated('label', 'ar'))->toBe('مدير')->and($role->translated('label', 'en'))->toBe('Admin');
});

it('falls back to the fallback locale, then any value, then null', function () {
    $role = new Role(['name' => 'admin', 'label' => ['en' => 'Admin']]);
    expect($role->translated('label', 'ar'))->toBe('Admin');

    $onlyFr = new Role(['name' => 'admin', 'label' => ['fr' => 'Administrateur']]);
    expect($onlyFr->translated('label', 'ar'))->toBe('Administrateur');

    expect((new Role(['name' => 'x']))->translated('label', 'ar'))->toBeNull()
        ->and((new Role(['name' => 'x', 'label' => ['en' => '']]))->translated('label'))->toBeNull();
});

it('uses the app locale by default and exposes label_text with a name fallback', function () {
    app()->setLocale('ar');
    $role = new Role(['name' => 'admin', 'label' => ['en' => 'Admin', 'ar' => 'مدير']]);
    expect($role->label_text)->toBe('مدير');

    expect((new Role(['name' => 'plain']))->label_text)->toBe('plain');
});
