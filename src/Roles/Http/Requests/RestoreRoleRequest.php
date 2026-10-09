<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RestoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('restore', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirm' => ['sometimes', 'boolean']];
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}
