<template>
  <div class="space-y-6 max-w-6xl mx-auto font-tajawal pb-12">
    <!-- Page Header (Master Level) -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-950/80 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl backdrop-blur-md">
      <div class="flex items-center gap-3.5">
        <!-- Back to Hub Button on Mobile when inside a sub-page -->
        <button
          v-if="selectedSection && isMobileView"
          type="button"
          @click="selectedSection = null"
          class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-800/90 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-amber-400 flex items-center justify-center transition-all active:scale-90 cursor-pointer shadow-xs shrink-0"
          :title="$t('settings.back_to_hub')"
        >
          <ArrowRight class="w-5 h-5" />
        </button>

        <div v-else class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-500 flex items-center justify-center shadow-md shadow-amber-500/10 shrink-0">
          <Sliders class="w-6 h-6" />
        </div>

        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
              {{ currentSectionTitle }}
            </h1>
            <span v-if="selectedSection" class="px-2.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-[10px] font-bold">
              {{ $t('settings.subpage_badge') }}
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ currentSectionSubtitle }}
          </p>
        </div>
      </div>

      <!-- Action Save Button (Visible when inside a section or on desktop) -->
      <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
        <button
          type="button"
          @click="saveSettings"
          :disabled="isSaving || isLoading"
          class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs sm:text-sm rounded-2xl shadow-lg shadow-amber-500/25 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer disabled:opacity-50"
        >
          <Save class="w-4.5 h-4.5" />
          <span>{{ isSaving ? $t('common.loading') : $t('profile.save_changes') }}</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-16 text-center bg-white dark:bg-slate-950/60 rounded-3xl border border-slate-200 dark:border-slate-800">
      <div class="w-12 h-12 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
      <p class="text-xs text-slate-500 dark:text-slate-300 font-bold">{{ $t('common.loading') }}</p>
    </div>

    <!-- Main Settings Navigation & Sub-Pages Layout -->
    <div v-else>
      <Transition name="settings-slide" mode="out-in">
        <!-- 📱 Mobile Hub Mode (Grid of Interactive Settings Cards when no section is selected) -->
        <div v-if="!selectedSection && isMobileView" key="settings-hub" class="grid grid-cols-1 gap-3.5">
          <div
            v-for="sec in sections"
            :key="sec.id"
            @click="selectedSection = sec.id"
            class="p-4.5 rounded-3xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all active:scale-[0.98] cursor-pointer shadow-sm dark:shadow-lg flex items-center justify-between gap-4 group"
          >
            <div class="flex items-center gap-4 min-w-0">
              <div
                class="w-13 h-13 rounded-2xl flex items-center justify-center shrink-0 shadow-xs transition-transform group-hover:scale-105"
                :class="sec.iconBg"
              >
                <component :is="sec.icon" class="w-6 h-6" :class="sec.iconColor" />
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-black text-slate-900 dark:text-white group-hover:text-amber-500 dark:group-hover:text-amber-400 transition-colors truncate">
                    {{ sec.label }}
                  </h3>
                  <span v-if="sec.badge" class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-bold">
                    {{ sec.badge }}
                  </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                  {{ sec.description }}
                </p>
              </div>
            </div>

            <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-slate-400 group-hover:text-amber-500 group-hover:bg-slate-200 dark:group-hover:bg-slate-800 transition-all shrink-0">
              <ChevronLeft class="w-5 h-5" />
            </div>
          </div>
        </div>

        <!-- 💻 Desktop Split View & Mobile Drill-Down Active Page -->
        <div v-else key="settings-detail" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <!-- Desktop Sidebar Menu (Col 4 - Hidden on mobile drill-down) -->
          <div class="hidden lg:block lg:col-span-4 space-y-2.5">
            <div class="p-3 bg-white dark:bg-slate-950/80 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-1.5">
              <div class="px-3.5 py-2 text-[11px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                {{ $t('settings.settings_sections_title') }}
              </div>

              <button
                v-for="sec in sections"
                :key="sec.id"
                type="button"
                @click="selectedSection = sec.id"
                class="w-full p-3 rounded-2xl text-xs font-bold transition-all flex items-center justify-between gap-3 text-start cursor-pointer group"
                :class="selectedSection === sec.id ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20 font-black' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900'"
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
                    <div class="text-[10px] font-normal truncate" :class="selectedSection === sec.id ? 'text-slate-900/80' : 'text-slate-400 dark:text-slate-500'">
                      {{ sec.subtitle }}
                    </div>
                  </div>
                </div>

                <ChevronLeft class="w-4 h-4 opacity-40 group-hover:opacity-100 transition-opacity shrink-0" />
              </button>
            </div>

            <!-- Quick Info Box -->
            <div class="p-4 bg-white dark:bg-slate-950/60 rounded-3xl border border-slate-200 dark:border-slate-800/80 text-xs text-slate-500 dark:text-slate-400 space-y-2 shadow-xs">
              <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200 font-bold">
                <ShieldCheck class="w-4 h-4 text-emerald-500 dark:text-emerald-400" />
                <span>{{ $t('settings.secure_management_title') }}</span>
              </div>
              <p class="text-[11px] leading-relaxed">
                {{ $t('settings.secure_management_desc') }}
              </p>
            </div>
          </div>

          <!-- Settings Sub-Page Content Area (Col 8 on Desktop, Col 12 on Mobile Drill-Down) -->
          <div class="lg:col-span-8">
            <Transition name="settings-crossfade" mode="out-in">
              <!-- 🏢 Sub-Page 1: Branding & Organization Info -->
              <div
                v-if="selectedSection === 'branding'"
                key="branding"
                class="bg-white dark:bg-slate-950/80 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6"
              >
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center">
                      <Building2 class="w-5 h-5" />
                    </div>
                    <div>
                      <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.branding_section_title') }}</h2>
                      <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.branding_section_sub') }}</p>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_name') }} *</label>
                    <input
                      v-model="form.company_name"
                      type="text"
                      :placeholder="$t('settings.company_name_placeholder')"
                      class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:outline-none transition"
                    />
                  </div>

                  <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_subtitle') }}</label>
                    <input
                      v-model="form.company_subtitle"
                      type="text"
                      :placeholder="$t('settings.company_subtitle_placeholder')"
                      class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:outline-none transition"
                    />
                  </div>

                  <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_phone') }}</label>
                    <input
                      v-model="form.company_phone"
                      type="text"
                      :placeholder="$t('settings.company_phone_placeholder')"
                      class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white font-mono focus:outline-none transition text-start"
                      dir="ltr"
                    />
                  </div>

                  <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.company_address') }}</label>
                    <input
                      v-model="form.company_address"
                      type="text"
                      :placeholder="$t('settings.company_address_placeholder')"
                      class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:outline-none transition"
                    />
                  </div>
                </div>
              </div>

              <!-- 🎨 Sub-Page 2: Appearance & Theme Color Customization -->
              <div
                v-else-if="selectedSection === 'appearance'"
                key="appearance"
                class="bg-white dark:bg-slate-950/80 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6"
              >
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center">
                      <Palette class="w-5 h-5" />
                    </div>
                    <div>
                      <h2 class="text-base font-black text-slate-900 dark:text-white">المظهر وهوية الألوان للسيستم</h2>
                      <p class="text-xs text-slate-500 dark:text-slate-400">تخصيص ثيم الألوان والوضع الفاتح/الداكن للمؤسسة والفرع</p>
                    </div>
                  </div>
                </div>

                <!-- Theme Color Palette Grid -->
                <div class="space-y-3">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    اختر اللون الأساسي للسيستم (Theme Accent Color):
                  </label>
                  
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <button
                      v-for="color in colorPalettes"
                      :key="color.id"
                      type="button"
                      @click="selectThemeColor(color.id)"
                      class="p-3.5 rounded-2xl border transition-all flex flex-col items-center gap-2.5 cursor-pointer relative"
                      :class="form.system_theme_color === color.id ? 'border-amber-500 bg-amber-500/10 ring-2 ring-amber-500/30' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 hover:border-slate-300 dark:hover:border-slate-700'"
                    >
                      <div class="w-8 h-8 rounded-full shadow-md flex items-center justify-center" :style="{ backgroundColor: color.hex }">
                        <span v-if="form.system_theme_color === color.id" class="text-white text-xs font-black">✓</span>
                      </div>
                      <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ color.name }}</span>
                    </button>
                  </div>
                </div>

                <!-- Light / Dark Mode Toggle -->
                <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                    وضع الإضاءة الافتراضي للمستخدم:
                  </label>

                  <div class="grid grid-cols-2 gap-3">
                    <button
                      type="button"
                      @click="appConfigStore.setTheme('dark')"
                      class="p-4 rounded-2xl border transition-all flex items-center justify-center gap-2.5 cursor-pointer"
                      :class="appConfigStore.isDark ? 'border-amber-500 bg-slate-900 text-white font-bold' : 'border-slate-200 dark:border-slate-800 bg-slate-50 text-slate-600'"
                    >
                      <Moon class="w-5 h-5 text-amber-400" />
                      <span>الوضع الداكن (Dark Mode)</span>
                    </button>

                    <button
                      type="button"
                      @click="appConfigStore.setTheme('light')"
                      class="p-4 rounded-2xl border transition-all flex items-center justify-center gap-2.5 cursor-pointer"
                      :class="!appConfigStore.isDark ? 'border-amber-500 bg-white text-slate-900 font-bold shadow-md' : 'border-slate-200 dark:border-slate-800 bg-slate-900/40 text-slate-400'"
                    >
                      <Sun class="w-5 h-5 text-amber-500" />
                      <span>الوضع الفاتح (Light Mode)</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- 🖨️ Sub-Page 3: Invoices & Thermal Printing -->
              <div
                v-else-if="selectedSection === 'printing'"
                key="printing"
                class="bg-white dark:bg-slate-950/80 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6"
              >
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-500 flex items-center justify-center">
                      <Printer class="w-5 h-5" />
                    </div>
                    <div>
                      <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.printing_section_title') }}</h2>
                      <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.printing_section_sub') }}</p>
                    </div>
                  </div>
                </div>

                <!-- Toggles List -->
                <div class="space-y-3">
                  <div
                    @click="form.show_print_company_name = !form.show_print_company_name"
                    class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition"
                  >
                    <div>
                      <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.print_company_name_toggle') }}</div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.print_company_name_desc') }}</div>
                    </div>
                    <div
                      class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                      :class="form.show_print_company_name ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-700'"
                    >
                      <div
                        class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                        :class="form.show_print_company_name ? '-translate-x-6' : 'translate-x-0'"
                      ></div>
                    </div>
                  </div>

                  <div
                    @click="form.show_print_subtitle = !form.show_print_subtitle"
                    class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition"
                  >
                    <div>
                      <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.print_subtitle_toggle') }}</div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.print_subtitle_desc') }}</div>
                    </div>
                    <div
                      class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                      :class="form.show_print_subtitle ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-700'"
                    >
                      <div
                        class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                        :class="form.show_print_subtitle ? '-translate-x-6' : 'translate-x-0'"
                      ></div>
                    </div>
                  </div>

                  <div
                    @click="form.thermal_show_customer_balance = !form.thermal_show_customer_balance"
                    class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition"
                  >
                    <div>
                      <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.thermal_balance_toggle') }}</div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.thermal_balance_desc') }}</div>
                    </div>
                    <div
                      class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                      :class="form.thermal_show_customer_balance ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-700'"
                    >
                      <div
                        class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
                        :class="form.thermal_show_customer_balance ? '-translate-x-6' : 'translate-x-0'"
                      ></div>
                    </div>
                  </div>

                  <div
                    @click="form.print_show_qr = !form.print_show_qr"
                    class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition"
                  >
                    <div>
                      <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.print_qr_toggle') }}</div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.print_qr_desc') }}</div>
                    </div>
                    <div
                      class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                      :class="form.print_show_qr ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-700'"
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
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.invoice_footer_note_label') }}</label>
                  <textarea
                    v-model="form.invoice_footer_note"
                    rows="3"
                    :placeholder="$t('settings.invoice_footer_placeholder')"
                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:outline-none transition leading-relaxed"
                  ></textarea>
                </div>
              </div>

              <!-- 🤖 Sub-Page 4: Telegram Bot Integration -->
              <div
                v-else-if="selectedSection === 'telegram'"
                key="telegram"
                class="bg-white dark:bg-slate-950/80 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6"
              >
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-500 flex items-center justify-center">
                      <Bot class="w-5 h-5" />
                    </div>
                    <div>
                      <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.telegram_section_title') }}</h2>
                      <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.telegram_section_sub') }}</p>
                    </div>
                  </div>
                </div>

                <!-- Master Toggle -->
                <div
                  @click="form.telegram_notifications_enabled = !form.telegram_notifications_enabled"
                  class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition"
                >
                  <div>
                    <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.telegram_enable_toggle') }}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.telegram_enable_desc') }}</div>
                  </div>
                  <div
                    class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
                    :class="form.telegram_notifications_enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'"
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
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.bot_token') }}</label>
                    <input
                      v-model="form.telegram_bot_token"
                      type="text"
                      :placeholder="$t('settings.bot_token_placeholder')"
                      class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white font-mono focus:outline-none transition text-start"
                      dir="ltr"
                    />
                  </div>

                  <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.chat_id') }}</label>
                    <input
                      v-model="form.telegram_chat_id"
                      type="text"
                      :placeholder="$t('settings.chat_id_input_placeholder')"
                      class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-amber-500 rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white font-mono focus:outline-none transition text-start"
                      dir="ltr"
                    />
                  </div>
                </div>

                <!-- Test Notification Button -->
                <div class="pt-2 flex items-center justify-between p-4 rounded-2xl bg-cyan-500/10 border border-cyan-500/20">
                  <div class="text-xs text-cyan-800 dark:text-cyan-200">
                    <span class="font-bold block">{{ $t('settings.test_connection_title') }}</span>
                    <span class="text-[11px] text-cyan-600 dark:text-cyan-400/80">{{ $t('settings.test_connection_desc') }}</span>
                  </div>

                  <button
                    type="button"
                    @click="sendTestTelegram"
                    :disabled="isTestingTelegram || !form.telegram_chat_id"
                    class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-cyan-600/20 flex items-center gap-2 transition active:scale-95 disabled:opacity-50 cursor-pointer"
                  >
                    <Send class="w-4 h-4" />
                    <span>{{ isTestingTelegram ? $t('common.loading') : $t('settings.send_test_btn') }}</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { useAppConfigStore } from '../../stores/appConfig';
