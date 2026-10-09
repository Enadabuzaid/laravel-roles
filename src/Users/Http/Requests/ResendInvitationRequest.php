<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

final class ResendInvitationRequest extends InvitationRequest
{
    protected function ability(): string
    {
        return 'create';
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
