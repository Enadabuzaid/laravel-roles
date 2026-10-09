<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Roles\Actions\SeedDefaultRoles;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Console\Command;

class SeedCommand extends Command
{
    protected $signature = 'roles:seed';

    protected $description = 'Create the default roles (idempotent).';

    public function handle(SeedDefaultRoles $seed): int
    {
        $roles = app(ActorContext::class)->runAs(null, fn () => $seed->handle());

        $this->components->info('Default roles ready: '.$roles->keys()->implode(', '));

        return self::SUCCESS;
    }
}
