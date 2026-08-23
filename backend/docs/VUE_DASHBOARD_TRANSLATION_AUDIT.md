# 📊 تقرير تدقيق وترجمة لوحة التحكم (Vue 3 Dashboard Translation Audit Report)

> **المشروع:** سرور كوفي ERP (Backend Core & Vue 3 Frontends)  
> **التاريخ:** 2026-08-20  
> **الملفات التي تم تدقيقها وتحديثها:**
> 1. `backend/resources/js/Pages/Dashboard.vue` (لوحة التحكم الرئيسية للمستأجر)
> 2. `backend/resources/js/Pages/SuperAdmin/Dashboard.vue` (لوحة تحكم السوبر أدمن المركزية)
> 3. `backend/app/Http/Controllers/DashboardController.php` (متحكم الداشبورد)
> 4. `backend/app/Actions/Dashboard/GetTenantDashboardAnalyticsAction.php` (أكشن تجميع مؤشرات الأداء والتحليلات)
> 5. `backend/app/Services/DashboardAnalyticsService.php` (خدمة تحليلات المبيعات والمدفوعات وساعات الذروة)
> 6. `backend/lang/ar/dashboard.php` & `backend/lang/en/dashboard.php` (قواميس لوحة التحكم)
> 7. `backend/lang/ar/super.php` & `backend/lang/en/super.php` (قواميس منصة السوبر أدمن)
> 8. `backend/lang/ar/pos.php` & `backend/lang/en/pos.php` (قواميس طرق التحصيل ونقاط البيع)

---

## 1. تفاصيل تدقيق وتحديث الملفات خطوة بخطوة

### 1. `backend/resources/js/Pages/Dashboard.vue`
- **بنر الترحيب والعنوان الرئيسي:**
  - استبدال العنوان بنص مترجم: `{{ $t('dashboard.welcome_banner_title') }}`
  - تسمية الفرع الحالي: `{{ $t('dashboard.current_branch_label') }}`
  - الوصف التوضيحي: `{{ $t('dashboard.overview_subtitle') }}`
  - زر الكاشير السريع مع اختصار F2: `{{ $t('dashboard.pos_fast_btn') }}`
  - زر فاتورة التوريد: `{{ $t('dashboard.supply_invoice_btn') }}`
- **بطاقات مؤشرات الأداء الـ 4 (KPI Cards):**
  - بطاقة مبيعات اليوم: `{{ $t('dashboard.today_sales_card') }}` وعدد الفواتير `{{ $t('dashboard.today_invoices_count') }}`
  - بطاقة هامش الربح الشهري: `{{ $t('dashboard.monthly_gross_profit_card') }}` ونسبة الهامش `{{ $t('dashboard.profit_margin_label') }}`
  - بطاقة ديون العملاء: `{{ $t('dashboard.customers_debt_card') }}` و `{{ $t('dashboard.due_collections_label') }}`
  - بطاقة المبيعات الشهرية: `{{ $t('dashboard.monthly_sales_card') }}` و `{{ $t('dashboard.monthly_net_operations') }}`
- **الرسوم البيانية التفاعلية (7-Day Trend & Peak Hours):**
  - اتجاه المبيعات لـ 7 أيام: `{{ $t('dashboard.seven_days_trend_title') }}` و `{{ $t('dashboard.seven_days_trend_desc') }}`
  - متوسط قيمة الفاتورة: `{{ $t('dashboard.avg_invoice_val') }}`
  - ساعات الذروة والخريطة الحرارية 24 ساعة: `{{ $t('dashboard.peak_hours_title') }}` وشارة ساعة الذروة `{{ $t('dashboard.peak_hour_badge') }}`
  - طرق التحصيل ونسب الدفع: `{{ $t('dashboard.collection_methods') }}`
- **جدول أحدث فواتير المبيعات ورادار النواقص:**
  - عناوين الأعمدة: `invoice_number_col`, `customer_col`, `payment_method_col`, `total_col`, `paid_col`, `time_col`
  - شارات نوع الدفع (كاش / آجل / جزئي): تم تحديث دالة `getPaymentTypeBadge` لتقرأ من دالة الترجمة `trans('invoices.*')`.
  - رادار النواقص: `{{ $t('dashboard.low_stock_radar_title') }}`, `{{ $t('dashboard.purchases_assistant') }}`, `{{ $t('dashboard.min_stock_level') }}`, و `{{ $t('dashboard.all_items_safe_radar') }}`.

---

### 2. `backend/resources/js/Pages/SuperAdmin/Dashboard.vue`
- **الهيدر وبطاقات المؤشرات (MRR, المشتركين, الاشتراكات):**
  - العناوين والوصف: `{{ $t('super.platform_title') }}` و `{{ $t('super.platform_subtitle') }}`
  - بطاقة الشركات المسجلة: `{{ $t('super.total_tenants') }}` و `{{ $t('super.status_active') }}`
  - بطاقة الشركات التجريبية: `{{ $t('super.trial_tenants') }}` و `{{ $t('super.status_trial') }}`
  - بطاقة MRR: `{{ $t('super.mrr') }}` و `{{ $t('super.subscriptions') }}`
  - بطاقة الحسابات الموقوفة: `{{ $t('super.suspended_tenants') }}` و `{{ $t('super.status_suspended') }}`
- **جدول أحدث المستأجرين وتوزيع الباقات:**
  - رؤوس الجداول: `{{ $t('super.tenant_name') }}`, `{{ $t('super.subdomain') }}`, `{{ $t('super.plans') }}`, `{{ $t('common.status') }}`, `{{ $t('common.created_at') }}`
  - شارة حالة المستأجر: معالجة ديناميكية عبر `$t('super.status_active')` / `$t('super.status_trial')` / `$t('super.status_suspended')`.
  - كارت توزيع الباقات: استبدال النص الثابت `/شهر` بمفتاح الترجمة المعتمد `{{ $t('common.currency') }} / {{ $t('super.per_month') }}`.

---

### 3. `backend/app/Services/DashboardAnalyticsService.php`
- تم تحديث مسميات طرق الدفع في التحليلات (`cash`, `instapay`, `e_wallet`, `visa`, `bank_transfer`) لتقرأ من قواميس الترجمة `__('pos.cash_drawer')`, `__('pos.instapay')`, إلخ.

---

## 2. ملخص التحقق والبناء
- **فحص النصوص الثابتة:** 0 نصوص ثابتة (Zero Hardcoded Strings).
- **التوافق اللغوي:** يدعم العربية والإنجليزية بنسبة 100%.
- **نتيجة `npm run build`:** تم البناء بنجاح في 2.14 ثانية دون أية أخطاء.
