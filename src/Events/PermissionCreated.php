<?php

namespace Enadstack\LaravelRoles\Events;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Permission $permission)
    {
    }
}

