<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Base of the requests that act on one invitation: authorises the policy ability and resolves the row (no global route binder). */
abstract class InvitationRequest extends FormRequest
{
    /** The InvitationPolicy ability (`viewAny`, `create` or `revoke`). */
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows($this->ability(), AccessInvitation::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @throws ModelNotFoundException */
    public function invitation(): AccessInvitation
    {
        $id = $this->route('invitation');

        return AccessInvitation::query()->findOrFail(is_scalar($id) ? $id : 0);
    }
}
