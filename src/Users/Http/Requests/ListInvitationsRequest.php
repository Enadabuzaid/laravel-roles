<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

final class ListInvitationsRequest extends InvitationRequest
{
    protected function ability(): string
    {
        return 'viewAny';
    }
}
