<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Support\HostSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Base of the user lifecycle requests: authorises the policy ability named by the subclass and resolves the
 * target user from the configured user model (no global route binder).
 */
abstract class UserLifecycleRequest extends FormRequest
{
    /** The UserPolicy ability (`update`, `suspend` or `delete`). */
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows($this->ability(), (string) config('roles.user_model'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** Whether the action works on a trashed user (restore and permanent delete); live-only actions answer 409 user.trashed. */
    protected function acceptsTrashed(): bool
    {
        return false;
    }

    /**
     * @throws ModelNotFoundException
     * @throws AccessException 409 user.trashed for a trashed user when the action is live-only
     */
    public function targetUser(): Model
    {
        $model = config('roles.user_model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new \LogicException('roles.user_model must be an Eloquent model class.');
        }

        $id = $this->route('user');
        $query = $model::query();

        if (HostSoftDeletes::usedBy($model)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $user = $query->findOrFail(is_scalar($id) ? $id : 0);

        if (! $this->acceptsTrashed() && method_exists($user, 'trashed') && $user->trashed()) {
            throw new AccessException((string) trans('access::user.trashed'), 'user.trashed', 409);
        }

        return $user;
    }
}
