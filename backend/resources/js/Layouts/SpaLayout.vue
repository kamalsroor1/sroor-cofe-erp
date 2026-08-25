<template>
  <div class="flex-1 h-full max-h-full min-h-0 overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 transition-colors duration-200" dir="rtl">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🔝 TOP HEADER BAR (Clean, Non-Crowded & Responsive)          -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <header class="min-h-[3.5rem] sm:min-h-[4rem] py-1.5 sm:py-0 shrink-0 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800/80 z-40 px-2.5 sm:px-5 flex items-center justify-between shadow-xs select-none backdrop-blur-md mobile-safe-header transition-all duration-200">
      <!-- Right Side (in RTL): Menu Toggle, User Pill, Theme, Notifications, Branch -->
      <div class="flex items-center gap-1.5 sm:gap-3 min-w-0">
        <!-- Mobile Menu Toggle Button -->
        <button
          type="button"
          @click="isSidebarOpen = true"
          class="min-h-[40px] min-w-[40px] p-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/80 rounded-xl transition md:hidden cursor-pointer active:scale-95 flex items-center justify-center"
          title="القائمة"
        >
          <Menu class="w-5 h-5" />
        </button>

        <!-- 👤 User Profile Dropdown Pill -->
        <div class="relative" ref="userDropdownRef">
          <button
            type="button"
            @click="isUserDropdownOpen = !isUserDropdownOpen"
            class="min-h-[38px] flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-xl sm:rounded-2xl text-xs font-bold text-slate-800 dark:text-slate-200 transition cursor-pointer active:scale-95 shadow-2xs"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
            <!-- Mobile: Short user/role name. Desktop: Full name & role -->
            <span class="font-black truncate max-w-[100px] sm:max-w-[200px]">
              {{ authStore.userName }}
              <span class="hidden sm:inline text-slate-500 dark:text-slate-400 font-normal"> - {{ authStore.roles?.[0] || 'المدير' }}</span>
            </span>
            <ChevronDown class="w-3 h-3 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': isUserDropdownOpen }" />
          </button>

          <!-- User Dropdown Menu -->
          <Transition name="fade">
            <div
              v-if="isUserDropdownOpen"
              class="absolute right-0 top-full mt-2 w-64 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-2 z-50 font-tajawal space-y-1"
            >
              <div class="p-3 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 rounded-xl mb-1">
                <div class="font-black text-xs text-slate-900 dark:text-white">{{ authStore.userName }} - {{ authStore.roles?.[0] || 'المدير العام' }}</div>
                <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ authStore.user?.phone || authStore.user?.email || '01012316954' }}</div>
              </div>

              <router-link
                to="/profile"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <span>⚙️</span>
                <span>الملف الشخصي والأمان</span>
              </router-link>

              <router-link
                to="/users"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <span>👥</span>
                <span>إدارة المستخدمين والصلاحيات</span>
              </router-link>

              <router-link
                to="/roles"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <span>🛡️</span>
                <span>مصفوفة الصلاحيات والأدوار</span>
              </router-link>

              <router-link
                to="/activity-logs"
                @click="isUserDropdownOpen = false"
                class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition"
              >
                <span>📜</span>
                <span>سجل العمليات والرقابة</span>
              </router-link>

              <div class="border-t border-slate-100 dark:border-slate-800/80 pt-1">
                <button
                  type="button"
                  @click="confirmLogout"
                  class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer text-start"
                >
                  <span>🚪</span>
                  <span>تسجيل الخروج</span>
                </button>
              </div>
            </div>
          </Transition>
        </div>

        <!-- ☀️/🌙 Theme Switcher Button -->
        <button
          type="button"
          @click="toggleTheme"
          class="min-h-[38px] min-w-[38px] px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700 rounded-xl sm:rounded-2xl text-xs font-bold text-slate-800 text-theme-primary flex items-center justify-center gap-1.5 transition cursor-pointer active:scale-95 shadow-2xs"
          :title="appConfigStore.isDark ? 'التحويل للوضع النهاري' : 'التحويل للوضع الليلي'"
        >
          <Sun v-if="appConfigStore.isDark" class="w-3.5 h-3.5 text-theme-primary" />
          <Moon v-else class="w-3.5 h-3.5 text-indigo-600" />
          <span class="hidden lg:inline">{{ appConfigStore.isDark ? 'الوضع النهاري' : 'الوضع الليلي' }}</span>
        </button>

        <!-- 🖨️ Desktop Hardware & Thermal Printer Settings (Desktop Mode Only) -->
        <button
          v-if="isDesktop"
          type="button"
          @click="isDesktopSettingsOpen = true"
          class="min-h-[38px] px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 hover:border-theme-primary rounded-xl sm:rounded-2xl text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5 transition cursor-pointer active:scale-95 shadow-2xs"
          :title="$t('settings.desktop_hardware_title')"
        >
          <span>🖨️</span>
          <span class="hidden xl:inline font-tajawal">{{ $t('settings.desktop_badge') }}</span>
        </button>

        <!-- 🔔 Notifications Bell Popover -->
        <div class="relative" ref="notificationsRef">
          <button
            type="button"
            @click="isNotificationsOpen = !isNotificationsOpen"
            class="min-h-[38px] min-w-[38px] p-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-xl sm:rounded-2xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition cursor-pointer active:scale-95 shadow-2xs flex items-center justify-center relative"
            title="الإشعارات والتنبيهات"
          >
            <Bell class="w-3.5 h-3.5 text-theme-primary" />
            <span class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white rounded-full text-[9px] font-black flex items-center justify-center animate-pulse">
              {{ notificationsList.length }}
            </span>
          </button>

          <!-- Notifications Dropdown Popup -->
          <Transition name="fade">
            <div
              v-if="isNotificationsOpen"
              class="absolute right-0 top-full mt-2 w-80 max-w-[90vw] bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-3 z-50 font-tajawal space-y-2"
            >
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                <div class="flex items-center gap-1.5 text-xs font-black text-slate-900 dark:text-white">
                  <span>🔔</span>
                  <span>مركز التنبيهات والإشعارات</span>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-theme-light text-theme-primary">
                  {{ notificationsList.length }} تنبيهات
                </span>
              </div>

              <!-- Notifications Items List -->
              <div class="space-y-1.5 max-h-64 overflow-y-auto pr-1 text-xs">
                <div
                  v-for="(n, idx) in notificationsList"
                  :key="idx"
                  class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-theme-border transition flex items-start gap-2.5"
                >
                  <span class="text-base">{{ n.icon }}</span>
                  <div class="flex-1 min-w-0">
                    <div class="font-bold text-slate-900 dark:text-white truncate">{{ n.title }}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">{{ n.desc }}</div>
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-100 dark:border-slate-800 pt-2 text-center">
                <router-link
                  to="/activity-logs"
                  @click="isNotificationsOpen = false"
                  class="text-[11px] font-bold text-theme-primary hover:underline"
                >
                  عرض كافة سجلات النشاط والرقابة ←
                </router-link>
              </div>
            </div>
          </Transition>
        </div>

        <!-- 🏬 Store Switcher (Branch context dropdown / badge - Desktop/Tablet) -->
        <div v-if="!isSuperAdminPanel" class="hidden lg:flex items-center gap-2">
          <!-- Multi-store selector -->
          <div v-if="authStore.stores?.length > 1" class="relative">
            <select
              :value="authStore.currentStore?.id"
              @change="handleStoreSwitch($event.target.value)"
              class="h-8 pr-7 pl-3 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-slate-800 dark:text-cyan-400 focus:outline-none focus:ring-2 focus:ring-theme-primary cursor-pointer font-tajawal shadow-2xs"
            >
              <option v-for="s in authStore.stores" :key="s.id" :value="s.id">
                🏬 {{ s.name }}
              </option>
            </select>
          </div>
          <!-- Single Store Badge -->
          <div v-else class="px-3 py-1.5 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-cyan-600 dark:text-cyan-400 flex items-center gap-1.5 shadow-2xs">
            <StoreIcon class="w-3.5 h-3.5" />
            <span>{{ authStore.activeStoreName || 'المخزن والفرع الرئيسي' }}</span>
          </div>

          <!-- 🔴/🟢 Shift Status Badge -->
          <router-link
            to="/daily-journal"
            class="px-3 py-1.5 rounded-2xl text-xs font-bold flex items-center gap-1.5 border transition cursor-pointer shadow-2xs"
            :class="appConfigStore.hasOpenShift
              ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
              : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'"
            :title="appConfigStore.hasOpenShift ? 'يومية مفتوحة' : 'اضغط لفتح يومية جديدة'"
          >
            <span class="w-2 h-2 rounded-full" :class="appConfigStore.hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500 animate-ping'"></span>
            <span>{{ appConfigStore.hasOpenShift ? `• يومية (#${appConfigStore.currentShiftNumber})` : '• فتح يومية' }}</span>
          </router-link>
        </div>
      </div>

      <!-- Left Side (in RTL): Live Clock & Super Admin / Logout -->
      <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
        <!-- Live Realtime Clock (Full on desktop/tablet, hidden on small phone to prevent clutter) -->
        <div class="hidden sm:flex text-xs font-bold text-slate-500 dark:text-slate-400 font-mono tracking-tight items-center gap-2">
          <span>{{ currentTimeStr }}</span>
        </div>

        <!-- Super Admin Switcher (If Permitted) -->
        <router-link
          v-if="canAccessSuperAdmin"
          to="/super-admin/dashboard"
          class="hidden sm:flex items-center gap-1 px-2.5 py-1 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-600 dark:text-purple-400 rounded-xl text-xs font-black transition shadow-2xs"
        >
          <span>👑</span>
          <span>السوبر أدمن</span>
        </router-link>

        <!-- Logout Button -->
        <button
          type="button"
          @click="confirmLogout"
          class="min-h-[38px] min-w-[38px] p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer flex items-center justify-center"
          title="تسجيل الخروج"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🖥️ MAIN BODY: DYNAMIC SIDEBAR + STAGE                        -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="flex-1 flex overflow-hidden relative min-h-0">
      <!-- 💻 MODERN DESKTOP SIDEBAR -->
      <DesktopSidebar
        :is-collapsed="isSidebarCollapsed"
        @toggle-collapse="toggleSidebarCollapse"
      />

      <!-- Main Content Stage -->
      <main
        class="flex-1 h-full min-h-0 bg-slate-50 dark:bg-slate-950"
        :class="isPosView ? 'p-0 overflow-hidden pb-0' : 'overflow-y-auto p-3 sm:p-6 lg:p-8 pb-24 md:pb-8 custom-scrollbar'"
      >
        <slot />
      </main>

      <!-- Fixed Mobile Bottom Navigation Bar -->
      <MobileBottomNav @open-drawer="isSidebarOpen = true" />
    </div>

    <!-- 📱 DYNAMIC MOBILE DRAWER (Complete Mirror of Full ERP System) -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="isSidebarOpen"
          @click="isSidebarOpen = false"
          class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs z-[9998] md:hidden"
        ></div>
      </Transition>

      <Transition name="sidebar-drawer">
        <aside
          v-if="isSidebarOpen"
          class="fixed inset-y-0 right-0 w-[88vw] max-w-[360px] bg-white dark:bg-slate-950 border-l border-slate-200 dark:border-slate-800 flex flex-col shadow-2xl z-[9999] font-tajawal md:hidden"
          dir="rtl"
        >
          <!-- Drawer Header -->
          <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50 mobile-safe-header">
            <div class="flex items-center gap-2.5 min-w-0">
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-950 font-black text-base shadow-sm shrink-0"
                :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
              >
                ☕
              </div>
              <div class="min-w-0">
                <span class="font-black text-sm text-slate-900 dark:text-white truncate block">{{ appConfigStore.companyName }}</span>
                <span class="text-[10px] text-slate-400 font-bold truncate block">{{ authStore.userName }} ({{ authStore.roles?.[0] || 'المدير' }})</span>
              </div>
            </div>
            <button
              @click="isSidebarOpen = false"
              class="min-h-[38px] min-w-[38px] p-2 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center justify-center font-bold"
            >
              ✕
            </button>
          </div>

          <!-- Drawer Navigation Items -->
          <div class="flex-1 overflow-y-auto p-3 space-y-3 custom-scrollbar">
            <!-- 🌟 Quick Action Button: POS -->
            <router-link
              v-if="isModuleEnabled('pos_and_sales') && !activeMobileSection"
              to="/pos"
              @click="isSidebarOpen = false"
              class="flex items-center justify-center gap-2.5 w-full py-3 px-4 rounded-2xl text-slate-950 font-black text-xs shadow-lg transition active:scale-95 cursor-pointer"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              <Plus class="w-4 h-4 stroke-[3]" />
              <span>+ نقطة البيع السريعة (POS)</span>
            </router-link>

            <!-- 📁 LEVEL 1: MODULES CATEGORIES HUB (When No Category Selected) -->
            <div v-if="!activeMobileSection" class="space-y-2.5">
              <div class="px-1 text-[11px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                أقسام وموديولات المنظومة
              </div>

              <!-- Module Cards Grid/List -->
              <div class="space-y-2">
                <template v-for="section in navigationSections" :key="'mob-sec-' + section.key">
                  <!-- Direct Dashboard Card -->
                  <router-link
                    v-if="section.isDirect"
                    :to="section.directPath || '/'"
                    @click="isSidebarOpen = false"
                    class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 hover:border-theme-primary flex items-center justify-between transition-all active:scale-[0.98] cursor-pointer shadow-2xs group"
                  >
                    <div class="flex items-center gap-3 min-w-0">
                      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="section.iconBg">
                        <component :is="section.icon" class="w-5 h-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-black text-slate-900 dark:text-white group-hover:text-theme-primary transition truncate">
                          {{ section.title }}
                        </div>
                        <div class="text-[10px] text-slate-400 font-bold truncate mt-0.5">
                          {{ section.subtitle || 'الرئيسية' }}
                        </div>
                      </div>
                    </div>
                    <ChevronLeft class="w-4 h-4 text-slate-400 group-hover:text-theme-primary transition shrink-0" />
                  </router-link>

                  <!-- Category Card with Drill-Down Sub-menu -->
                  <button
                    v-else
                    type="button"
                    @click="activeMobileSection = section"
                    class="w-full p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 hover:border-theme-primary flex items-center justify-between transition-all active:scale-[0.98] cursor-pointer shadow-2xs group text-start"
                  >
                    <div class="flex items-center gap-3 min-w-0">
                      <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="section.iconBg">
                        <component :is="section.icon" class="w-5 h-5" />
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-black text-slate-900 dark:text-white group-hover:text-theme-primary transition truncate">
                          {{ section.title }}
                        </div>
                        <div class="text-[10px] text-slate-400 font-bold truncate mt-0.5">
                          {{ section.subtitle || `${section.items?.length || 0} روابط` }}
                        </div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <span class="px-2 py-0.5 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-[10px] font-mono text-slate-600 dark:text-slate-400 font-bold">
                        {{ section.items?.length || 0 }} روابط
                      </span>
                      <ChevronLeft class="w-4 h-4 text-slate-400 group-hover:text-theme-primary group-hover:-translate-x-0.5 transition" />
                    </div>
                  </button>
                </template>

                <!-- Super Admin Section (If Allowed) -->
                <router-link
                  v-if="canAccessSuperAdmin"
                  to="/super-admin/dashboard"
                  @click="isSidebarOpen = false"
                  class="p-3.5 rounded-2xl bg-purple-500/10 border border-purple-500/20 hover:border-purple-500 flex items-center justify-between transition-all active:scale-[0.98] cursor-pointer shadow-2xs group"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center shrink-0 text-lg">
                      👑
                    </div>
                    <div class="min-w-0">
                      <div class="text-xs font-black text-purple-400 truncate">لوحة السوبر أدمن</div>
                      <div class="text-[10px] text-slate-400 font-bold truncate mt-0.5">إدارة المستأجرين والباقات والمنصة</div>
                    </div>
                  </div>
                  <ChevronLeft class="w-4 h-4 text-purple-400 shrink-0" />
                </router-link>
              </div>
            </div>

            <!-- 📂 LEVEL 2: DRILL-DOWN SUB-MENU (When Category is Active) -->
            <div v-else class="space-y-3 animate-in fade-in slide-in-from-left duration-200">
              <!-- Back to Modules Bar -->
              <div class="p-3 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <button
                  type="button"
                  @click="activeMobileSection = null"
                  class="flex items-center gap-1.5 text-xs font-black text-theme-primary hover:opacity-80 transition cursor-pointer active:scale-95"
                >
                  <ChevronRight class="w-4 h-4 stroke-[3]" />
                  <span>العودة لجميع الأقسام</span>
                </button>

                <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                  <component :is="activeMobileSection.icon" class="w-4 h-4 text-theme-primary" />
                  <span>{{ activeMobileSection.title }}</span>
                </div>
              </div>

              <!-- Module Sub-Links List -->
              <div class="space-y-1.5 pt-1">
                <router-link
                  v-for="item in activeMobileSection.items"
                  :key="'drill-mob-' + item.key"
                  :to="item.path"
                  @click="isSidebarOpen = false; activeMobileSection = null"
                  class="flex items-center justify-between p-3.5 rounded-2xl text-xs font-bold transition-all border shadow-2xs active:scale-[0.98] cursor-pointer"
                  :class="isItemActive(item)
                    ? 'font-black border-theme-primary shadow-sm'
                    : 'bg-white dark:bg-slate-900/80 text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-800 hover:border-theme-primary'"
                  :style="isItemActive(item) ? {
                    color: 'var(--color-primary, #f59e0b)',
                    borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                    backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
                  } : {}"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div
                      class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                      :class="isItemActive(item)
                        ? 'text-slate-950'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                      :style="isItemActive(item) ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
                    >
                      <component :is="item.icon" class="w-4 h-4 stroke-[2.4]" />
                    </div>
                    <span class="truncate">{{ item.title }}</span>
                  </div>
                  <ChevronLeft class="w-4 h-4 opacity-40 shrink-0" />
                </router-link>
              </div>
            </div>
          </div>

          <!-- Drawer Footer Version & Quick Update Check -->
          <div class="p-3.5 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50 dark:bg-slate-900/90 flex items-center justify-between text-xs font-mono">
            <div class="flex items-center gap-2">
              <span class="font-tajawal font-bold text-slate-700 dark:text-slate-300">الإصدار الحالي:</span>
              <span class="px-2 py-0.5 rounded-lg bg-theme-primary/10 text-theme-primary font-black font-mono">v{{ currentVersionName }}</span>
            </div>
            <button
              type="button"
              @click="checkForUpdates(true)"
              class="px-3 py-1 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl text-[11px] font-bold font-tajawal transition flex items-center gap-1.5 shadow-2xs cursor-pointer active:scale-95"
            >
              <span>🚀</span>
              <span>فحص التحديثات</span>
            </button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- 🖨️ Desktop Hardware Settings Modal -->
    <DesktopPrinterSettingsModal
      v-if="isDesktop"
      :show="isDesktopSettingsOpen"
      @close="isDesktopSettingsOpen = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { useModules } from '../Composables/useModules';
