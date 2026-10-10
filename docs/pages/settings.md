# ⚙️ وثيقة المكون والصفحة: إعدادات النظام والمؤسسة (`SettingsView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إعدادات النظام والمؤسسة (System & Organization Settings)
* **المسار (Route):** `/settings`
* **اسم المسار (Route Name):** `settings.index`
* **الصلاحية المطلوبة (Permission):** `settings.manage` أو `roles.manage` (أو دور `admin`).
* **الملف الرئيسي:** `resources/js/views/Settings/SettingsView.vue` (~138 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة إعدادات الهوية المؤسسية للمستأجر (اسم المؤسسة، الشعار، العنوان، الهاتف، السجل التجاري، الرقم الضريبي).
  * تخصيص المظهر ونظام الألوان: اختيار باليتات ألوان جاهزة (Emerald, Amber, Blue, Purple...) أو إدخال لون سداسي مخصص (Hex) مع دعم EyeDropper والوضع الداكن/الفاتح.
  * إعدادات الطباعة الحرارية والفواتير: التحكم في ظهور الشعار، اسم المؤسسة، السجل التجاري، رصيد العميل السابق، رمز الاستجابة السريعة (QR Code)، والملاحظة الختامية للفاتورة.
  * إدارة وحدات القياس المعتمدة للأصناف في المخزون (`inventory_units`).
  * ربط إشعارات تيليجرام (`telegram_bot_token`, `telegram_chat_id`) واختبار الإرسال المباشر.
  * إخفاء البيانات السرية (Write-Only Secrets): حجب توكنات تيليجرام تلقائياً عبر `SettingSecrets::mask` لمنع تسريبها في استجابة الـ API.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SettingsView.vue (~138 lines)
├── PageHeader.vue                     <-- رأس الصفحة مع زر حفظ التعديلات وزر الرجوع للهواتف
├── SettingsMobileHub.vue              <-- شبكة بطاقات الأقسام المخصصة للهواتف (Drill-Down Hub)
├── SettingsNavigationSidebar.vue      <-- القائمة الجانبية للتنقل بين الأقسام للشاشات الكبيرة
├── SettingsBrandingSection.vue        <-- قسم الهوية المؤسسية والبيانات الضريبية
├── SettingsAppearanceSection.vue      <-- قسم المظهر وباليتات الألوان واللون المخصص
├── SettingsPrintingSection.vue        <-- قسم خيارات الطباعة الحرارية وتذييل الفاتورة
├── SettingsTelegramSection.vue        <-- قسم إعدادات وتكامل بوت تيليجرام
└── SettingsUnitsSection.vue           <-- قسم إدارة وحدات القياس المعتمدة للمخزون
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseSelect.vue`.
* **الـ Composable:** `useSettings.js` لإدارة نموذج الإعدادات والتحقق وتطبيق الثيم المباشر واختبار التيليجرام.
* **المخازن المستخدمة:** `useAppConfigStore` لتحديث إعدادات المؤسسة والهوية وألوان الواجهة فورياً في كافة أرجاء التطبيق.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/settings` (أو مسار المستأجر `/settings`):
  * **الوصف:** جلب قاموس الإعدادات المحمية للمؤسسة مع حجب الأسرار وقائمة الفروع الفعالة.
  * **الكنترولر:** `App\Http\Controllers\Api\SettingController@index`
  * **الصلاحية:** `settings.manage` أو `roles.manage` أو `admin`.
* `POST /api/v1/settings` (أو مسار المستأجر `/settings`):
  * **الوصف:** تحديث إعدادات النظام والهوية والطباعة والوحدات.
  * **الكنترولر:** `App\Http\Controllers\Api\SettingController@update`
  * **Form Request:** `App\Http\Requests\UpdateSettingsRequest`
  * **Action:** `App\Actions\Settings\UpdateSettingsAction`
* `POST /api/v1/settings/telegram/test`:
  * **الوصف:** إرسال رسالة تجريبية فورية للتحقق من صحة توكن البوت ومعرف القناة.
  * **الكنترولر:** `App\Http\Controllers\Api\SettingController@sendTestTelegram`
  * **Form Request:** `App\Http\Requests\Settings\SendTestTelegramRequest`
  * **Action:** `App\Actions\Settings\SendTestTelegramAction`
  * **DTO:** `App\DTOs\Settings\TelegramTestDTO`

---

## 5. الحماية الأمنية وعزل البيانات:
* **حماية الأسرار (SettingSecrets Masking):** الـ API لا يعيد توكنات تيليجرام أو كلمات المرور المشفرة كنصوص صريحة، بل يعيد حالة التعيين فقط لمنع كشفها في المتصفح.
* **عزل المستأجر:** كافة الإعدادات تُخزن في جدول `settings` ضمن قاعدة بيانات المستأجر الخاصة به حصراً.
