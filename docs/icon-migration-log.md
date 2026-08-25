# 🎨 سجل تدقيق وترقية الأيقونات (Icon Migration Log)

---

## 📅 مراجعة شاملة بتاريخ 2026-08-26

### 🏛️ المرحلة 0: المكتبة المختارة (قرار موحد للمشروع)
* **المكتبة المختارة:** `lucide-vue-next` (v1.0.0)
* **سبب الاختيار:**
  1. مكتبة مفتوحة المصدر بالكامل وخفيفة الوزن مع دعم **Tree-Shaking** التلقائي (استيراد الأيقونة المطلوبة فقط).
  2. تصميم هندسي موحد بنمط الـ Outline متناسق 100% مع واجهات الـ ERP والـ POS ولوحات التحكم.
  3. مكتبة غنية (+1500 أيقونة) تغطي كافة سيناريوهات نقاط البيع، الخزينة، الفواتير، المخزون، والتقارير.
  4. وراثة الألوان ديناميكياً عبر فئات Tailwind CSS (`text-current`, `text-theme-primary`, `text-emerald-500`...).
  5. إنهاء مشكلة تباين حجم وشكل الإيموجي بين أنظمة التشغيل المختلفة (Windows, Android, iOS, Linux).

---

### 📊 المرحلة 1: الجرد الشامل وتصنيف الرموز

| نوع الرمز | الوصف | الإجراء المتخذ |
| :--- | :--- | :--- |
| **أيقونة وظيفية (Functional Icon)** | تمثل قسم، زر إجراء، قناة دفع، فئة منتجات، أو حالة تشغيلية | **تم الاستبدال بالكامل بمكونات SVG نظيفة من `lucide-vue-next`** |
| **زخرفة تعبيرية (Expressive Toast/Alert)** | نصوص رسائل النجاح أو التنبيهات النصية | الإبقاء عليها في الإشعارات النصية فقط |

---

### 🔄 جدول الاستبدال الموحد (Mapping Table: Emoji ➔ Lucide SVG)

| الرمز القديم | الأيقونة البديلة (`lucide-vue-next`) | السياق والاستخدام في المشروع |
| :---: | :--- | :--- |
| ☕ | `Coffee` | الشعار، تصنيف القهوة والمشروبات، خلاط البن |
| 👑 | `Crown` / `ShieldCheck` | لوحة السوبر أدمن، إدارة المستأجرين |
| 🛍️ | `ShoppingBag` / `FileText` | المبيعات، فواتير العملاء |
| 🛒 | `ShoppingCart` / `PackagePlus` | المشتريات، توريدات المخازن، سلة الكاشير |
| ⚡ | `Zap` | نقطة البيع السريعة POS، تحصيل إنستاباي InstaPay، ساعات الذروة |
| 💵 | `Banknote` | المبيعات النقدية، حركة الخزينة، اختبار درج النقدية |
| 💳 | `CreditCard` | مدفوعات البطاقات وفيزا، ديون وآجل العملاء |
| 📱 | `Smartphone` | المحافظ الإلكترونية، تطبيق الأندرويد APK |
| 💻 | `Monitor` | برنامج سطح المكتب Windows EXE |
| 🏦 | `Building2` | التحويلات البنكية، الفروع والمستأجرين |
| 📦 | `Package` / `Boxes` / `PackageSearch` | الأصناف، المخازن، النواقص، الحالات الفارغة (Empty State) |
| ⭐ | `Star` | المنتجات الأكثر مبيعاً، المفضلة |
| 🏪 | `Store` | كافة المنتجات، الفروع والمخازن |
| ⚖️ | `Scale` | ميزان البن والأوزان الحساسة، إدارة وحدات القياس |
| 🖨️ | `Printer` | طباعة الفواتير، إعدادات الطابعات الحرارية |
| 🏷️ | `Tag` | فئات المنتجات، تسعير العميل السابق |
| 👥 | `Users` | العملاء، إدارة علاقات العملاء CRM |
| 📊 / 📈 | `BarChart3` / `TrendingUp` | التقارير، الرسوم البيانية، حركة 7 أيام |
| 🧾 | `Receipt` | المصروفات، ورق طباعة 58mm |
| 📄 | `FileText` | الفواتير، ورق طباعة 80mm |
| 🚀 | `Sparkles` / `Rocket` | أزرار التحديث، إطلاق الإصدارات |
| 🗑️ / ✕ | `Trash2` / `X` | حذف الصنف، إغلاق النوافذ المنبثقة |
| ➕ / ➖ | `Plus` / `Minus` | عدادات الكميات والزيادة والنقصان |
| ✅ / ✓ | `CheckCircle2` | اعتماد الفاتورة، شاشات النجاح |
| 🔄 | `RefreshCw` / `RotateCw` | تحديث البيانات الحية، فحص الطابعات |
| 📂 | `Folder` | مجلدات وتصنيفات المنتجات في القوائم المصغرة |
| ⚠️ | `AlertTriangle` | تنبيهات التحديث الإلزامي والمخزون الحرج |
| 💾 | `Save` | حفظ الإعدادات والوحدات |

