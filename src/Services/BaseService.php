<?php

namespace Enadstack\LaravelRoles\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

abstract class BaseService
{
    /**
     * Calculate growth statistics for a model
     *
     * @param string $modelClass The fully qualified model class name (e.g., App\Models\Role::class)
     * @param string $dateColumn The date column to use (default: created_at)
     * @param array $periods The periods to calculate growth for
     * @return array
     */
    protected function calculateGrowth(string $modelClass, string $dateColumn = 'created_at', array $periods = []): array
    {
        $defaultPeriods = [
            'last_7_days',
            'last_30_days',
            'last_3_months',
            'last_6_months',
            'last_year',
        ];

        $periods = empty($periods) ? $defaultPeriods : $periods;
        $growth = [];

        foreach ($periods as $period) {
            $growth[$period] = $this->calculatePeriodGrowth($modelClass, $dateColumn, $period);
        }

        return $growth;
    }

    /**
     * Calculate growth for a specific period
     *
     * @param string $modelClass
     * @param string $dateColumn
     * @param string $period
     * @return array
     */
    protected function calculatePeriodGrowth(string $modelClass, string $dateColumn, string $period): array
    {
        [$currentStart, $previousStart, $previousEnd] = $this->getPeriodDates($period);

        // Current period count
        $currentCount = $modelClass::where($dateColumn, '>=', $currentStart)->count();

        // Previous period count
        $previousCount = $modelClass::whereBetween($dateColumn, [$previousStart, $previousEnd])->count();

        // Calculate percentage
        $percentage = $previousCount > 0
            ? round((($currentCount - $previousCount) / $previousCount) * 100, 2)
            : ($currentCount > 0 ? 100 : 0);

        return [
            'current' => $currentCount,
            'previous' => $previousCount,
            'difference' => $currentCount - $previousCount,
            'percentage' => $percentage,
            'trend' => $this->getTrend($percentage),
        ];
    }

    /**
     * Get date ranges for a period
     *
     * @param string $period
     * @return array [currentStart, previousStart, previousEnd]
     */
    protected function getPeriodDates(string $period): array
    {
        $now = Carbon::now();

        return match($period) {
            'last_7_days' => [
                $now->copy()->subDays(7),
                $now->copy()->subDays(14),
                $now->copy()->subDays(7),
            ],
            'last_30_days', 'last_month' => [
                $now->copy()->subDays(30),
                $now->copy()->subDays(60),
                $now->copy()->subDays(30),
            ],
            'last_3_months' => [
                $now->copy()->subMonths(3),
                $now->copy()->subMonths(6),
                $now->copy()->subMonths(3),
            ],
            'last_6_months' => [
                $now->copy()->subMonths(6),
                $now->copy()->subMonths(12),
                $now->copy()->subMonths(6),
            ],
            'last_year' => [
                $now->copy()->subYear(),
                $now->copy()->subYears(2),
                $now->copy()->subYear(),
            ],
            'this_week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            default => [
                $now->copy()->subDays(7),
                $now->copy()->subDays(14),
                $now->copy()->subDays(7),
            ],
        };
    }

    /**
     * Get trend indicator based on percentage
     *
     * @param float $percentage
     * @return string
     */
    protected function getTrend(float $percentage): string
    {
        if ($percentage > 0) {
            return 'up';
        } elseif ($percentage < 0) {
            return 'down';
        }
        return 'stable';
    }

    /**
     * Calculate growth with custom query builder
     *
     * @param Builder $currentQuery
     * @param Builder $previousQuery
     * @return array
     */
    protected function calculateCustomGrowth(Builder $currentQuery, Builder $previousQuery): array
    {
        $currentCount = $currentQuery->count();
        $previousCount = $previousQuery->count();

        $percentage = $previousCount > 0
            ? round((($currentCount - $previousCount) / $previousCount) * 100, 2)
            : ($currentCount > 0 ? 100 : 0);

        return [
            'current' => $currentCount,
            'previous' => $previousCount,
            'difference' => $currentCount - $previousCount,
            'percentage' => $percentage,
            'trend' => $this->getTrend($percentage),
        ];
    }
}

