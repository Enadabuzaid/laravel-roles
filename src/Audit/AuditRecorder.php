<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit;

use Enadstack\ActionLogger\Services\ActionLogger;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AuditRecorder
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * Records an audit entry after the surrounding transaction commits; failures are reported, never thrown.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $module,
        string $verb,
        ?Model $subject,
        ?array $old = null,
        ?array $new = null,
        array $meta = [],
        Severity $severity = Severity::Info,
    ): void {
        if (! config('roles.modules.audit') || ! config('roles.audit.enabled', true) || ! config('action-logger.enabled', true)) {
            return;
        }

        $actor = $this->actors->actor();

        DB::afterCommit(function () use ($module, $verb, $subject, $old, $new, $meta, $severity, $actor): void {
            try {
                app(ActionLogger::class)->log(
                    $subject,
                    $actor,
                    "{$module}.{$verb}",
                    $old,
                    $new,
                    $meta === [] ? null : $meta,
                    module: $module,
                    severity: $severity->value,
                    subjectLabel: $this->labelOf($subject) ?? $this->labelFromValues($old),
                    actorLabel: $this->labelOf($actor),
                );
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /** The human label snapshot of a model in the default locale: translated label, then name, then email. */
    private function labelOf(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $candidates = [];

        if (method_exists($model, 'translated') && is_array($model->getAttribute('label'))) {
            $candidates[] = $model->translated('label', (string) config('roles.default_locale', 'en'));
        }

        $candidates[] = $model->getAttribute('name');
        $candidates[] = $model->getAttribute('email');

        return $this->firstString($candidates);
    }

    /**
     * The label of a subject that is gone (deletes record no subject): from the values it had.
     *
     * @param  array<string, mixed>|null  $values
     */
    private function labelFromValues(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        $label = $values['label'] ?? null;
        $locale = (string) config('roles.default_locale', 'en');

        return $this->firstString([
            is_array($label) ? ($label[$locale] ?? null) : $label,
            $values['name'] ?? null,
            $values['email'] ?? null,
        ]);
    }

    /** @param  list<mixed>  $candidates */
    private function firstString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
