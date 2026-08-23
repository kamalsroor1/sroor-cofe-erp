# 🗑️ وثيقة المكون والصفحة: سلة المحذوفات والاسترجاع الآمن (`TrashView.vue`)

> **المسار (Route):** `/trash`  
> **الملف الرئيسي:** `resources/js/views/Trash/TrashView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **سلة المحذوفات المركزية (Trash & Soft Deletes Recovery)** صمام الأمان لمنع فقدان البيانات:
1. **إدارة المحذوفات المؤقتة (Soft Delete Recovery):** استعراض واستعادة كافة السجلات المحذوفة عبر 6 تبويبات مخصصة (الأصناف، العملاء، الموردين، الفروع، المصروفات، المرتجعات).
2. **عداد المحذوفات الحي:** إظهار عدد السجلات المحذوفة بجانب كل تبويب مع شارة مميزة عند وجود عناصر.
3. **البحث السريع:** إمكانية البحث داخل سلة المحذوفات بالاسم أو الكود.
4. **الاسترجاع الفوري والحذف النهائي:** أزرار استرجاع مع تأكيد آمن عبر SweetAlert وزر حذف نهائي صارم (Force Delete).

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 261 سطرًا إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Trash/
│   └── TrashView.vue                          <-- Thin Orchestrator (~65 lines)
├── Components/Trash/
│   ├── TrashModuleTabs.vue                    <-- شريط التبويبات الـ 6 مع الأيقونات والعدادات
│   ├── TrashFilterBar.vue                     <-- شريط البحث الفوري في سلة المحذوفات
│   └── TrashTable.vue                         <-- جدول المحذوفات وبطاقات الهواتف مع أزرار الاسترجاع والحذف
└── Composables/
    └── useTrash.js                            <-- كبسولة المنطق والاتصال بالـ API والاستعادة والحذف
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر تحديث السلة.
* `BaseButton.vue`: زر التحديث والإجراءات مع مؤشرات التحميل.
* `BaseSearchInput.vue`: حقل البحث الفوري.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجداول.
* `EmptyState.vue`: حالة عدم وجود محذوفات في التبويب المختار.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب المحذوفات والعدادات** | `GET /api/v1/trash` | `tab`, `search`, `page` | قائمة العناصر المحذوفة + العدادات + الترقيم |
| **استرجاع عنصر محذوف** | `POST /api/v1/trash/{module}/{id}/restore` | - | استعادة العنصر إلى السجلات النشطة |
| **حذف نهائي لعنصر** | `DELETE /api/v1/trash/{module}/{id}/force` | - | الحذف الفيزيائي النهائي من قاعدة البيانات |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * تبويبات قابلة للتمرير الأفقي، بطاقات لمسية متراصة لكل عنصر محذوف، وأزرار استرجاع/حذف بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول محاسبي أنيق مع تاريخ الحذف والوصف وأزرار واضحة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/trash.php` و `lang/en/trash.php`:
* `trash.trash_title`: سلة المحذوفات / Trash & Recovery
* `trash.trash_subtitle`: استرجاع أو الحذف النهائي للسجلات المحذوفة... / Restore or permanently purge deleted records...
* `trash.tab_items_label`: الأصناف والخامات / Items & Raw Materials
* `trash.restore_success`: تم الاسترجاع / Restored Successfully
* `trash.force_delete_success`: تم الحذف النهائي / Purged Successfully

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/trash-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.15 ثانية.
