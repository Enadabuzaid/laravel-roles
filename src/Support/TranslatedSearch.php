<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

/**
 * Matches a search term against the per-locale values of translated JSON columns only,
 * never against the JSON keys or punctuation.
 */
final class TranslatedSearch
{
    /**
     * Adds OR conditions to $query (call it inside a where-group).
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns
     * @param  list<string>  $locales
     */
    public static function apply(Builder $query, string $term, array $columns, array $locales): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';
        $escapeManually = $query->getQuery()->getGrammar() instanceof SQLiteGrammar;

        foreach ($columns as $column) {
            foreach ($locales as $locale) {
                $path = $column.'->'.$locale;

                if ($escapeManually) {
                    $query->orWhere(new EscapedLikeCondition($path))->addBinding($like, 'where');
                } else {
                    $query->orWhereLike($path, $like);
                }
            }
        }
    }
}
