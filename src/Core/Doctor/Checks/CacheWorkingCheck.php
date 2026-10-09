<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Illuminate\Support\Facades\Cache;

final class CacheWorkingCheck implements Check
{
    public function name(): string
    {
        return 'cache-working';
    }

    public function run(): CheckResult
    {
        $key = 'roles:doctor:'.bin2hex(random_bytes(4));

        try {
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
        } catch (\Throwable $e) {
            return CheckResult::fail('Cache error: '.$e->getMessage());
        }

        return $value === 'ok'
            ? CheckResult::pass('Cache store round-trips.')
            : CheckResult::fail('Cache store did not return the stored value.');
    }
}
