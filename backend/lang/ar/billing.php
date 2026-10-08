<?php

return [
    'subscription_status' => [
        'trialing' => 'فترة تجريبية',
        'active' => 'نشط',
        'past_due' => 'متأخر السداد',
        'pending_payment' => 'في انتظار الدفع',
        'cancelled' => 'ملغي',
        'expired' => 'منتهي',
    ],
    'subscription_addon_status' => [
        'active' => 'نشطة',
        'pending_payment' => 'في انتظار الدفع',
        'cancelled' => 'ملغاة',
        'expired' => 'منتهية',
    ],
    'billing_cycle' => [
        'monthly' => 'شهري',
        'yearly' => 'سنوي',
        'biennial' => 'كل سنتين',
    ],
    'addon_type' => [
        'recurring' => 'إضافة متكررة',
        'service' => 'خدمة',
    ],
    'billing_invoice_status' => [
        'draft' => 'مسودة',
        'pending' => 'في انتظار الدفع',
        'paid' => 'مدفوعة',
        'void' => 'ملغاة',
        'refunded' => 'مستردة',
    ],
    'billing_invoice_type' => [
        'plan' => 'اشتراك جديد',
        'renewal' => 'تجديد',
        'upgrade' => 'ترقية الباقة',
        'addon' => 'إضافة',
        'service' => 'خدمة',
    ],
    'billing_payment_status' => [
        'pending' => 'قيد المراجعة',
        'verified' => 'تم التحقق',
        'rejected' => 'مرفوض',
        'failed' => 'فشل',
        'refunded' => 'مسترد',
    ],
    'billing_payment_method' => [
        'instapay' => 'إنستاباي',
        'vodafone_cash' => 'فودافون كاش',
        'bank_transfer' => 'تحويل بنكي',
        'cash' => 'نقدًا',
        'paymob_card' => 'بطاقة بنكية (Paymob)',
        'paymob_wallet' => 'محفظة إلكترونية (Paymob)',
        'fawry_reference' => 'كود فوري',
    ],
    'billing_gateway' => [
        'manual' => 'مراجعة يدوية',
        'paymob' => 'Paymob',
        'fawry' => 'فوري',
    ],
    'addon_pricing' => [
        'cycle_not_sellable' => 'دورة الفوترة «:cycle» غير متاحة للشراء.',
        'yearly_price_missing' => 'لا يوجد سعر سنوي محدد للإضافة :addon.',
        'invalid_quantity' => 'كمية الإضافة غير صحيحة (:quantity)، ويجب ألا تقل عن 1.',
        'invalid_price_tiers' => 'شرائح أسعار الكمية للإضافة :addon غير صحيحة.',
        'invalid_price' => 'السعر المسجل للإضافة :addon غير صحيح.',
    ],
    'sequence' => [
        'transaction_required' => 'لا يمكن إصدار رقم فاتورة اشتراك إلا داخل معاملة قاعدة بيانات.',
        'invalid_key' => 'تسلسل ترقيم الفواتير غير صحيح.',
        'invalid_configuration' => 'إعداد ترقيم الفواتير :key غير صحيح.',
    ],
    'founder_pricing' => [
        'transaction_required' => 'لا يمكن حجز مقعد سعر المؤسسين إلا داخل معاملة قاعدة بيانات.',
        'payment_not_verified' => 'لا يمكن حجز مقعد سعر المؤسسين إلا لدفعة تم التحقق منها.',
        'payment_tenant_mismatch' => 'الدفعة لا تخص حساب هذا الاشتراك.',
        'cycle_not_sellable' => 'دورة الفوترة «:cycle» غير متاحة للشراء.',
        'invalid_configuration' => 'إعداد سعر المؤسسين :key غير صحيح.',
    ],
    'subscription_addon' => [
        'subscription_missing' => 'لا يمكن حفظ بند الإضافة بدون اشتراك موجود.',
        'tenant_mismatch' => 'بند الإضافة لا يخص حساب الاشتراك الذي يتبعه.',
    ],
];
