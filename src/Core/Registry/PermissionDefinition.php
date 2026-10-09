<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Registry;

final class PermissionDefinition
{
    /**
     * @param  array<string, string>  $label
     * @param  array<string, string>|null  $description
     */
    public function __construct(
        public readonly string $name,
        public readonly string $module,
        public readonly array $label,
        public readonly ?array $description,
        public readonly string $risk,
        public readonly int $sortOrder,
    ) {}
}
