<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Support\HostSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Base for the user-role endpoints: authorises through the role policy and resolves the
 * target user from the configured user model (no global route binder).
 */
class UserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('assign', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /**
     * @throws ModelNotFoundException
     * @throws AccessException 409 user.trashed for a user in the trash
     */
    public function targetUser(): AccessUser&Model
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

        if (method_exists($user, 'trashed') && $user->trashed()) {
            throw new AccessException((string) trans('access::user.trashed'), 'user.trashed', 409);
        }

        if (! $user instanceof AccessUser) {
            throw new \LogicException('roles.user_model must implement AccessUser.');
        }

        return $user;
    }
}
