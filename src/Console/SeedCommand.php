<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Roles\Actions\SeedDefaultRoles;
use Illuminate\Console\Command;

class SeedCommand extends Command
{
    protected $signature = 'roles:seed';

    protected $description = 'Create the default roles (idempotent).';

    public function handle(SeedDefaultRoles $seed): int
    {
        $roles = $seed->handle();

        $this->components->info('Default roles ready: '.$roles->keys()->implode(', '));

        return self::SUCCESS;
    }
}
