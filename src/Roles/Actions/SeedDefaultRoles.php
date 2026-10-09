<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Illuminate\Support\Collection;

final class SeedDefaultRoles extends Action
{
    /**
     * @param  list<string>  $only
     * @return Collection<string, Role>
     */
    public function handle(array $only = []): Collection
    {
        $guard = (string) config('roles.guard');
        /** @var array<string, array<string, mixed>> $templates */
        $templates = config('roles.default_roles', []);

        return $this->transaction(function () use ($templates, $only, $guard): Collection {
            /** @var Collection<string, Role> $roles */
            $roles = collect();

            foreach ($templates as $name => $template) {
                if ($only !== [] && ! in_array($name, $only, true)) {
                    continue;
                }

                $role = Role::withTrashed()->where('name', $name)->where('guard_name', $guard)->first();

                if ($role === null) {
                    $role = Role::query()->create(['name' => $name, 'guard_name' => $guard, ...$template]);
                } elseif ($role->trashed()) {
                    $role->restore();
                }

                $roles->put($name, $role);
            }

            return $roles;
        });
    }
}
