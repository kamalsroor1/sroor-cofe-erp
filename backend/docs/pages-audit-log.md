# 📋 سجل مراجعة وهندسة الصفحات (Pages Audit & Component Architecture Log)
> آخر تحديث: 2026-08-23

---

## ✅ صفحة 1: لوحة التحكم الرئيسية (Dashboard)
* **التاريخ:** 2026-08-23
* **الملف الرئيسي:** `resources/js/views/DashboardView.vue` (Thin Orchestrator: ~55 سطر)
* **المسار (Route):** `/`
* **الحالة:** ✅ مكتملة 100% — تقطيع كامل إلى مكونات فرعية + استخراج المكونات العامة + تعريب وترجمة كاملة $t() بنسبة 100% + البناء ناجح (npm run build).

---

### 1. المكونات الفرعية الخاصة بالصفحة (`resources/js/Components/Dashboard/`):
| المكون | الوصف والمسؤولية | المصادر والـ Props |
|---|---|---|
| `DashboardWelcomeBanner.vue` | رأس الصفحة وبانر الترحيب وأزرار العمليات السريعة (POS، المشتريات، التحديث) | `companyName`, `loading`, emit(`refresh`) |
| `DashboardKpiGrid.vue` | شبكة بطاقات المؤشرات المالية الـ 4 (المبيعات، الأرباح، الديون، الشهر) | `metrics` |
| `DashboardAnalyticsRow.vue` | قسم المخطط البياني لـ 7 أيام + توزيع طرق الدفع + حالة الوردية | `dailyTrend`, `period`, `paymentDistribution`, `activeShift` |
| `DashboardPeakHours.vue` | خريطة حرارة ساعات الذروة على مدار 24 ساعة ومؤشر الذروة القصوى | `hourlySales`, `peakHour` |
| `DashboardRecentInvoices.vue` | جدول آخر الفواتير الصادرة وشارات الحالات وزر المعاينة | `invoices`, emit(`preview`) |
| `DashboardLowStock.vue` | قائمة تنبيهات نواقص المخزون ورابط مساعد المشتريات | `items` |

---

### 2. المكونات العامة المشتركة (`resources/js/Components/Common/`):
| المكون | التعديل / الإضافة | الغرض وقابلية الاستخدام في صفحات أخرى |
|---|---|---|
| `MetricCard.vue` | توسيع وإضافة props: `iconBg`, `iconColor`, `footerLeft`, `footerRight`, `footerRightClass`, variants (`cyan`, `indigo`) | بطاقات KPI موحدة لأي لوحة قيادة أو تقرير |
| `DashboardSectionCard.vue` | **مكون جديد** | حاوية الكروت الموحدة مع Header وأيقونة أو Dot وشارة تفاعلية (Slot) |
| `SimpleBarChart.vue` | **مكون جديد** | رسم بياني أعمدة CSS تفاعلي مع Tooltip و Pulse وتلوين مخصص |
| `ProgressDistributionList.vue` | **مكون جديد** | قائمة توزيع بالنسب المئوية وأشرطة التقدم الملونة |

---

### 3. دوال الـ Composables المستخرجة (`resources/js/Composables/`):
* `useFormatters.js`: دوال موحدة لتنسيق العملات (`formatMoney`)، الكميات (`formatQty`)، والنسب (`formatPercent`).

---

### 4. بوابة الترجمة والتعريب (100% Localization Gate):
* ✅ تم استبدال 100% من النصوص الثابتة داخل كافة المكونات بدوال `$t('dashboard.*')` و `trans('dashboard.*')`.
* ✅ تم تحديث `resources/js/helpers/defaultTranslations.js` بكامل المفاتيح والترجمات.
* ✅ متوافق تماماً مع تبديل اللغات وسياق المستأجرين.

---

## ✅ صفحة 2: فواتير المبيعات (Sales Invoices)
* **التاريخ:** 2026-08-23
* **الملف الرئيسي:** `resources/js/views/Invoices/InvoicesView.vue` (Thin Orchestrator: ~70 سطر بعد تفكيك 1024 سطر)
* **المسار (Route):** `/invoices`
* **الحالة:** ✅ مكتملة 100% — تقطيع كامل إلى 6 مكونات فرعية + استخراج المكونات العامة + تعريب وترجمة كاملة $t() بنسبة 100% + البناء واختبارات E2E و Feature ناجحة 100%.

