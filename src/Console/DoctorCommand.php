<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Illuminate\Console\Command;

class DoctorCommand extends Command
{
    protected $signature = 'roles:doctor';

    protected $description = 'Check the access-control setup for problems.';

    public function handle(): int
    {
        $failed = false;

        foreach ((array) config('roles.doctor.checks', []) as $class) {
            $check = app($class);
            if (! $check instanceof Check) {
                $this->line('FAIL  '.(is_string($class) ? $class : 'check').' — does not implement '.Check::class);
                $failed = true;

                continue;
            }

            $result = $check->run();
            $failed = $failed || $result->status === CheckStatus::Fail;
            $this->line(strtoupper($result->status->value).'  '.$check->name().' — '.$result->message);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
