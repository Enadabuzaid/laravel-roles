<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Http\Requests;

use Enadstack\ActionLogger\Models\ActionLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Authorises the audit read routes (list, detail, overview, actors); query values are parsed leniently by AuditFilters. */
class AuditListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows($this->ability(), ActionLog::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function ability(): string
    {
        return 'viewAny';
    }
}
