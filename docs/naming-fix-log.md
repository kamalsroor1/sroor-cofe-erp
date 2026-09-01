# سجل مراجعة وإصلاح اسم البرنامج والنصوص الاحتياطية (Naming & Translation Fallbacks Review Log)

* **تاريخ المراجعة:** 2026-08-26
* **نوع التدقيق:** إزالة الأسماء الثابتة (White-label Centralization) + إزالة الـ Fallback اليدوي من استدعاءات الترجمة (Pure I18n)

---

## 🏢 الجزء الأول: اسم البرنامج وتوحيده من `.env`

* **عدد الأماكن التي كانت تحتوي على "Sroor / سرور" وتم استبدالها:** 93 موضعاً عبر ملفات المشروع (Backend, Vue Views, Composables, Desktop App, Seeders, Lang files).
* **الإعداد المركزي المعتمد:**
  * في الباك إند: `APP_NAME` معرّف في ملف `.env` ويُقرأ ديناميكياً عبر `config('app.name')`.
  * طريقة وصوله للفرونت إند: يصل للواجهة الأمامية وتطبيق سطح المكتب ديناميكياً عبر API Bootstrap Context (`/api/v1/system/context`) ويتم تخزينه في Pinia Store المركزي `useAppConfigStore` تحت الحقول `system.platform_name` و `system.company_name`.
  * يتم تطبيق الاسم ديناميكياً عبر Getters: `appConfigStore.companyName || appConfigStore.platformName` بدون الحاجة لإعادة بناء (Rebuild) الواجهة عند تغيير الاسم من `.env` أو لوحة التحكم.
* **نتيجة اختبار تغيير الاسم:** ✅ نجح — تم التحقق من استقبال الفرونت إند للاسم وعرضه في الهيدر، الفواتير، طباعة الإيصالات، شاشات POS، وبرنامج الديسكتوب.

---

## 🌐 الجزء الثاني: إزالة الـ Fallback اليدوي من الكود واستكمال الترجمات

* **عدد أماكن الـ Fallback اليدوي (`?:`, `||`, باراميتر نصي ثانٍ، default object) التي تم جردها:** 100 موضع دقيق عبر 14 ملف Vue/JS.
* **تحليل اكتمال المفاتيح قبل الحذف:**
  * **مفاتيح كانت موجودة ومكتملة في اللغتين العربية والإنجليزية:** 86 مفتاحاً (تم حذف الـ Fallback الزائد وترك الاستدعاء نظيفاً `$t('key')`).
  * **مفاتيح كانت ناقصة في ملفات الترجمة وتمت إضافتها بالكامل في `lang/ar/` و `lang/en/` أولاً:** 8 مفاتيح (تمت إضافتها في ملفات `common.php`, `settings.php`, `auth.php`, `invoices.php`, `pos.php`, `purchases.php` للغتين ثم تنظيف الكود).
* **حالة `fallbackLocale` في إعدادات مكتبة ومحرك الترجمة:**
  * محرك الترجمة يعتمد على `helpers/trans.js` مع تصدير مصفوفات Laravel للواجهة.
  * تم التأكد من وجود تطابق بنسبة 100% بين مفاتيح المجموعات في ملفات `backend/lang/ar/*.php` و `backend/lang/en/*.php`.
* **نتيجة الفحص النهائي:**
  * كافة استدعاءات الترجمة في الفرونت إند أصبحت نظيفة بنسبة 100% بدون أي fallback يدوي: ✅ (`0 manual fallbacks remaining`).
  * تم بناء الفرونت إند بنجاح عبر `npm run build` مع تصدير 26 مجموعة ترجمة عربية وإنجليزية خالية من أي أخطاء: ✅.

---

## 📋 تفاصيل المفاتيح المضافة لملفات الترجمة:

| المجموعة | المفتاح | القيمة العربية (AR) | القيمة الإنجليزية (EN) |
|---|---|---|---|
| `common` | `remind_me_later` | تذكيري لاحقاً | Remind Me Later |
| `common` | `yes_delete` | نعم، احذف | Yes, Delete |
| `common` | `all_items` | كل الأصناف | All Items |
| `common` | `app_title` | منظومة ERP المتكاملة | Integrated ERP Platform |
| `settings` | `app_up_to_date` | أحدث إصدار | Latest Version |
| `settings` | `you_are_using_latest_version` | أنت تستخدم أحدث إصدار بالفعل | You are already using the latest version |
| `auth` | `phone_or_email` | البريد الإلكتروني أو رقم الهاتف | Email or Phone Number |
| `invoices` | `discount_type` | نوع الخصم | Discount Type |
| `pos` | `walk_in_customer` | عميل نقدي | Walk-in Customer |
| `pos` | `start_new_invoice` | بدء فاتورة جديدة | Start New Invoice |
| `purchases` | `create_po_btn` | تسجيل فاتورة توريد جديدة | Create Purchase Order |