import {
    Sliders,
    Save,
    Building2,
    Palette,
    Printer,
    Bot,
    ArrowRight,
    ChevronLeft,
    ShieldCheck,
    Send,
    Sun,
    Moon
} from 'lucide-vue-next';

const appConfigStore = useAppConfigStore();
const windowWidth = ref(window.innerWidth);
const isMobileView = computed(() => windowWidth.value < 1024);

const selectedSection = ref(isMobileView.value ? null : 'branding');
const isLoading = ref(false);
const isSaving = ref(false);
const isTestingTelegram = ref(false);

const colorPalettes = [
    { id: 'amber', name: 'عنبري ذهبي (Gold)', hex: '#f59e0b' },
    { id: 'emerald', name: 'أخضر زمردي (Emerald)', hex: '#10b981' },
    { id: 'blue', name: 'أزرق عصري (Sky Blue)', hex: '#3b82f6' },
    { id: 'purple', name: 'بنفسجي ملكي (Purple)', hex: '#a855f7' },
    { id: 'rose', name: 'وردي ياقوتي (Rose)', hex: '#f43f5e' },
    { id: 'orange', name: 'برتقالي مشرق (Orange)', hex: '#f97316' },
    { id: 'teal', name: 'فيروزي هادئ (Teal)', hex: '#14b8a6' },
    { id: 'indigo', name: 'نيلي داكن (Indigo)', hex: '#6366f1' },
];

