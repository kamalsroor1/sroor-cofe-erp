# 📜 وثيقة المكون والصفحة: سجل التدقيق الأمني والنشاطات (`ActivityLogsView.vue`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** سجل التدقيق الأمني ونشاطات النظام (System Activity & Audit Logs)
* **المسار (Route):** `/activity-logs`
* **اسم المسار (Route Name):** `activity-logs.index`
* **الصلاحية المطلوبة (Permission):** `logs.view`
* **الملف الرئيسي:** `resources/js/views/ActivityLogs/ActivityLogsView.vue` (Thin Orchestrator: ~70 سطر).
* **الغرض والتحليل التشغيلي:**
  * مركز الحوكمة والرقابة الإدارية والأمنية لتتبع أثر العمليات الحساسة عبر النظام.
  * تسجيل شامل لكل إجراء (إنشاء فاتورة، تعديل صنف، حذف، سداد، فتح/إغلاق وردية، تسجيل دخول) مع هوية الموظف والفرع والتوقيت وIP.
  * بطاقات مؤشرات النشاط اليومي (KPIs): إجمالي العمليات، العمليات الحرجة/الحذف، عدد الموظفين النشطين، والفروع النشطة.
  * فلاتر تصفية متقدمة: البحث النصي، الموديول (المبيعات، المخزون، الورديات، المشتريات...)، الموظف، والفرع.
  * نافذة فحص تفاصيل التغييرات والحمولة (`AppModal` + Payload Details) لمراجعة البيانات السابقة والجديدة.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
ActivityLogsView.vue (Thin Orchestrator ~70 lines)
├── ActivityLogsMetricsGrid.vue            <-- بطاقات المؤشرات الأربعة للنشاط اليومي
├── ActivityLogsFilterBar.vue              <-- شريط الفلاتر (بحث، موديول، موظف، فرع)
├── ActivityLogsTimeline.vue               <-- القائمة والجدول الزمني للنشاطات مع الترقيم
└── ActivityLogDetailsModal.vue            <-- نافذة تفاصيل العملية والتغييرات JSON
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المخازن والـ Composables:** `useActivityLogs.js` (إدارة الحالة والفلترة والاستعلام)، `useAuthStore` (التحقق من صلاحية `logs.view`)، `useFormatters.js`.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/activity-logs`: جلب قائمة سجلات النشاط مع الإحصائيات والفلاتر:
  * **الكنترولر:** `App\Http\Controllers\Api\ActivityLogController@index`
  * **Form Request:** `App\Http\Requests\FilterActivityLogsRequest`
  * **Actions:** `App\Actions\Logs\GetActivityLogsAction`
  * **Query Parameters:** `search`, `module`, `action`, `user_id`, `store_id`, `page`, `per_page`
  * **شكل الاستجابة:** `{ success: true, data: [...], stats: {...}, total_count: int }`
* `GET /api/v1/activity-logs/export`: تصدير السجل بصيغة CSV (`ExportActivityLogsCsvAction`).

---

## 5. مصفوفة الصلاحيات ونطاق الفروع:
* **الصلاحية (`PermissionsSeeder.php`):** `logs.view` (ممنوحة للمدير `admin`).
* **نطاق الفروع (`Store Scoping`):** يتم التحقق من ترويسة `X-Store-Id`؛ في حال طلب بيانات فرع لا يمتلك المستخدم تصريحاً بالوصول إليه يُرفض الطلب بكود 403 (`store_access_denied`).

---

## 6. الدقة وتكامل البيانات:
* الحركات المرتبطة بقيم مالية أو كميات مسجلة داخل الـ Properties تُحفظ كنصوص عشرية دقيقة `DECIMAL(12,3)`.
* التواريخ مطابقة للتوقيت الإقليمي وتوقيت جلسة العمل المعتمد للمؤسسة.
