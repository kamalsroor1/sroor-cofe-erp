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

    // CTO W1 Q2 (IDEN-3.2): reason codes of a suspension / cancellation.
    'suspension_reasons' => [
        'non_payment' => 'عدم السداد',
        'violation' => 'مخالفة شروط الاستخدام',
        'customer_request' => 'بناءً على طلب العميل',
        'other' => 'سبب آخر',
    ],

    // ENTI-2.1: names of plan limits used in subscription.errors.limit_reached.
    'limit_resources' => [
        'users' => 'المستخدمين',
        'stores' => 'الفروع',
        'warehouses' => 'المخازن',
        'vans' => 'السيارات',
        'items' => 'الأصناف',
        'invoices_month' => 'الفواتير الشهرية',
        'storage_mb' => 'مساحة التخزين',
        'other' => 'هذا المورد',
    ],

    // IDEN-3.3 (TenantLifecycleException) + ENTI-2.1 (entitlement exceptions).
    'errors' => [
        'read_only' => 'الحساب للعرض فقط حاليًا، ومش هتقدر تضيف أو تعدّل بيانات. جدّد الاشتراك لإعادة التفعيل.',
        'access_blocked' => 'الوصول للحساب موقوف (الحالة: :status). تواصل مع إدارة المنصة.',
        'activation_requires_payment' => 'تفعيل الحساب بيتم بعد تأكيد الدفع فقط.',
        'invalid_transition' => 'لا يمكن تغيير حالة الحساب من «:from» إلى «:to».',
        'status_conflict' => 'حالة الحساب اتغيّرت وأصبحت «:status». حدّث الصفحة وحاول تاني.',
        'archive_not_allowed' => 'الأرشفة مسموحة للحسابات الموقوفة أو الملغية فقط. الحالة الحالية: «:status».',
        'not_archived' => 'الحساب مش مؤرشف.',
        'suspension_reason_required' => 'اختار سبب الإيقاف.',
        'limit_reached' => 'وصلت للحد الأقصى المسموح في باقتك من :resource (:max). رقّي باقتك أو أضف إضافة.',
        'feature_unavailable' => 'الميزة دي مش متاحة في باقتك الحالية.',
    ],
];
