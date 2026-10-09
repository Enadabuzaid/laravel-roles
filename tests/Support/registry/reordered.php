<?php

// en BEFORE ar: Postgres jsonb stores keys ordered ('ar' sorts first), so a round-trip reorders them.
return [
    'orders' => [
        'permissions' => [
            'orders.view' => ['label' => ['en' => 'View orders', 'ar' => 'عرض الطلبات'], 'description' => ['en' => 'Desc', 'ar' => 'وصف']],
        ],
    ],
];
