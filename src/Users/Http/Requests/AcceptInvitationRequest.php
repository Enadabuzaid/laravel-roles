<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Public: no authenticated user. Only the five declared fields are ever read, so a `role`, `roles` or
 * `is_admin` in the body is ignored.
 */
final class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ];
    }

    public function token(): string
    {
        return (string) $this->validated('token');
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /** @return array{name: string, password: string} */
    public function profile(): array
    {
        return ['name' => trim((string) $this->validated('name')), 'password' => (string) $this->validated('password')];
    }
}
