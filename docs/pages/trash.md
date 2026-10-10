# 🗑️ وثيقة المكون والصفحة: سلة المحذوفات والاسترجاع الآمن (`TrashView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** سلة المحذوفات (Trash & Recovery)
* **المسار (Route):** `/trash`
* **اسم المسار (Route Name):** `trash.index`
* **الصلاحية المطلوبة (Permission):** `trash.access` (أو دور `admin`).
* **الملف الرئيسي:** `resources/js/views/Trash/TrashView.vue` (~63 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة السجلات المحذوفة ناعماً (Soft Deleted Records) في بيئة المستأجر عبر تبويبات منفصلة (الأصناف، العملاء، الموردين، الفروع، المصروفات، المرتجعات).
  * استعراض عدادات السجلات المحذوفة لكل موديول بصورة حية.
  * البحث السريع داخل السجلات المحذوفة بالاسم أو الرمز.
  * استرجاع السجلات المحذوفة فورياً إلى حالتها النشطة (`Restore`).
  * الحذف النهائي والفيزيائي الصارم للسجلات (`Force Delete`) مع رسائل تأكيد.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
TrashView.vue (~63 lines)
├── PageHeader.vue            <-- رأس الصفحة مع زر تحديث السلة
├── TrashModuleTabs.vue       <-- شريط التبويبات مع الأيقونات وشارات العدادات
├── TrashFilterBar.vue        <-- شريط البحث في السجلات المحذوفة
└── TrashTable.vue            <-- جدول السجلات مع تفاصيل الحذف وأزرار الاسترجاع والحذف النهائي
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **الـ Composable:** `useTrash.js` لإدارة التبويب النشط وجلب المحذوفات وتنفيذ عمليات الاسترجاع والحذف النهائي.
* **المخازن المستخدمة:** `useAuthStore` للتحقق من صلاحية `trash.access`.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/trash` (أو مسار المستأجر `/trash`):
  * **الوصف:** جلب السجلات المحذوفة للتبويب المحدد مع العدادات وترقيم الصفحات.
  * **الكنترولر:** `App\Http\Controllers\Api\TrashController@index`
  * **Action:** `App\Actions\Trash\GetTrashRecordsAction`
  * **المعاملات:** `tab` (items, customers, suppliers, stores, expenses, returns), `search`, `per_page`, `page`.
* `POST /api/v1/trash/{type}/{id}/restore`:
  * **الوصف:** استرجاع السجل المحذوف وإعادته للعمليات النشطة.
  * **الكنترولر:** `App\Http\Controllers\Api\TrashController@restore`
  * **Action:** `App\Actions\Trash\RestoreTrashRecordAction`
* `DELETE /api/v1/trash/{type}/{id}/force`:
  * **الوصف:** الحذف الفيزيائي النهائي للسجل من قاعدة البيانات.
  * **الكنترولر:** `App\Http\Controllers\Api\TrashController@forceDelete`
  * **Action:** `App\Actions\Trash\ForceDeleteTrashRecordAction`

---

## 5. الحماية والأمان وعزل البيانات:
* **حماية الصلاحيات:** تقتصر إمكانية فتح السلة أو استرجاع أو حذف السجلات على حاملي صلاحية `trash.access` أو مديري النظام `admin`.
* **عزل المستأجر:** كافة السجلات المحذوفة والاستعلامات تقتصر حصراً على قاعدة بيانات المستأجر النشط.
