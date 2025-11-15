<?php

namespace Enadstack\LaravelRoles\Events;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Role $role, public bool $forceDeleted = false)
    {
    }
}

