<?php

// IDEN-1.3: platform-operator (CentralUser) authentication, /api/v1/super-admin/auth/*.
return [
    'failed' => 'البريد الإلكتروني أو كلمة السر غير صحيحة.',
    'login_success' => 'تم تسجيل الدخول إلى لوحة إدارة المنصة.',
    'two_factor_required' => 'مطلوب كود التحقق بخطوتين لإكمال تسجيل الدخول.',
    'logout_success' => 'تم تسجيل الخروج من لوحة إدارة المنصة.',
    'unauthenticated' => 'غير مصرح، سجّل الدخول إلى لوحة إدارة المنصة أولًا.',
    'session_expired' => 'انتهت جلسة لوحة إدارة المنصة، سجّل الدخول مرة أخرى.',
    // IDEN-1.12: mandatory two-factor (TOTP), step-up and password reset.
    'two_factor_setup_needed' => 'تم تسجيل الدخول، فعّل التحقق بخطوتين للمتابعة.',
    'two_factor_setup_required' => 'فعّل التحقق بخطوتين قبل استخدام لوحة إدارة المنصة.',
    'two_factor_challenge_invalid' => 'انتهت صلاحية محاولة تسجيل الدخول، سجّل الدخول مرة أخرى.',
    'two_factor_invalid' => 'كود التحقق غير صحيح.',
    'two_factor_enabled' => 'امسح الكود بتطبيق المصادقة، ثم أكّده بكود تحقق.',
    'two_factor_confirmed' => 'تم تفعيل التحقق بخطوتين، احفظ أكواد الاسترداد في مكان آمن.',
    'two_factor_already_confirmed' => 'التحقق بخطوتين مفعّل بالفعل لهذا الحساب.',
    'two_factor_not_enabled' => 'ابدأ إعداد التحقق بخطوتين أولًا.',
    'step_up_required' => 'أكّد هويتك بكود التحقق للمتابعة.',
    'step_up_confirmed' => 'تم تأكيد الهوية.',
    'password_reset_link_sent' => 'إذا كان هذا البريد مسجلًا لحساب على المنصة، فقد تم إرسال رابط إعادة تعيين كلمة السر إليه.',
    'password_reset_invalid' => 'رابط إعادة تعيين كلمة السر غير صالح أو انتهت صلاحيته.',
    'password_reset_success' => 'تم تغيير كلمة السر، سجّل الدخول من جديد على كل الأجهزة.',
    'password_reset_mail' => [
        'subject' => 'إعادة تعيين كلمة سر لوحة إدارة المنصة',
        'greeting' => 'أهلًا :name،',
        'intro' => 'وصلنا طلب لإعادة تعيين كلمة سر حسابك على لوحة إدارة المنصة.',
        'action' => 'إعادة تعيين كلمة السر',
        'expiry' => 'هذا الرابط صالح لمدة :minutes دقيقة.',
        'ignore' => 'إذا لم تطلب إعادة تعيين كلمة السر، تجاهل هذه الرسالة وستبقى كلمة السر كما هي.',
    ],
];
