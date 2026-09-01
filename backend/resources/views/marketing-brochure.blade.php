<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دليل العروض والأسعار الرسمية - منظومة ERP السحابية</title>
    
    <!-- Google Fonts: Cairo & Tajawal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Cairo', 'Tajawal', 'sans-serif'],
                        tajawal: ['Tajawal', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: #0b0f19;
            color: #0f172a;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Screen Presentation Mode */
        @media screen {
            body {
                padding: 2rem 1rem;
            }
            .slide-page {
                background: #ffffff;
                width: 100%;
                max-width: 1220px;
                min-height: 820px;
                margin: 0 auto 3rem auto;
                border-radius: 1.5rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75);
                padding: 2rem 2.25rem;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
        }

        /* 🖨️ Precision A4 Landscape Print Engine */
        @media print {
            @page {
                size: A4 landscape;
                margin: 5mm 7mm 5mm 7mm;
            }
            html, body {
                background: #ffffff !important;
                color: #0f172a !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }
            .no-print {
                display: none !important;
            }
            .slide-page {
                width: 100% !important;
                height: 196mm !important;
                max-height: 196mm !important;
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                page-break-inside: avoid !important;
                page-break-after: always !important;
            }
            .slide-page:last-child {
                page-break-after: auto !important;
            }
        }

        .hero-banner {
            background: linear-gradient(135deg, #090d16 0%, #1e293b 50%, #0f172a 100%);
        }
        .pro-card-bg {
            background: linear-gradient(145deg, #090d16 0%, #1e293b 100%);
        }
    </style>
</head>
<body>

    <!-- Floating Print Button (Screen Only) -->
    <div class="no-print fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900/95 backdrop-blur-md text-white px-7 py-3.5 rounded-full shadow-2xl border border-amber-500/60 flex items-center gap-4">
        <div class="flex items-center gap-2">
            <span class="text-amber-400 font-black text-sm">📐 عرض أفقي A4 Landscape</span>
            <span class="text-xs text-slate-300">• باقات 2026 الرسمية المحدثة</span>
        </div>
        <button onclick="window.print()" class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs rounded-full shadow-xl transition-all active:scale-95 flex items-center gap-2 cursor-pointer">
            <span>🖨️ طباعة أو حفظ كملف PDF</span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- 📄 SLIDE 1 (A4 LANDSCAPE): HERO, VALUE PROPOSITION & SYSTEM MODULES       -->
    <!-- ========================================================================= -->
    <div class="slide-page">
        
        <!-- 🌟 Top Hero Header -->
        <div class="hero-banner text-white rounded-2xl p-4 sm:p-5 border border-slate-800 shadow-md flex justify-between items-center">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-0.5 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-black">
                        ✨ الجيل القادم من أنظمة إدارة المبيعات ونقاط البيع السحابية
                    </span>
                    <span class="px-3 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-[11px] font-black">
                        🛡️ تسعير ثابت بالجنيه المصري غير مرتبط بالدولار
                    </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                    منظومة <span class="text-amber-400">ERP المتطورة</span> لإدارة المبيعات والمخزون والفروع
                </h1>
                <p class="text-slate-300 text-xs max-w-2xl leading-relaxed">
                    حل سحابي متكامل للمحلات التجارية، المحامص والمطاحن، سلاسل التوزيع، وتجار الجملة والتجزئة. تحكم كامل في مبيعاتك، مخازنك، وخزائنك من أي جهاز وفي أي وقت.
                </p>
            </div>

            <div class="hidden sm:flex flex-col items-center justify-center bg-white/10 backdrop-blur-md px-4 py-2.5 rounded-2xl border border-white/20 text-center shrink-0">
                <span class="text-[10px] text-amber-300 font-bold uppercase tracking-wider">توافق وتشغيل فوري</span>
                <div class="text-xs font-black text-white mt-1 space-y-0.5">
                    <div>💻 الكمبيوتر واللابتوب</div>
                    <div>📱 الموبايل والتابلت</div>
                    <div>📟 طابعات الباركود و POS</div>
                </div>
            </div>
        </div>

        <!-- 💡 Main Body Grid (Values + Modules) -->
        <div class="grid grid-cols-12 gap-4 my-auto items-stretch">
            
            <!-- Right Column: 6 Value Cards (7 Cols) -->
            <div class="col-span-7 flex flex-col justify-between space-y-2">
                <div class="flex items-center justify-between border-b-2 border-slate-100 pb-1">
                    <h2 class="text-xs sm:text-sm font-black text-slate-900 flex items-center gap-1.5">
                        <span class="text-amber-600 text-sm">🎯</span> ماذا يضيف النظام لنشاطك التجاري؟ (العائد على الاستثمار)
                    </h2>
                    <span class="text-[10.5px] font-bold text-slate-500">حلول جذرية لمشاكل السوق اليومية</span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <!-- Value 1 -->
                    <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200/80 space-y-0.5">
                        <div class="flex items-center gap-1.5 text-rose-700 font-black text-[11.5px]">
                            <span>🔒</span> منع العجز وسرقات الخزينة
                        </div>
                        <p class="text-slate-600 text-[10.5px] leading-relaxed">
                            تقفيل دقيق للورديات ومطابقة نقدية الدرج الفعلية مع تنبيه فوري للإدارة عند أي عجز مالي.
                        </p>
                    </div>

                    <!-- Value 2 -->
                    <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200/80 space-y-0.5">
                        <div class="flex items-center gap-1.5 text-amber-800 font-black text-[11.5px]">
                            <span>⚡</span> سرعة قياسية في نقطة البيع
                        </div>
                        <p class="text-slate-600 text-[10.5px] leading-relaxed">
                            إصدار الفاتورة في أقل من 3 ثوانٍ بالباركود، ودعم الدفع النقدي والآجل والطباعة الحرارية فوراً.
                        </p>
                    </div>

                    <!-- Value 3 -->
                    <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200/80 space-y-0.5">
                        <div class="flex items-center gap-1.5 text-emerald-700 font-black text-[11.5px]">
                            <span>📈</span> معرفة صافي الأرباح الحقيقي
                        </div>
                        <p class="text-slate-600 text-[10.5px] leading-relaxed">
                            احتساب تلقائي لهامش ربح كل صنف ومتابعة فورية للأرباح الصافية بعد خصم المصروفات بدقة مليمية.
                        </p>
                    </div>

                    <!-- Value 4 -->
                    <div class="p-2.5 rounded-xl bg-blue-50 border border-blue-200/80 space-y-0.5">
                        <div class="flex items-center gap-1.5 text-blue-700 font-black text-[11.5px]">
                            <span>🚚</span> رقابة سيارات التوزيع
                        </div>
                        <p class="text-slate-600 text-[10.5px] leading-relaxed">
                            تحويل البضاعة من المخزن لسيارات المندوبين، والبيع والتحصيل من خط السير مع جرد عهدة السيارة يومياً.
                        </p>
                    </div>

                    <!-- Value 5 -->
                    <div class="p-2.5 rounded-xl bg-indigo-50 border border-indigo-200/80 space-y-0.5">
                        <div class="flex items-center gap-1.5 text-indigo-700 font-black text-[11.5px]">
                            <span>📑</span> ضبط حسابات العملاء والموردين
                        </div>
                        <p class="text-slate-600 text-[10.5px] leading-relaxed">
                            كشوف حساب تفصيلية متحركة وسندات قبض وصرف مسجلة لمنع الخلافات ومتابعة مواعيد السداد.
                        </p>
                    </div>

                    <!-- Value 6 -->
                    <div class="p-2.5 rounded-xl bg-teal-50 border border-teal-200/80 space-y-0.5">
                        <div class="flex items-center gap-1.5 text-teal-700 font-black text-[11.5px]">
                            <span>✈️</span> تقارير ونسخ بالتليجرام
                        </div>
                        <p class="text-slate-600 text-[10.5px] leading-relaxed">
                            تقرير يومي شامل ومفصل بالأرباح والخزينة على هاتفك، مع وصول نسخة احتياطية مشفرة لقاعدة بياناتك يومياً.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Left Column: Modules Grid & Guarantee (5 Cols) -->
            <div class="col-span-5 flex flex-col justify-between space-y-2">
                <div class="flex items-center justify-between border-b-2 border-slate-100 pb-1">
                    <h3 class="text-xs sm:text-sm font-black text-slate-900 flex items-center gap-1">
                        <span>📦</span> الأنظمة المدمجة بالنظام (All-in-One)
                    </h3>
                    <span class="text-[10px] bg-slate-100 font-bold px-2 py-0.5 rounded text-slate-600">8 موديولات متكاملة</span>
                </div>

                <div class="grid grid-cols-2 gap-1.5 text-[11px] font-bold text-slate-800">
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>🧾</span> كاشير ونقاط بيع POS
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>🏢</span> مخازن وفروع متعددة
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>🚚</span> سيارات ومندوبي توزيع
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>📦</span> مشتريات وموردين
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>👥</span> كشوف عملاء ومديونيات
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>💰</span> خزينة ويومية ومصروفات
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>📊</span> تقارير وأرباح تفصيلية
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-1.5">
                        <span>🤖</span> تنبيهات تليجرام ذكية
                    </div>
                </div>

                <!-- Highlight Guarantee Box -->
                <div class="bg-gradient-to-r from-amber-500/10 via-emerald-500/10 to-amber-500/10 p-2.5 rounded-xl border border-amber-500/30 text-[11px] space-y-0.5">
                    <div class="font-black text-slate-900 flex items-center gap-1.5">
                        <span class="text-emerald-600">🛡️</span> دقة محاسبية ومعاملات ذرية آمنة 100%
                    </div>
                    <div class="text-slate-600 text-[10px] leading-relaxed">
                        دقة حسابية حتى 3 خانات عشرية، حماية ضد انقطاع الكهرباء، وتطبيق ويب فوري PWA.
                    </div>
                </div>
            </div>

        </div>

        <!-- 🌟 Slide 1 Bottom Footer Strip -->
        <div class="pt-2 border-t-2 border-slate-100 flex justify-between items-center text-[10.5px] text-slate-500 font-bold">
            <span>📄 العرض التقديمي • الصفحة 1 من 2 (هيكل الباقات والأسعار الرسمية في الصفحة التالية)</span>
            <span class="text-amber-600 font-mono font-black">{{ config('app.url', 'https://baraa-solutions.com') }}</span>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- 📄 SLIDE 2 (A4 LANDSCAPE): 6-TIER PRICING, TIERED ADD-ONS & CONTACT       -->
    <!-- ========================================================================= -->
    <div class="slide-page">
        
        <!-- 🌟 Top Section Title & Currency Badge -->
        <div class="flex items-center justify-between border-b-2 border-slate-100 pb-1.5">
            <div>
                <span class="text-[10px] font-black text-amber-600 uppercase tracking-wider">خطط الاشتراك السحابية المرنة (دعم الدفع السنوي: شهرين مجاناً)</span>
                <h2 class="text-base sm:text-lg font-black text-slate-900">اختر الباقة المناسبة لحجم ونشاط أعمالك</h2>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-xl text-[10.5px] font-black text-emerald-800 flex items-center gap-1">
                <span>🇪🇬</span> أسعار ثابتة بالجنيه المصري (شاملة السيرفر والتحديثات)
            </div>
        </div>

        <!-- 💰 6 Pricing Cards Row -->
        <div class="grid grid-cols-6 gap-2 my-auto items-stretch">
            
            <!-- Plan 1: Starter -->
            <div class="bg-white rounded-xl p-2.5 border border-slate-200 flex flex-col justify-between shadow-sm">
                <div class="space-y-1">
                    <div>
                        <span class="text-[8.5px] font-black uppercase text-slate-500">المحلات الفردية</span>
                        <h3 class="text-xs font-black text-slate-900">البداية Starter</h3>
                    </div>

                    <div class="pt-1 border-t border-slate-100">
                        <div class="text-lg font-black text-slate-900 font-mono">349 <span class="text-[9px] font-bold text-slate-500">ج.م/ش</span></div>
                        <div class="text-[9px] text-emerald-600 font-bold font-mono">3,490 ج.م سنوي</div>
                    </div>

                    <div class="space-y-1 pt-1 border-t border-slate-100 text-[9.5px] text-slate-700 font-medium leading-tight">
                        <div>✓ <strong>فرع واحد</strong> (POS)</div>
                        <div>✓ <strong>2 مستخدمين</strong></div>
                        <div>✓ مبيعات وباركود</div>
                        <div>✓ مخزن وتنبيهات</div>
                        <div>✓ تقفيل الخزينة</div>
                        <div class="text-slate-400">✕ الصلاحيات المتقدمة</div>
                    </div>
                </div>
            </div>

            <!-- Plan 2: Basic+ (New) -->
            <div class="bg-amber-50/40 rounded-xl p-2.5 border border-amber-200/80 flex flex-col justify-between shadow-sm">
                <div class="space-y-1">
                    <div>
                        <span class="text-[8.5px] font-black uppercase text-amber-700">محل بفريق أكبر</span>
                        <h3 class="text-xs font-black text-slate-900">الانطلاق Basic+</h3>
                    </div>

                    <div class="pt-1 border-t border-amber-100">
                        <div class="text-lg font-black text-slate-900 font-mono">499 <span class="text-[9px] font-bold text-slate-500">ج.م/ش</span></div>
                        <div class="text-[9px] text-emerald-600 font-bold font-mono">4,990 ج.م سنوي</div>
                    </div>

                    <div class="space-y-1 pt-1 border-t border-amber-100 text-[9.5px] text-slate-700 font-medium leading-tight">
                        <div>✓ <strong>فرع واحد</strong> (POS)</div>
                        <div>✓ <strong>4 مستخدمين</strong></div>
                        <div>✓ صلاحيات متقدمة</div>
                        <div>✓ تقارير مبيعات مفصلة</div>
                        <div>✓ كشوفات الحسابات</div>
                        <div class="text-slate-400">✕ التحويل بين الفروع</div>
                    </div>
                </div>
            </div>

            <!-- Plan 3: Pro (Golden Hero) -->
            <div class="pro-card-bg text-white rounded-xl p-2.5 border-2 border-amber-500 flex flex-col justify-between shadow-lg relative">
                <div class="absolute -top-2 left-1/2 -translate-x-1/2 bg-amber-500 text-slate-950 text-[7.5px] font-black uppercase px-2 py-0.2 rounded-full">
                    ⭐ الأكثر طلباً
                </div>

                <div class="space-y-1">
                    <div>
                        <span class="text-[8.5px] font-black uppercase text-amber-400">متاجر متوسطة</span>
                        <h3 class="text-xs font-black text-white">النمو Pro</h3>
                    </div>

                    <div class="pt-1 border-t border-slate-700">
                        <div class="text-lg font-black text-amber-400 font-mono">699 <span class="text-[9px] font-bold text-slate-300">ج.م/ش</span></div>
                        <div class="text-[9px] text-emerald-400 font-bold font-mono">6,990 ج.م سنوي</div>
                    </div>

                    <div class="space-y-1 pt-1 border-t border-slate-700 text-[9.5px] text-slate-200 font-medium leading-tight">
                        <div>✓ <strong>حتى 3 فروع / نقاط</strong></div>
                        <div>✓ <strong>6 مستخدمين</strong></div>
                        <div>✓ تحويلات مخزنية فورية</div>
                        <div>✓ كشوف عملاء وموردين</div>
                        <div>✓ أرباح وخزينة شاملة</div>
                        <div>✓ <strong>إشعارات ونسخ تليجرام</strong></div>
                    </div>
                </div>
            </div>

            <!-- Plan 4: Vans -->
            <div class="bg-white rounded-xl p-2.5 border border-slate-200 flex flex-col justify-between shadow-sm">
                <div class="space-y-1">
                    <div>
                        <span class="text-[8.5px] font-black uppercase text-slate-500">شركات التوزيع</span>
                        <h3 class="text-xs font-black text-slate-900">التوزيع Vans</h3>
                    </div>

                    <div class="pt-1 border-t border-slate-100">
                        <div class="text-lg font-black text-slate-900 font-mono">899 <span class="text-[9px] font-bold text-slate-500">ج.م/ش</span></div>
                        <div class="text-[9px] text-emerald-600 font-bold font-mono">8,990 ج.م سنوي</div>
                    </div>

                    <div class="space-y-1 pt-1 border-t border-slate-100 text-[9.5px] text-slate-700 font-medium leading-tight">
                        <div>✓ <strong>مخزن + 3 سيارات</strong></div>
                        <div>✓ <strong>4 مستخدمين</strong> (مناديب)</div>
                        <div>✓ شحن بضاعة للسيارات</div>
                        <div>✓ فواتير خط السير</div>
                        <div>✓ جرد عهدة السيارة يومياً</div>
                        <div>✓ نسخ سحابي يومي</div>
                    </div>
                </div>
            </div>

            <!-- Plan 5: Business (New) -->
            <div class="bg-white rounded-xl p-2.5 border border-slate-200 flex flex-col justify-between shadow-sm">
                <div class="space-y-1">
                    <div>
                        <span class="text-[8.5px] font-black uppercase text-slate-500">سلاسل الفروع</span>
                        <h3 class="text-xs font-black text-slate-900">الأعمال Business</h3>
                    </div>

                    <div class="pt-1 border-t border-slate-100">
                        <div class="text-lg font-black text-slate-900 font-mono">1,099 <span class="text-[9px] font-bold text-slate-500">ج.م/ش</span></div>
                        <div class="text-[9px] text-emerald-600 font-bold font-mono">10,990 ج.م سنوي</div>
                    </div>

                    <div class="space-y-1 pt-1 border-t border-slate-100 text-[9.5px] text-slate-700 font-medium leading-tight">
                        <div>✓ <strong>حتى 6 فروع ومخازن</strong></div>
                        <div>✓ <strong>12 مستخدم</strong> بصلاحيات</div>
                        <div>✓ مقارنة أداء الفروع</div>
                        <div>✓ صلاحيات هرمية وإقليمية</div>
                        <div>✓ كشوف تحليلية للمبيعات</div>
                        <div>✓ دعم فني بأولوية</div>
                    </div>
                </div>
            </div>

            <!-- Plan 6: Enterprise -->
            <div class="bg-white rounded-xl p-2.5 border border-slate-200 flex flex-col justify-between shadow-sm">
                <div class="space-y-1">
                    <div>
                        <span class="text-[8.5px] font-black uppercase text-slate-500">الشركات الكبرى</span>
                        <h3 class="text-xs font-black text-slate-900">المؤسسات Enterprise</h3>
                    </div>

                    <div class="pt-1 border-t border-slate-100">
                        <div class="text-lg font-black text-slate-900 font-mono">1,499+ <span class="text-[9px] font-bold text-slate-500">ج.م/ش</span></div>
                        <div class="text-[9px] text-emerald-600 font-bold font-mono">14,990 ج.م سنوي</div>
                    </div>

                    <div class="space-y-1 pt-1 border-t border-slate-100 text-[9.5px] text-slate-700 font-medium leading-tight">
                        <div>✓ <strong>فروع غير محدودة</strong></div>
                        <div>✓ <strong>مستخدمين بلا حدود</strong></div>
                        <div>✓ <strong>الفاتورة الإلكترونية</strong></div>
                        <div>✓ نطاق خاص بالشركة</div>
                        <div>✓ دعم فني VIP 24/7</div>
                        <div>✓ نسخ لحظي مشفر</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 🧩 Bottom Row: Tiered Add-ons + Hardware + Contact -->
        <div class="grid grid-cols-12 gap-2.5 items-stretch text-[10px]">
            
            <!-- Col 1: Tiered Add-ons (5 Cols) -->
            <div class="col-span-5 bg-slate-50 rounded-xl p-2.5 border border-slate-200 space-y-1">
                <div class="flex items-center justify-between">
                    <h4 class="font-black text-slate-900 text-[10.5px]">🧩 الإضافات المرنة (بخصم الكمية)</h4>
                    <span class="text-[8.5px] text-amber-700 font-bold">كلما زادت الوحدات قل السعر</span>
                </div>
                <div class="grid grid-cols-2 gap-1 text-[9.5px]">
                    <div class="bg-white p-1 rounded border border-slate-200/60">
                        <span>🏬 <strong>فرع إضافي:</strong> 100 ج.م <span class="text-slate-400">(يصل لـ 70)</span></span>
                    </div>
                    <div class="bg-white p-1 rounded border border-slate-200/60">
                        <span>👤 <strong>مستخدم إضافي:</strong> 40 ج.م <span class="text-slate-400">(يصل لـ 25)</span></span>
                    </div>
                    <div class="bg-white p-1 rounded border border-slate-200/60">
                        <span>🧾 <strong>فاتورة إلكترونية مبكرة:</strong> 200 ج.م/ش</span>
                    </div>
                    <div class="bg-white p-1 rounded border border-slate-200/60">
                        <span>📱 <strong>واتساب:</strong> 500=100ج | 1500=250ج</span>
                    </div>
                </div>
            </div>

            <!-- Col 2: Hardware Bundles (4 Cols) -->
            <div class="col-span-4 bg-slate-50 rounded-xl p-2.5 border border-slate-200 space-y-1">
                <div class="flex items-center justify-between">
                    <h4 class="font-black text-slate-900 text-[10.5px]">🖨️ باقات أجهزة الكاشير</h4>
                    <span class="text-[8.5px] bg-amber-500/20 text-amber-800 px-1 rounded font-bold">ضمان سنة</span>
                </div>
                <div class="space-y-0.5 text-[9.5px]">
                    <div class="flex justify-between items-center bg-white px-2 py-0.5 rounded border border-slate-200/60">
                        <span>📦 طابعة 80mm + باركود ليزر:</span>
                        <strong class="text-slate-900 font-mono">4,900 ج.م</strong>
                    </div>
                    <div class="flex justify-between items-center bg-white px-2 py-0.5 rounded border border-slate-200/60">
                        <span>⭐ طابعة + باركود + درج نقدية:</span>
                        <strong class="text-amber-600 font-mono">6,800 ج.م</strong>
                    </div>
                    <div class="flex justify-between items-center bg-amber-500/15 px-2 py-0.5 rounded border border-amber-500/40">
                        <span>👑 <strong>المحل الجاهز VIP (أجهزة + سنة Pro):</strong></span>
                        <strong class="text-emerald-700 font-mono">11,900 ج.م</strong>
                    </div>
                </div>
            </div>

            <!-- Col 3: Call to Action & Contact (3 Cols) -->
            <div class="col-span-3 hero-banner text-white rounded-xl p-2 flex flex-col justify-between border border-slate-800 text-center">
                <div class="space-y-0.5">
                    <h4 class="text-xs font-black text-amber-400">تجربة مجانية 14 يوماً</h4>
                    <p class="text-[8.5px] text-slate-300">تشغيل فوري وعرض حي (Live Demo)</p>
                </div>
                <div class="space-y-0.5 text-[9px] font-bold text-slate-200 pt-1 border-t border-slate-700">
                    <div>📞 <strong>واتساب:</strong> 01000000000</div>
                    <div>🌐 {{ config('app.url', 'https://baraa-solutions.com') }}</div>
                </div>
            </div>

        </div>

        <!-- 🌟 Slide 2 Bottom Footer Strip -->
        <div class="pt-1.5 border-t-2 border-slate-100 flex justify-between items-center text-[10px] text-slate-500 font-bold">
            <span>الصفحة 2 من 2 • منظومة ERP السحابية لإدارة المبيعات والمخزون</span>
            <span>جميع الحقوق محفوظة © {{ date('Y') }}</span>
        </div>

    </div>

</body>
</html>
