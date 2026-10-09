<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Requests;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\Gate;

final class UpdatePermissionRequest extends StorePermissionRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', Permission::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->permissionRules(partial: true);
    }
}
