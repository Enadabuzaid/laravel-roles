<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Requests;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CheckAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('view', Permission::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user' => ['required', 'string', 'regex:/^[0-9]{1,18}$/'],
            'permission' => ['required', 'string', 'max:255'],
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
