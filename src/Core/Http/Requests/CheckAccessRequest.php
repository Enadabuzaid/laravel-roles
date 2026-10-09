<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Requests;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CheckAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gate = Gate::forUser($this->user());

        return $gate->allows('view', Permission::class) && $gate->allows('access.users.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user' => ['required', 'string', 'regex:/^[0-9]{1,18}$/'],
            'permission' => [
                'required', 'string', 'max:255',
                Rule::exists((new Permission)->getTable(), 'name')->where('guard_name', (string) config('roles.guard')),
            ],
        ];
    }

    public function userId(): string
    {
        return (string) $this->validated('user');
    }

    public function permissionName(): string
    {
        return (string) $this->validated('permission');
    }
}