import { useNavigation } from '../Composables/useNavigation';
import { useAppUpdate } from '../Composables/useAppUpdate';
import { useDesktopHardware } from '../Composables/useDesktopHardware';
import DesktopPrinterSettingsModal from '../Components/Common/DesktopPrinterSettingsModal.vue';
import versionData from '../version.json';
import MobileBottomNav from '../Components/Navigation/MobileBottomNav.vue';
import DesktopSidebar from '../Components/Navigation/DesktopSidebar.vue';
import Swal from 'sweetalert2';
import {
    Store as StoreIcon,
    LogOut,
    Sun,
    Moon,
    Bell,
    Menu,
    Plus,
    ChevronDown,
    ChevronRight,
    ChevronLeft,
} from 'lucide-vue-next';

const { currentVersionName, checkForUpdates } = useAppUpdate();
const { isDesktop } = useDesktopHardware();
const isDesktopSettingsOpen = ref(false);
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const { isModuleEnabled } = useModules();
const { navigationSections, isItemActive } = useNavigation();

const route = useRoute();
const router = useRouter();

const isSidebarOpen = ref(false);
const activeMobileSection = ref(null);
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
let wasCollapsedBeforePos = localStorage.getItem('sidebar_collapsed') === 'true';

const isPosView = computed(() => route.path === '/pos' || route.path.startsWith('/pos'));

