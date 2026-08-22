<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 transition-colors duration-200" dir="rtl">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🔝 TOP HEADER BAR (Exact match to reference image)          -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <header class="h-16 bg-white/95 dark:bg-slate-950/95 border-b border-slate-200 dark:border-slate-800/80 sticky top-0 z-40 px-3 sm:px-6 flex items-center justify-between shadow-xs select-none backdrop-blur-xs">
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

        <!-- 👤 User Profile Dropdown Pill (Matching Screenshot 100%) -->
        <div class="relative" ref="userDropdownRef">
          <button
            type="button"
            @click="isUserDropdownOpen = !isUserDropdownOpen"
            class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-slate-800 dark:text-slate-200 transition cursor-pointer active:scale-95 shadow-2xs"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <span class="font-black">{{ authStore.userName }} - {{ authStore.roles?.[0] || 'المدير العام' }}</span>
            <span class="text-[10px] text-slate-400">▼</span>
          </button>

          <!-- User Dropdown Menu -->
          <Transition name="fade">
            <div
              v-if="isUserDropdownOpen"
              class="absolute right-0 top-full mt-2 w-64 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-2 z-50 font-tajawal space-y-1"
            >
              <!-- Dropdown Header -->
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
          class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700 rounded-2xl text-xs font-bold text-slate-800 dark:text-amber-400 flex items-center gap-1.5 transition cursor-pointer active:scale-95"
          :title="appConfigStore.isDark ? 'التحويل للوضع النهاري' : 'التحويل للوضع الليلي'"
        >
          <Sun v-if="appConfigStore.isDark" class="w-3.5 h-3.5 text-amber-400" />
          <Moon v-else class="w-3.5 h-3.5 text-indigo-600" />
          <span class="hidden md:inline">{{ appConfigStore.isDark ? 'الوضع النهاري' : 'الوضع الليلي' }}</span>
        </button>

        <!-- 🔔 Notifications Bell Popover -->
        <div class="relative" ref="notificationsRef">
          <button
            type="button"
            @click="isNotificationsOpen = !isNotificationsOpen"
            class="relative p-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-2xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition cursor-pointer active:scale-95"
            title="الإشعارات والتنبيهات"
          >
            <Bell class="w-4 h-4 text-amber-500 dark:text-amber-400" />
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
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-500">
                  {{ notificationsList.length }} تنبيهات
                </span>
              </div>

              <!-- Notifications Items List -->
              <div class="space-y-1.5 max-h-64 overflow-y-auto pr-1 text-xs">
                <div
                  v-for="(n, idx) in notificationsList"
                  :key="idx"
                  class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-amber-500/30 transition flex items-start gap-2.5"
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
              class="h-8 pr-7 pl-3 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-slate-800 dark:text-cyan-400 focus:outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer font-tajawal"
            >
              <option v-for="s in authStore.stores" :key="s.id" :value="s.id">
                🏬 {{ s.name }}
              </option>
            </select>
          </div>
          <!-- Single Store Badge -->
          <div v-else class="px-3 py-1.5 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-cyan-600 dark:text-cyan-400 flex items-center gap-1.5">
            <StoreIcon class="w-3.5 h-3.5" />
            <span>{{ authStore.activeStoreName || 'المخزن والفرع الرئيسي' }}</span>
          </div>

          <!-- 🔴/🟢 Shift Status Badge (Red when closed, Green when open) -->
          <router-link
            to="/daily-journal"
            class="px-3 py-1.5 rounded-2xl text-xs font-bold flex items-center gap-1.5 border transition cursor-pointer"
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
          class="hidden sm:flex items-center gap-1 px-2.5 py-1 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-600 dark:text-purple-400 rounded-xl text-xs font-black transition"
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
      <!-- 💻 DESKTOP SIDEBAR (Collapsible, Sticky Header, Sticky Footer, Tooltips on Mini Mode) -->
      <aside
        class="hidden md:flex bg-white dark:bg-slate-950 border-l border-slate-200 dark:border-slate-800/80 flex-col shrink-0 font-tajawal select-none transition-all duration-300 shadow-sm"
        :class="isSidebarCollapsed ? 'w-20' : 'w-72'"
      >
        <!-- 📌 1. STICKY TOP HEADER OF SIDEBAR -->
        <div class="p-4 border-b border-slate-200 dark:border-slate-800/80 flex items-center justify-between shrink-0 sticky top-0 bg-white dark:bg-slate-950 z-20">
          <div class="flex items-center gap-3 overflow-hidden">
            <div
              class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-950 font-black text-lg shadow-lg shrink-0 transition-colors"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              ☕
            </div>
            <div v-if="!isSidebarCollapsed" class="min-w-0">
              <h2 class="font-black text-sm text-slate-900 dark:text-white tracking-tight truncate">
                {{ appConfigStore.companyName || 'سرور كوفي' }}
              </h2>
              <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold truncate">
                {{ appConfigStore.companySubtitle || 'توزيع خامات ومطاحن البن' }}
              </p>
            </div>
          </div>

          <!-- Collapse Toggle Button -->
          <button
            type="button"
            @click="toggleSidebarCollapse"
            class="p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition cursor-pointer text-xs font-mono"
            :title="isSidebarCollapsed ? 'توسيع القائمة' : 'تصغير القائمة'"
          >
            {{ isSidebarCollapsed ? '»' : '«' }}
          </button>
        </div>

        <!-- 📌 2. SCROLLABLE NAVIGATION LIST -->
        <div class="flex-1 overflow-y-auto p-3 space-y-1.5 custom-scrollbar">
          <!-- 🌟 Big Dynamic Theme Action Button: New Invoice (F2) -->
          <div class="relative group">
            <router-link
              to="/pos"
              class="flex items-center justify-center gap-2 w-full py-3 rounded-2xl font-black text-xs text-slate-950 transition-all active:scale-95 cursor-pointer mb-3 shadow-md"
              :class="isSidebarCollapsed ? 'px-2' : 'px-4'"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              <Plus class="w-4 h-4 stroke-[3] shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">+ فاتورة بيع جديدة (F2)</span>
            </router-link>

            <!-- Mini Sidebar Tooltip -->
            <div
              v-if="isSidebarCollapsed"
              class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50"
            >
              + فاتورة بيع جديدة (F2)
            </div>
          </div>

          <!-- 🏠 Dashboard Active Tab -->
          <div class="relative group">
            <router-link
              to="/"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name === 'dashboard'
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name === 'dashboard' ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <LayoutDashboard class="w-4 h-4 shrink-0" :style="$route.name === 'dashboard' ? { color: 'var(--color-primary, #f59e0b)' } : {}" />
              <span v-if="!isSidebarCollapsed" class="truncate">لوحة التحكم (Dashboard)</span>
            </router-link>

            <div
              v-if="isSidebarCollapsed"
              class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50"
            >
              لوحة التحكم (Dashboard)
            </div>
          </div>

          <!-- 📂 Section 1: المبيعات والفواتير -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-xs font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            المبيعات والفواتير
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div class="relative group">
            <router-link
              to="/invoices"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('invoices')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('invoices') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <FileText class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">فواتير المبيعات</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              فواتير المبيعات
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/daily-journal"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('daily_journal')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('daily_journal') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Wallet class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">اليومية وحركة الدرج</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              اليومية وحركة الدرج
            </div>
          </div>

          <!-- 📂 Section 2: العملاء والحسابات -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-xs font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            العملاء والحسابات
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div class="relative group">
            <router-link
              to="/customers"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('customers')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('customers') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Users class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">العملاء والشركات</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              العملاء والشركات
            </div>
          </div>

          <!-- 📂 Section 3: المخزون والفروع والتوزيع -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-xs font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            المخزون والفروع والتوزيع
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div class="relative group">
            <router-link
              to="/items"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('items')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('items') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Package class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">الأصناف والأسعار</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              الأصناف والأسعار
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/purchases"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('purchases')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('purchases') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <ShoppingCart class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">فواتير المشتريات</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              فواتير المشتريات
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/suppliers"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('suppliers')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('suppliers') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Building2 class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">الموردون والشركات</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              الموردون والشركات
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/stores"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('stores')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('stores') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <StoreIcon class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">المخازن والفروع</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              المخازن والفروع
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/coffee-blender"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('coffee_blender')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('coffee_blender') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Layers class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">صانع الخلطات والبن</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              صانع الخلطات والبن
            </div>
          </div>

          <!-- 📂 Section 4: المرتجعات والمصروفات والتقارير -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-xs font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            المرتجعات والمصروفات والتقارير
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div class="relative group">
            <router-link
              to="/expenses"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('expenses')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('expenses') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Receipt class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">المصروفات والنثريات</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              المصروفات والنثريات
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/returns"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('returns')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('returns') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <RotateCcw class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">سجل المرتجعات</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              سجل المرتجعات
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/reports"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('reports')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('reports') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <BarChart3 class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">التقارير المالية والأرباح</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              التقارير المالية والأرباح
            </div>
          </div>

          <!-- 📂 Section 5: إدارة النظام والمستخدمين -->
          <div v-if="!isSidebarCollapsed" class="pt-3 pb-1 px-3 text-xs font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider">
            إدارة النظام والمستخدمين
          </div>
          <div v-else class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

          <div class="relative group">
            <router-link
              to="/users"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('users')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('users') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Users class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">المستخدمون والكاشير</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              المستخدمون والكاشير
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/roles"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('roles')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('roles') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <ShieldCheck class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">الأدوار والصلاحيات</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              الأدوار والصلاحيات
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/activity-logs"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('activity_logs')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('activity_logs') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Activity class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">سجل العمليات والرقابة</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              سجل العمليات والرقابة
            </div>
          </div>

          <div class="relative group">
            <router-link
              to="/settings"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all"
              :class="$route.name?.startsWith('settings')
                ? 'font-black border shadow-xs'
                : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-900'"
              :style="$route.name?.startsWith('settings') ? {
                color: 'var(--color-primary, #f59e0b)',
                borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
              } : {}"
            >
              <Sliders class="w-4 h-4 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">إعدادات المؤسسة</span>
            </router-link>
            <div v-if="isSidebarCollapsed" class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-slate-950 text-white text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap shadow-2xl border border-slate-800 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-200 z-50">
              إعدادات المؤسسة
            </div>
          </div>
        </div>

        <!-- 📌 3. STICKY BOTTOM FOOTER OF SIDEBAR -->
        <div class="p-3 border-t border-slate-200 dark:border-slate-800/80 flex items-center justify-between shrink-0 sticky bottom-0 bg-white/95 dark:bg-slate-950/95 z-20">
          <div class="flex items-center gap-2.5 overflow-hidden">
            <div
              class="w-8 h-8 rounded-xl flex items-center justify-center font-bold text-xs shrink-0"
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
            class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition cursor-pointer shrink-0"
            title="تسجيل الخروج"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </aside>

      <!-- Main Content Stage -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-50 dark:bg-slate-900/90 pb-24 md:pb-8">
        <slot />
      </main>

      <!-- Fixed Mobile Bottom Navigation Bar -->
      <MobileBottomNav @open-drawer="isSidebarOpen = true" />
    </div>

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
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
} from 'lucide-vue-next';

const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const route = useRoute();
const router = useRouter();

const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const isUserDropdownOpen = ref(false);
const isNotificationsOpen = ref(false);
const userDropdownRef = ref(null);
const notificationsRef = ref(null);

const currentTimeStr = ref('');
let clockInterval = null;

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
</style>
