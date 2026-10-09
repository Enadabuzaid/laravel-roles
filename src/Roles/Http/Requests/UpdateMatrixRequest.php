<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

final class UpdateMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'changes' => ['required', 'array', 'min:1', 'max:'.PermissionReferenceRules::MAX_ROLES],
            'changes.*' => ['array'],
            'changes.*.grant' => PermissionReferenceRules::list(),
            'changes.*.revoke' => PermissionReferenceRules::list(),
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (array_keys((array) $this->input('changes', [])) as $key) {
                if (preg_match('/^[1-9][0-9]{0,17}$/', (string) $key) !== 1) {
                    $validator->errors()->add('changes', 'Each key of changes must be a role id.');

                    return;
                }
            }
        }];
    }

    /**
     * @return array<int, array{grant: list<int|string>, revoke: list<int|string>}>
     */
    public function changes(): array
    {
        $changes = [];

        foreach ((array) $this->validated('changes') as $roleId => $change) {
            $change = (array) $change;
            $changes[(int) $roleId] = [
                'grant' => PermissionReferenceRules::normalize($change['grant'] ?? []),
                'revoke' => PermissionReferenceRules::normalize($change['revoke'] ?? []),
            ];
        }

        return $changes;
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}
