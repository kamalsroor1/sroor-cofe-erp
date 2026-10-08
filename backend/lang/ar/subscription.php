<?php

/*
| Tenant subscription lifecycle (IDEN-3.1). Created in Wave 1; later tasks
| (ENTI-2.1, IDEN-3.x) APPEND their keys at the end of this file. Keep the same
| keys in lang/en/subscription.php.
*/

return [
    'statuses' => [
        'trial' => 'فترة تجريبية',
        'active' => 'نشط',
        'past_due' => 'متأخر السداد',
        'read_only' => 'للعرض فقط',
        'suspended' => 'موقوف',
        'cancelled' => 'ملغي',
        'archived' => 'مؤرشف',
    ],

    'actors' => [
        'system' => 'النظام',
        'super_admin' => 'إدارة المنصة',
        'billing' => 'الفوترة',
    ],

    'access_levels' => [
        'full' => 'وصول كامل',
        'read_only' => 'عرض وتصدير فقط',
        'blocked' => 'الوصول موقوف',
    ],

    'state_messages' => [
        'trial' => 'أنت في الفترة التجريبية. الأيام المتبقية: :days',
        'active' => 'اشتراكك نشط.',
        'past_due' => 'انتهت مدة اشتراكك. جدّد الاشتراك قبل تحويل الحساب للعرض فقط. الأيام المتبقية: :days',
        'read_only' => 'الحساب للعرض فقط: تقدر تشوف بياناتك وتصدّرها، لكن مش هتقدر تضيف أو تعدّل. جدّد الاشتراك قبل إيقاف الحساب. الأيام المتبقية: :days',
        'suspended' => 'تم إيقاف الحساب. تواصل مع إدارة المنصة أو جدّد الاشتراك لإعادة التفعيل.',
        'cancelled' => 'تم إلغاء الاشتراك. تواصل مع إدارة المنصة لإعادة التفعيل.',
        'archived' => 'تمت أرشفة الحساب. تواصل مع إدارة المنصة.',
    ],

    'trial_extension' => [
        'already_used' => 'تم تمديد الفترة التجريبية لهذا الحساب من قبل، والتمديد مسموح مرة واحدة فقط.',
        'already_paid' => 'لا يمكن تمديد الفترة التجريبية لحساب سبق له الدفع.',
        'not_eligible' => 'لا يمكن تمديد الفترة التجريبية في حالة الحساب الحالية.',
    ],
];
