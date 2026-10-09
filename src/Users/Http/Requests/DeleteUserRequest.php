<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

final class DeleteUserRequest extends UserLifecycleRequest
{
    protected function ability(): string
    {
        return 'delete';
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