// 📱 Smart Adaptive Sidebar: Auto-collapse on POS, restore user's original state on exit
watch(
    () => route.path,
    (newPath, oldPath) => {
        const isGoingToPos = newPath === '/pos' || newPath.startsWith('/pos');
        const isComingFromPos = oldPath && (oldPath === '/pos' || oldPath.startsWith('/pos'));

        if (isGoingToPos) {
            wasCollapsedBeforePos = localStorage.getItem('sidebar_collapsed') === 'true';
            isSidebarCollapsed.value = true;
        } else if (isComingFromPos) {
            const savedPref = localStorage.getItem('sidebar_collapsed') === 'true';
            isSidebarCollapsed.value = savedPref;
        } else {
            isSidebarCollapsed.value = localStorage.getItem('sidebar_collapsed') === 'true';
        }
    },
    { immediate: true }
);

const isUserDropdownOpen = ref(false);
const isNotificationsOpen = ref(false);
const userDropdownRef = ref(null);
const notificationsRef = ref(null);

const currentTimeStr = ref('');
let clockInterval = null;

// Floating tooltip for collapsed sidebar (Teleported directly into <body>)
const hoveredTooltip = ref({
    show: false,
    text: '',
    top: 0,
    right: 0,
});

const handleItemHover = (e, text) => {
    if (!isSidebarCollapsed.value) return;
    const rect = e.currentTarget.getBoundingClientRect();
    hoveredTooltip.value = {
        show: true,
        text,
        top: rect.top + (rect.height / 2),
        right: window.innerWidth - rect.left + 16,
    };
};