const sections = computed(() => [
    {
        id: 'branding',
        label: trans('settings.sec_branding_label'),
        subtitle: trans('settings.sec_branding_subtitle'),
        description: trans('settings.sec_branding_desc'),
        icon: Building2,
        iconBg: 'bg-amber-500/10 border border-amber-500/20',
        iconColor: 'text-amber-500 dark:text-amber-400',
        badge: trans('settings.sec_branding_badge')
    },
    {
        id: 'appearance',
        label: 'المظهر وثيم الألوان',
        subtitle: 'ألوان المنظومة والوضع الفاتح والداكن',
        description: 'تخصيص لوحة الألوان وثيم النظام بالكامل للتطبيق والمتجر',
        icon: Palette,
        iconBg: 'bg-purple-500/10 border border-purple-500/20',
        iconColor: 'text-purple-500 dark:text-purple-400',
        badge: 'مظهر مميز'
    },
    {
        id: 'printing',
        label: trans('settings.sec_printing_label'),
        subtitle: trans('settings.sec_printing_subtitle'),
        description: trans('settings.sec_printing_desc'),
        icon: Printer,
        iconBg: 'bg-blue-500/10 border border-blue-500/20',
        iconColor: 'text-blue-500 dark:text-blue-400',
        badge: trans('settings.sec_printing_badge')
    },
    {
        id: 'telegram',
        label: trans('settings.sec_telegram_label'),
        subtitle: trans('settings.sec_telegram_subtitle'),
        description: trans('settings.sec_telegram_desc'),
        icon: Bot,
        iconBg: 'bg-cyan-500/10 border border-cyan-500/20',
        iconColor: 'text-cyan-500 dark:text-cyan-400',
        badge: trans('settings.sec_telegram_badge')
    },
]);

