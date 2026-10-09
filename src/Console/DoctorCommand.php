<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Illuminate\Console\Command;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'roles:doctor';

    protected $description = 'Check the access-control setup for problems.';

    public function handle(): int
    {
        $failed = false;

        foreach ((array) config('roles.doctor.checks', []) as $class) {
            $label = is_string($class) ? $class : 'check';

            try {
                $check = app($class);
                if (! $check instanceof Check) {
                    $this->line('FAIL  '.$label.' — does not implement '.Check::class);
                    $failed = true;

                    continue;
                }

                $label = $check->name();
                $result = $check->run();
            } catch (Throwable $e) {
                $this->line('FAIL  '.$label.' — '.$e->getMessage());
                $failed = true;

                continue;
            }

            $failed = $failed || $result->status === CheckStatus::Fail;
            $this->line(strtoupper($result->status->value).'  '.$label.' — '.$result->message);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
