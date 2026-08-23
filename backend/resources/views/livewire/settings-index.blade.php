<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/70 dark:bg-slate-900/80 backdrop-blur-md p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-600 to-amber-400 p-0.5 shadow-lg shadow-amber-500/20 flex items-center justify-center text-white text-2xl">
                ⚙️
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-tajawal">
                    إعدادات النظام والتحكم الشامل
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium">
                    تخصيص الهوية واللوجو، إشعارات تيليجرام التلقائية، النسخ الاحتياطي السحابي، وصيانة السيرفر
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 self-end sm:self-center">
            <button
                wire:click="clearAndOptimizeCache"
                type="button"
                wire:loading.attr="disabled"
                class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-2xl transition-all shadow-sm flex items-center gap-2 cursor-pointer font-tajawal"
            >
                <span wire:loading.remove wire:target="clearAndOptimizeCache">⚡ تنظيف وتسريع الكاش</span>
                <span wire:loading wire:target="clearAndOptimizeCache">جاري التنظيف...</span>
            </button>
            <a
                href="{{ url('/pulse') }}"
                target="_blank"
                class="px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs rounded-2xl shadow-md shadow-purple-600/20 transition-all flex items-center gap-2 font-tajawal"
            >
                📊 مراقبة Pulse
            </a>
            <a
                href="{{ url('/telescope') }}"
                target="_blank"
                class="px-4 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white font-bold text-xs rounded-2xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-2 font-tajawal"
            >
                🔭 تليسكوب Telescope
            </a>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-slate-200/60 dark:bg-slate-950/60 p-1.5 rounded-2xl border border-slate-300/50 dark:border-slate-800/80">
        <!-- Tab 1: Branding -->
        <button
            wire:click="setTab('branding')"
            type="button"
            class="px-4 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 cursor-pointer font-tajawal {{ $activeTab === 'branding' ? 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <span class="text-base">🏢</span>
            <span>الهوية والطباعة</span>
        </button>

        <!-- Tab 2: Telegram -->
        <button
            wire:click="setTab('telegram')"
            type="button"
            class="px-4 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 cursor-pointer font-tajawal {{ $activeTab === 'telegram' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <span class="text-base">✈️</span>
            <span>إشعارات تيليجرام</span>
        </button>

        <!-- Tab 3: Backups -->
        <button
            wire:click="setTab('backup')"
            type="button"
            class="px-4 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 cursor-pointer font-tajawal {{ $activeTab === 'backup' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <span class="text-base">💾</span>
            <span>النسخ الاحتياطي</span>
        </button>

        <!-- Tab 4: System & Cache -->
        <button
            wire:click="setTab('system')"
            type="button"
            class="px-4 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 cursor-pointer font-tajawal {{ $activeTab === 'system' ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <span class="text-base">⚡</span>
            <span>الأداء والصيانة</span>
        </button>
    </div>

    <!-- Tab Content Area -->
    <div class="bg-white dark:bg-slate-900/90 backdrop-blur-md border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">

        <!-- ========================================== -->
        <!-- TAB 1: BRANDING & INVOICE PRINTING SETTINGS -->
        <!-- ========================================== -->
        @if ($activeTab === 'branding')
        <div class="space-y-6">
            <div class="flex items-center gap-3.5 pb-4 border-b border-slate-200 dark:border-slate-800">
                <div class="w-16 h-16 rounded-2xl bg-white dark:bg-slate-800 p-1.5 flex items-center justify-center shadow-md border border-slate-200 dark:border-slate-700 shrink-0 relative overflow-hidden">
                    @if ($logo_file)
                        <img src="{{ $logo_file->temporaryUrl() }}" alt="معاينة اللوجو" class="w-full h-full object-contain">
                    @else
                        <img src="{{ asset('logo.png') }}?v={{ time() }}" alt="اللوجو" class="w-full h-full object-contain">
                    @endif
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white font-tajawal">
                        ترويسة وهُوِيّة الفواتير والطباعة (Store Branding)
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        تخصيص اللوجو واسم المؤسسة والوصف الفرعي المطبوع على فواتير المبيعات A4 والإيصالات الحرارية 80mm
                    </p>
                </div>
            </div>

            <form wire:submit.prevent="updateBrandingSettings" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Company Name -->
                    <div>
                        <label for="company_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                            اسم المحل / المؤسسة في الفاتورة <span class="text-rose-500">*</span>
                        </label>
                        <input
                            wire:model.defer="company_name"
                            type="text"
                            id="company_name"
                            required
                            placeholder="مثال: اسم المؤسسة أو المتجر"
                            class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        >
                        @error('company_name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Company Subtitle -->
                    <div>
                        <label for="company_subtitle" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                            الوصف الفرعي (العنوان التوضيحي)
                        </label>
                        <input
                            wire:model.defer="company_subtitle"
                            type="text"
                            id="company_subtitle"
                            placeholder="مثال: لتوريدات خامات مطاحن البن"
                            class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        >
                        @error('company_subtitle') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Custom Logo Upload Box -->
                <div class="p-5 rounded-2xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <span class="text-2xl mt-0.5">🖼️</span>
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">تغيير ورفع صورة الشعار (Logo)</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                ارفع ملف صورة الشعار بصيغة PNG أو JPG بدقة واضحة (الخلفية الشفافة هي الأفضل للطباعة الحرارية).
                            </p>
                        </div>
                    </div>

                    <div class="shrink-0 flex items-center gap-3">
                        <label class="px-5 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-amber-500/40 text-amber-600 dark:text-amber-400 font-bold text-xs rounded-xl shadow-sm cursor-pointer transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            <span>اختيار صورة جديدة</span>
                            <input type="file" wire:model="logo_file" accept="image/*" class="hidden">
                        </label>
                        @if($logo_file)
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20">
                            تم اختيار الصورة ✓
                        </span>
                        @endif
                    </div>
                </div>
                @error('logo_file') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror

                <!-- Printing Visibility Switches -->
                <div class="space-y-3.5 pt-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">عناصر الظهور في رأس الفاتورة</h3>

                    <!-- 1. Print Company Name -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">إظهار اسم النشاط في رأس الفاتورة</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                إظهار اسم ({{ $company_name ?: 'المؤسسة' }}) في قمة فواتير A4 وإيصالات الكاشير
                            </p>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer shrink-0 self-end sm:self-center">
                            <input type="checkbox" wire:model.live="show_print_company_name" class="sr-only peer">
                            <div class="w-12 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                            <span class="ms-3 text-xs font-bold text-slate-700 dark:text-slate-300 min-w-[75px]">
                                {{ $show_print_company_name ? 'مُفعّل (ظاهر)' : 'مُعطّل (مخفي)' }}
                            </span>
                        </label>
                    </div>

                    <!-- 2. Print Subtitle -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">إظهار الوصف الفرعي في الطباعة</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                إظهار سطر "<span class="font-semibold text-slate-700 dark:text-slate-300">{{ $company_subtitle ?: 'لتوريدات خامات مطاحن البن' }}</span>" أسفل اسم النشاط
                            </p>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer shrink-0 self-end sm:self-center">
                            <input type="checkbox" wire:model.live="show_print_subtitle" class="sr-only peer">
                            <div class="w-12 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                            <span class="ms-3 text-xs font-bold text-slate-700 dark:text-slate-300 min-w-[75px]">
                                {{ $show_print_subtitle ? 'مُفعّل (ظاهر)' : 'مُعطّل (مخفي)' }}
                            </span>
                        </label>
                    </div>

                    <!-- 3. Print Logo -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">إظهار صورة الشعار (Logo) في الطباعة</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                طباعة الشعار في رأس الفاتورة والإيصال
                            </p>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer shrink-0 self-end sm:self-center">
                            <input type="checkbox" wire:model.live="show_print_logo" class="sr-only peer">
                            <div class="w-12 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                            <span class="ms-3 text-xs font-bold text-slate-700 dark:text-slate-300 min-w-[75px]">
                                {{ $show_print_logo ? 'مُفعّل (ظاهر)' : 'مُعطّل (مخفي)' }}
                            </span>
                        </label>
                    </div>

                    <!-- 4. Thermal Show Customer Balance -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">إظهار الرصيد السابق والمتبقي على العميل في الإيصال الحراري</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                طباعة كشف حساب مصغر في ذيل الفاتورة للعملاء الآجلين
                            </p>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer shrink-0 self-end sm:self-center">
                            <input type="checkbox" wire:model.live="thermal_show_customer_balance" class="sr-only peer">
                            <div class="w-12 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                            <span class="ms-3 text-xs font-bold text-slate-700 dark:text-slate-300 min-w-[75px]">
                                {{ $thermal_show_customer_balance ? 'مُفعّل (ظاهر)' : 'مُعطّل (مخفي)' }}
                            </span>
                        </label>
                    </div>

                    <!-- 5. Print Show QR Code -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">إظهار رمز الاستجابة السريع (QR Code) للتحقق</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                طباعة QR كود إلكتروني للتحقق من الفاتورة ومطابقتها
                            </p>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer shrink-0 self-end sm:self-center">
                            <input type="checkbox" wire:model.live="print_show_qr" class="sr-only peer">
                            <div class="w-12 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                            <span class="ms-3 text-xs font-bold text-slate-700 dark:text-slate-300 min-w-[75px]">
                                {{ $print_show_qr ? 'مُفعّل (ظاهر)' : 'مُعطّل (مخفي)' }}
                            </span>
                        </label>
                    </div>

                    <!-- 6. Invoice Footer Note Text -->
                    <div class="pt-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                            نص التذييل والشكر في أسفل الفاتورة (Footer Note):
                        </label>
                        <input
                            wire:model.defer="invoice_footer_note"
                            type="text"
                            placeholder="مثال: شكراً لتعاملكم معنا - البضاعة المباعة ترد وتستبدل خلال 14 يوماً"
                            class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl text-slate-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        >
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white font-bold text-sm rounded-2xl shadow-lg shadow-amber-600/30 transition-all font-tajawal flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span wire:loading.remove wire:target="updateBrandingSettings">💾 حفظ إعدادات الهوية والطباعة</span>
                        <span wire:loading wire:target="updateBrandingSettings">جاري الحفظ...</span>
                    </button>
                </div>
            </form>
        </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 2: TELEGRAM BOT NOTIFICATIONS SETTINGS -->
        <!-- ========================================== -->
        @if ($activeTab === 'telegram')
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 flex-wrap gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-2xl border border-sky-500/20">
                        ✈️
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white font-tajawal flex items-center gap-2">
                            <span>إشعارات وتقارير تيليجرام التلقائية</span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full {{ $telegram_notifications_enabled ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-500 border border-slate-500/20' }}">
                                {{ $telegram_notifications_enabled ? '● الخدمة نشطة' : '○ متوقفة' }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            استقبال تقارير الإغلاق اليومي، إنذارات النواقص، وتنبيهات عجز الورديات في جروب الإدارة
                        </p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="telegram_notifications_enabled" class="sr-only peer">
                    <div class="w-12 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-sky-500"></div>
                    <span class="ms-3 text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ $telegram_notifications_enabled ? 'تفعيل الإشعارات' : 'تعطيل الإشعارات' }}
                    </span>
                </label>
            </div>

            <!-- Bot Credentials Form -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="telegram_bot_token" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                        رمز البوت (Bot API Token) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        wire:model.defer="telegram_bot_token"
                        type="password"
                        id="telegram_bot_token"
                        placeholder="مثال: 8929805305:AAHQR8NT5PiN..."
                        class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                    >
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">رمز التحقق السري للبوت المستخرج من @BotFather</p>
                </div>

                <div>
                    <label for="telegram_chat_id" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                        معرف المحادثة أو الجروب (Chat ID / Group ID) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        wire:model.defer="telegram_chat_id"
                        type="text"
                        id="telegram_chat_id"
                        placeholder="مثال: -5387084549 أو 6697765154, 945537272"
                        class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                    >
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">معرف جروب الإدارة (يبدأ بـ -) أو معرفات متعددة مفصولة بفاصلة</p>
                </div>
            </div>

            <!-- Telegram Schedule Details Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2">
                <div class="p-4 rounded-2xl bg-sky-500/5 dark:bg-sky-500/10 border border-sky-500/20">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg">📊</span>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white font-tajawal">تقرير تقفيل اليومية (EOD)</h4>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        يُرسل يومياً الساعة <b>11:59 مساءً</b> بمبيعات الكاش والآجل والمصروفات وصافي الدرج.
                    </p>
                </div>

                <div class="p-4 rounded-2xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-500/20">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg">⚠️</span>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white font-tajawal">إنذار نواقص المخزون</h4>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        يُرسل يومياً الساعة <b>09:00 صباحاً</b> بالأصناف التي قاربت على النفاد لطلب شرائها.
                    </p>
                </div>

                <div class="p-4 rounded-2xl bg-rose-500/5 dark:bg-rose-500/10 border border-rose-500/20">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg">🚨</span>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white font-tajawal">إنذار الشفتات المعلقة</h4>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        فحص تلقائي <b>كل ساعتين</b> للشفتات المفتوحة > 24 ساعة وإنذار فوري لعجز النقدية.
                    </p>
                </div>
            </div>

            @if($telegramStatusMessage)
            <div class="p-4 rounded-2xl text-xs font-bold {{ str_starts_with($telegramStatusMessage, '✅') ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' }}">
                {{ $telegramStatusMessage }}
            </div>
            @endif

            <!-- Action & Test Buttons -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-3">
                <button
                    wire:click="updateTelegramSettings"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs rounded-xl shadow-md transition-all font-tajawal flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="updateTelegramSettings">💾 حفظ بيانات التيليجرام</span>
                    <span wire:loading wire:target="updateTelegramSettings">جاري الحفظ...</span>
                </button>

                <button
                    wire:click="sendTestTelegramMessage"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-sky-600 dark:text-sky-400 border border-sky-500/30 font-bold text-xs rounded-xl shadow-sm transition-all font-tajawal flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="sendTestTelegramMessage">📩 اختبار الاتصال</span>
                    <span wire:loading wire:target="sendTestTelegramMessage">جاري الإرسال...</span>
                </button>

                <button
                    wire:click="sendDailySummaryTest"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-4 py-2.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-bold text-xs rounded-xl shadow-sm transition-all font-tajawal flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="sendDailySummaryTest">📊 تجربة تقرير اليومية</span>
                    <span wire:loading wire:target="sendDailySummaryTest">جاري الإرسال...</span>
                </button>

                <button
                    wire:click="sendLowStockTest"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-4 py-2.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold text-xs rounded-xl shadow-sm transition-all font-tajawal flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="sendLowStockTest">⚠️ تجربة إنذار النواقص</span>
                    <span wire:loading wire:target="sendLowStockTest">جاري الإرسال...</span>
                </button>

                <button
                    wire:click="sendOverdueShiftTest"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-4 py-2.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 font-bold text-xs rounded-xl shadow-sm transition-all font-tajawal flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="sendOverdueShiftTest">🚨 تجربة إنذار الشفتات</span>
                    <span wire:loading wire:target="sendOverdueShiftTest">جاري الإرسال...</span>
                </button>
            </div>
        </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 3: CLOUD DATABASE BACKUP SETTINGS      -->
        <!-- ========================================== -->
        @if ($activeTab === 'backup')
        <div class="space-y-6">
            <div class="flex items-center gap-3.5 pb-4 border-b border-slate-200 dark:border-slate-800">
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center text-2xl border border-indigo-500/20">
                    💾
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white font-tajawal">
                        النسخ الاحتياطي السحابي والأمان لقاعدة البيانات
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        تصدير وضغط قاعدة البيانات (.sql.gz) وإرسالها يومياً تلقائياً لحفظ بياناتك في أمان تام
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Backup Information Card -->
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white font-tajawal flex items-center gap-2">
                        <span>🛡️ نظام الحماية التلقائي اليومي</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        يقوم النظام كل ليلة في تمام الساعة <b>12:05 بعد منتصف الليل (00:05 AM)</b> بضغط قاعدة بياناتك بالكامل (الفواتير، العملاء، الموردين، والمخزون) وإرسال ملف <code>.sql.gz</code> مرفقاً في جروب التيليجرام.
                    </p>
                    <div class="flex items-center gap-2 pt-2 text-xs font-bold text-indigo-600 dark:text-indigo-400">
                        <span>● الجدولة: نشطة وتعمل تلقائياً</span>
                    </div>
                </div>

                <!-- Instant Backup Actions Card -->
                <div class="p-5 rounded-2xl bg-indigo-500/5 dark:bg-indigo-500/10 border border-indigo-500/20 flex flex-col justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white font-tajawal mb-1">
                            تصدير نسخة احتياطية فورية الآن
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            يمكنك تنزيل النسخة مباشرة إلى جهاز الكمبيوتر أو إرسالها فوراً إلى جروب التيليجرام
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            wire:click="downloadDatabaseBackupDirectly"
                            type="button"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md transition-all font-tajawal flex items-center gap-2 cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="downloadDatabaseBackupDirectly">📥 تنزيل النسخة للكمبيوتر (.sql.gz)</span>
                            <span wire:loading wire:target="downloadDatabaseBackupDirectly">جاري التجهيز...</span>
                        </button>

                        <button
                            wire:click="sendDatabaseBackupTest"
                            type="button"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30 font-bold text-xs rounded-xl shadow-sm transition-all font-tajawal flex items-center gap-2 cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="sendDatabaseBackupTest">✈️ إرسال للتيليجرام الآن</span>
                            <span wire:loading wire:target="sendDatabaseBackupTest">جاري الرفع...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 4: SYSTEM PERFORMANCE & CACHE          -->
        <!-- ========================================== -->
        @if ($activeTab === 'system')
        <div class="space-y-6">
            <div class="flex items-center gap-3.5 pb-4 border-b border-slate-200 dark:border-slate-800">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-2xl border border-emerald-500/20">
                    ⚡
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white font-tajawal">
                        أداء وصيانة وتسريع السيرفر
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        إدارة التخزين المؤقت (Cache Optimization)، فحص سلامة النظام، ومراقبة استهلاك الموارد
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Card 1: Production Mode -->
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500">بيئة التشغيل</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Production Mode</span>
                    </div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">وضع الإنتاج السريع مُفعّل</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">تم تعطيل Debug Mode لتحقيق أعلى درجات الأمان والسرعة في الاستجابة.</p>
                </div>

                <!-- Card 2: Caching Engine -->
                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500">كاش الإعدادات والمسارات</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Cached & Optimized</span>
                    </div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">Config, Routes & Views</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">يتم تحميل المسارات والإعدادات وقوالب Blade من الذاكرة الفورية بدون قراءة القرص.</p>
                </div>

                <!-- Card 3: Live APM Monitoring -->
                <div class="p-5 rounded-2xl bg-purple-500/5 dark:bg-purple-500/10 border border-purple-500/20 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-purple-600 dark:text-purple-400">مراقبة الأداء APM</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-600 dark:text-purple-300">Laravel Pulse</span>
                    </div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">مراقبة المعالج واستعلامات DB</p>
                    <a href="{{ url('/pulse') }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline">
                        <span>فتح لوحة مراقبة Pulse</span>
                        <span>↗</span>
                    </a>
                </div>
            </div>

            <!-- Maintenance Actions -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-3">
                <button
                    wire:click="clearAndOptimizeCache"
                    type="button"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md transition-all font-tajawal flex items-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="clearAndOptimizeCache">⚡ تنظيف وإعادة بناء الكاش بالكامل</span>
                    <span wire:loading wire:target="clearAndOptimizeCache">جاري البناء والتسريع...</span>
                </button>
            </div>
        </div>
        @endif

    </div>

</div>
