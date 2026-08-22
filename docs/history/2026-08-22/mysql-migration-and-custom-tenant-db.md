# سجل تعديل: الانتقال إلى قاعدة بيانات MySQL وفصل لوحة السوبر أدمن عن الكاشير

* **التاريخ والوقت:** 2026-08-22 16:53
* **الدور المفعل:** Backend Architect & Frontend UI
* **الهدف:** الانتقال الكامل من SQLite إلى MySQL الحقيقية لقاعدة بيانات المنصة المركزية وقواعد المستأجرين المنفصلة، مع إتاحة حقل قاعدة البيانات اليدوية للمستأجرين في هوستنجر.

## 1. الملفات المعدلة:
* `[MODIFIED]` `backend/config/tenancy.php` - ضبط البادئة `TENANT_DB_PREFIX=u910151740_` والدومين المركزي.
* `[MODIFIED]` `backend/app/DTOs/CreateTenantDTO.php` - إضافة دعم `tenancyDbName`.
* `[MODIFIED]` `backend/app/Http/Requests/StoreTenantRequest.php` - إضافة التحقق من `tenancy_db_name`.
* `[MODIFIED]` `backend/app/Services/TenantProvisionerService.php` - ربط المستأجر بقاعدة بيانات MySQL المخصصة.
* `[MODIFIED]` `backend/resources/js/views/SuperAdmin/SuperAdminTenantsView.vue` - إضافة حقل اسم قاعدة البيانات المخصصة لنافذة إضافة المستأجر.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - الفصل التام بين لوحة السوبر أدمن المركزية ولوحة الكاشير والمخزون.
* `[MODIFIED]` `deploy_root_baraa.py` - ربط وتوجيه اتصال `DB_CONNECTION=mysql` مع `u910151740_baraa_central`.

## 2. القرارات التقنية:
* استخدام محرك MySQL/InnoDB الأصلي مع قاعدة البيانات المركزية `u910151740_baraa_central`.
* إتاحة إضافة اسم قاعدة بيانات منفصلة لكل مستأجر على هوستنجر (`u910151740_tenant_xxx`) لضمان العزل التام واستقرار الاستضافة المشتركة لحين الانتقال إلى VPS.

## 3. التحقق والاختبار:
* [x] تم تشغيل الميجريشنز والبذر على قاعدة بيانات MySQL الحية بنجاح.
* [x] خلو الكود من أي أخطاء ونجاح 254 اختباراً محلياً.
* [x] تم اختبار تسجيل الدخول وقراءة الباقات من MySQL الحية (`Live MySQL Connected! Total Plans in MySQL: 4`).
