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
                );
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
