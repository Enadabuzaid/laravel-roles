<?php
namespace Enadstack\LaravelRoles\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('roles.guard', 'web');
        $multi = (bool) config('roles.i18n.enabled', false);

        // 1) Base roles (package defaults) + merge with config
        $baseRoles   = ['super-admin', 'admin', 'user'];
        $configRoles = (array) config('roles.seed.roles', []);
        $roles       = array_values(array_unique(array_merge($baseRoles, $configRoles)));

        foreach ($roles as $name) {
            $attrs = ['name' => $name, 'guard_name' => $guard];

            // Optionally set description/label if the columns exist
            if ($this->colExists('roles', 'description')) {
                $attrs['description'] = $multi
                    ? $this->asJsonValue(config("roles.seed.role_descriptions.{$name}", ['en' => ucfirst($name)]))
                    : (string) config("roles.seed.role_descriptions.{$name}", ucfirst($name));
            }
            if ($multi && $this->colExists('roles', 'label')) {
                $attrs['label'] = $this->asJsonValue(config("roles.seed.role_labels.{$name}", ['en' => ucfirst($name)]));
            }

            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                $attrs
            );
        }

        // 2a) Flat permissions
        $flatPerms = (array) config('roles.seed.permissions', []);
        foreach ($flatPerms as $p) {
            $this->createPermission($p, $guard, $multi);
        }

        // 2b) Grouped permissions → "<group>.<action>"
        $groups = (array) config('roles.seed.permission_groups', []);
        foreach ($groups as $group => $actions) {
            foreach ((array) $actions as $action) {
                $permName = "{$group}.{$action}";
                $this->createPermission($permName, $guard, $multi, $group);
            }
        }

        // 3) Map permissions to roles (supports '*', 'group.*', explicit slugs)
        $map = (array) config('roles.seed.map', []);
        foreach ($map as $roleName => $permList) {
            $role = Role::where(['name' => $roleName, 'guard_name' => $guard])->first();
            if (! $role) {
                continue;
            }

            $expanded = [];
            foreach ((array) $permList as $perm) {
                if ($perm === '*') {
                    $expanded = Permission::where('guard_name', $guard)->pluck('name')->all();
                    break;
                }

                if ($this->endsWith($perm, '.*')) {
                    $prefix   = rtrim($perm, '.*');
                    $expanded = array_merge(
                        $expanded,
                        Permission::where('guard_name', $guard)
                            ->where('name', 'like', $prefix . '.%')
                            ->pluck('name')
                            ->all()
                    );
                } else {
                    $expanded[] = $perm;
                }
            }

            $role->syncPermissions(array_values(array_unique($expanded)));
        }
    }

    /**
     * Create a permission record with optional group/labels respecting i18n and existing columns.
     */
    protected function createPermission(string $name, string $guard, bool $multi, ?string $group = null): void
    {
        $attrs = ['name' => $name, 'guard_name' => $guard];

        // group slug
        if ($group && $this->colExists('permissions', 'group')) {
            $attrs['group'] = $group;
        }

        // description
        if ($this->colExists('permissions', 'description')) {
            $descConfig = config("roles.seed.permission_descriptions.{$name}");
            $defaultDesc = $group
                ? ucfirst($group) . ' ' . ucfirst(basename(str_replace('.', '/', $name)))
                : ucfirst(basename(str_replace('.', '/', $name)));

            if ($multi) {
                // For multi-language, ensure it's an array
                $attrs['description'] = $this->asJsonValue($descConfig ?: ['en' => $defaultDesc]);
            } else {
                // For single language, extract string value if array provided
                $attrs['description'] = is_array($descConfig)
                    ? ($descConfig['en'] ?? $descConfig[config('roles.i18n.default', 'en')] ?? $defaultDesc)
                    : ($descConfig ?: $defaultDesc);
            }
        }

        // labels (multi only)
        if ($multi && $this->colExists('permissions', 'label')) {
            $labelConfig = config("roles.seed.permission_labels.{$name}");
            $defaultLabel = ['en' => ucfirst(basename(str_replace('.', '/', $name)))];
            $attrs['label'] = $this->asJsonValue($labelConfig ?: $defaultLabel);
        }

        // group_label (multi only)
        if ($multi && $group && $this->colExists('permissions', 'group_label')) {
            $groupLabelConfig = config("roles.seed.permission_group_labels.{$group}");
            $attrs['group_label'] = $this->asJsonValue($groupLabelConfig ?: ['en' => ucfirst($group)]);
        }

        // Use updateOrCreate to ensure descriptions are updated if they change in config
        Permission::updateOrCreate(
            ['name' => $name, 'guard_name' => $guard],
            $attrs
        );
    }

    /**
     * Safely check if a table column exists (so seeder runs even when certain columns are omitted).
     */
    protected function colExists(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Normalize array → JSON for multi-language columns (guards against strings).
     */
    protected function asJsonValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        // Wrap string under default locale
        $default = config('roles.i18n.default', 'en');
        return [$default => (string) $value];
    }

    /**
     * Polyfill for str_ends_with for older PHP if needed via simple fallback.
     */
    protected function endsWith(string $haystack, string $needle): bool
    {
        if (function_exists('str_ends_with')) {
            return \str_ends_with($haystack, $needle);
        }
        $len = strlen($needle);
        return $len === 0 || (substr($haystack, -$len) === $needle);
    }
}