<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Requests;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SyncPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('sync', Permission::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['dry_run' => ['sometimes', 'boolean'], 'prune' => ['sometimes', 'boolean']];
    }

    public function dryRun(): bool
    {
        return $this->boolean('dry_run');
    }

    public function prune(): bool
    {
        return $this->boolean('prune');
    }
}
