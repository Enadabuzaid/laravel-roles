<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

final class RevokeInvitationRequest extends InvitationRequest
{
    protected function ability(): string
    {
        return 'revoke';
    }
}
