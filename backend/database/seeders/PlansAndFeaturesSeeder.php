<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlansAndFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. تسجيل كافة الفيتشرز المتاحة في النظام
        $features = [
            // === المبيعات والكاشير ===
            ['key' => 'pos.access',            'name' => 'كاشير نقاط البيع السريع (POS)', 'description' => 'واجهة كاشير سريعة تدعم ميزان الباركود والدفع الفوري', 'module' => 'sales', 'type' => 'boolean', 'default_value' => 'true', 'icon' => '⚡', 'sort_order' => 1],
            ['key' => 'invoices.create',       'name' => 'إصدار فواتير المبيعات',        'description' => 'إنشاء فواتير نقدية وآجلة وجزئية مع كشف الحساب', 'module' => 'sales', 'type' => 'boolean', 'default_value' => 'true', 'icon' => '🧾', 'sort_order' => 2],
            ['key' => 'invoices.edit',         'name' => 'تعديل وإلغاء الفواتير',        'description' => 'تعديل بنود الفاتورة أو إلغاؤها مع عكس المخزون والخزينة', 'module' => 'sales', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '✏️', 'sort_order' => 3],
            ['key' => 'whatsapp.share',        'name' => 'مشاركة الفاتورة عبر واتساب',   'description' => 'إرسال رابط وتفاصيل الفاتورة مباشرة للعميل على واتساب', 'module' => 'sales', 'type' => 'boolean', 'default_value' => 'true', 'icon' => '💬', 'sort_order' => 4],

            // === المخزون والتوريدات ===
            ['key' => 'items.manage',          'name' => 'دليل الأصناف والمخزون',       'description' => 'إدارة أسعار البيع والتكلفة ووحدات القياس والحد الأدنى', 'module' => 'inventory', 'type' => 'boolean', 'default_value' => 'true', 'icon' => '📦', 'sort_order' => 5],
            ['key' => 'items.movements',       'name' => 'كارت حركة الصنف التفصيلي',    'description' => 'تتبع وتدقيق حركات الوارد والصادر والرصيد التراكمي', 'module' => 'inventory', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '📊', 'sort_order' => 6],
            ['key' => 'transfers.manage',      'name' => 'التحويلات المخزنية بين الفروع', 'description' => 'أذون تحويل البضاعة بين المخازن وسيارات التوزيع', 'module' => 'inventory', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '🚚', 'sort_order' => 7],
            ['key' => 'blender.access',        'name' => 'استوديو توليف وخلاط البن',    'description' => 'حساب نسب خلط البن والتكلفة وهالك التحميص', 'module' => 'inventory', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '☕', 'sort_order' => 8],

            // === المشتريات والموردين ===
            ['key' => 'purchases.manage',      'name' => 'فواتير المشتريات والتوريد',    'description' => 'تسجيل فواتير استلام البضاعة من الموردين وحساب متوسط التكلفة', 'module' => 'purchases', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '🚛', 'sort_order' => 9],
            ['key' => 'purchases.reorder',     'name' => 'مساعد إعادة الطلب الذكي',     'description' => 'تنبؤ ذكي بالكميات الواجب شراؤها بناءً على معدل الاستهلاك', 'module' => 'purchases', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '🤖', 'sort_order' => 10],

            // === الحسابات والمالية ===
            ['key' => 'expenses.manage',       'name' => 'المصروفات والنثريات اليومية', 'description' => 'تسجيل مصروفات الدرج وتصنيفها وخصمها من الخزينة', 'module' => 'finance', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '💸', 'sort_order' => 11],
            ['key' => 'payments.manage',       'name' => 'سندات القبض والصرف',          'description' => 'تحصيل دفعات من العملاء وسداد دفعات للموردين', 'module' => 'finance', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '💵', 'sort_order' => 12],
            ['key' => 'shifts.manage',         'name' => 'ورديات الكاشير و Z-Report',   'description' => 'فتح وإغلاق الورديات ومطابقة عهدة الدرج والعجز والزيادة', 'module' => 'finance', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '💰', 'sort_order' => 13],
            ['key' => 'treasury.view',         'name' => 'حركة الخزينة والسيولة النقدية', 'description' => 'متابعة المقبوضات والمدفوعات وصافي السيولة النقدية الحية', 'module' => 'finance', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '🏦', 'sort_order' => 14],
            ['key' => 'returns.manage',        'name' => 'مرتجعات المبيعات والمشتريات', 'description' => 'إرجاع البضاعة وتسوية الأرصدة المالية والمخزنية', 'module' => 'finance', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '↩️', 'sort_order' => 15],

            // === التقارير والرقابة ===
            ['key' => 'reports.basic',         'name' => 'التقارير اليومية الأساسية',   'description' => 'ملخص المبيعات اليومية وفواتير الورديات', 'module' => 'reports', 'type' => 'boolean', 'default_value' => 'true', 'icon' => '📈', 'sort_order' => 16],
            ['key' => 'reports.advanced',      'name' => 'تقارير الأرباح وتحليل COGS',   'description' => 'حساب صافي الربح الحقيقي وهامش الربح والأصناف الأكثر مبيعاً', 'module' => 'reports', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '📊', 'sort_order' => 17],
            ['key' => 'reports.export',        'name' => 'تصدير البيانات Excel & CSV',   'description' => 'تصدير كشوف الحساب والمخزون والفواتير إلى ملفات إكسل', 'module' => 'reports', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '📥', 'sort_order' => 18],
            ['key' => 'audit.logs',            'name' => 'سجل الرقابة وتدقيق العمليات', 'description' => 'تتبع من قام بإنشاء أو تعديل أو إلغاء أي حركة في النظام', 'module' => 'system', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '🛡️', 'sort_order' => 19],

            // === التكاملات والطباعة ===
            ['key' => 'printing.thermal',      'name' => 'الطباعة الحرارية السريعة (80mm)', 'description' => 'طباعة الإيصالات والفواتير وبون الكاشير مباشرة', 'module' => 'system', 'type' => 'boolean', 'default_value' => 'true', 'icon' => '🖨️', 'sort_order' => 20],
            ['key' => 'printing.a4',           'name' => 'طباعة الفواتير الرسمية A4',   'description' => 'فواتير ضريبية A4 مع الشعار والختم', 'module' => 'system', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '📄', 'sort_order' => 21],
            ['key' => 'telegram.notifications', 'name' => 'إشعارات تيليجرام التلقائية',   'description' => 'إرسال ملخص الوردية والمبيعات تلقائياً على بوت تيليجرام', 'module' => 'system', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '🤖', 'sort_order' => 22],
            ['key' => 'api.access',            'name' => 'تطبيق الموبايل وواجهة API',    'description' => 'ربط تطبيق الموبايل (NativePHP / Flutter) بحساب المستأجر', 'module' => 'system', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '📱', 'sort_order' => 23],
            ['key' => 'custom.domain',         'name' => 'النطاق المخصص (Custom Domain)', 'description' => 'تشغيل لوحة التحكم على رابط المحل الخاص (مثلاً: shop.com)', 'module' => 'system', 'type' => 'boolean', 'default_value' => 'false', 'icon' => '🌐', 'sort_order' => 24],
        ];

        foreach ($features as $f) {
            PlanFeature::updateOrCreate(['key' => $f['key']], $f);
        }

        // 2. إنشاء الباقات الـ 4 الرسمية
        $plans = [
            [
                'name' => 'التجريبية المجانية (Free Trial)',
                'slug' => 'free',
                'description' => 'مناسبة للمحلات والمطاحن الناشئة للتجربة والاستخدام الفردي المحدود.',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'max_users' => 1,
                'max_stores' => 1,
                'max_items' => 50,
                'max_invoices_per_month' => 100,
                'max_storage_mb' => 500,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
                'features' => [
                    'pos.access' => true,
                    'invoices.create' => true,
                    'invoices.edit' => false,
                    'whatsapp.share' => true,
                    'items.manage' => true,
                    'items.movements' => false,
                    'transfers.manage' => false,
                    'blender.access' => false,
                    'purchases.manage' => false,
                    'purchases.reorder' => false,
                    'expenses.manage' => false,
                    'payments.manage' => false,
                    'shifts.manage' => false,
                    'treasury.view' => false,
                    'returns.manage' => false,
                    'reports.basic' => true,
                    'reports.advanced' => false,
                    'reports.export' => false,
                    'audit.logs' => false,
                    'printing.thermal' => true,
                    'printing.a4' => false,
                    'telegram.notifications' => false,
                    'api.access' => false,
                    'custom.domain' => false,
                ],
            ],
            [
                'name' => 'الباقة الأساسية (Basic Starter)',
                'slug' => 'basic',
                'description' => 'المثالية للمطاحن والمحلات الفردية التي تحتاج إدارة كاملة للمبيعات والمصروفات.',
                'price_monthly' => 299.00,
                'price_yearly' => 2990.00,
                'max_users' => 3,
                'max_stores' => 2,
                'max_items' => 500,
                'max_invoices_per_month' => 1000,
                'max_storage_mb' => 2048,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 2,
                'features' => [
                    'pos.access' => true,
                    'invoices.create' => true,
                    'invoices.edit' => true,
                    'whatsapp.share' => true,
                    'items.manage' => true,
                    'items.movements' => true,
                    'transfers.manage' => false,
                    'blender.access' => false,
                    'purchases.manage' => true,
                    'purchases.reorder' => false,
                    'expenses.manage' => true,
                    'payments.manage' => true,
                    'shifts.manage' => true,
                    'treasury.view' => true,
                    'returns.manage' => true,
                    'reports.basic' => true,
                    'reports.advanced' => false,
                    'reports.export' => true,
                    'audit.logs' => false,
                    'printing.thermal' => true,
                    'printing.a4' => true,
                    'telegram.notifications' => false,
                    'api.access' => false,
                    'custom.domain' => false,
                ],
            ],
            [
                'name' => 'الباقة الاحترافية (Pro Growth)',
                'slug' => 'pro',
                'description' => 'الأكثر طلباً للشركات والمطاحن المتعددة الفروع مع استوديو خلط البن وتطبيق الموبايل.',
                'price_monthly' => 599.00,
                'price_yearly' => 5990.00,
                'max_users' => 10,
                'max_stores' => 5,
                'max_items' => 5000,
                'max_invoices_per_month' => 10000,
                'max_storage_mb' => 10240,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 3,
                'features' => [
                    'pos.access' => true,
                    'invoices.create' => true,
                    'invoices.edit' => true,
                    'whatsapp.share' => true,
                    'items.manage' => true,
                    'items.movements' => true,
                    'transfers.manage' => true,
                    'blender.access' => true,
                    'purchases.manage' => true,
                    'purchases.reorder' => true,
                    'expenses.manage' => true,
                    'payments.manage' => true,
                    'shifts.manage' => true,
                    'treasury.view' => true,
                    'returns.manage' => true,
                    'reports.basic' => true,
                    'reports.advanced' => true,
                    'reports.export' => true,
                    'audit.logs' => true,
                    'printing.thermal' => true,
                    'printing.a4' => true,
                    'telegram.notifications' => true,
                    'api.access' => true,
                    'custom.domain' => false,
                ],
            ],
            [
                'name' => 'باقة المؤسسات الكبرى (Enterprise)',
                'slug' => 'enterprise',
                'description' => 'للشركات وسلاسل الفروع الكبرى مع نطاق مخصص، ودعم فني مخصص، وموارد غير محدودة.',
                'price_monthly' => 999.00,
                'price_yearly' => 9990.00,
                'max_users' => 999,
                'max_stores' => 99,
                'max_items' => 99999,
                'max_invoices_per_month' => 999999,
                'max_storage_mb' => 51200,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 4,
                'features' => [
                    'pos.access' => true,
                    'invoices.create' => true,
                    'invoices.edit' => true,
                    'whatsapp.share' => true,
                    'items.manage' => true,
                    'items.movements' => true,
                    'transfers.manage' => true,
                    'blender.access' => true,
                    'purchases.manage' => true,
                    'purchases.reorder' => true,
                    'expenses.manage' => true,
                    'payments.manage' => true,
                    'shifts.manage' => true,
                    'treasury.view' => true,
                    'returns.manage' => true,
                    'reports.basic' => true,
                    'reports.advanced' => true,
                    'reports.export' => true,
                    'audit.logs' => true,
                    'printing.thermal' => true,
                    'printing.a4' => true,
                    'telegram.notifications' => true,
                    'api.access' => true,
                    'custom.domain' => true,
                ],
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
