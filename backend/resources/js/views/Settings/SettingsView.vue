<template>
  <div class="space-y-6 max-w-6xl mx-auto font-tajawal pb-12">
    <!-- Page Header (Master Level) -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-3xl border border-slate-800 shadow-xl backdrop-blur-md">
      <div class="flex items-center gap-3.5">
        <!-- Back to Hub Button on Mobile when inside a sub-page -->
        <button
          v-if="selectedSection && isMobileView"
          type="button"
          @click="selectedSection = null"
          class="w-10 h-10 rounded-2xl bg-slate-800/90 hover:bg-slate-700 border border-slate-700 text-amber-400 flex items-center justify-center transition-all active:scale-90 cursor-pointer shadow-md shrink-0"
          title="الرجوع لمركز الإعدادات"
        >
          <ArrowRight class="w-5 h-5" />
        </button>

        <div v-else class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-amber-600/10 border border-amber-500/30 text-amber-400 flex items-center justify-center shadow-lg shadow-amber-500/10 shrink-0">
          <Sliders class="w-6 h-6" />
        </div>

        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg sm:text-xl font-black text-white">
              {{ currentSectionTitle }}
            </h1>
            <span v-if="selectedSection" class="px-2.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-400 text-[10px] font-bold">
              صفحة فرعية
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-0.5">
            {{ currentSectionSubtitle }}
          </p>
        </div>
      </div>

      <!-- Action Save Button (Visible when inside a section or on desktop) -->
      <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
        <button
          v-if="selectedSection !== 'system'"
          type="button"
          @click="saveSettings"
          :disabled="isSaving || isLoading"
          class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs sm:text-sm rounded-2xl shadow-lg shadow-amber-500/25 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer disabled:opacity-50"
        >
          <Save class="w-4.5 h-4.5" />
          <span>{{ isSaving ? 'جاري الحفظ...' : 'حفظ التعديلات' }}</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-16 text-center bg-slate-950/60 rounded-3xl border border-slate-800">
      <div class="w-12 h-12 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
      <p class="text-xs text-slate-300 font-bold">جاري تحميل إعدادات النظام...</p>
    </div>

    <!-- Main Settings Navigation & Sub-Pages Layout -->
    <div v-else>
      <!-- 📱 Mobile Hub Mode (Grid of Interactive Settings Cards when no section is selected) -->
      <div v-if="!selectedSection && isMobileView" class="grid grid-cols-1 gap-3.5">
        <div
          v-for="sec in sections"
          :key="sec.id"
          @click="selectedSection = sec.id"
          class="p-4.5 rounded-3xl bg-slate-950/80 border border-slate-800 hover:border-slate-700 transition-all active:scale-[0.98] cursor-pointer shadow-lg flex items-center justify-between gap-4 group"
        >
          <div class="flex items-center gap-4 min-w-0">
            <div
              class="w-13 h-13 rounded-2xl flex items-center justify-center shrink-0 shadow-md transition-transform group-hover:scale-105"
              :class="sec.iconBg"
            >
              <component :is="sec.icon" class="w-6 h-6" :class="sec.iconColor" />
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-black text-white group-hover:text-amber-400 transition-colors truncate">
                  {{ sec.label }}
                </h3>
                <span v-if="sec.badge" class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-300 text-[10px] font-bold">
                  {{ sec.badge }}
                </span>
              </div>
              <p class="text-xs text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                {{ sec.description }}
              </p>
            </div>
          </div>

          <div class="w-9 h-9 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-400 group-hover:text-amber-400 group-hover:bg-slate-800 transition-all shrink-0">
            <ChevronLeft class="w-5 h-5" />
          </div>
        </div>
      </div>

      <!-- 💻 Desktop Split View & Mobile Drill-Down Active Page -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Desktop Sidebar Menu (Col 4 - Hidden on mobile drill-down) -->
        <div class="hidden lg:block lg:col-span-4 space-y-2.5">
          <div class="p-3 bg-slate-950/80 rounded-3xl border border-slate-800 shadow-xl space-y-1.5">
            <div class="px-3.5 py-2 text-[11px] font-black text-slate-500 uppercase tracking-wider">
              أقسام الإعدادات
            </div>

            <button
              v-for="sec in sections"
              :key="sec.id"
              type="button"
              @click="selectedSection = sec.id"
              class="w-full p-3 rounded-2xl text-xs font-bold transition-all flex items-center justify-between gap-3 text-start cursor-pointer group"
              :class="selectedSection === sec.id ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900'"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div
                  class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                  :class="selectedSection === sec.id ? 'bg-slate-950/20 text-slate-950' : sec.iconBg"
                >
                  <component :is="sec.icon" class="w-4.5 h-4.5" :class="selectedSection === sec.id ? 'text-slate-950' : sec.iconColor" />
                </div>
                <div class="min-w-0">
                  <div class="truncate">{{ sec.label }}</div>
                  <div class="text-[10px] font-normal truncate" :class="selectedSection === sec.id ? 'text-slate-900/80' : 'text-slate-400'">
                    {{ sec.subtitle }}
                  </div>
                </div>
              </div>

              <ChevronLeft class="w-4 h-4 opacity-40 group-hover:opacity-100 transition-opacity shrink-0" />
            </button>
          </div>

          <!-- Quick Info Box -->
          <div class="p-4 bg-slate-950/60 rounded-3xl border border-slate-800/80 text-xs text-slate-400 space-y-2">
            <div class="flex items-center gap-2 text-slate-200 font-bold">
              <ShieldCheck class="w-4 h-4 text-emerald-400" />
              <span>إدارة آمنة 100%</span>
            </div>
            <p class="text-[11px] leading-relaxed">
              تنعكس التعديلات على كافة فروع المؤسسة وتطبيق الكاشير والطباعة السريعة فور الضغط على حفظ.
            </p>
          </div>
        </div>

        <!-- Settings Sub-Page Content Area (Col 8 on Desktop, Col 12 on Mobile Drill-Down) -->
        <div class="lg:col-span-8">
          <Transition name="page" mode="out-in">
            <!-- 🏢 Sub-Page 1: Branding & Organization Info -->
            <div
              v-if="selectedSection === 'branding'"
              key="branding"
              class="bg-slate-950/80 rounded-3xl border border-slate-800 p-6 sm:p-7 shadow-xl space-y-6"
            >
              <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                    <Building2 class="w-5 h-5" />
                  </div>
                  <div>
                    <h2 class="text-base font-black text-white">الهوية التجارية وبيانات المحمصة</h2>
                    <p class="text-xs text-slate-400">تخصيص اسم المتجر، الهاتف، والعنوان الذي يظهر للعملاء</p>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-300">اسم المؤسسة / المحمصة *</label>
                  <input
                    v-model="form.company_name"
                    type="text"
                    placeholder="مثال: سرور كوفي"
                    class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-white focus:outline-none transition"
                  />
                </div>

                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-300">الوصف الفرعي (يظهر أسفل الاسم)</label>
                  <input
                    v-model="form.company_subtitle"
                    type="text"
                    placeholder="مثال: لتوريدات خامات مطاحن البن الفاخر"
                    class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-white focus:outline-none transition"
                  />
                </div>

                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-300">رقم الهاتف الرسمي للتواصل</label>
                  <input
                    v-model="form.company_phone"
                    type="text"
                    placeholder="01012345678"
                    class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-white font-mono focus:outline-none transition text-start"
                    dir="ltr"
                  />
                </div>

                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-300">العنوان الرئيسي للمقر</label>
                  <input
                    v-model="form.company_address"
                    type="text"
                    placeholder="مثال: القاهرة - المعادي"
                    class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-white focus:outline-none transition"
                  />
                </div>
              </div>
            </div>

            <!-- 🖨️ Sub-Page 2: Invoices & Thermal Printing -->
            <div
              v-else-if="selectedSection === 'printing'"
              key="printing"
              class="bg-slate-950/80 rounded-3xl border border-slate-800 p-6 sm:p-7 shadow-xl space-y-6"
            >
              <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                    <Printer class="w-5 h-5" />
                  </div>
                  <div>
                    <h2 class="text-base font-black text-white">تخصيص الفواتير والطباعة الحرارية</h2>
                    <p class="text-xs text-slate-400">التحكم في محتويات إيصال البيع الحراري ومقاسات الورق</p>
                  </div>
                </div>
              </div>

              <!-- Toggles List -->
              <div class="space-y-3">
                <div
                  @click="form.show_print_company_name = !form.show_print_company_name"
                  class="flex items-center justify-between p-4 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 cursor-pointer transition"
                >
                  <div>
                    <div class="text-xs sm:text-sm font-bold text-white">طباعة اسم المؤسسة أعلى الإيصال</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">إظهار الاسم التجاري المخصص في رأس الفاتورة</div>
                  </div>
                  <div
                    class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                    :class="form.show_print_company_name ? 'bg-amber-500' : 'bg-slate-700'"
                  >
                    <div
                      class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                      :class="form.show_print_company_name ? '-translate-x-6' : 'translate-x-0'"
                    ></div>
                  </div>
                </div>

                <div
                  @click="form.show_print_subtitle = !form.show_print_subtitle"
                  class="flex items-center justify-between p-4 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 cursor-pointer transition"
                >
                  <div>
                    <div class="text-xs sm:text-sm font-bold text-white">طباعة الوصف الفرعي في الإيصال</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">إظهار جملة النشاط أسفل الاسم التجاري</div>
                  </div>
                  <div
                    class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                    :class="form.show_print_subtitle ? 'bg-amber-500' : 'bg-slate-700'"
                  >
                    <div
                      class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                      :class="form.show_print_subtitle ? '-translate-x-6' : 'translate-x-0'"
                    ></div>
                  </div>
                </div>

                <div
                  @click="form.thermal_show_customer_balance = !form.thermal_show_customer_balance"
                  class="flex items-center justify-between p-4 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 cursor-pointer transition"
                >
                  <div>
                    <div class="text-xs sm:text-sm font-bold text-white">طباعة رصيد ومديونية العميل أسفل الفاتورة</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">توضيح الرصيد الإجمالي السابق والحالي للعميل في الإيصال</div>
                  </div>
                  <div
                    class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                    :class="form.thermal_show_customer_balance ? 'bg-amber-500' : 'bg-slate-700'"
                  >
                    <div
                      class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                      :class="form.thermal_show_customer_balance ? '-translate-x-6' : 'translate-x-0'"
                    ></div>
                  </div>
                </div>

                <div
                  @click="form.print_show_qr = !form.print_show_qr"
                  class="flex items-center justify-between p-4 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 cursor-pointer transition"
                >
                  <div>
                    <div class="text-xs sm:text-sm font-bold text-white">توليد رمز الاستجابة السريعة (QR Code)</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">تضمين رمز QR المتوافق مع متطلبات الفاتورة الإلكترونية</div>
                  </div>
                  <div
                    class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                    :class="form.print_show_qr ? 'bg-amber-500' : 'bg-slate-700'"
                  >
                    <div
                      class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                      :class="form.print_show_qr ? '-translate-x-6' : 'translate-x-0'"
                    ></div>
                  </div>
                </div>
              </div>

              <!-- Footer Note -->
              <div class="space-y-1.5 pt-2">
                <label class="block text-xs font-bold text-slate-300">الملاحظة التذييلية للفواتير (Footer Note)</label>
                <textarea
                  v-model="form.invoice_footer_note"
                  rows="3"
                  placeholder="مثال: البضاعة المباعة ترد وتستبدل خلال 14 يوماً بموجب الفاتورة. شكراً لزيارتكم!"
                  class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-white focus:outline-none transition leading-relaxed"
                ></textarea>
              </div>
            </div>

            <!-- 🤖 Sub-Page 3: Telegram Bot Integration -->
            <div
              v-else-if="selectedSection === 'telegram'"
              key="telegram"
              class="bg-slate-950/80 rounded-3xl border border-slate-800 p-6 sm:p-7 shadow-xl space-y-6"
            >
              <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center">
                    <Bot class="w-5 h-5" />
                  </div>
                  <div>
                    <h2 class="text-base font-black text-white">الربط مع بوت تلجرام للإشعارات الفورية</h2>
                    <p class="text-xs text-slate-400">استقبال تنبيهات فورية للمبيعات، النواقص، وإغلاق الورديات على هاتفك</p>
                  </div>
                </div>
              </div>

              <!-- Master Toggle -->
              <div
                @click="form.telegram_notifications_enabled = !form.telegram_notifications_enabled"
                class="flex items-center justify-between p-4 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-slate-700 cursor-pointer transition"
              >
                <div>
                  <div class="text-xs sm:text-sm font-bold text-white">تفعيل نظام إشعارات تلجرام اللحظي</div>
                  <div class="text-[11px] text-slate-400 mt-0.5">إرسال تقارير المبيعات ونواقص المخزن تلقائياً</div>
                </div>
                <div
                  class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                  :class="form.telegram_notifications_enabled ? 'bg-emerald-500' : 'bg-slate-700'"
                >
                  <div
                    class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                    :class="form.telegram_notifications_enabled ? '-translate-x-6' : 'translate-x-0'"
                  ></div>
                </div>
              </div>

              <!-- Credentials -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-300">رمز البوت (Bot Token)</label>
                  <input
                    v-model="form.telegram_bot_token"
                    type="text"
                    placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                    class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-white font-mono focus:outline-none transition text-start"
                    dir="ltr"
                  />
                </div>

                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-300">معرف القناة أو المحادثة (Chat ID)</label>
                  <input
                    v-model="form.telegram_chat_id"
                    type="text"
                    placeholder="-1001234567890 أو 12345678"
                    class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-white font-mono focus:outline-none transition text-start"
                    dir="ltr"
                  />
                </div>
              </div>

              <!-- Test Notification Button -->
              <div class="pt-2 flex items-center justify-between p-4 rounded-2xl bg-cyan-950/20 border border-cyan-800/30">
                <div class="text-xs text-cyan-200">
                  <span class="font-bold block">اختبار صحة الاتصال بالبوت</span>
                  <span class="text-[11px] text-cyan-400/80">سيتم إرسال رسالة تجريبية إلى معرف المحادثة المحدد</span>
                </div>

                <button
                  type="button"
                  @click="sendTestTelegram"
                  :disabled="isTestingTelegram || !form.telegram_chat_id"
                  class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-cyan-600/20 flex items-center gap-2 transition active:scale-95 disabled:opacity-50 cursor-pointer"
                >
                  <Send class="w-4 h-4" />
                  <span>{{ isTestingTelegram ? 'جاري الإرسال...' : 'إرسال تجريبي ✈️' }}</span>
                </button>
              </div>
            </div>

            <!-- ⚙️ Sub-Page 4: System Information -->
            <div
              v-else-if="selectedSection === 'system'"
              key="system"
              class="bg-slate-950/80 rounded-3xl border border-slate-800 p-6 sm:p-7 shadow-xl space-y-6"
            >
              <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center">
                    <Cpu class="w-5 h-5" />
                  </div>
                  <div>
                    <h2 class="text-base font-black text-white">معلومات الخادم وبيئة التشغيل</h2>
                    <p class="text-xs text-slate-400">مواصفات السيرفر، إصدارات الأطر البرمجية ومحرك قواعد البيانات</p>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-800 flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center text-slate-300">
                      <Code2 class="w-4.5 h-4.5" />
                    </div>
                    <div>
                      <span class="text-slate-400 text-xs block">إصدار PHP</span>
                      <span class="text-white font-bold font-mono text-sm">{{ systemInfo.php_version || '8.3+' }}</span>
                    </div>
                  </div>
                  <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 text-[10px] font-bold">نشط</span>
                </div>

                <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-800 flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center text-slate-300">
                      <Layers class="w-4.5 h-4.5" />
                    </div>
                    <div>
                      <span class="text-slate-400 text-xs block">إصدار Laravel</span>
                      <span class="text-white font-bold font-mono text-sm">{{ systemInfo.laravel_version || '11.x' }}</span>
                    </div>
                  </div>
                  <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 text-[10px] font-bold">محدث</span>
                </div>

                <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-800 flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center text-slate-300">
                      <Server class="w-4.5 h-4.5" />
                    </div>
                    <div>
                      <span class="text-slate-400 text-xs block">بيئة التشغيل</span>
                      <span class="text-emerald-400 font-bold font-mono text-sm">{{ systemInfo.environment || 'Production' }}</span>
                    </div>
                  </div>
                  <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-[10px] font-bold font-mono">APP_ENV</span>
                </div>

                <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-800 flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center text-slate-300">
                      <Database class="w-4.5 h-4.5" />
                    </div>
                    <div>
                      <span class="text-slate-400 text-xs block">محرك قاعدة البيانات</span>
                      <span class="text-cyan-400 font-bold font-mono text-sm">{{ systemInfo.db_driver || 'MySQL' }}</span>
                    </div>
                  </div>
                  <span class="px-2.5 py-1 rounded-lg bg-cyan-500/10 text-cyan-400 text-[10px] font-bold">متصل</span>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    Sliders,
    Save,
    Building2,
    Printer,
    Bot,
    Cpu,
    ArrowRight,
    ChevronLeft,
    ShieldCheck,
    Send,
    Code2,
    Layers,
    Server,
    Database
} from 'lucide-vue-next';

