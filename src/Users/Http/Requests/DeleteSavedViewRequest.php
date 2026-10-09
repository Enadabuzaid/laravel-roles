<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Queries\SavedViewsQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class DeleteSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', (string) config('roles.user_model'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /**
     * The current user's own view; any other id (including another user's) is a 404, never a 403.
     *
     * @throws ModelNotFoundException
     */
    public function view(): SavedView
    {
        $id = $this->route('view');

        return SavedViewsQuery::own((string) $this->user()?->getAuthIdentifier(), is_scalar($id) ? $id : 0);
    }
}
