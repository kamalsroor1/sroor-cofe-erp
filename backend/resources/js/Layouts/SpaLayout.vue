<template>
  <div class="h-screen max-h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col font-tajawal selection:bg-theme-primary selection:text-slate-950 transition-colors duration-200" dir="rtl">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🔝 TOP HEADER BAR (Clean, Non-Crowded & Responsive)          -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <header class="h-14 sm:h-16 shrink-0 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800/80 z-40 px-2.5 sm:px-5 flex items-center justify-between shadow-xs select-none backdrop-blur-md">
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
      <!-- 💻 DESKTOP SIDEBAR -->
      <aside
        class="hidden md:flex h-full max-h-full bg-white dark:bg-slate-900/95 border-l border-slate-200 dark:border-slate-800/80 flex-col shrink-0 font-tajawal select-none transition-all duration-300 shadow-sm z-30 overflow-hidden"
        :class="isSidebarCollapsed ? 'w-20' : 'w-72'"
      >
        <!-- 📌 1. PERMANENTLY FIXED TOP HEADER OF SIDEBAR -->
        <div class="p-3 border-b border-slate-200 dark:border-slate-800/80 shrink-0 bg-white dark:bg-slate-900/95 z-20">
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
                  {{ appConfigStore.companyName }}
                </h2>
                <p v-if="appConfigStore.companySubtitle" class="text-[10px] text-slate-500 dark:text-slate-400 font-bold truncate">
                  {{ appConfigStore.companySubtitle }}
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

        <!-- 📌 2. DYNAMIC SCROLLABLE NAVIGATION LIST -->
        <div class="flex-1 overflow-y-auto min-h-0 p-2.5 space-y-1.5 custom-scrollbar">
          <!-- 🌟 Big Action Button: New Sale Invoice (F2) -->
          <div v-if="isModuleEnabled('pos_and_sales')"
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

          <!-- Dynamic Sections Loop -->
          <template v-for="section in navigationSections" :key="section.key">
            <!-- Section Header (Only when expanded) -->
            <div
              v-if="!isSidebarCollapsed && section.title"
              class="pt-3 pb-1 px-3 text-[11px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider"
            >
              {{ section.title }}
            </div>
            <div v-else-if="section.title" class="my-1 border-t border-slate-200 dark:border-slate-800"></div>

            <!-- Section Items -->
            <div
              v-for="item in section.items"
              :key="item.key"
              class="relative"
              @mouseenter="handleItemHover($event, item.title)"
              @mouseleave="handleItemLeave"
            >
              <router-link
                :to="item.path"
                class="group flex items-center gap-3 p-2 rounded-2xl text-sm font-bold transition-all"
                :class="isItemActive(item)
                  ? 'font-black border shadow-xs'
                  : 'text-slate-700 dark:text-slate-200 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-900/80'"
                :style="isItemActive(item) ? {
                  color: 'var(--color-primary, #f59e0b)',
                  borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                  backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
                } : {}"
              >
                <!-- Solid Tactile Icon Tile -->
                <div
                  class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200 shadow-2xs"
                  :class="isItemActive(item)
                    ? 'text-slate-950 shadow-md font-bold'
                    : 'bg-slate-100 dark:bg-slate-900/90 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800/80 group-hover:border-slate-400 dark:group-hover:border-slate-700 group-hover:text-slate-900 dark:group-hover:text-white'"
                  :style="isItemActive(item) ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
                >
                  <component :is="item.icon" class="w-4 h-4 stroke-[2.4]" />
                </div>
                <span v-if="!isSidebarCollapsed" class="truncate font-bold">{{ item.title }}</span>
              </router-link>
            </div>
          </template>
        </div>

        <!-- 📌 3. PERMANENTLY FIXED BOTTOM FOOTER OF SIDEBAR -->
        <div class="p-2.5 border-t border-slate-200 dark:border-slate-800/80 flex flex-col gap-2 shrink-0 bg-white/95 dark:bg-slate-900/95 z-20">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0 overflow-hidden">
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

          <!-- App Version Indicator -->
          <div v-if="!isSidebarCollapsed" class="flex items-center justify-between text-[10px] text-slate-400 pt-1.5 border-t border-slate-100 dark:border-slate-800/60 font-mono">
            <span class="text-slate-500 dark:text-slate-400 font-tajawal font-medium">إصدار النظام</span>
            <span class="px-1.5 py-0.5 rounded-md bg-theme-light text-theme-primary font-bold font-mono">v{{ appVersion }}</span>
          </div>
          <div v-else class="text-center pt-1 border-t border-slate-100 dark:border-slate-800/60">
            <span class="text-[9px] font-mono font-bold text-theme-primary opacity-80" :title="'الإصدار v' + appVersion">v{{ appVersion }}</span>
          </div>
        </div>
      </aside>

      <!-- Main Content Stage -->
      <main class="flex-1 h-full overflow-y-auto min-h-0 p-3 sm:p-6 lg:p-8 bg-slate-50 dark:bg-slate-950 pb-24 md:pb-8 custom-scrollbar">
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
          <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
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
              v-if="isModuleEnabled('pos_and_sales')"
              to="/pos"
              @click="isSidebarOpen = false"
              class="flex items-center justify-center gap-2.5 w-full py-3 px-4 rounded-2xl text-slate-950 font-black text-xs shadow-lg transition active:scale-95"
              :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
            >
              <Plus class="w-4 h-4 stroke-[3]" />
              <span>+ فاتورة بيع جديدة (F2)</span>
            </router-link>

            <!-- Dynamic Navigation Sections -->
            <div v-for="section in navigationSections" :key="'mob-' + section.key" class="space-y-1">
              <!-- Section Title -->
              <div v-if="section.title" class="pt-2 pb-1 px-2 text-[11px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-theme-primary"></span>
                <span>{{ section.title }}</span>
              </div>

              <!-- Section Links -->
              <div class="space-y-1">
                <router-link
                  v-for="item in section.items"
                  :key="'mob-item-' + item.key"
                  :to="item.path"
                  @click="isSidebarOpen = false"
                  class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all"
                  :class="isItemActive(item)
                    ? 'font-black border shadow-xs'
                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-900/90'"
                  :style="isItemActive(item) ? {
                    color: 'var(--color-primary, #f59e0b)',
                    borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.35))',
                    backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.15))'
                  } : {}"
                >
                  <div
                    class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                    :class="isItemActive(item)
                      ? 'text-slate-950'
                      : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                    :style="isItemActive(item) ? { backgroundColor: 'var(--color-primary, #f59e0b)' } : {}"
                  >
                    <component :is="item.icon" class="w-3.5 h-3.5 stroke-[2.4]" />
                  </div>
                  <span class="truncate">{{ item.title }}</span>
                </router-link>
              </div>
            </div>
          </div>

          <!-- Drawer Footer Version & Logout -->
          <div class="p-3 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50 dark:bg-slate-900/90 flex items-center justify-between text-[11px] font-mono">
            <span class="font-tajawal font-bold text-slate-600 dark:text-slate-400">إصدار النظام</span>
            <span class="px-2 py-0.5 rounded-md bg-theme-light text-theme-primary font-bold">v{{ appVersion }}</span>
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
import { useModules } from '../Composables/useModules';
import { useNavigation } from '../Composables/useNavigation';
import versionData from '../version.json';
import MobileBottomNav from '../Components/Navigation/MobileBottomNav.vue';
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

const appVersion = ref(versionData?.version || '1.0.1');
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const { isModuleEnabled } = useModules();
const { navigationSections, isItemActive } = useNavigation();

const route = useRoute();
const router = useRouter();

const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
let wasCollapsedBeforePos = localStorage.getItem('sidebar_collapsed') === 'true';

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
