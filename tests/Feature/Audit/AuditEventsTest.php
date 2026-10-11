<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Audit\AuditModules;
use Symfony\Component\Finder\Finder;

it('has an english and arabic summary for every known event and a generic fallback', function (): void {
    foreach (['en', 'ar'] as $locale) {
        foreach (AuditModules::EVENTS as $event) {
            $key = 'access::audit.events.'.$event;
            expect(trans($key, [], $locale))->not->toBe($key, "{$event} ({$locale})");
        }
        expect(trans('access::audit.events.generic', [], $locale))->not->toBe('access::audit.events.generic');
    }
});

it('knows every event the package records', function (): void {
    $found = [];

    foreach ((new Finder)->files()->name('*.php')->in(dirname(__DIR__, 3).'/src') as $file) {
        if (preg_match_all("/record\(\s*'([a-z]+)',\s*'([a-z_]+)'/", $file->getContents(), $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $found[] = $match[1].'.'.$match[2];
            }
        }
    }

    expect($found)->not->toBeEmpty()->and(array_values(array_diff($found, AuditModules::EVENTS)))->toBe([]);
});
