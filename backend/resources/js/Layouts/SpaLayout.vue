<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 transition-colors duration-200" dir="rtl">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🔝 TOP HEADER BAR                                           -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <header class="h-16 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800/80 sticky top-0 z-40 px-3 sm:px-6 flex items-center justify-between shadow-xs select-none backdrop-blur-md">
      <!-- Right Side: User Dropdown, Theme Switcher, Notifications, Store & Shift Context -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Mobile Menu Toggle -->
        <button
          type="button"
          @click="isSidebarOpen = true"
          class="p-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/80 rounded-xl transition md:hidden cursor-pointer active:scale-95"
          title="القائمة"
        >
          <Menu class="w-6 h-6" />
        </button>

        <!-- 👤 User Profile Dropdown Pill -->
        <div class="relative" ref="userDropdownRef">
          <button
            type="button"
            @click="isUserDropdownOpen = !isUserDropdownOpen"
            class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-slate-800 dark:text-slate-200 transition cursor-pointer active:scale-95 shadow-2xs"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <span class="font-black">{{ authStore.userName }} - {{ authStore.roles?.[0] || 'المدير العام' }}</span>
            <ChevronDown class="w-3 h-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': isUserDropdownOpen }" />
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
          class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700 rounded-2xl text-xs font-bold text-slate-800 text-theme-primary flex items-center gap-1.5 transition cursor-pointer active:scale-95 shadow-2xs"
          :title="appConfigStore.isDark ? 'التحويل للوضع النهاري' : 'التحويل للوضع الليلي'"
        >
          <Sun v-if="appConfigStore.isDark" class="w-3.5 h-3.5 text-theme-primary" />
          <Moon v-else class="w-3.5 h-3.5 text-indigo-600" />
          <span class="hidden md:inline">{{ appConfigStore.isDark ? 'الوضع النهاري' : 'الوضع الليلي' }}</span>
        </button>

        <!-- 🔔 Notifications Bell Popover -->
        <div class="relative" ref="notificationsRef">
          <button
            type="button"
            @click="isNotificationsOpen = !isNotificationsOpen"
            class="relative p-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-2xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition cursor-pointer active:scale-95 shadow-2xs"
            title="الإشعارات والتنبيهات"
          >
            <Bell class="w-4 h-4 text-theme-primary text-theme-primary" />
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

        <!-- 🏬 Store Switcher (Branch context dropdown / badge) -->
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
            <span>{{ appConfigStore.hasOpenShift ? `• يومية مفتوحة (#${appConfigStore.currentShiftNumber})` : '• فتح يومية' }}</span>
          </router-link>
        </div>
      </div>

      <!-- Left Side: Live Arabic Clock & Brand Header -->
      <div class="flex items-center gap-3">
        <!-- Live Realtime Clock -->
        <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-mono tracking-tight flex items-center gap-2">
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
          class="p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer"
          title="تسجيل الخروج"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🖥️ MAIN BODY: SIDEBAR + CONTENT                             -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="flex-1 flex overflow-hidden relative">
      <!-- 💻 DESKTOP SIDEBAR -->
      <aside
        class="hidden md:flex bg-white dark:bg-slate-900/95 border-l border-slate-200 dark:border-slate-800/80 flex-col shrink-0 font-tajawal select-none transition-all duration-300 shadow-sm"
        :class="isSidebarCollapsed ? 'w-20' : 'w-72'"
      >
        <!-- 📌 1. STICKY TOP HEADER OF SIDEBAR (Modern Redesigned Toggle Button) -->
        <div class="p-3 border-b border-slate-200 dark:border-slate-800/80 shrink-0 sticky top-0 bg-white dark:bg-slate-900/95 z-20">
          <!-- Expanded Mode -->
          <div v-if="!isSidebarCollapsed" class="flex items-center justify-between">
            <div class="flex items-center gap-3 overflow-hidden">
              <div
                class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-950 font-black text-lg shadow-lg shrink-0 transition-colors"
                :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
              >
                ☕
              </div>
              <div class="min-w-0">
                <h2 class="font-black text-sm text-slate-900 dark:text-white tracking-tight truncate">
                  {{ appConfigStore.companyName || 'سرور كوفي' }}
                </h2>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold truncate">
                  {{ appConfigStore.companySubtitle || 'توزيع خامات ومطاحن البن' }}
                </p>
              </div>
            </div>

            <!-- Sleek Icon Toggle Button -->
            <button
              type="button"
              @click="toggleSidebarCollapse"
              class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-300 dark:border-slate-800 transition-all duration-200 flex items-center justify-center cursor-pointer shadow-2xs hover:scale-105 active:scale-95"
              title="تصغير القائمة الجانبية"
            >
              <ChevronRight class="w-4 h-4 stroke-[2.5]" />
            </button>
          </div>

          <!-- Collapsed Mode (Mini Sidebar Header) -->
          <div v-else class="flex flex-col items-center gap-2.5 py-1">
            <div
              class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-950 font-black text-lg shadow-lg shrink-0 transition-colors"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              ☕
            </div>
            <!-- Expand Button -->
            <button
              type="button"
              @click="toggleSidebarCollapse"
              class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-slate-300 dark:border-slate-800 transition-all duration-200 flex items-center justify-center cursor-pointer shadow-2xs hover:scale-105 active:scale-95"
              title="توسيع القائمة الجانبية"
            >
              <ChevronLeft class="w-4 h-4 stroke-[2.5]" />
            </button>
          </div>
        </div>

        <!-- 📌 2. SCROLLABLE NAVIGATION LIST (Solid Tactile Icon Tiles) -->
        <div class="flex-1 overflow-y-auto p-2.5 space-y-1.5 custom-scrollbar">
          <!-- 🌟 Big Action Button: New Sale Invoice (F2) -->
          <div
            class="relative"
            @mouseenter="handleItemHover($event, '+ فاتورة بيع جديدة (F2)')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/pos"
              class="flex items-center justify-center gap-2.5 w-full py-3 rounded-2xl font-black text-xs text-slate-950 transition-all active:scale-95 cursor-pointer mb-3 shadow-md hover:brightness-105"
              :class="isSidebarCollapsed ? 'px-0' : 'px-4'"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              <Plus class="w-4 h-4 stroke-[3] shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">+ فاتورة بيع جديدة (F2)</span>
            </router-link>
          </div>

          <!-- 🏠 Dashboard Active Tab -->
          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'لوحة التحكم (Dashboard)')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name === 'dashboard'
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name === 'dashboard' ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <!-- Solid Tactile Icon Tile -->
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name === 'dashboard'
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name === 'dashboard' ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <LayoutDashboard class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">لوحة التحكم (Dashboard)</span>
            </router-link>
          </div>

          <!-- 📂 Section 1: المبيعات والفواتير -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-[11px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            المبيعات والفواتير
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'فواتير المبيعات')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/invoices"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('invoices')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('invoices') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('invoices')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('invoices') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <FileText class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">فواتير المبيعات</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'اليومية وحركة الدرج')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/daily-journal"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('daily_journal')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('daily_journal') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('daily_journal')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('daily_journal') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Wallet class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">اليومية وحركة الدرج</span>
            </router-link>
          </div>

          <!-- 📂 Section 2: العملاء والحسابات -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-[11px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            العملاء والحسابات
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'العملاء والشركات')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/customers"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('customers')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('customers') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('customers')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('customers') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Users class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">العملاء والشركات</span>
            </router-link>
          </div>

          <!-- 📂 Section 3: المخزون والفروع والتوزيع -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-[11px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            المخزون والفروع والتوزيع
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'الأصناف والأسعار')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/items"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('items')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('items') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('items')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('items') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Package class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">الأصناف والأسعار</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'فواتير المشتريات')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/purchases"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('purchases')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('purchases') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('purchases')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('purchases') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <ShoppingCart class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">فواتير المشتريات</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'الموردون والشركات')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/suppliers"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('suppliers')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('suppliers') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('suppliers')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('suppliers') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Building2 class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">الموردون والشركات</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'المخازن والفروع')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/stores"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('stores')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('stores') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('stores')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('stores') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <StoreIcon class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">المخازن والفروع</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'صانع الخلطات والبن')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/coffee-blender"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('coffee_blender')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('coffee_blender') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('coffee_blender')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('coffee_blender') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Layers class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">صانع الخلطات والبن</span>
            </router-link>
          </div>

          <!-- 📂 Section 4: المرتجعات والمصروفات والتقارير -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-[11px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            المرتجعات والمصروفات والتقارير
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'المصروفات والنثريات')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/expenses"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('expenses')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('expenses') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('expenses')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('expenses') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Receipt class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">المصروفات والنثريات</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'سجل المرتجعات')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/returns"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('returns')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('returns') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('returns')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('returns') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <RotateCcw class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">سجل المرتجعات</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'التقارير المالية والأرباح')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/reports"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('reports')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('reports') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('reports')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('reports') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <BarChart3 class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">التقارير المالية والأرباح</span>
            </router-link>
          </div>

          <!-- 📂 Section 5: إدارة النظام والمستخدمين -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-[11px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            إدارة النظام والمستخدمين
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'المستخدمون والكاشير')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/users"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('users')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('users') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('users')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('users') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Users class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">المستخدمون والكاشير</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'الأدوار والصلاحيات')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/roles"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('roles')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('roles') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('roles')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('roles') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <ShieldCheck class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">الأدوار والصلاحيات</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'سجل العمليات والرقابة')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/activity-logs"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('activity_logs')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('activity_logs') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('activity_logs')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('activity_logs') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Activity class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">سجل العمليات والرقابة</span>
            </router-link>
          </div>

          <div
            class="relative"
            @mouseenter="handleItemHover($event, 'إعدادات المؤسسة')"
            @mouseleave="handleItemLeave"
          >
            <router-link
              to="/settings"
              class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('settings')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
              :style="$route.name?.startsWith('settings') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                :class="$route.name?.startsWith('settings')
                  ? 'text-slate-950 shadow-md font-bold'
                  : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                :style="$route.name?.startsWith('settings') ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
              >
                <Sliders class="w-4 h-4 stroke-[2.4]" />
              </div>
              <span v-if="!isSidebarCollapsed" class="truncate font-bold">إعدادات المؤسسة</span>
            </router-link>
          </div>
        </div>

        <!-- 📌 3. STICKY BOTTOM FOOTER OF SIDEBAR -->
        <div class="p-3 border-t border-slate-200 dark:border-slate-800/80 flex items-center justify-between shrink-0 sticky bottom-0 bg-white/95 dark:bg-slate-900/95 z-20">
          <div class="flex items-center gap-2.5 overflow-hidden">
            <div
              class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-xs shrink-0 shadow-2xs"
              :style="{ backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))', color: 'var(--color-primary, #f59e0b)' }"
            >
              {{ authStore.userName?.charAt(0) || 'U' }}
            </div>
            <div v-if="!isSidebarCollapsed" class="min-w-0">
              <div class="text-xs font-black text-slate-900 dark:text-white truncate">{{ authStore.userName }}</div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono truncate">{{ authStore.roles?.[0] || 'المدير العام' }}</div>
            </div>
          </div>

          <button
            type="button"
            @click="confirmLogout"
            class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer shrink-0"
            title="تسجيل الخروج"
          >
            <LogOut class="w-4 h-4 stroke-[2.2]" />
          </button>
        </div>
      </aside>

      <!-- Main Content Stage (Dark Canvas Base) -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-50 dark:bg-slate-950 pb-24 md:pb-8">
        <slot />
      </main>

      <!-- Fixed Mobile Bottom Navigation Bar -->
      <MobileBottomNav @open-drawer="isSidebarOpen = true" />
    </div>

    <!-- 🌟 Teleported Non-Clipped Animated Tooltip for Collapsed Sidebar -->
    <Teleport to="body">
      <Transition name="tooltip-fade">
        <div
          v-if="hoveredTooltip.show && isSidebarCollapsed"
          class="fixed z-[99999] pointer-events-none -translate-y-1/2 px-3 py-2 bg-slate-950 text-white text-xs font-black rounded-xl shadow-2xl border border-slate-700 flex items-center gap-2.5 font-tajawal whitespace-nowrap"
          :style="{ top: `${hoveredTooltip.top}px`, right: `${hoveredTooltip.right}px` }"
        >
          <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"></span>
          <span>{{ hoveredTooltip.text }}</span>
        </div>
      </Transition>
    </Teleport>

    <!-- Mobile Drawer Sidebar (When opened on phone/tablet) -->
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
          class="fixed inset-y-0 right-0 w-[85vw] max-w-[360px] bg-white dark:bg-slate-950 border-l border-slate-200 dark:border-slate-800 flex flex-col shadow-2xl z-[9999] font-tajawal md:hidden"
          dir="rtl"
        >
          <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-xl">☕</span>
              <span class="font-black text-sm text-slate-900 dark:text-white">{{ appConfigStore.companyName || 'سرور كوفي' }}</span>
            </div>
            <button @click="isSidebarOpen = false" class="text-slate-400 p-2 font-bold">✕</button>
          </div>

          <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <router-link
              to="/pos"
              @click="isSidebarOpen = false"
              class="flex items-center justify-center gap-2 w-full py-3 rounded-2xl text-slate-950 font-black text-xs shadow-lg mb-3"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              <span>+ فاتورة بيع جديدة (F2)</span>
            </router-link>

            <router-link
              to="/"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <LayoutDashboard class="w-4 h-4" />
              <span>لوحة التحكم (Dashboard)</span>
            </router-link>

            <router-link
              to="/invoices"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <FileText class="w-4 h-4" />
              <span>فواتير المبيعات</span>
            </router-link>

            <router-link
              to="/daily-journal"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <Wallet class="w-4 h-4" />
              <span>اليومية وحركة الدرج</span>
            </router-link>

            <router-link
              to="/customers"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <Users class="w-4 h-4" />
              <span>العملاء والشركات</span>
            </router-link>

            <router-link
              to="/items"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <Package class="w-4 h-4" />
              <span>الأصناف والأسعار</span>
            </router-link>

            <router-link
              to="/purchases"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <ShoppingCart class="w-4 h-4" />
              <span>فواتير المشتريات</span>
            </router-link>

            <router-link
              to="/suppliers"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <Building2 class="w-4 h-4" />
              <span>الموردون والشركات</span>
            </router-link>

            <router-link
              to="/expenses"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <Receipt class="w-4 h-4" />
              <span>المصروفات والنثريات</span>
            </router-link>

            <router-link
              to="/reports"
              @click="isSidebarOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-200"
            >
              <BarChart3 class="w-4 h-4" />
              <span>التقارير المالية والأرباح</span>
            </router-link>
          </div>
        </aside>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import MobileBottomNav from '../Components/Navigation/MobileBottomNav.vue';
import Swal from 'sweetalert2';
import {
    LayoutDashboard,
    ShoppingCart,
    FileText,
    RotateCcw,
    Layers,
    Package,
    Store as StoreIcon,
    Users,
    Building2,
    Receipt,
    Wallet,
    BarChart3,
    ShieldCheck,
    Activity,
    Sliders,
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

const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const route = useRoute();
const router = useRouter();

const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');

// Force collapse mini-sidebar on POS page for maximum cashier workspace
watch(
    () => route.path,
    (path) => {
        if (path === '/pos' || path.startsWith('/pos')) {
            isSidebarCollapsed.value = true;
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
