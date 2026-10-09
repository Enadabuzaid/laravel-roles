<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

final class UnlockUserRequest extends UserLifecycleRequest
{
    protected function ability(): string
    {
        return 'update';
    }
}
