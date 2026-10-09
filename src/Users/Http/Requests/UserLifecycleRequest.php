<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /** @throws ModelNotFoundException */
    public function targetUser(): Model
    {
        $model = config('roles.user_model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new \LogicException('roles.user_model must be an Eloquent model class.');
        }

        $id = $this->route('user');

        return $model::query()->findOrFail(is_scalar($id) ? $id : 0);
    }
}
