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
        'intro' => 'شكرًا إنك سجّلت في :app. اكتب الكود ده في صفحة التسجيل علشان تأكّد إيميلك:',
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
        'forgot_password' => 'نسيت كلمة السر؟ غيّرها من هنا:',
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

    // Security review: الإيميل المتأكد عنده محل بالفعل (ماتعملش محل جديد).
    'signup_shop_exists' => [
        'subject' => 'عندك محل بالفعل على :app',
        'heading' => 'عندك محل بالفعل',
        'intro' => 'أكّدنا إيميلك، بس البيانات اللي دخلتها مسجّل بيها محل على :app بالفعل، علشان كده ماعملناش محل جديد.',
        'login_button' => 'ادخل على محلك',
        'login_url' => 'لينك الدخول:',
        'forgot_password' => 'نسيت كلمة السر؟ استخدم «نسيت كلمة السر» في صفحة الدخول.',
        'contact_support' => 'لو شايف إن في غلط أو محتاج محل تاني، كلّم فريق الدعم.',
        'not_you' => 'لو مش انت اللي حاولت تسجّل، تجاهل الرسالة دي.',
    ],

    'failed' => [
        'telegram' => 'فشل إرسال إيميل المنصة :mailable نهائيًا بعد كل المحاولات. دومين المستلم: :domains. السبب: :exception.',
    ],

    // FREE-15: المحلات المجانية الخاملة.
    'dormant_reminder' => [
        'subject' => 'محلك ":business" مستنيك',
        'heading' => 'محلك ":business" مستنيك',
        'intro' => 'لاحظنا إن محدش دخل على ":business" بقاله :days يوم. ادخل علشان يفضل شغال.',
        'login_button' => 'ادخل على محلك',
        'login_url' => 'رابط الدخول:',
        'what_happens' => 'لو المحل فضل من غير استخدام، هيتأرشف يوم :date (والدخول عليه هيقف لحد ما نرجّعهولك).',
        'data_kept' => 'بياناتك عمرها ما بتتمسح. حتى لو المحل اتأرشف، كل حاجة بتفضل محفوظة وبنرجّعهالك لما تطلب.',
    ],

    'dormant_archived' => [
        'subject' => 'محلك ":business" اتأرشف',
        'heading' => 'محلك ":business" اتأرشف',
        'intro' => 'محدش دخل على ":business" بقاله :days يوم، علشان كده أرشفناه.',
        'data_kept' => 'بياناتك في أمان: مفيش حاجة اتمسحت وكل حاجة محفوظة.',
        'contact_support' => 'علشان يرجع محلك، كلّم فريق الدعم وهنرجّعهولك:',
    ],

    // SIGN-15: App\Mail\TenantPasswordResetMail.
    'tenant_password_reset' => [
        'subject' => 'تغيير كلمة السر على :app',
        'heading' => 'تغيير كلمة السر',
        'greeting' => 'أهلاً :name،',
        'intro' => 'وصلنا طلب لتغيير كلمة السر لحسابك في «:shop». دوس على الزرار اللي تحت واختار كلمة سر جديدة.',
        'button' => 'اختار كلمة سر جديدة',
        'link_label' => 'أو افتح اللينك ده:',
        'expiry' => 'اللينك ده بيشتغل مرة واحدة وصلاحيته :minutes دقيقة.',
        'not_you' => 'لو مش انت اللي طلبت ده، تجاهل الرسالة: كلمة السر هتفضل زي ما هي.',
    ],

    // #232 UI §5 (option B): operator e-mails. App\Mail\BillingReceiptUploadedMail.
    'billing_receipt' => [
        'subject' => 'إيصال تحويل جديد من :tenant — :amount',
        'title' => 'إيصال تحويل جديد محتاج مراجعة',
        'tenant' => 'المحل:',
        'invoice' => 'الفاتورة:',
        'amount' => 'المبلغ:',
        'uploaded_at' => 'وقت الرفع:',
        'access' => 'التفعيل المؤقت:',
        'access_until' => 'شغال لحد',
        'access_manual' => 'مراجعة يدوية — مفيش تفعيل مؤقت',
        'button' => 'راجع الإيصال في اللوحة',
    ],

    // #232 UI §5 (option B): App\Mail\BillingTemporaryAccessReminderMail.
    'billing_grace_reminder' => [
        'subject' => 'باقي 6 ساعات على التفعيل المؤقت — :tenant',
        'title' => 'باقي 6 ساعات على تفعيل مؤقت',
        'tenant' => 'المحل:',
        'invoice' => 'الفاتورة:',
        'ends_at' => 'التفعيل المؤقت بينتهي:',
        'consequence' => 'لو ماتأكدش لحد كده المحل هيرجع لاشتراكه السابق.',
        'button' => 'راجع الآن',
    ],

    // #232 UI §5: footer shared by the operator e-mails above.
    'billing_admin' => [
        'footer' => 'وصلك الإيميل ده لأنك مسؤول منصة :app. الإيصال نفسه مش مرفق (بيانات خاصة).',
    ],
];