---

### 📂 الملفات المعدلة والمحدثة

1. **المكونات المشتركة والهياكل العامة (Common & Navigation):**
   * `backend/resources/js/Components/Common/DynamicIcon.vue` *(مكون جديد ذكي لحل الأيقونات ديناميكياً)*
   * `backend/resources/js/Components/Common/EmptyState.vue`
   * `backend/resources/js/Components/Common/MetricCard.vue`
   * `backend/resources/js/Components/Common/PageHeader.vue`
   * `backend/resources/js/Components/Common/ProgressDistributionList.vue`
   * `backend/resources/js/Components/Common/AppUpdateModal.vue`
   * `backend/resources/js/Components/Common/DesktopPrinterSettingsModal.vue`
   * `backend/resources/js/Components/Common/DesktopShortcutsModal.vue`
   * `backend/resources/js/Components/Navigation/DesktopSidebar.vue`
   * `backend/resources/js/Components/Navigation/DesktopTabsBar.vue`
   * `backend/resources/js/Components/Navigation/MobileBottomNav.vue`
   * `backend/resources/js/stores/tabs.js`

2. **شاشات ومكونات الـ POS والكاشير (POS Components):**
   * `backend/resources/js/Components/POS/POSCategorySidebar.vue`
   * `backend/resources/js/Components/POS/POSProductGrid.vue`
   * `backend/resources/js/Components/POS/POSCustomerBar.vue`
   * `backend/resources/js/Components/POS/POSCartItem.vue`
   * `backend/resources/js/Components/POS/POSCheckoutPanel.vue`
   * `backend/resources/js/Components/POS/POSWeightPickerModal.vue`
   * `backend/resources/js/Components/POS/POSCustomerPickerModal.vue`
   * `backend/resources/js/Components/POS/POSQuickCustomerModal.vue`
   * `backend/resources/js/Components/POS/POSQuickPinnedItems.vue`
   * `backend/resources/js/Components/POS/POSCategoryBar.vue`
   * `backend/resources/js/Components/POS/POSSuccessModal.vue`

3. **لوحة التحكم واليومية والتقارير والسوبر أدمن (Dashboard, Reports & Super Admin):**
   * `backend/resources/js/Components/Dashboard/DashboardWelcomeBanner.vue`
   * `backend/resources/js/Components/Dashboard/DashboardKpiGrid.vue`
   * `backend/resources/js/Components/Dashboard/DashboardAnalyticsRow.vue`
   * `backend/resources/js/Components/Dashboard/DashboardPeakHours.vue`
   * `backend/resources/js/Components/DailyJournal/DailyJournalTabs.vue`
   * `backend/resources/js/Components/Reports/ReportsNavigationTabs.vue`
   * `backend/resources/js/Composables/useReports.js`
   * `backend/resources/js/views/Reports/ReportsView.vue`
   * `backend/resources/js/views/SuperAdmin/SuperAdminDashboardView.vue`
   * `backend/resources/js/views/SuperAdmin/SuperAdminUnitsView.vue`
   * `backend/resources/js/Components/SuperAdmin/UploadApkModal.vue`

---

### ✅ نتائج التحقق والبناء
* **فحص البناء (`npm run build`):** ناجح 100% بدون أي أخطاء استيراد أو تحذيرات مفقودة (`✓ built in 4.80s`).
* **التوافق البصري:** مظهر موحد نظيف وعصري عبر كافة الشاشات والأجهزة.