const handleItemLeave = () => {
    hoveredTooltip.value.show = false;
};

const notificationsList = computed(() => [
    { icon: '📦', title: 'تنبيه نواقص المخزون', desc: 'صنف جيهان أخضر وصل للحد الأدنى (5 كجم)' },
    { icon: '🧾', title: 'فاتورة مبيعات جديدة', desc: 'تم اعتماد فاتورة للعميل بن الأصيل بقيمة 4,495 ج.م' },
    { icon: '🛡️', title: 'جلسة تسجيل دخول', desc: 'تم تسجيل الدخول بنجاح من لوحة الإدارة' },
]);

const isSuperAdminPanel = computed(() => {
    return route.path.startsWith('/super-admin');
});

const canAccessSuperAdmin = computed(() => {
    return authStore.user?.roles?.includes('super_admin') || authStore.roles?.includes('super_admin') || authStore.user?.email?.includes('admin');
});

const toggleSidebarCollapse = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem('sidebar_collapsed', isSidebarCollapsed.value ? 'true' : 'false');
    hoveredTooltip.value.show = false;
};

const updateLiveClock = () => {
    const now = new Date();
    const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    const dayName = days[now.getDay()];
    const day = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();
    const time = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    currentTimeStr.value = `${dayName}، ${day} ${monthName} ${year} | ${time}`;
};

