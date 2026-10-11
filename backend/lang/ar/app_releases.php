<?php

// APP-3 extension (W2 batch 5): release upload validation, update check texts and the
// app-releases:relocate command. Keep the key set identical to lang/en/app_releases.php.
return [
    // Upload (POST /api/v1/super-admin/app-versions): 422 messages.
    'version_not_newer' => 'رقم الإصدار (version code) لازم يكون أكبر من كل إصدارات :platform الموجودة (أعلى رقم حاليًا: :max).',
    'version_code_taken' => 'فيه إصدار :platform بنفس رقم الإصدار ده بالفعل.',
    'windows_code_mismatch' => 'في ويندوز لازم رقم الإصدار يطابق اسم الإصدار (:name يساوي :code).',
    'version_name_format' => 'اسم الإصدار لازم يكون بالشكل 1.2.3 (ثلاث أرقام، كل رقم لحد 3 خانات).',
    'platform_not_supported' => 'نشر الإصدارات متاح لأندرويد وويندوز بس.',
    'extension_mismatch' => 'نوع الملف مش مناسب للمنصة: أندرويد محتاج ملف apk وويندوز محتاج برنامج تثبيت exe.',
    'min_version_too_high' => 'أقل رقم إصدار مدعوم مينفعش يكون أكبر من رقم الإصدار نفسه.',
    'invalid_binary' => 'الملف المرفوع مش حزمة إصدار صالحة للمنصة دي.',
    'binary_errors' => [
        'mime_not_allowed' => 'محتوى الملف مش حزمة أندرويد ولا برنامج ويندوز.',
        'unreadable' => 'مقدرناش نقرا الملف المرفوع.',
        'zip_magic' => 'الملف مش حزمة أندرويد بصيغة ZIP.',
        'zip_unreadable' => 'مقدرناش نفتح حزمة الأندرويد كملف ZIP.',
        'unsafe_entry' => 'حزمة الأندرويد فيها مسار ملف غير آمن.',
        'manifest_missing' => 'حزمة الأندرويد مفيهاش AndroidManifest.xml.',
        'dex_missing' => 'حزمة الأندرويد مفيهاش كود مُجمَّع (classes.dex).',
        'signing_block_missing' => 'حزمة الأندرويد مش موقّعة بنظام توقيع APK الإصدار 2 أو 3.',
        'signing_block_too_large' => 'جزء التوقيع في حزمة الأندرويد أكبر من إنه يكون توقيع حقيقي.',
        'mz_magic' => 'الملف مش برنامج ويندوز (ناقصه ترويسة MZ).',
        'pe_header' => 'ترويسة PE في برنامج الويندوز تالفة.',
        'machine_not_allowed' => 'برنامج الويندوز مش مبني لمعالج x64 أو x86 أو ARM64.',
        'not_executable' => 'ملف الويندوز مش متعلَّم كبرنامج قابل للتشغيل.',
        'is_dll' => 'ملف الويندوز ده مكتبة (DLL) مش برنامج تثبيت.',
    ],
    'platforms' => [
        'android' => 'أندرويد',
        'windows' => 'ويندوز',
    ],
    'store_failed' => 'مقدرناش نحفظ ملف الإصدار. حاول تاني.',
    'binary_write_failed' => 'مقدرناش نكتب ملف الإصدار على القرص الخاص بالإصدارات.',

    // Update check (GET /api/v1/app/check-update).
    'check' => [
        'title_force' => 'فيه تحديث إجباري متاح',
        'title_optional' => 'فيه تحديث جديد متاح',
        'title_up_to_date' => 'التطبيق محدَّث',
        'message_available' => 'الإصدار :version من التطبيق متاح دلوقتي.',
        'message_force' => 'لازم تحدّث للإصدار :version عشان تكمل استخدام التطبيق. حدّث دلوقتي.',
        'message_up_to_date' => 'إنت بتستخدم أحدث إصدار من التطبيق.',
    ],
    'size' => [
        'bytes' => ':size بايت',
        'kilobytes' => ':size ك.ب',
        'megabytes' => ':size م.ب',
    ],

    // php artisan app-releases:relocate
    'relocate' => [
        'description' => 'نسخ ملفات إصدارات التطبيق من القرص العام للقرص الخاص بالإصدارات، و--prune-legacy يمسح النسخ العامة اللي اتأكدنا منها (آمن لو اتشغّل أكتر من مرة)',
        'tenant_context' => 'شغّل الأمر ده من السياق المركزي، مش جوه محل.',
        'dry_run' => 'تجربة بس: مفيش أي ملف هيتنسخ أو يتمسح.',
        'nothing_to_do' => 'مفيش ملفات إصدارات متبقية على القرص العام.',
        'would_copy' => 'هيتنسخ :path',
        'copied' => 'اتنسخ واتأكدنا منه :path (النسخة العامة لسه موجودة)',
        'already_private' => 'موجود بالفعل على القرص الخاص بنفس الـ SHA-256: :path',
        'pruned' => 'اتمسحت النسخة العامة المتأكد منها لـ :path',
        'prune_skipped' => 'النسخة العامة من :path فضلت زي ما هي: النسخة الخاصة مش مطابقة للـ SHA-256 بتاعها.',
        'conflict' => 'متنسخش: :path موجود على القرصين بمحتوى مختلف. لازم يتحل يدويًا.',
        'failed' => 'فشل نسخ :path',
        'public_binary_found' => 'لسه متاح للتحميل العام، امسحه يدويًا: public/:file',
        'summary' => 'اتنسخ: :copied، موجود خاص بالفعل: :verified، نسخ عامة اتمسحت: :pruned، تعارضات: :conflicts، فشل: :failed.',
    ],
];