const windowWidth = ref(window.innerWidth);
const isMobileView = computed(() => windowWidth.value < 1024);

const selectedSection = ref(isMobileView.value ? null : 'branding');
const isLoading = ref(false);
const isSaving = ref(false);
const isTestingTelegram = ref(false);

const sections = [
    {
        id: 'branding',
        label: 'الهوية والمؤسسة',
        subtitle: 'اسم المحمصة، الهاتف والعنوان',
        description: 'تخصيص الاسم التجاري، الشعار، والبيانات الرسمية للمؤسسة والفروع.',
        icon: Building2,
        iconBg: 'bg-amber-500/10 border border-amber-500/20',
        iconColor: 'text-amber-400',
        badge: 'أساسي'
    },
    {
        id: 'printing',
        label: 'الفواتير والطباعة',
        subtitle: 'إيصالات الكاشير و QR Code',
        description: 'التحكم في شكل الفاتورة الحرارية، إظهار الرصيد، ورمز الاستجابة السريع.',
        icon: Printer,
        iconBg: 'bg-blue-500/10 border border-blue-500/20',
        iconColor: 'text-blue-400',
        badge: 'طباعة'
    },
    {
        id: 'telegram',
        label: 'إشعارات تلجرام',
        subtitle: 'ربط البوت والتقارير الفورية',
        description: 'إرسال تنبيهات المبيعات، نواقص المخزن، وإغلاق الورديات لحظياً.',
        icon: Bot,
        iconBg: 'bg-cyan-500/10 border border-cyan-500/20',
        iconColor: 'text-cyan-400',
        badge: 'تلقائي'
    },
    {
        id: 'system',
        label: 'معلومات النظام',
        subtitle: 'بيئة التشغيل ومواصفات السيرفر',
        description: 'متابعة إصدارات PHP و Laravel ومحرك قاعدة البيانات وحالة الخادم.',
        icon: Cpu,
        iconBg: 'bg-purple-500/10 border border-purple-500/20',
        iconColor: 'text-purple-400',
        badge: 'تقني'
    },
];

