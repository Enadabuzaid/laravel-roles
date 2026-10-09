<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Requests;

use Enadstack\LaravelRoles\Http\Support\TranslatedFieldRules;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', Permission::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->permissionRules(partial: false);
    }

    /** @return array<string, mixed> */
    protected function permissionRules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $rules = [
            'module' => [$presence, 'string', 'min:1', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            ...TranslatedFieldRules::rules($partial),
            'risk' => ['sometimes', Rule::in([Permission::RISK_LOW, Permission::RISK_MEDIUM, Permission::RISK_HIGH])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];

        if (! $partial) {
            $rules['name'] = [
                'required', 'string', 'min:2', 'max:120', 'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique((new Permission)->getTable(), 'name')->where('guard_name', (string) config('roles.guard')),
            ];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function permissionData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated;
    }
}
