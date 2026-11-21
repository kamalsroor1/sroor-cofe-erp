import{B as e,Ct as t,Dt as n,Ft as r,G as i,L as a,O as o,P as s,R as c,V as l,at as u,en as d,ht as f,nn as p,st as m,vt as ee,z as h}from"./centralApi-DCX6LU68.js";import{a as g,i as te}from"./vue-router-CGa2WyDU.js";import{t as _}from"./appConfig-BSRaMJH8.js";import"./useTrans-BzmKqvbx.js";import{t as v}from"./message-circle-CJg8mPuh.js";import{Ct as y,M as b,d as x,fn as S,rt as C}from"./app-BnIGVtxw.js";import{t as w}from"./useSupportContact-BitQ2NVf.js";import{t as T}from"./PageHeader-BIaOrYrJ.js";import{t as E}from"./EmptyState-BgBVzhG_.js";var D={},O={class:`grid grid-cols-1 lg:grid-cols-12 gap-6 animate-pulse font-tajawal`,"data-testid":`help-skeleton`},k={class:`lg:col-span-4 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-4`},A={class:`space-y-2.5`},j={class:`lg:col-span-8 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 sm:p-8 space-y-6`};function M(e,t){return u(),l(`div`,O,[c(`div`,k,[t[0]||=c(`div`,{class:`h-11 rounded-2xl bg-slate-200 dark:bg-slate-800`},null,-1),c(`div`,A,[(u(),l(s,null,m(7,e=>c(`div`,{key:e,class:`h-12 rounded-2xl bg-slate-100 dark:bg-slate-800/70`})),64))])]),c(`div`,j,[t[2]||=c(`div`,{class:`space-y-2`},[c(`div`,{class:`h-5 w-28 rounded-full bg-slate-200 dark:bg-slate-800`}),c(`div`,{class:`h-8 w-2/3 rounded-xl bg-slate-200 dark:bg-slate-800`})],-1),(u(),l(s,null,m(3,e=>c(`div`,{key:e,class:`space-y-3 pt-2`},[...t[1]||=[c(`div`,{class:`h-6 w-1/3 rounded-lg bg-slate-200 dark:bg-slate-800`},null,-1),c(`div`,{class:`h-4 w-full rounded-lg bg-slate-100 dark:bg-slate-800/60`},null,-1),c(`div`,{class:`h-4 w-5/6 rounded-lg bg-slate-100 dark:bg-slate-800/60`},null,-1)]])),64))])])}var N=x(D,[[`render`,M]]),P={class:`rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 space-y-4 shadow-xs font-tajawal`},F={class:`relative`},I=[`value`,`placeholder`],L=[`aria-label`],R={class:`flex items-center justify-between px-1`},z={class:`text-xs font-black text-slate-500 dark:text-slate-400`},B={class:`text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300`},V={key:0,class:`space-y-1.5`,"data-testid":`help-articles-list`},H=[`data-testid`,`onClick`],U={class:`min-w-0`},W={class:`text-xs sm:text-sm truncate`},G={class:`text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5`},K={__name:`HelpSidebarList`,props:{articles:{type:Array,default:()=>[]},activeSlug:{type:String,default:``},searchQuery:{type:String,default:``}},emits:[`select`,`update:searchQuery`],setup(t){return(n,a)=>(u(),l(`aside`,P,[c(`div`,F,[i(r(C),{class:`w-4 h-4 text-slate-400 absolute top-1/2 -translate-y-1/2 start-3.5 pointer-events-none`}),c(`input`,{type:`search`,"data-testid":`help-search-input`,value:t.searchQuery,onInput:a[0]||=e=>n.$emit(`update:searchQuery`,e.target.value),placeholder:n.$t(`help.search_placeholder`),class:`w-full min-h-[44px] ps-10 pe-9 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/80 text-xs sm:text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-hidden focus:border-theme-primary transition`},null,40,I),t.searchQuery?(u(),l(`button`,{key:0,type:`button`,onClick:a[1]||=e=>n.$emit(`update:searchQuery`,``),"aria-label":n.$t(`help.search_clear`),class:`min-h-[36px] min-w-[36px] absolute top-1/2 -translate-y-1/2 end-1.5 flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl cursor-pointer`},[i(r(b),{class:`w-4 h-4`})],8,L)):e(``,!0)]),c(`div`,R,[c(`h2`,z,p(n.$t(`help.articles_list_title`)),1),c(`span`,B,p(n.$t(`help.articles_count`,{count:t.articles.length})),1)]),t.articles.length>0?(u(),l(`nav`,V,[(u(!0),l(s,null,m(t.articles,e=>(u(),l(`button`,{key:e.slug,type:`button`,"data-testid":`help-article-item-${e.slug}`,onClick:t=>n.$emit(`select`,e.slug),class:d([`w-full min-h-[44px] px-3.5 py-3 rounded-2xl text-start flex items-center justify-between gap-3 border transition cursor-pointer`,t.activeSlug===e.slug?`border-theme-primary bg-theme-light text-theme-primary font-black shadow-2xs`:`border-transparent bg-slate-50/70 dark:bg-slate-800/40 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold`])},[c(`div`,U,[c(`div`,W,p(e.title),1),c(`div`,G,p(e.categoryLabel),1)]),i(r(S),{class:`w-4 h-4 shrink-0 opacity-70`})],10,H))),128))])):e(``,!0)]))}},q={key:0,"data-testid":`help-article-viewer`,class:`rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 sm:p-8 shadow-xs space-y-6 font-tajawal`},ne={class:`border-b border-slate-200 dark:border-slate-800 pb-5 space-y-2`},J={class:`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-theme-light text-theme-primary border border-theme-border`},Y={class:`text-lg sm:text-2xl font-black text-slate-900 dark:text-white leading-snug`},X={key:0,class:`font-black text-slate-900 dark:text-white`},Z={key:1,dir:`ltr`,class:`px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 font-mono text-xs text-theme-primary`},re={key:2},ie={class:`space-y-6`},ae={key:0,class:`text-sm sm:text-base font-black text-slate-900 dark:text-white`},oe={key:0,class:`font-black text-slate-900 dark:text-white`},se={key:1,dir:`ltr`,class:`px-1.5 py-0.5 rounded-md bg-slate-200/70 dark:bg-slate-900 font-mono text-xs text-theme-primary`},ce={key:2},le={key:1,class:`space-y-2 ps-1`},ue={key:0,class:`font-black text-slate-900 dark:text-white`},de={key:1,dir:`ltr`,class:`px-1.5 py-0.5 rounded-md bg-slate-200/70 dark:bg-slate-900 font-mono text-xs text-theme-primary`},fe={key:2},pe={class:`pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4`},me={class:`text-xs sm:text-sm font-black text-slate-900 dark:text-white`},he={class:`text-xs text-slate-500 dark:text-slate-400 mt-0.5`},ge=[`href`],_e={__name:`HelpArticleViewer`,props:{article:{type:Object,default:null},supportHref:{type:String,default:``}},setup(t){return(n,a)=>t.article?(u(),l(`article`,q,[c(`header`,ne,[c(`span`,J,[i(r(S),{class:`w-3.5 h-3.5`}),c(`span`,null,p(t.article.categoryLabel),1)]),c(`h2`,Y,p(t.article.title),1),(u(!0),l(s,null,m(t.article.intro,(e,t)=>(u(),l(`p`,{key:`intro-`+t,class:`text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium leading-relaxed`},[(u(!0),l(s,null,m(e,(e,t)=>(u(),l(s,{key:t},[e.type===`bold`?(u(),l(`strong`,X,p(e.value),1)):e.type===`code`?(u(),l(`code`,Z,p(e.value),1)):(u(),l(`span`,re,p(e.value),1))],64))),128))]))),128))]),c(`div`,ie,[(u(!0),l(s,null,m(t.article.sections,(t,n)=>(u(),l(`section`,{key:`sec-`+n,class:`p-4 sm:p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800 space-y-3`},[t.heading?(u(),l(`h3`,ae,p(t.heading),1)):e(``,!0),(u(!0),l(s,null,m(t.paragraphs,(e,t)=>(u(),l(`p`,{key:`p-`+t,class:`text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed`},[(u(!0),l(s,null,m(e,(e,t)=>(u(),l(s,{key:t},[e.type===`bold`?(u(),l(`strong`,oe,p(e.value),1)):e.type===`code`?(u(),l(`code`,se,p(e.value),1)):(u(),l(`span`,ce,p(e.value),1))],64))),128))]))),128)),t.bullets.length>0?(u(),l(`ul`,le,[(u(!0),l(s,null,m(t.bullets,(e,t)=>(u(),l(`li`,{key:`b-`+t,class:`flex items-start gap-2.5 text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed`},[a[0]||=c(`span`,{class:`w-1.5 h-1.5 rounded-full bg-theme-primary mt-2 shrink-0`},null,-1),c(`span`,null,[(u(!0),l(s,null,m(e,(e,t)=>(u(),l(s,{key:t},[e.type===`bold`?(u(),l(`strong`,ue,p(e.value),1)):e.type===`code`?(u(),l(`code`,de,p(e.value),1)):(u(),l(`span`,fe,p(e.value),1))],64))),128))])]))),128))])):e(``,!0)]))),128))]),c(`footer`,pe,[c(`div`,null,[c(`h4`,me,p(n.$t(`help.support_card_title`)),1),c(`p`,he,p(t.supportHref?n.$t(`help.support_card_desc`):n.$t(`help.support_not_configured`)),1)]),t.supportHref?(u(),l(`a`,{key:0,href:t.supportHref,target:`_blank`,rel:`noopener noreferrer`,"data-testid":`help-article-support-link`,class:`min-h-[44px] px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition active:scale-95 shrink-0`},[i(r(v),{class:`w-4 h-4`}),c(`span`,null,p(n.$t(`help.contact_support_whatsapp`)),1)],8,ge)):e(``,!0)])])):e(``,!0)}},ve=Object.assign({"../../help/ar/01-getting-started.md":`# أول تشغيل وتهيئة المحل

أهلاً بك في نظام إدارة المبيعات والمخزون ونقاط البيع. هذا الدليل السريع يوضح الخطوات الأساسية لتجهيز حساب منشأتك والبدء في البيع خلال دقائق.

## 1. استكمال بيانات وهوية المنشأة
- افتح **إعدادات المؤسسة** من قائمة إدارة النظام.
- سجّل **الاسم التجاري**، **رقم الهاتف الرسمي**، **العنوان**، والبيانات الضريبية (السجل التجاري ورقم التسجيل الضريبي).
- اختر **العملة الأساسية**، **المنطقة الزمنية**، وموعد **بداية اليوم التجاري** لو المحل بيشتغل بعد منتصف الليل.
- ارفع شعار المحل للوضع الفاتح والداكن وحدد ترويسة وتذييل الإيصال الحراري.

## 2. إعداد الفروع والمخازن
- من شاشة **المخازن والفروع**، راجع بيانات الفرع الرئيسي أو أضف فروعك وعربات التوزيع الإضافية.
- يمكنك التبديل بين الفروع من الشريط العلوي في أي وقت.

## 3. قائمة «ابدأ من هنا» في لوحة التحكم
- تظهر في **لوحة التحكم الرئيسية** قائمة خطوات التشغيل الأول تلقائياً.
- كل خطوة مكتملة تتحول لعلامة صح خضراء، ويمكنك الضغط على أي خطوة للانتقال مباشرة لشاشاتها أو الضغط على زر **؟** بجوارها لقراءة شرحها.
`,"../../help/ar/02-items.md":`# إضافة الأصناف والفئات والاستيراد

إدارة كتالوج الأصناف هي الأساس لسرعة البيع في الكاشير ودقة جرد المخزون.

## 1. فئات وتصنيفات الأصناف
- من شاشة **فئات الأصناف**، أنشئ الأقسام الرئيسية (مثل: بن محمص، مشروبات ساخنة، مستلزمات).
- تظهر هذه الفئات كأزرار فلترة سريعة داخل شاشة الكاشير لتسهيل الوصول للأصناف.

## 2. إضافة صنف جديد
- من شاشة **الأصناف والأسعار**، اضغط **إضافة صنف جديد**.
- حدد **اسم الصنف**، **الكود أو الباركود**، **الفئة**، و**وحدة القياس** المعتمدة (قطعة، كجم، جرام، علبة...).
- أدخل **سعر التكلفة**، **سعر البيع القطاعي**، **سعر الجملة** (اختياري)، و**حد النواقص الأدنى** للتنبيه عند انخفاض الرصيد.

## 3. استيراد وتصدير الأصناف
- يمكنك رفع قائمة أصنافك دفعة واحدة أو تصدير أرصدة وأسعار الأصناف للمراجعة.
- عند إنشاء صنف جديد، يقوم النظام بتهيئة رصيد ابتدائي صفر للصنف في كافة الفروع النشطة تلقائياً لمنع سقوط أي فرع من الرقابة المخزنية.
`,"../../help/ar/03-pos.md":`# نقطة البيع السريعة (POS)

صُممت شاشة الكاشير للعمل بأقصى سرعة سواء باللمس أو بلوحة المفاتيح وقارئ الباركود.

## 1. اختصارات الكيبورد والضرب السريع
- **F1**: عرض نافذة دليل اختصارات لوحة المفاتيح.
- **F2**: التركيز الفوري في حقل البحث والباركود.
- **F4**: اختيار أو البحث عن عميل وربطه بالفاتورة.
- **F8**: تعليق الفاتورة الحالية مؤقتاً واسترجاعها لاحقاً.
- **F9** أو **F10**: فتح لوحة الدفع وإتمام الفاتورة وتأكيد البيع.
- **الضرب السريع (\`3*\`)**: اكتب الكمية متبوعة بعلامة النجمة مثل \`3*\` ثم امسح الباركود أو اختر الصنف لإضافة 3 وحدات دفعة واحدة.

## 2. البيع بالوزن للأصناف الموزونة
- الأصناف التي تُباع بالكيلوجرام أو الجرام تفتح نافذة تحديد الوزن أو المبلغ السريع.
- يمكنك اختيار أوزان جاهزة (مثل 125 جم، 250 جم، 500 جم، 1 كجم) أو كتابة المبلغ المطلوب ليحسب النظام الوزن المقابل تلقائياً بدقة عشرية.

## 3. الخصم وتفويض المدير (PIN)
- لكل فرع نسبة خصم قصوى مسموح بها للكاشير مباشرة.
- إذا تجاوز الخصم الحد المسموح للكاشير، يطلب النظام إدخال **رمز PIN السري للمدير** للموافقة الفورية دون الخروج من الفاتورة.

## 4. بوابة الوردية وطرق الدفع
- لا يمكن إصدار فاتورة بيع قبل **فتح وردية كاشير** نشطة على الفرع الحالي.
- يدعم الكاشير الدفع **النقدي**، **البطاقة/الشبكة**، **المحفظة الإلكترونية**، **الآجل (حساب عميل)**، أو **الدفع المختلط (Split Payment)** مع حساب الباقي للعميل تلقائياً.
`,"../../help/ar/04-returns.md":`# المرتجعات وشروط الاسترجاع

يحمي النظام المخزون والخزينة عن طريق ربط كل مرتجع مبيعات بفاتورته الأصلية.

## 1. قاعدة الربط الإلزامي بالفاتورة الأصلية
- **لا يوجد مرتجع حر بدون فاتورة**: يجب اختيار فاتورة البيع الأصلية أولاً لضمان إرجاع الأصناف بنفس سعر بيعها الفعلي وخصوماتها.
- يمنع النظام إرجاع كمية أكبر من الكمية المتبقية القابلة للإرجاع في الفاتورة الأصلية.

## 2. خطوات تسجيل مرتجع
- من شاشة **سجل المرتجعات** اضغط **تسجيل مرتجع جديد**، أو استخدم **المرتجع السريع** من درج آخر المبيعات في الكاشير.
- ابحث برقم الفاتورة الأصلية، ثم حدد الكميات المرتجعة لكل صنف واكتب سبب الإرجاع.
- عند الحفظ، يعيد النظام الكميات لمخزن الفرع، ويخصم القيمة من الخزينة أو من مديونية العميل الآجل داخل معاملة آمنة.
`,"../../help/ar/05-purchases.md":`# المشتريات والموردين وإعادة الطلب

تضمن وحدة المشتريات تغذية المخازن بالبضاعة وحساب متوسط التكلفة المرجح بدقة.

## 1. تسجيل فاتورة مشتريات جديدة
- من شاشة **فواتير المشتريات**، اضغط **فاتورة مشتريات جديدة**.
- اختر **المورد** والفرع/المخزن المستلم، ثم أضف الأصناف والكميات وأسعار الشراء.
- حدد المبلغ المدفوع وطريقة السداد، وسيقوم النظام بتحديث رصيد المخزن، حساب **متوسط التكلفة المرجح (Weighted Average Cost)**، وتحديث كشف حساب المورد تلقائياً.

## 2. رادار إعادة الطلب الذكي
- من شاشة **مساعد المشتريات وإعادة الطلب**، يعرض النظام الأصناف التي وصلت لحد النواقص مقسمة حسب المورد لتوليد أوامر شراء سريعة.
`,"../../help/ar/06-treasury-shifts.md":`# الخزينة والورديات ودفتر اليومية

يتابع النظام كل حركة نقدية داخل الدرج والخزينة ويربطها بالوردية والفرع.

## 1. فتح وإغلاق الوردية (Z-Report)
- عند بداية العمل، يفتح الكاشير وردية جديدة مع تسجيل **رصيد بداية الدرج (العهدة الافتتاحية)**.
- عند انتهاء الوردية، يتم جرد النقدية الفعلية وإغلاق الوردية، ليحسب النظام **الرصيد المتوقع** و**العجز أو الزيادة** بدقة ويصدر تقرير إغلاق الوردية.

## 2. دفتر اليومية والمصروفات
- من شاشة **اليومية وحركة الدرج**، يمكنك متابعة كافة المقبوضات والمدفوعات اللحظية، وتسجيل عمليات التوريد أو السحب النقدي.
- من شاشة **المصروفات والعهد**، سجّل المصروفات التشغيلية اليومية مع تحديد الفئة والبيان لخصمها من الخزينة وإدراجها في تقارير صافي الربح.
`,"../../help/ar/07-reports.md":`# التقارير المالية وتقارير المخزون

توفر شاشة التقارير رؤية مالية ومخزنية شاملة لكل فرع أو للمؤسسة بالكامل.

## 1. التقارير المالية والأرباح
- **تقرير المبيعات والأرباح**: يعرض إجمالي المبيعات، تكلفة البضاعة المباعة، المصروفات، وصافي الربح خلال الفترة المحددة.
- **تقرير الأداء المالي والذروة**: يحلل ساعات الذروة، طرق الدفع، وأفضل الأصناف مبيعاً.

## 2. تقارير المخزون وفروق الأرصدة
- **حركة وتقييم المخزون**: يعرض قيمة المخزون الحالية بسعر التكلفة وسعر البيع.
- **تقرير فروق المخزون**: يقارن الرصيد الإجمالي للصنف بمجموع أرصدته في كافة الفروع لاكتشاف أي انحراف محاسبي فوراً.
`,"../../help/ar/08-users-roles.md":`# الصلاحيات والمستخدمين والأمان

تحكم كامل في صلاحيات كل موظف والفروع المسموح له بالعمل عليها.

## 1. إضافة المستخدمين وتعيين الفروع
- من شاشة **المستخدمون والكاشير**، أضف حسابات الموظفين وحدد الفرع الافتراضي والفروع المسموح لكل موظف بالوصول إليها.
- يمكنك تعيين **رمز الدخول السريع (PIN)** للمديرين والمشرفين لاستخدامه في تفويض الخصومات داخل الكاشير.

## 2. مصفوفة الأدوار والصلاحيات
- من شاشة **الأدوار والصلاحيات**، خصص صلاحيات كل دور وظيفي (مدير، كاشير، محاسب، أمين مخزن) بدقة على مستوى كل عملية (عرض، إضافة، تعديل، حذف، تقارير).
- تُسجل كافة التعديلات الحساسة في **سجل الرقابة والتدقيق** للمراجعة في أي وقت.
`,"../../help/ar/09-plans-billing.md":`# الباقات والاشتراك والدعم الفني

تُدار حدود الاستخدام والمزايا المتاحة لمنشأتك وفق باقة الاشتراك الفعّالة.

## 1. حدود الباقة والمزايا
- تحدد الباقة الحد الأقصى لعدد **الفروع**، **المستخدمين**، **الأصناف**، و**الفواتير الشهرية**، بالإضافة إلى المزايا المتقدمة مثل التحويلات المخزنية، التقارير المتقدمة، وسجل الرقابة.
- عند اقترابك من سقف الباقة أو الحاجة لتفعيل ميزة إضافية، يظهر تنبيه واضح داخل الشاشة.

## 2. الترقية والتواصل مع الدعم الفني
- لترقية باقتك أو زيادة حدود الفروع والمستخدمين أو طلب مساعدة فنية، اضغط على زر **تواصل مع الدعم** في أعلى مركز المساعدة للتحدث مباشرة مع فريق الدعم عبر واتساب.
`,"../../help/en/01-getting-started.md":`# Getting Started & Initial Setup

Welcome to the Sales, Inventory, and Point of Sale ERP. This quick guide walks you through setting up your shop and making your first sale in minutes.

## 1. Complete Business Identity & Settings
- Open **Business Settings** from the System Management menu.
- Enter your **Trade Name**, **Official Phone Number**, **Address**, and legal registration numbers (Commercial Register and Tax ID).
- Select your **Primary Currency**, **Time Zone**, and **Business Day Cutoff** if your shop operates past midnight.
- Upload your Light and Dark mode logos and customize your thermal receipt header and footer.

## 2. Configure Branches & Warehouses
- Under **Stores & Branches**, review your main branch details or add additional retail branches and distribution vans.
- Switch between active branches anytime using the top header bar.

## 3. "Getting Started" Checklist on the Dashboard
- The **Main Dashboard** displays your onboarding checklist automatically.
- Completed steps turn green, and you can click any step to go directly to its screen or click the **?** icon next to a step to read its guide.
`,"../../help/en/02-items.md":`# Adding Items, Categories & Importing

Managing your item catalog is the foundation for fast POS checkout and accurate stock tracking.

## 1. Item Categories
- Under **Item Categories**, create your main groups (e.g., Roasted Coffee, Hot Drinks, Supplies).
- Categories appear as quick filter tabs on the POS screen for fast cashier access.

## 2. Creating a New Item
- Under **Items & Prices**, click **Add New Item**.
- Specify the **Item Name**, **Code or Barcode**, **Category**, and approved **Unit of Measure** (piece, kg, gram, box...).
- Enter the **Cost Price**, **Retail Selling Price**, **Wholesale Price** (optional), and **Minimum Stock Level** for low-stock alerts.

## 3. Importing & Exporting Items
- Upload your item catalog in bulk or export stock balances and prices for review.
- Whenever a new item is created, the system initializes a zero stock row across all active branches automatically so no branch is ever skipped during stock checks.
`,"../../help/en/03-pos.md":`# Quick Point of Sale (POS)

The POS cashier screen is built for maximum speed with touch screens, keyboards, and barcode scanners.

## 1. Keyboard Shortcuts & Quantity Multiplier
- **F1**: Open the keyboard shortcuts reference modal.
- **F2**: Focus the barcode and item search input immediately.
- **F4**: Search and attach a customer to the current invoice.
- **F8**: Hold (park) the current cart and recall it later.
- **F9** or **F10**: Open the payment panel and complete checkout.
- **Quick Multiplier (\`3*\`)**: Type a quantity followed by an asterisk like \`3*\` then scan a barcode or click an item to add 3 units at once.

## 2. Selling Weighed Items
- Items sold by weight (kg or gram) open the quick weight and price picker modal.
- Choose preset weights (e.g., 125g, 250g, 500g, 1kg) or enter a target amount and the system calculates the exact decimal weight automatically.

## 3. Discounts & Manager PIN Override
- Each branch configures a maximum discount percentage allowed directly for cashiers.
- If a discount exceeds the cashier limit, the system prompts for the **Manager PIN** to authorize the discount inline without leaving the sale.

## 4. Shift Gate & Payment Methods
- A sale cannot be completed until an **active cashier shift** is opened for the current branch.
- Checkout supports **Cash**, **Card**, **Digital Wallet**, **Credit (Customer Account)**, and **Split Payment** with automatic change calculation.
`,"../../help/en/04-returns.md":`# Sales Returns & Refund Rules

The system protects inventory and treasury integrity by linking every sales return to its original invoice.

## 1. Mandatory Original Invoice Link
- **No unlinked returns**: Every return must reference an original sales invoice so items are refunded at their actual selling price and discount.
- The system prevents returning a quantity greater than the remaining returnable quantity on the original invoice.

## 2. Recording a Return
- Open **Returns Register** and click **New Return**, or use **Quick Return** from the Recent Sales drawer in the POS.
- Search by the original invoice number, enter the returned quantities for each line, and provide a return reason.
- Upon saving, the system restores stock to the branch warehouse and adjusts the treasury or customer credit balance inside a database transaction.
`,"../../help/en/05-purchases.md":`# Purchases, Suppliers & Smart Reorder

The Purchasing module replenishes branch stock and maintains accurate Weighted Average Cost calculations.

## 1. Recording a Purchase Invoice
- Under **Purchases**, click **New Purchase Invoice**.
- Select the **Supplier** and receiving branch/warehouse, then add items, quantities, and purchase unit costs.
- Enter the paid amount and payment method. The system updates branch stock, recalculates **Weighted Average Cost**, and updates the supplier statement automatically.

## 2. Smart Reorder Radar
- Under **Purchasing Assistant & Reorder**, view all items at or below their low-stock threshold grouped by supplier to generate purchase orders quickly.
`,"../../help/en/06-treasury-shifts.md":`# Treasury, Cash Shifts & Daily Journal

Every cash movement in the drawer and treasury is tracked and linked to its shift and branch.

## 1. Opening & Closing Shifts (Z-Report)
- At the start of work, the cashier opens a shift and records the **Opening Cash Balance**.
- At the end of the shift, count the actual cash in the drawer and close the shift. The system calculates the **Expected Balance**, reports any **Shortage or Overage**, and generates the shift Z-report.

## 2. Daily Journal & Expenses
- Under **Daily Journal, Cash Drawer & Shifts**, monitor live cash inflows and outflows and record cash deposits or withdrawals.
- Under **Expenses & Custody**, record daily operating expenses by category so they are deducted from the treasury and reflected in net profit reports.
`,"../../help/en/07-reports.md":`# Financial & Inventory Reports

The Reports center provides a complete financial and inventory overview per branch or across the entire business.

## 1. Financial & Profit Reports
- **Sales & Profit Summary**: Displays gross sales, cost of goods sold (COGS), operating expenses, and net profit over the selected date range.
- **Performance & Peak Hours**: Analyzes peak business hours, payment method distribution, and top-selling items.

## 2. Inventory Valuation & Stock Drift
- **Stock Valuation & Movements**: Shows current inventory value at both cost price and retail selling price.
- **Stock Drift Report**: Compares each item's master stock balance against the sum of its branch quantities to detect any accounting discrepancy immediately.
`,"../../help/en/08-users-roles.md":`# Users, Roles & Permissions

Full control over staff access, branch assignments, and role permissions.

## 1. Adding Staff & Assigning Branches
- Under **Users & Cashiers**, create staff accounts and select each user's default branch and allowed branches.
- Assign a **Quick PIN** to managers and supervisors so they can authorize discounts directly on the POS screen.

## 2. Roles & Permissions Matrix
- Under **Roles & Permissions**, customize the permissions granted to each role (Admin, Cashier, Accountant, Storekeeper) across every module.
- Sensitive permission and setting changes are recorded in the **Activity & Audit Log** for review at any time.
`,"../../help/en/09-plans-billing.md":`# Plans, Subscription & Support

Your workspace limits and enabled features are governed by your active subscription plan.

## 1. Plan Limits & Entitlements
- Your plan defines the maximum number of **Branches**, **Users**, **Items**, and **Monthly Invoices**, as well as advanced features such as Stock Transfers, Advanced Reports, and Audit Logs.
- When you approach a plan limit or attempt to open a gated feature, a clear notice is displayed.

## 2. Upgrading & Contacting Technical Support
- To upgrade your plan, increase branch or user limits, or request technical assistance, click the **Contact Support** button at the top of the Help Center to chat directly with our support team on WhatsApp.
`});function ye(e){let t=String(e??``).replace(/\r\n/g,`
`).split(`
`),n=``,r=[],i=[],a=null;for(let e of t){let t=e.trim();if(t){if(t.startsWith(`# `)&&!n){n=t.slice(2).trim();continue}if(t.startsWith(`## `)){a={heading:t.slice(3).trim(),paragraphs:[],bullets:[]},i.push(a);continue}if(t.startsWith(`- `)||t.startsWith(`* `)){let e=t.slice(2).trim();a||(a={heading:``,paragraphs:[],bullets:[]},i.push(a)),a.bullets.push(Q(e));continue}a?a.paragraphs.push(Q(t)):r.push(Q(t))}}return{title:n,intro:r,sections:i}}function Q(e){let t=[],n=/(\*\*[^*]+\*\*|`[^`]+`)/g,r=0,i;for(;(i=n.exec(e))!==null;){i.index>r&&t.push({type:`text`,value:e.slice(r,i.index)});let a=i[0];a.startsWith(`**`)&&a.endsWith(`**`)?t.push({type:`bold`,value:a.slice(2,-2)}):a.startsWith("`")&&a.endsWith("`")&&t.push({type:`code`,value:a.slice(1,-1)}),r=n.lastIndex}return r<e.length&&t.push({type:`text`,value:e.slice(r)}),t}function be(){let e=te(),t=g(),r=_(),{t:i}=o(),s=n(!1),c=n(``),l=n(``),u=a(()=>{let e=r.locale===`en`?`en`:`ar`,t=[];for(let[n,r]of Object.entries(ve)){let a=n.match(/\/help\/(ar|en)\/(\d+)-([a-z0-9-]+)\.md$/i);if(!a)continue;let[,o,s,c]=a;if(o!==e)continue;let l=ye(r);t.push({slug:c,order:Number(s),title:l.title||i(`help.categories.${c}`),categoryLabel:i(`help.categories.${c}`),intro:l.intro,sections:l.sections})}return t.sort((e,t)=>e.order-t.order)}),d=a(()=>{let e=c.value.trim().toLowerCase();return e?u.value.filter(t=>t.title.toLowerCase().includes(e)||t.categoryLabel.toLowerCase().includes(e)):u.value}),p=a(()=>{let e=d.value;return e.length===0?null:e.find(e=>e.slug===l.value)||e[0]});return f(()=>e?.query?.article,e=>{typeof e==`string`&&e.trim()!==``?l.value=e.trim():!l.value&&u.value.length>0&&(l.value=u.value[0].slug)},{immediate:!0}),{isLoading:s,searchQuery:c,articles:u,filteredArticles:d,activeSlug:l,activeArticle:p,selectArticle:n=>{l.value=n,e&&t&&e.query?.article!==n&&t.replace({query:{...e.query,article:n}}).catch(()=>{})}}}var $={class:`space-y-6 max-w-6xl mx-auto font-tajawal pb-12`,"data-testid":`help-view`},xe=[`href`],Se=[`title`],Ce={key:1,class:`grid grid-cols-1 lg:grid-cols-12 gap-6 items-start`},we={class:`lg:col-span-4`},Te={class:`lg:col-span-8`},Ee={__name:`HelpView`,setup(e){let{isLoading:n,searchQuery:a,filteredArticles:o,activeSlug:s,activeArticle:d,selectArticle:f}=be(),{supportHref:m}=w();return(e,g)=>(u(),l(`div`,$,[i(T,{title:e.$t(`help.title`),subtitle:e.$t(`help.subtitle`),icon:r(y)},{actions:ee(()=>[r(m)?(u(),l(`a`,{key:0,href:r(m),target:`_blank`,rel:`noopener noreferrer`,"data-testid":`contact-support-btn`,class:`min-h-[44px] px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition active:scale-95`},[i(r(v),{class:`w-4 h-4`}),c(`span`,null,p(e.$t(`help.contact_support`)),1)],8,xe)):(u(),l(`span`,{key:1,"data-testid":`contact-support-btn`,class:`min-h-[44px] px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold text-xs flex items-center justify-center gap-2`,title:e.$t(`help.support_not_configured`)},[i(r(v),{class:`w-4 h-4`}),c(`span`,null,p(e.$t(`help.contact_support`)),1)],8,Se))]),_:1},8,[`title`,`subtitle`,`icon`]),r(n)?(u(),h(N,{key:0})):(u(),l(`div`,Ce,[c(`div`,we,[i(K,{articles:r(o),"active-slug":r(d)?.slug||r(s),"search-query":r(a),"onUpdate:searchQuery":g[0]||=e=>t(a)?a.value=e:null,onSelect:r(f)},null,8,[`articles`,`active-slug`,`search-query`,`onSelect`])]),c(`div`,Te,[r(d)?(u(),h(_e,{key:0,article:r(d),"support-href":r(m)},null,8,[`article`,`support-href`])):(u(),h(E,{key:1,icon:r(y),title:e.$t(`help.no_articles_title`),description:e.$t(`help.no_articles_desc`),"action-label":e.$t(`help.search_clear`),onAction:g[1]||=e=>a.value=``},null,8,[`icon`,`title`,`description`,`action-label`]))])]))]))}};export{Ee as default};