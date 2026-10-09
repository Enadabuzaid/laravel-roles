<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Support;

/**
 * Validation rules for the translated `label` (default locale required) and `description` maps.
 */
final class TranslatedFieldRules
{
    /** @return array<string, list<string>> */
    public static function rules(bool $partial): array
    {
        $locales = array_values((array) config('roles.locales', ['en']));
        $default = (string) config('roles.default_locale', 'en');

        return [
            'label' => [$partial ? 'sometimes' : 'required', 'array:'.implode(',', $locales)],
            "label.{$default}" => [$partial ? 'required_with:label' : 'required', 'string', 'min:1', 'max:120'],
            'label.*' => ['string', 'min:1', 'max:120'],
            'description' => ['sometimes', 'nullable', 'array:'.implode(',', $locales)],
            'description.*' => ['string', 'min:1', 'max:500'],
        ];
    }
}
