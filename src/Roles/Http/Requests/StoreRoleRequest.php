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
        return Gate::forUser($this->user())->allows('create', Role::class);
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

        return [
            'name' => [$presence, 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/', $unique],
            ...TranslatedFieldRules::rules($partial),
            'color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'permissions' => ['sometimes', 'array', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['permissions.max' => 'Set permissions through the role permissions endpoint after creating the role.'];
    }

    /** @return array<string, mixed> */
    public function roleData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        unset($validated['permissions']);

        return $validated;
    }
}
