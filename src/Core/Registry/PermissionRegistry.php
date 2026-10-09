<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Registry;

final class PermissionRegistry
{
    private const RISKS = ['low', 'medium', 'high'];

    /** @param  list<string>  $enabledModules */
    public function __construct(
        private readonly ?string $file,
        private readonly array $enabledModules,
    ) {}

    /** @return list<PermissionDefinition> */
    public function definitions(): array
    {
        $definitions = PackagePermissions::definitions($this->enabledModules);

        if ($this->file !== null) {
            $definitions = [...$definitions, ...$this->fromFile($this->file)];
        }

        $seen = [];
        foreach ($definitions as $definition) {
            if (isset($seen[$definition->name])) {
                throw new PermissionRegistryException("Duplicate permission [{$definition->name}] in the registry.");
            }
            $seen[$definition->name] = true;
        }

        return $definitions;
    }

    /** @return list<PermissionDefinition> */
    private function fromFile(string $file): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new PermissionRegistryException("Permissions file not found or unreadable: {$file}");
        }

        $data = require $file;

        if (! is_array($data)) {
            throw new PermissionRegistryException("Permissions file must return an array: {$file}");
        }

        $definitions = [];

        foreach ($data as $module => $group) {
            if (! is_array($group)) {
                throw new PermissionRegistryException("Module [{$module}] must be an array.");
            }

            $permissions = $group['permissions'] ?? [];
            if (! is_array($permissions)) {
                throw new PermissionRegistryException("Module [{$module}] permissions must be an array.");
            }

            $order = 0;
            foreach ($permissions as $name => $spec) {
                $name = (string) $name;

                if (is_array($spec) && ! array_key_exists('label', $spec)) {
                    throw new PermissionRegistryException("Permission [{$name}] is an array without a [label] key; use 'label' => ... (or a plain string).");
                }

                $spec = is_array($spec) ? $spec : ['label' => $spec];
                $risk = $spec['risk'] ?? 'low';

                if (! is_string($risk) || ! in_array($risk, self::RISKS, true)) {
                    throw new PermissionRegistryException("Permission [{$name}] has invalid risk.");
                }

                $definitions[] = new PermissionDefinition(
                    name: $name,
                    module: (string) $module,
                    label: $this->translations($spec['label'], $name),
                    description: isset($spec['description']) ? $this->translations($spec['description'], $name) : null,
                    risk: $risk,
                    sortOrder: ++$order,
                );
            }
        }

        return $definitions;
    }

    /**
     * @return array<string, string>
     */
    private function translations(mixed $value, string $name): array
    {
        if (! is_string($value) && ! is_array($value)) {
            throw new PermissionRegistryException("Permission [{$name}] label/description must be a string or a locale => string array.");
        }

        if (is_string($value)) {
            return [(string) config('roles.default_locale', 'en') => $value];
        }

        if ($value === []) {
            throw new PermissionRegistryException("Permission [{$name}] has an empty label.");
        }

        $normalized = [];
        foreach ($value as $locale => $text) {
            if (! is_string($text)) {
                throw new PermissionRegistryException("Permission [{$name}] translations must be strings.");
            }
            $normalized[(string) $locale] = $text;
        }

        return $normalized;
    }
}
