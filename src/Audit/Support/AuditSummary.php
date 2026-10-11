<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Support;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\AuditModules;

/**
 * The translatable one-line summary of an entry: a lang key plus string params, so the client (or the CSV
 * writer) renders it in the viewer's language.
 */
final class AuditSummary
{
    /** @return array{key: string, params: array<string, string>} */
    public static function for(ActionLog $log): array
    {
        $params = [
            'actor' => self::label($log->actor_label, $log->actor_id, $log->actor_type === null ? (string) trans('access::audit.system') : null),
            'subject' => self::label($log->subject_label, $log->subject_id, null),
        ];

        $meta = is_array($log->meta) ? $log->meta : [];
        $new = is_array($log->new_values) ? $log->new_values : [];

        foreach (['role', 'reason'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key]) && $meta[$key] !== '') {
                $params[$key] = mb_substr($meta[$key], 0, 190);
            }
        }

        foreach ([$meta['members_count'] ?? null, $meta['revoked_invitations'] ?? null, $new['created'] ?? null] as $count) {
            if (is_int($count)) {
                $params['count'] = (string) $count;
                break;
            }
        }

        $known = AuditModules::isKnownEvent($log->event);

        if (! $known) {
            $params['event'] = $log->event;
        }

        return [
            'key' => 'access.audit.events.'.($known ? $log->event : 'generic'),
            'params' => $params,
        ];
    }

    private static function label(?string $label, int|string|null $id, ?string $fallback): string
    {
        if ($label !== null && $label !== '') {
            return $label;
        }

        if ($id !== null) {
            return '#'.$id;
        }

        return $fallback ?? '';
    }
}
