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
            $order = 0;
            foreach (($group['permissions'] ?? []) as $name => $spec) {
                $spec = is_array($spec) && array_key_exists('label', $spec) ? $spec : ['label' => $spec];
                $risk = $spec['risk'] ?? 'low';

                if (! in_array($risk, self::RISKS, true)) {
                    throw new PermissionRegistryException("Permission [{$name}] has invalid risk [{$risk}].");
                }

                $definitions[] = new PermissionDefinition(
                    name: (string) $name,
                    module: (string) $module,
                    label: $this->translations($spec['label'], (string) $name),
                    description: isset($spec['description']) ? $this->translations($spec['description'], (string) $name) : null,
                    risk: $risk,
                    sortOrder: ++$order,
                );
            }
        }

        return $definitions;
    }

    /**
     * @param  string|array<string, string>  $value
     * @return array<string, string>
     */
    private function translations(string|array $value, string $name): array
    {
        if (is_string($value)) {
            return [(string) config('roles.default_locale', 'en') => $value];
        }

        if ($value === []) {
            throw new PermissionRegistryException("Permission [{$name}] has an empty label.");
        }

        return $value;
    }
}
