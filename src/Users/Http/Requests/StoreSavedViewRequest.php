<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

use Enadstack\LaravelRoles\Users\Models\SavedView;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class StoreSavedViewRequest extends FormRequest
{
    private const MAX_KEYS = 20;

    private const MAX_JSON_BYTES = 2048;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', (string) config('roles.user_model'));
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $page = $this->input('page');

        return [
            'page' => ['required', 'string', Rule::in(SavedViewPages::all())],
            'name' => [
                'required', 'string', 'min:1', 'max:80',
                Rule::unique((new SavedView)->getTable(), 'name')->where('user_id', $this->user()?->getAuthIdentifier())->where('page', is_string($page) ? $page : ''),
            ],
            'filters' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                $problem = $this->filtersProblem($value);
                if ($problem !== null) {
                    $fail($problem);
                }
            }],
        ];
    }

    public function page(): string
    {
        return (string) $this->validated('page');
    }

    public function viewName(): string
    {
        return (string) $this->validated('name');
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $filters = $this->validated('filters');

        /** @var array<string, mixed> */
        return is_array($filters) ? $filters : [];
    }

    /** @param  array<mixed>  $filters */
    private function filtersProblem(array $filters): ?string
    {
        if (count($filters) > self::MAX_KEYS) {
            return 'The filters may contain at most '.self::MAX_KEYS.' entries.';
        }

        foreach ($filters as $key => $value) {
            if (! is_string($key) || $key === '' || strlen($key) > 64) {
                return 'The filters must be an object keyed by filter name.';
            }

            if (is_array($value)) {
                if (! array_is_list($value) || count(array_filter($value, 'is_scalar')) !== count($value)) {
                    return 'Filter values must be a scalar or a list of scalars.';
                }
            } elseif (! is_scalar($value)) {
                return 'Filter values must be a scalar or a list of scalars.';
            }
        }

        if (strlen((string) json_encode($filters)) > self::MAX_JSON_BYTES) {
            return 'The filters are too large (2 KB at most).';
        }

        return null;
    }
}