const currentSectionTitle = computed(() => {
    if (!selectedSection.value) return 'إعدادات النظام والمؤسسة';
    const found = sections.find((s) => s.id === selectedSection.value);
    return found ? found.label : 'إعدادات النظام';
});

const currentSectionSubtitle = computed(() => {
    if (!selectedSection.value) return 'اختر أحد أقسام الإعدادات لتخصيص الخيارات المتقدمة';
    const found = sections.find((s) => s.id === selectedSection.value);
    return found ? found.subtitle : '';
});

const form = ref({
    company_name: '',
    company_subtitle: '',
    company_phone: '',
    company_address: '',
    invoice_footer_note: '',
    show_print_company_name: true,
    show_print_subtitle: true,
    show_print_logo: true,
    thermal_show_customer_balance: true,
    print_show_qr: true,
    telegram_notifications_enabled: true,
    telegram_bot_token: '',
    telegram_chat_id: '',
});

const systemInfo = ref({});

const onResize = () => {
    windowWidth.value = window.innerWidth;
    if (!isMobileView.value && !selectedSection.value) {
        selectedSection.value = 'branding';
    }
};

const fetchSettings = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/settings');
        const s = res.data?.settings || {};
        form.value = {
            company_name: s.company_name || 'سرور كوفي',
            company_subtitle: s.company_subtitle || '',
            company_phone: s.company_phone || '',
            company_address: s.company_address || '',
            invoice_footer_note: s.invoice_footer_note || '',
            show_print_company_name: !!s.show_print_company_name,
            show_print_subtitle: !!s.show_print_subtitle,
            show_print_logo: !!s.show_print_logo,
            thermal_show_customer_balance: !!s.thermal_show_customer_balance,
            print_show_qr: !!s.print_show_qr,
            telegram_notifications_enabled: !!s.telegram_notifications_enabled,
            telegram_bot_token: s.telegram_bot_token || '',
            telegram_chat_id: s.telegram_chat_id || '',
        };
        systemInfo.value = res.data?.system_info || {};
    } catch (e) {
        console.error('Failed to load settings:', e);
    } finally {
        isLoading.value = false;
    }
};

const saveSettings = async () => {
    isSaving.value = true;
    try {
        await api.post('/settings', form.value);
        Swal.fire({
            icon: 'success',
            title: 'تم الحفظ',
            text: 'تم حفظ وتحديث إعدادات النظام بنجاح ✓',
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: e.response?.data?.message || 'تعذر حفظ الإعدادات',
        });
    } finally {
        isSaving.value = false;
    }
};

const sendTestTelegram = async () => {
    isTestingTelegram.value = true;
    try {
        const res = await api.post('/settings/telegram/test', {
            bot_token: form.value.telegram_bot_token,
            chat_id: form.value.telegram_chat_id,
        });
        if (res.data?.success) {
            Swal.fire({ icon: 'success', title: 'نجاح الإرسال', text: res.data.message });
        } else {
            Swal.fire({ icon: 'error', title: 'فشل الإرسال', text: res.data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: e.response?.data?.message || 'فشل إرسال الإشعار' });
    } finally {
        isTestingTelegram.value = false;
    }
};

onMounted(() => {
    window.addEventListener('resize', onResize);
    fetchSettings();
});

onUnmounted(() => {
    window.removeEventListener('resize', onResize);
});
</script>
