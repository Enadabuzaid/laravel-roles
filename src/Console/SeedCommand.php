<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Roles\Actions\SeedDefaultRoles;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\TeamContext;
use Illuminate\Console\Command;

class SeedCommand extends Command
{
    protected $signature = 'roles:seed {--team= : Seed the roles for this team id (teams mode); omit for global roles}';

    protected $description = 'Create the default roles (idempotent).';

    public function handle(SeedDefaultRoles $seed): int
    {
        $team = $this->option('team');
        $teamId = is_string($team) && $team !== '' ? (ctype_digit($team) ? (int) $team : $team) : TeamContext::get();

        $roles = TeamContext::run($teamId, fn () => app(ActorContext::class)->runAs(null, fn () => $seed->handle()));

        $this->components->info('Default roles ready: '.$roles->keys()->implode(', '));

        return self::SUCCESS;
    }
}