const currentSectionTitle = computed(() => {
    if (!selectedSection.value) return trans('settings.hub_title');
    const found = sections.value.find((s) => s.id === selectedSection.value);
    return found ? found.label : trans('settings.title');
});

const currentSectionSubtitle = computed(() => {
    if (!selectedSection.value) return trans('settings.hub_subtitle');
    const found = sections.value.find((s) => s.id === selectedSection.value);
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
    system_theme_color: 'amber',
    telegram_notifications_enabled: true,
    telegram_bot_token: '',
    telegram_chat_id: '',
});

const selectThemeColor = (colorId) => {
    form.value.system_theme_color = colorId;
    appConfigStore.setThemeColor(colorId);
};

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
            company_name: s.company_name || appConfigStore.companyName || '',
            company_subtitle: s.company_subtitle || '',
            company_phone: s.company_phone || '',
            company_address: s.company_address || '',
            invoice_footer_note: s.invoice_footer_note || '',
            show_print_company_name: !!s.show_print_company_name,
            show_print_subtitle: !!s.show_print_subtitle,
            show_print_logo: !!s.show_print_logo,
            thermal_show_customer_balance: !!s.thermal_show_customer_balance,
            print_show_qr: !!s.print_show_qr,
            system_theme_color: s.system_theme_color || 'amber',
            telegram_notifications_enabled: !!s.telegram_notifications_enabled,
            telegram_bot_token: s.telegram_bot_token || '',
            telegram_chat_id: s.telegram_chat_id || '',
        };
        if (s.system_theme_color) {
            appConfigStore.setThemeColor(s.system_theme_color);
        }
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
        if (form.value.system_theme_color) {
            appConfigStore.setThemeColor(form.value.system_theme_color);
        }
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('settings.settings_saved_success'),
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.response?.data?.message || trans('settings.settings_save_failed'),
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
            Swal.fire({ icon: 'success', title: trans('settings.test_send_success'), text: res.data.message });
        } else {
            Swal.fire({ icon: 'error', title: trans('settings.test_send_failed'), text: res.data.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: trans('common.error'), text: e.response?.data?.message || trans('settings.test_send_failed') });
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
