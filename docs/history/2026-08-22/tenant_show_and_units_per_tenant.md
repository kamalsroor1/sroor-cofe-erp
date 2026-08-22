# سجل تعديل: صفحة تفاصيل المستأجر والتحكم، تخصيص الوحدات لكل مستأجر، وإصلاح إعدادات المنشأة والدارك مود

* **التاريخ والوقت:** 2026-08-22 19:40
* **الدور المفعل:** Backend Architect & Frontend UI
* **الهدف:** توفير صفحة متكاملة لعرض وتحكم المستأجر في السوبر أدمن مع إحصائيات حية وأزرار إجراءات تنفيذية، تخصيص وحدات القياس لكل مستأجر على حدة، إصلاح صلاحية حفظ إعدادات الـ Tenant، وضبط الوضعين الفاتح والداكن (Dark Mode) في لوحة تحكم السوبر أدمن.

## 1. الملفات المعدلة والمنشأة:
* `[NEW]` `backend/resources/js/views/SuperAdmin/SuperAdminTenantShowView.vue` - صفحة تفاصيل وتحكم المستأجر الشاملة.
* `[MODIFIED]` `backend/resources/js/router/index.js` - إضافة مسار `/super-admin/tenants/:id`.
* `[MODIFIED]` `backend/resources/js/views/SuperAdmin/SuperAdminTenantsView.vue` - إضافة زر ورابط "🔍 عرض وتحكم" في جدول المنشآت.
* `[MODIFIED]` `backend/app/Actions/Tenants/GetTenantDetailsAction.php` - تجميع إحصائيات المنشأة الحية (مستخدمين، فروع، أصناف، فواتير، مبيعات) ومصفوفات الوحدات.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/SuperAdminApiController.php` - إضافة `updateTenantUnits` و `runTenantMigrations`.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات وحدات وميجريشن المستأجر.
* `[MODIFIED]` `backend/app/Http/Requests/UpdateSettingsRequest.php` - إصلاح دالة `authorize()` لتمكين مديري المنشأة من حفظ الإعدادات بدون 403.
* `[MODIFIED]` `backend/resources/js/Layouts/SuperAdminLayout.vue` - ضبط تباين النصوص والخلفيات في الوضعين الفاتح والداكن.

## 2. القرارات التقنية:
1. **تخصيص الوحدات لكل مستأجر (Per-Tenant Units Isolation):** أصبح بإمكان السوبر أدمن اختيار وتفعيل وحدات قياس محددة لكل مستأجر على حدة، ويتم تخزينها في بيانات المنشأة ومزامنتها ذرياً داخل جدول `settings` في قاعدة بيانات المستأجر.
2. **شاشة عرض وتحكم المستأجر (Tenant Executive Show View):** إتاحة كافة أدوات الإدارة السريعة (دخول كأدمن، تحديث الميجريشن، تفعيل وتعطيل المميزات، تعديل الاشتراك، الحذف).
3. **التوافق التام مع الوضع الداكن واللغة العربية RTL.**

## 3. التحقق والاختبار:
* [x] بناء أصول Vite بنجاح بدون أي أخطاء
* [x] النشر التلقائي على خادم هوستنجر بنجاح
* [x] اختبار فتح تفاصيل المستأجر `2m` وتعديل وحداته
* [x] اختبار حفظ إعدادات المستأجر والتحقق من زوال الخطأ
