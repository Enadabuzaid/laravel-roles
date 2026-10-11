<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Support;

use Enadstack\ActionLogger\Support\Redactor;

/**
 * The field-level diff of an audit entry. Top-level keys only: lists of scalars are diffed as sets (`from` holds
 * the removed items and `to` the added ones), anything else is compared by its JSON form.
 */
final class ChangeSet
{
    /**
     * @param  array<array-key, mixed>|null  $old
     * @param  array<array-key, mixed>|null  $new
     * @return list<array{field: string, from: mixed, to: mixed, kind: 'added'|'removed'|'changed'}>
     */
    public static function between(?array $old, ?array $new, ?Redactor $redactor = null): array
    {
        $changes = self::diff($old, $new);

        if ($redactor === null) {
            return $changes;
        }

        // The diff runs on the stored values so a changed secret still shows as a change; only its values are hidden.
        return array_map(static function (array $change) use ($redactor): array {
            $hidden = $redactor->redact([$change['field'] => ['from' => $change['from'], 'to' => $change['to']]]);
            $pair = $hidden[$change['field']] ?? [];

            return [...$change, 'from' => is_array($pair) ? ($pair['from'] ?? null) : $pair, 'to' => is_array($pair) ? ($pair['to'] ?? null) : $pair];
        }, $changes);
    }

    /**
     * @param  array<array-key, mixed>|null  $old
     * @param  array<array-key, mixed>|null  $new
     * @return list<array{field: string, from: mixed, to: mixed, kind: 'added'|'removed'|'changed'}>
     */
    private static function diff(?array $old, ?array $new): array
    {
        $old ??= [];
        $new ??= [];
        $fields = array_values(array_unique([...array_keys($old), ...array_keys($new)]));
        sort($fields, SORT_STRING);

        $changes = [];

        foreach ($fields as $field) {
            $field = (string) $field;
            $hasOld = array_key_exists($field, $old);
            $hasNew = array_key_exists($field, $new);
            $from = $old[$field] ?? null;
            $to = $new[$field] ?? null;

            if (self::isScalarList($from) && self::isScalarList($to)
                || (self::isScalarList($from) && ! $hasNew)
                || (self::isScalarList($to) && ! $hasOld)) {
                $before = is_array($from) ? $from : [];
                $after = is_array($to) ? $to : [];
                $removed = array_values(array_filter($before, static fn (mixed $v): bool => ! in_array($v, $after, true)));
                $added = array_values(array_filter($after, static fn (mixed $v): bool => ! in_array($v, $before, true)));

                if ($removed === [] && $added === []) {
                    continue;
                }

                $changes[] = [
                    'field' => $field,
                    'from' => $removed,
                    'to' => $added,
                    'kind' => $removed === [] ? 'added' : ($added === [] ? 'removed' : 'changed'),
                ];

                continue;
            }

            if ($hasOld && $hasNew && json_encode($from) === json_encode($to)) {
                continue;
            }

            if (! $hasOld && ! $hasNew) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => $from,
                'to' => $to,
                'kind' => ! $hasOld ? 'added' : (! $hasNew ? 'removed' : 'changed'),
            ];
        }

        return $changes;
    }

    private static function isScalarList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }
}
