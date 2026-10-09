<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Concerns;

trait HasTranslations
{
    public function translated(string $attribute, ?string $locale = null): ?string
    {
        $value = $this->getAttribute($attribute);

        if (is_string($value)) {
            return $value !== '' ? $value : null;
        }

        if (! is_array($value)) {
            return null;
        }

        $locale ??= app()->getLocale();
        $fallback = (string) config('roles.fallback_locale', 'en');

        foreach ([$locale, $fallback] as $candidate) {
            if (isset($value[$candidate]) && is_string($value[$candidate]) && $value[$candidate] !== '') {
                return $value[$candidate];
            }
        }

        foreach ($value as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
