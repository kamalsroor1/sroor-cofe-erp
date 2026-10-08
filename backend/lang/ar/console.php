<?php

declare(strict_types=1);

return [
    'populate_realistic_data' => [
        'refused_production' => 'مرفوض توليد بيانات تجريبية على بيئة الإنتاج. أعد التشغيل باستخدام --force-unsafe لو متأكد.',
        'confirm_production' => 'سيتم توليد بيانات تجريبية على بيئة الإنتاج. هل تريد المتابعة؟',
        'aborted' => 'تم الإلغاء: لم يتم تأكيد التشغيل على بيئة الإنتاج.',
        'existing_data' => 'المستأجر لديه بيانات تشغيلية بالفعل؛ أعد التشغيل باستخدام --fresh لمسحها أولاً.',
        'created_users' => 'تم إنشاء مستخدمين تجريبيين:',
        'generated_password' => 'كلمة المرور المولدة للمستخدمين التجريبيين الجدد: :password',
        'password_from_option' => 'المستخدمون التجريبيون الجدد يستخدمون كلمة المرور الممررة عبر --password.',
        'change_password_warning' => 'غيّر بيانات الدخول هذه فوراً بعد أول تسجيل دخول.',
    ],
    'seed' => [
        'generated_password' => 'كلمة المرور المولدة للحساب :user: :password ، غيّرها بعد أول تسجيل دخول.',
        'password_from_env' => 'الحساب :user يستخدم كلمة المرور المحددة في متغيرات البيئة.',
        'change_password_warning' => 'غيّر بيانات الدخول هذه فوراً بعد أول تسجيل دخول.',
        'tenant_exists' => 'المستأجر :tenant موجود بالفعل.',
        'tenant_provisioned' => 'تم تجهيز المستأجر: :tenant',
        'tenant_domains' => 'النطاقات: :domains',
    ],
    'audit_super_admin' => [
        'description' => 'فحص للقراءة فقط لقواعد بيانات المستأجرين بحثاً عن دور super_admin أو صلاحياته أو أرقام موبايل المنصة.',
        'tenant_not_found' => 'المستأجر غير موجود: :tenant',
        'no_tenants' => 'لا يوجد مستأجرين للفحص.',
        'tenant_failed' => 'تعذر فحص المستأجر :tenant: :error',
        'col_tenant' => 'المستأجر',
        'col_role_exists' => 'دور super_admin',
        'col_role_users' => 'مستخدمين بدور super_admin',
        'col_permissions' => 'صلاحيات super_admin.*',
        'col_permission_roles' => 'الأدوار الحاملة لها',
        'col_phone_matches' => 'مستخدمين بأرقام موبايل المنصة',
        'findings' => 'مستأجرين بهم ملاحظات: :count من :total. لم يتم تعديل أي شيء.',
    ],
];
