<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

final class DeactivateUserRequest extends UserLifecycleRequest
{
    protected function ability(): string
    {
        return 'suspend';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
