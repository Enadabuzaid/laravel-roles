<?php

declare(strict_types=1);

return [
    'invitation_invalid' => 'هذه الدعوة غير صالحة أو منتهية.',
    'invitation_user_exists' => 'يوجد مستخدم بهذا البريد الإلكتروني بالفعل.',
    'invitation_pending_exists' => 'توجد دعوة معلقة لهذا البريد الإلكتروني. أعد إرسالها بدلًا من ذلك.',
    'invitation_not_pending' => 'تم قبول هذه الدعوة أو إلغاؤها بالفعل.',
    'invalid_transition' => 'لا يمكن :action مستخدم :status.',
    'saved_view_limit' => 'يمكنك حفظ :max عروض كحد أقصى لكل صفحة. احذف أحدها أولًا.',
    'last_owner_delete' => 'يجب أن يبقى مالك واحد على الأقل.',
    'status' => [
        'invited' => 'مدعو',
        'pending_verification' => 'بانتظار التحقق',
        'active' => 'نشط',
        'locked' => 'مقفل',
        'suspended' => 'معلّق',
        'deactivated' => 'معطّل',
    ],
    'action' => [
        'suspended' => 'تعليق',
        'reactivated' => 'إعادة تفعيل',
        'deactivated' => 'تعطيل',
        'unlocked' => 'فتح قفل',
    ],
];
