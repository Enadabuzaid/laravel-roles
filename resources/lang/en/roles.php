<?php

return [
    'roles' => [
        'super-admin' => ['label' => 'Super Admin', 'description' => 'Full access to all actions.'],
        'admin' => ['label' => 'Admin', 'description' => 'Manage users and settings.'],
        'user' => ['label' => 'User', 'description' => 'Standard application access.'],
        'manager' => ['label' => 'Manager', 'description' => 'Supervises teams and resources.'],
    ],
    'permissions' => [
        'users' => [
            'label' => 'Users',
            'description' => 'User management permissions grouped by action.',
            'actions' => [
                'list' => ['label' => 'List', 'description' => 'View users list'],
                'create' => ['label' => 'Create', 'description' => 'Create a new user'],
                'update' => ['label' => 'Update', 'description' => 'Edit existing user'],
                'delete' => ['label' => 'Delete', 'description' => 'Soft-delete a user'],
                'restore' => ['label' => 'Restore', 'description' => 'Restore a soft-deleted user'],
                'force-delete' => ['label' => 'Force Delete', 'description' => 'Permanently delete a user'],
            ],
        ],
        // 'roles' => [...]
    ],
];