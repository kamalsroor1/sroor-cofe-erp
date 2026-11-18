<?php

declare(strict_types=1);

/*
| SIGN-10: platform emails (sent by the SaaS operator, not by a shop).
| Layout, the SMTP test message, the mail:test command and the failure alert.
*/

return [
    'layout' => [
        'logo_alt' => 'شعار :app',
        'support_title' => 'محتاج مساعدة؟',
        'support_intro' => 'فريق الدعم في :app موجود يساعدك.',
        'phone_label' => 'تليفون / واتساب:',
        'email_label' => 'الإيميل:',
        'website_label' => 'الموقع:',
        'automated_notice' => 'الرسالة دي اتبعتت تلقائيًا من :app.',
        'copyright' => '© :year :name',
    ],

    'test' => [
        'subject' => 'رسالة تجريبية من :app',
        'heading' => 'إعدادات الإيميل شغالة',
        'body' => 'لو الرسالة دي وصلتك، يبقى إعدادات إرسال الإيميل في :app مظبوطة.',
        'no_action' => 'مش محتاج تعمل أي حاجة، تقدر تتجاهل الرسالة دي.',
    ],

    'test_command' => [
        'description' => 'إرسال إيميل تجريبي من المنصة للتأكد من إعدادات SMTP.',
        'invalid_email' => 'عنوان الإيميل غير صالح.',
        'invalid_locale' => 'اللغة لازم تكون ar أو en.',
        'log_mailer_warning' => 'الإرسال الحالي (:mailer) بيكتب الرسائل في ملف اللوج ومش بيبعتها فعليًا. اضبط MAIL_MAILER=smtp وبيانات SMTP الأول.',
        'queued' => 'الرسالة التجريبية دخلت طابور الإرسال لعنوان على الدومين :domain (طريقة الإرسال: :mailer).',
        'queue_hint' => 'لازم يكون فيه queue worker شغال عشان الرسالة تتبعت. استخدم --now للإرسال الفوري.',
        'sent' => 'الرسالة التجريبية اتبعتت لعنوان على الدومين :domain (طريقة الإرسال: :mailer).',
        'failed' => 'فشل الإرسال (:exception): :reason',
    ],

    // SIGN-11: self-signup email verification code.
    'signup_code' => [
        'subject' => ':code هو كود تأكيد حسابك في :app',
        'heading' => 'أكّد إيميلك',
        'intro' => 'شكرًا إنك سجّلت ":business" في :app. اكتب الكود ده في صفحة التسجيل علشان نجهّز محلك:',
        'validity' => 'الكود صالح لمدة :minutes دقيقة.',
        'never_share' => 'متقولش الكود ده لحد، وفريق :app عمره ما هيطلبه منك.',
        'not_you' => 'لو مش انت اللي سجّلت، تجاهل الرسالة دي ومحدش هيقدر يستخدم إيميلك.',
    ],

    // SIGN-12: "your shop is ready" and "we're on it" after a self-signup.
    'signup_ready' => [
        'subject' => 'محلك ":business" جاهز على :app',
        'heading' => 'محلك ":business" جاهز',
        'intro' => 'جهّزنا حسابك على :app وتقدر تدخل وتبدأ شغل دلوقتي.',
        'login_button' => 'ادخل على محلك',
        'login_url' => 'لينك الدخول:',
        'login_email' => 'هتدخل بالإيميل:',
        'password_hint' => 'كلمة السر: اللي انت اخترتها وقت التسجيل.',
        'plan' => 'الباقة: :plan',
        'trial_ends' => 'الفترة التجريبية بتنتهي يوم :date.',
        'help' => 'مركز المساعدة:',
    ],

    'signup_delayed' => [
        'subject' => 'بنجهّز محلك ":business"',
        'heading' => 'محتاجين شوية وقت زيادة',
        'body' => 'إيميلك اتأكد، بس تجهيز محل ":business" على :app اتأخر شوية. فريقنا عرف وشغال عليه، وهنبعتلك أول ما يجهز.',
        'no_action' => 'مش محتاج تعمل أي حاجة دلوقتي.',
    ],

    'failed' => [
        'telegram' => 'فشل إرسال إيميل المنصة :mailable نهائيًا بعد كل المحاولات. دومين المستلم: :domains. السبب: :exception.',
    ],
];
