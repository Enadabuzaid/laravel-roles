<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

final class RestoreUserRequest extends UserLifecycleRequest
{
    protected function ability(): string
    {
        return 'delete';
    }

    protected function acceptsTrashed(): bool
    {
        return true;
    }
}
