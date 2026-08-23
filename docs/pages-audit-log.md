# 📋 سجل مراجعة وهندسة الصفحات (Pages Audit & Component Architecture Log)
> آخر تحديث: 2026-08-23

---

## ✅ صفحة 1: لوحة التحكم الرئيسية (Dashboard)
* **التاريخ:** 2026-08-23
* **الملف الرئيسي:** `resources/js/views/DashboardView.vue` (Thin Orchestrator: ~55 سطر)
* **المسار (Route):** `/`
* **الحالة:** ✅ مكتملة 100% — تقطيع كامل إلى مكونات فرعية + استخراج المكونات العامة + تعريب وترجمة كاملة $t() بنسبة 100% + البناء ناجح (npm run build).

---

### 1. المكونات الفرعية الخاصة بالصفحة (`resources/js/Components/Dashboard/`):
| المكون | الوصف والمسؤولية | المصادر والـ Props |
|---|---|---|
| `DashboardWelcomeBanner.vue` | رأس الصفحة وبانر الترحيب وأزرار العمليات السريعة (POS، المشتريات، التحديث) | `companyName`, `loading`, emit(`refresh`) |
| `DashboardKpiGrid.vue` | شبكة بطاقات المؤشرات المالية الـ 4 (المبيعات، الأرباح، الديون، الشهر) | `metrics` |
| `DashboardAnalyticsRow.vue` | قسم المخطط البياني لـ 7 أيام + توزيع طرق الدفع + حالة الوردية | `dailyTrend`, `period`, `paymentDistribution`, `activeShift` |
| `DashboardPeakHours.vue` | خريطة حرارة ساعات الذروة على مدار 24 ساعة ومؤشر الذروة القصوى | `hourlySales`, `peakHour` |
| `DashboardRecentInvoices.vue` | جدول آخر الفواتير الصادرة وشارات الحالات وزر المعاينة | `invoices`, emit(`preview`) |
| `DashboardLowStock.vue` | قائمة تنبيهات نواقص المخزون ورابط مساعد المشتريات | `items` |

---

### 2. المكونات العامة المشتركة (`resources/js/Components/Common/`):
| المكون | التعديل / الإضافة | الغرض وقابلية الاستخدام في صفحات أخرى |
|---|---|---|
| `MetricCard.vue` | توسيع وإضافة props: `iconBg`, `iconColor`, `footerLeft`, `footerRight`, `footerRightClass`, variants (`cyan`, `indigo`) | بطاقات KPI موحدة لأي لوحة قيادة أو تقرير |
| `DashboardSectionCard.vue` | **مكون جديد** | حاوية الكروت الموحدة مع Header وأيقونة أو Dot وشارة تفاعلية (Slot) |
| `SimpleBarChart.vue` | **مكون جديد** | رسم بياني أعمدة CSS تفاعلي مع Tooltip و Pulse وتلوين مخصص |
| `ProgressDistributionList.vue` | **مكون جديد** | قائمة توزيع بالنسب المئوية وأشرطة التقدم الملونة |

---

### 3. دوال الـ Composables المستخرجة (`resources/js/Composables/`):
* `useFormatters.js`: دوال موحدة لتنسيق العملات (`formatMoney`)، الكميات (`formatQty`)، والنسب (`formatPercent`).

---

### 4. بوابة الترجمة والتعريب (100% Localization Gate):
* ✅ تم استبدال 100% من النصوص الثابتة داخل كافة المكونات بدوال `$t('dashboard.*')` و `trans('dashboard.*')`.
* ✅ تم تحديث `resources/js/helpers/defaultTranslations.js` بكامل المفاتيح والترجمات.
* ✅ متوافق تماماً مع تبديل اللغات وسياق المستأجرين.

---

## 📌 الصفحة التالية في خطة التدقيق:
**صفحة الفواتير (`resources/js/views/InvoicesView.vue`)**
* **الأهداف:** تقطيعها إلى Sub-components (`InvoicesFilterDrawer`, `InvoicesTable`, `InvoicesBulkActions`, `InvoicesStats`) + استخراج `useInvoiceHelpers.js` + تطبيق التعريب الشامل 100%.