### 1. المكونات الفرعية الخاصة بالصفحة (`resources/js/Components/Invoices/`):
| المكون | الوصف والمسؤولية | المصادر والـ Props |
|---|---|---|
| `InvoicesMetricsCards.vue` | شبكة بطاقات المؤشرات المالية الـ 4 (إجمالي المبيعات، المحصل، المتبقي، عدد الفواتير) | `summary` |
| `InvoicesQuickSearch.vue` | شريط البحث وأقراص التواريخ السريعة الملموسة | `modelValue`, `activePreset`, `presets` |
| `InvoicesBulkActionsBar.vue` | شريط الإجراءات المجمعة العائم (طباعة، تصدير، إلغاء) | `selectedCount` |
| `InvoicesFilterSidebar.vue` | درج وقائمة الفلترة المتقدمة (الفرع، نوع السداد، الحالة، نطاق التاريخ) | `isOpen`, `storeId`, `paymentType`, `status`, `dateFrom`, `dateTo` |
| `InvoicesTable.vue` | الجدول المزدوج التجاوبي (Desktop Table + Mobile Touch Cards) | `invoices`, `loading`, `selectedIds`, `isAllSelected`, `pagination` |
| `InvoiceDetailsModal.vue` | نافذة منبثقة تفاعلية لتفاصيل الفاتورة والأصناف والطباعة والواتساب | `show`, `invoice`, `whatsApp` |

---

### 2. المكونات العامة المشتركة:
* `PageHeader.vue`, `MetricCard.vue`, `BaseSelect.vue`, `AppModal.vue`, `EmptyState.vue`.

---

### 3. بوابة الترجمة والتعريب (100% Localization Gate):
* ✅ تم إضافة كافة مفاتيح الترجمة في `lang/ar/invoices.php`, `lang/en/invoices.php` و `defaultTranslations.js`.
* ✅ خلو الكود 100% من أي Hardcoded Strings.

---

---

## ✅ صفحة 3: نقطة البيع والكاشير السريع (POS Cashier)
* **التاريخ:** 2026-08-23
* **الملف الرئيسي:** `resources/js/views/POS/PosView.vue` (Thin Orchestrator: ~70 سطر بعد تفكيك 1034 سطر)
* **المسار (Route):** `/pos`
* **الحالة:** ✅ مكتملة 100% — تقطيع كامل إلى 6 مكونات فرعية + استخراج المكونات العامة + تعريب وترجمة كاملة $t() بنسبة 100% + البناء واختبارات E2E و Feature ناجحة 100%.

### 1. المكونات الفرعية الخاصة بالصفحة (`resources/js/Components/POS/`):
| المكون | الوصف والمسؤولية | المصادر والـ Props |
|---|---|---|
| `POSHeader.vue` | شريط الرأس والبحث بالباركود واختيار العميل والأسعار وإفراغ السلة | `appVersion`, `activeStore`, `activeShift`, `searchQuery`, `searchResults`, `activePriceTier`, `selectedCustomer` |
| `POSCartTable.vue` | جدول بنود الفاتورة وإدارة الكميات والأسعار والحذف والحالة الفارغة | `cart`, `totalQty` |
| `POSQuickPinnedItems.vue` | شريط الأصناف الشائعة الملموسة بنقرة واحدة | `items`, `activePriceTier` |
| `POSCheckoutPanel.vue` | لوحة المحاسبة والتلخيص المالي والدفع وحساب الباقي للعميل | `subtotal`, `discountAmount`, `netTotal`, `paymentType`, `paymentMethod`, `cashReceived`, `changeDue` |
| `POSCustomerModal.vue` | نافذة اختيار أو إضافة عميل سريع مع فحص الرصيد | `show`, `searchQuery`, `customers`, `selectedCustomerId` |
| `POSSuccessModal.vue` | نافذة تأكيد الفاتورة وطباعة الإيصال الفوري | `show`, `invoice` |

---

### 2. المكونات العامة المشتركة:
* `AppModal.vue`.

---

### 3. بوابة الترجمة والتعريب (100% Localization Gate):
* ✅ تم إضافة كافة مفاتيح الترجمة في `lang/ar/pos.php`, `lang/en/pos.php` و `defaultTranslations.js`.
* ✅ خلو الكود 100% من أي Hardcoded Strings.

---

## 📌 الصفحة التالية في خطة التدقيق:
**صفحة إدارة الأصناف والمنتجات (`resources/js/views/Items/ItemsView.vue`)**



