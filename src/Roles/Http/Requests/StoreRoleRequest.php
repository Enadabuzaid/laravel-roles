<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Http\Support\TranslatedFieldRules;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gate = Gate::forUser($this->user());

        // Granting permissions at creation is a role update as well: `create` alone only makes an empty role.
        $grantsPermissions = is_array($this->input('permissions')) && $this->input('permissions') !== [];

        return $gate->allows('create', Role::class) && (! $grantsPermissions || $gate->allows('update', Role::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->roleRules(partial: false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function roleRules(bool $partial, ?int $ignoreId = null): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $table = (new Role)->getTable();

        $unique = Rule::unique($table, 'name')
            ->where('guard_name', (string) config('roles.guard'))
            ->whereNull('deleted_at')
            ->ignore($ignoreId);

        $rules = [
            'name' => [$presence, 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/', $unique],
            ...TranslatedFieldRules::rules($partial),
            'color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];

        if (! $partial) {
            $rules['permissions'] = PermissionReferenceRules::list();
            $rules['confirm'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function roleData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        unset($validated['permissions'], $validated['confirm']);

        return $validated;
    }

    /** @return list<int|string> */
    public function permissionReferences(): array
    {
        return PermissionReferenceRules::normalize($this->validated('permissions', []));
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}
