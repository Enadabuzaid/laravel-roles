<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ListSavedViewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', (string) config('roles.user_model'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['page' => ['nullable', 'string', Rule::in(SavedViewPages::all())]];
    }

    public function page(): ?string
    {
        $page = $this->validated('page');

        return is_string($page) ? $page : null;
    }
}
