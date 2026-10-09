<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Closure;
use Illuminate\Support\Facades\DB;

abstract class Action
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
