# 📜 وثيقة المكون والصفحة: سجل التدقيق الأمني والنشاطات (`ActivityLogsView.vue`)

> **المسار (Route):** `/activity-logs`  
> **الملف الرئيسي:** `resources/js/views/ActivityLogs/ActivityLogsView.vue` (Thin Orchestrator: ~70 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **سجل التدقيق الأمني ونشاطات النظام (System Activity & Audit Logs)** مركز الرقابة والحوكمة الإدارية بالمنظومة:
1. **الرقابة الإدارية وتتبع الأثر (Audit Trail):** تسجيل كل إجراء يحدث بالنظام (إنشاء فاتورة، تعديل صنف، شطب، إلغاء، تسجيل دخول، فتح/إغلاق وردية) مع هوية الموظف والفرع والتوقيت وعنوان الـ IP.
2. **مؤشرات النشاط اليومي (Activity KPI Cards):** إجمالي العمليات اليوم، العمليات الحرجة/الحذف، عدد الموظفين النشطين، وعدد الفروع المتفاعلة.
3. **فلاتر رقابية متقدمة:** بحث بالوصف، تصفية حسب الموديول (فواتير، مخزون، مستخدمين...)، تصفية حسب الموظف، وحسب الفرع.
4. **فحص تفاصيل الحمولة والتغييرات (Payload Details Modal):** نافذة `AppModal` تعرض التغييرات والـ JSON Payload بالتفصيل عند النقر على "التفاصيل".

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 304 أسطر إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/ActivityLogs/
│   └── ActivityLogsView.vue                   <-- Thin Orchestrator (~70 lines)
├── Components/ActivityLogs/
│   ├── ActivityLogsMetricsGrid.vue            <-- بطاقات المؤشرات الأربعة للنشاط اليومي
│   ├── ActivityLogsFilterBar.vue              <-- شريط الفلاتر (بحث، قسم، موظف، فرع)
│   ├── ActivityLogsTimeline.vue               <-- القائمة والجدول الزمني للنشاطات والترقيم
│   └── ActivityLogDetailsModal.vue            <-- نافذة تفاصيل العملية والـ JSON Payload
└── Composables/
    └── useActivityLogs.js                     <-- كبسولة المنطق والاتصال بالـ API والفلترة
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر تحديث السجل.
* `BaseButton.vue`: أزرار التحديث والإجراءات مع مؤشرات التحميل.
* `BaseSearchInput.vue`: حقل البحث الفوري.
* `BaseSelect.vue`: قوائم اختيار الأقسام والموظفين والفروع.
* `StatCardSkeleton.vue`: هياكل تحميل وميضية لبطاقات المؤشرات.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجدول الزمني.
* `EmptyState.vue`: حالة عدم وجود نشاطات مطابقة للبحث.
* `AppModal.vue`: الحاوية الموحدة لنافذة التفاصيل.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب سجل النشاطات والمؤشرات** | `GET /api/v1/activity-logs` | `search`, `module`, `action`, `user_id`, `store_id`, `page` | قائمة السجلات + إحصائيات اليوم + القوائم + الترقيم |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * بطاقات نشاط لمسية واضحة ومتباعدة، زر التفاصيل بارتفاع $\ge 40	ext{px}$، ونصوص واضحة.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول زمني أفقي مريح مع أيقونات الموديولات وتاريخ العملية وعنوان الـ IP وشارات الحالة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/activity.php` و `lang/en/activity.php`:
* `activity.title`: سجل التدقيق الأمني والعمليات / System Activity & Audit Logs
* `activity.refresh_log`: تحديث السجل / Refresh Log
* `activity.today_total`: إجمالي عمليات اليوم / Today's Operations
* `activity.today_critical`: عمليات حساسة وإلغاءات / Critical & Void Operations
* `activity.details_btn`: التفاصيل / Details

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/activity-logs-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 2.96 ثانية.
