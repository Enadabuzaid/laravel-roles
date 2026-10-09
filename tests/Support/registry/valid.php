<?php

return [
    'orders' => [
        'permissions' => [
            'orders.view' => 'View orders',
            'orders.update' => ['label' => ['en' => 'Edit orders', 'ar' => 'تعديل الطلبات']],
            'orders.refund' => ['label' => 'Refund orders', 'description' => 'Money back', 'risk' => 'high'],
        ],
    ],
];
