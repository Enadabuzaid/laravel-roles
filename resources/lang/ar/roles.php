<?php

return [
    'roles' => [
        'super-admin' => ['label' => 'مدير عام', 'description' => 'صلاحيات كاملة لجميع العمليات.'],
        'admin'       => ['label' => 'مسؤول',   'description' => 'إدارة المستخدمين والإعدادات.'],
        'user'        => ['label' => 'مستخدم',  'description' => 'وصول عادي للتطبيق.'],
        'manager'     => ['label' => 'مدير',    'description' => 'يشرف على الفرق والموارد.'],
    ],
    'permissions' => [
        'users' => [
            'label' => 'المستخدمون',
            'description' => 'صلاحيات إدارة المستخدمين حسب الإجراء.',
            'actions' => [
                'list'         => ['label' => 'عرض',        'description' => 'عرض قائمة المستخدمين'],
                'create'       => ['label' => 'إضافة',      'description' => 'إضافة مستخدم جديد'],
                'update'       => ['label' => 'تعديل',      'description' => 'تعديل مستخدم'],
                'delete'       => ['label' => 'حذف',        'description' => 'حذف مستخدم (مؤقت)'],
                'restore'      => ['label' => 'استعادة',    'description' => 'استعادة مستخدم محذوف'],
                'force-delete' => ['label' => 'حذف نهائي',  'description' => 'حذف مستخدم بشكل دائم'],
            ],
        ],
    ],
];