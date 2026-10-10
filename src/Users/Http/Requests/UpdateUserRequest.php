<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Enadstack\LaravelRoles\Users\Support\UserLookup;

/** Name and/or email of a member; at least one is required. */
final class UpdateUserRequest extends UserLifecycleRequest
{
    protected function ability(): string
    {
        return 'update';
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('name'))) {
            $merge['name'] = trim((string) $this->input('name'));
        }

        if (is_string($this->input('email'))) {
            $merge['email'] = UserLookup::normalize((string) $this->input('email'));
        }

        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required_without:email', 'nullable', 'string', 'min:1', 'max:255'],
            'email' => ['required_without:name', 'nullable', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /** @return array{name?: string, email?: string} */
    public function changes(): array
    {
        $changes = [];

        foreach (['name', 'email'] as $field) {
            $value = $this->validated($field);

            if (is_string($value) && $value !== '') {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }
}
