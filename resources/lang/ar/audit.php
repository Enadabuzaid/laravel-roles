<?php

declare(strict_types=1);

return [
    'system' => 'النظام',
    'target_gone' => 'لم يعد موجودًا',
    'redacted' => 'مخفي',
    'export_too_large' => 'يحتوي هذا التصدير على :count صفًا، وهو أكثر من الحد :max. ضيّق عوامل التصفية وحاول مرة أخرى.',
    'csv' => [
        'time' => 'الوقت',
        'actor' => 'المنفّذ',
        'module' => 'الوحدة',
        'event' => 'الإجراء',
        'summary' => 'الملخص',
        'target' => 'الهدف',
        'severity' => 'الخطورة',
        'ip' => 'عنوان IP',
        'changes' => 'التغييرات',
    ],
    'events' => [
        'users' => [
            'updated' => 'قام :actor بتعديل :subject',
            'suspended' => 'قام :actor بإيقاف :subject',
            'reactivated' => 'قام :actor بإعادة تفعيل :subject',
            'deactivated' => 'قام :actor بتعطيل :subject',
            'unlocked' => 'قام :actor بفتح قفل :subject',
            'activated' => 'قبل :subject الدعوة وأصبح نشطًا',
            'password_reset_forced' => 'فرض :actor إعادة تعيين كلمة المرور لـ :subject',
            'email_verified' => 'قام :actor بتوثيق البريد الإلكتروني لـ :subject',
            'verification_resent' => 'أعاد :actor إرسال بريد التوثيق إلى :subject',
            'trashed' => 'نقل :actor :subject إلى سلة المحذوفات',
            'restored' => 'استعاد :actor :subject من سلة المحذوفات',
            'deleted' => 'حذف :actor :subject',
            'force_deleted' => 'حذف :actor :subject نهائيًا',
        ],
        'invitations' => [
            'created' => 'دعا :actor :subject',
            'resent' => 'أعاد :actor إرسال الدعوة إلى :subject',
            'revoked' => 'ألغى :actor دعوة :subject',
            'accepted' => 'قبل :actor الدعوة',
            'revoked_role_removed' => 'أُلغيت دعوات بسبب إزالة دور',
            'revoked_unauthorized' => 'أُلغيت دعوات لأن الداعي فقد صلاحية إرسالها',
        ],
        'roles' => [
            'created' => 'أنشأ :actor الدور :subject',
            'updated' => 'عدّل :actor الدور :subject',
            'trashed' => 'نقل :actor الدور :subject إلى سلة المحذوفات',
            'restored' => 'استعاد :actor الدور :subject',
            'deleted' => 'حذف :actor الدور :subject',
            'seeded' => 'تمت تهيئة الأدوار الافتراضية',
            'assigned' => 'أسند :actor دورًا إلى :subject',
            'removed' => 'أزال :actor دورًا من :subject',
            'synced' => 'غيّر :actor أدوار :subject',
            'permissions_updated' => 'غيّر :actor صلاحيات :subject',
        ],
        'permissions' => [
            'created' => 'أنشأ :actor الصلاحية :subject',
            'updated' => 'عدّل :actor الصلاحية :subject',
            'deleted' => 'حذف :actor الصلاحية :subject',
            'synced' => 'زامن :actor الصلاحيات',
        ],
        'audit' => [
            'exported' => 'صدّر :actor سجل التدقيق',
        ],
        'generic' => 'نفّذ :actor الإجراء :event على :subject',
    ],
];
