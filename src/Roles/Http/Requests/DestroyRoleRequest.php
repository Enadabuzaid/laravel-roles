<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class DestroyRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('delete', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirmation' => ['sometimes', 'nullable', 'string', 'max:255']];
    }

    public function confirmation(): ?string
    {
        $value = $this->validated('confirmation');

        return is_string($value) ? $value : null;
    }
}
