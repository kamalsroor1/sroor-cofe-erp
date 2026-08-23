# سجل تعديل: تفعيل مسارات المستأجر المعزولة وإصلاح كاش Tenancy
* **التاريخ والوقت:** 2026-08-20 01:13
* **الدور المفعل:** Mobile Backend & Multi-Tenancy Architect
* **الهدف:** ربط وتفعيل كافة مسارات ERP و POS داخل سياق المستأجر المعزول routes/tenant.php وتصحيح CacheTenancyBootstrapper للتوافق مع database cache driver.

## 1. الملفات المعدلة:
* [MODIFIED] backend/routes/tenant.php - ربط مسارات لوحة التحكم، الكاشير السريع POS، الأصناف، الفواتير، اليومية، والعملاء داخل سياق المستأجر.
* [MODIFIED] backend/config/tenancy.php - تعطيل CacheTenancyBootstrapper لمنع استدعاء Cache::tags غير المدعومة في SQLite/Database cache.
* [MODIFIED] backend/app/Services/TenantProvisionerService.php - تهيئة مصفوفة الصلاحيات (PermissionsSeeder) وتعيين رتبة admin للمستخدم الجديد مع تحميل أصناف البن الافتراضية.

## 2. القرارات التقنية:
* عزل كامل لكل مستأجر في قاعدة بياناته المستقلة، مع تمكينه من كافة ميزات ERP.
* منح مستخدم المستأجر الأساسي صلاحيات المدير الكاملة (admin).

## 3. التحقق والاختبار:
* [x] تم فحص استجابة http://sroor.makhzani.test/login والحصول على 200 OK
* [x] تم فحص إعادة التوجيه التلقائية إلى /login عند عدم المصادقة (302 Found)
* [x] التحقق من توافق Spatie Permissions داخل قاعدة بيانات المستأجر
