<?php

function roles_registry_fixture_helper(): string
{
    return 'once';
}

return ['orders' => ['permissions' => ['orders.view' => roles_registry_fixture_helper()]]];
