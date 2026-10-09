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
                'bail', 'required', 'string', 'min:1', 'max:80',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                        $fail('The name must be valid UTF-8 text.');
                    }
                },
                Rule::unique((new SavedView)->getTable(), 'name')->where('user_id', $this->user()?->getAuthIdentifier())->where('page', is_string($page) ? $page : ''),
            ],
            'filters' => ['bail', 'nullable', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
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

        if (! $this->isValidUtf8($filters)) {
            return 'The filters must be valid UTF-8 text.';
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

        try {
            if (strlen(json_encode($filters, JSON_THROW_ON_ERROR)) > self::MAX_JSON_BYTES) {
                return 'The filters are too large (2 KB at most).';
            }
        } catch (\JsonException) {
            return 'The filters must be valid UTF-8 text.';
        }

        return null;
    }

    /**
     * Every key and string value, recursively, must be valid UTF-8.
     *
     * @param  array<mixed>  $values
     */
    private function isValidUtf8(array $values): bool
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && ! mb_check_encoding($key, 'UTF-8')) {
                return false;
            }

            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                return false;
            }

            if (is_array($value) && ! $this->isValidUtf8($value)) {
                return false;
            }
        }

        return true;
    }
}