const toggleTheme = () => {
    const nextTheme = appConfigStore.isDark ? 'light' : 'dark';
    appConfigStore.setTheme(nextTheme);
};

const handleStoreSwitch = (storeId) => {
    const store = authStore.stores?.find(s => String(s.id) === String(storeId));
    if (store) {
        authStore.switchStore(store);
        window.location.reload();
    }
};

const confirmLogout = async () => {
    isUserDropdownOpen.value = false;
    const result = await Swal.fire({
        title: 'تسجيل الخروج؟',
        text: 'هل أنت متأكد من رغبتك في تسجيل الخروج وإنهاء الجلسة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، تسجيل الخروج',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
    });

    if (result.isConfirmed) {
        await authStore.logout();
        router.push({ name: 'login' });
    }
};

// Close dropdowns on outside click
const handleOutsideClick = (e) => {
    if (userDropdownRef.value && !userDropdownRef.value.contains(e.target)) {
        isUserDropdownOpen.value = false;
    }
    if (notificationsRef.value && !notificationsRef.value.contains(e.target)) {
        isNotificationsOpen.value = false;
    }
};

onMounted(() => {
    updateLiveClock();
    clockInterval = setInterval(updateLiveClock, 1000);
    document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    document.removeEventListener('click', handleOutsideClick);
});
</script>

<style scoped>
.sidebar-drawer-enter-active,
.sidebar-drawer-leave-active {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.sidebar-drawer-enter-from,
.sidebar-drawer-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
    transition: opacity 0.15s cubic-bezier(0.16, 1, 0.3, 1), transform 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}
.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
    opacity: 0;
    transform: translate(6px, -50%);
}
</style>
