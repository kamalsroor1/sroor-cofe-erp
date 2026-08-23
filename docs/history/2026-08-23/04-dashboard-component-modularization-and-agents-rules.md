# سجل تعديل: هندسة وتقطيع لوحة التحكم وتحديث دستور الذكاء الاصطناعي AGENTS.md
* **التاريخ والوقت:** 2026-08-23 20:55
* **الدور المفعل:** Frontend / UI Agent & Backend Architect Agent
* **الهدف:** تطبيق نمط المنسق النحيف (Thin Orchestrator) وتقطيع صفحة الداشبورد إلى 6 مكونات فرعية ومكونات عامة مشتركة، مع فرض بوابة التعريب والترجمة 100%، وتحديث AGENTS.md بالمعايير الصارمة لكافة الصفحات القادمة.

## 1. الملفات المعدلة والجديدة:
* [MODIFIED] AGENTS.md - إضافة معيار هندسة صفحات Vue 3 Component-Driven وبوابة الترجمة الإلزامية 100% ونمط المنسق النحيف.
* [MODIFIED] 
esources/js/views/DashboardView.vue - تحويل الصفحة إلى منسق نحيف خفيف (~55 سطر) يجمع المكونات ويدير الـ State فقط.
* [NEW] 
esources/js/Components/Dashboard/DashboardWelcomeBanner.vue - بانر الترحيب وأزرار الـ POS والمشتريات وتحديث البيانات الحية.
* [NEW] 
esources/js/Components/Dashboard/DashboardKpiGrid.vue - شبكة بطاقات المؤشرات المالية الأربع.
* [NEW] 
esources/js/Components/Dashboard/DashboardAnalyticsRow.vue - قسم مخطط الـ 7 أيام وتوزيع طرق الدفع وحالة الوردية.
* [NEW] 
esources/js/Components/Dashboard/DashboardPeakHours.vue - خريطة حرارة ساعات الذروة (24 ساعة) وشارة الذروة القصوى.
* [NEW] 
esources/js/Components/Dashboard/DashboardRecentInvoices.vue - جدول آخر الفواتير الصادرة وزر المعاينة.
* [NEW] 
esources/js/Components/Dashboard/DashboardLowStock.vue - قائمة تنبيهات نواقص المخزون ورابط إعادة الطلب الذكي.
* [NEW] 
esources/js/Components/Common/DashboardSectionCard.vue - حاوية موحدة للكروت مع Header وأيقونة و Slot للإجراءات.
* [NEW] 
esources/js/Components/Common/SimpleBarChart.vue - مخطط بياني أعمدة تفاعلي مع Tooltip و Pulse.
* [NEW] 
esources/js/Components/Common/ProgressDistributionList.vue - قائمة توزيع بالنسب المئوية وأشرطة التقدم.
* [NEW] 
esources/js/Composables/useFormatters.js - كائن Composable مشترك لتنسيق العملات والكميات والنسب.
* [MODIFIED] 
esources/js/Components/Common/MetricCard.vue - توسيع الـ Props لتشمل تخصيص الأيقونة وتفاصيل السطر السفلي و Variants جديدة.
* [MODIFIED] 
esources/js/helpers/defaultTranslations.js - إضافة قاموس ترجمة شامل لكافة نصوص ومصطلحات الداشبورد.
* [MODIFIED] docs/pages-audit-log.md - تحديث سجل التدقيق بالهيكلية الموديلار الكاملة للداشبورد.

## 2. القرارات التقنية:
* اعتماد معيار الـ Single Responsibility لكل جزء من أجزاء الشاشة.
* خفض حجم الـ View الرئيسي بنسبة 85% (من ~600 سطر إلى ~55 سطر).
* تعريب وترجمة كامل النصوص عبر منظومة $t('key') بدون أي نص Hardcoded نهائياً.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (npm run build: ✓ 1898 modules transformed in 3.72s)
* [x] فحص الحفظ والتراجع والربط مع الـ APIs والـ Props
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 80 lines per view)
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن