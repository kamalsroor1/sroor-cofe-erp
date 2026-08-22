<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans selection:bg-amber-500 selection:text-white" dir="rtl">
    <!-- Top Navigation Header (Desktop & Mobile) -->
    <header class="h-16 bg-slate-950/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 px-4 flex items-center justify-between shadow-sm select-none">
      <!-- Right: Brand Logo & Mobile Toggle -->
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="isSidebarOpen = true"
          class="p-2.5 text-slate-300 hover:text-white hover:bg-slate-800/80 rounded-2xl transition-all md:hidden cursor-pointer active:scale-90"
          :title="$t('nav.more_menu')"
        >
          <Menu class="w-6 h-6" />
        </button>

        <router-link :to="isSuperAdminPanel ? '/super-admin/dashboard' : '/'" class="flex items-center gap-2.5 group">
          <div class="w-10 h-10 rounded-2xl bg-slate-800 border border-slate-700 p-1.5 flex items-center justify-center shadow-md">
            <img :src="appConfigStore.branding?.logo || '/logo.png'" alt="Logo" class="w-full h-full object-contain">
          </div>
          <div>
            <h1 class="font-black text-sm text-white group-hover:text-amber-400 transition-colors font-tajawal">
              {{ isSuperAdminPanel ? 'منظومة ERP السحابية المركزية' : (appConfigStore.companyName || appConfigStore.platformName) }}
            </h1>
            <p class="text-[10px] text-slate-400 font-bold -mt-0.5 font-tajawal">
              {{ isSuperAdminPanel ? 'لوحة القيادة المركزية للسوبر أدمن' : authStore.activeStoreName }}
            </p>
          </div>
        </router-link>
      </div>

      <!-- Center / Store Context Switcher (Desktop) - Only on Tenant Mode -->
      <div v-if="!isSuperAdminPanel" class="hidden sm:flex items-center gap-2">
        <div v-if="authStore.stores?.length > 1" class="relative">
          <select
            :value="authStore.currentStore?.id"
            @change="handleStoreChange($event.target.value)"
            class="h-9 pr-8 pl-4 bg-slate-900 border border-slate-700 hover:border-slate-600 rounded-xl text-xs font-bold text-slate-200 focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all cursor-pointer font-tajawal"
          >
            <option v-for="store in authStore.stores" :key="store.id" :value="store.id">
              🏬 {{ store.name }} {{ store.is_main ? `(${$t('common.main_store_default')})` : '' }}
            </option>
          </select>
        </div>

        <!-- Active Shift Badge -->
        <div v-if="appConfigStore.hasOpenShift" class="px-2.5 py-1 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-[11px] font-bold text-emerald-400 flex items-center gap-1.5 font-tajawal">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>{{ $t('nav.active_shift') }}: {{ appConfigStore.currentShiftNumber }}</span>
        </div>
      </div>
      <!-- Center: Super Admin Indicator -->
      <div v-else class="hidden sm:flex items-center gap-2">
        <div class="px-3 py-1.5 bg-purple-500/10 border border-purple-500/30 rounded-xl text-xs font-bold text-purple-300 flex items-center gap-2 font-tajawal shadow-sm">
          <Crown class="w-4 h-4 text-purple-400" />
          <span>المنصة المركزية السحابية (Central SaaS Hub)</span>
        </div>
      </div>

      <!-- Left: Theme, User Profile, Logout -->
      <div class="flex items-center gap-2">
        <!-- Theme Switcher -->
        <button
          type="button"
          @click="toggleTheme"
          class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-800 rounded-xl transition-all cursor-pointer"
          :title="appConfigStore.isDark ? $t('nav.switch_to_light') : $t('nav.switch_to_dark')"
        >
          <Sun v-if="appConfigStore.isDark" class="w-4 h-4" />
          <Moon v-else class="w-4 h-4" />
        </button>

        <!-- User Profile -->
        <div class="flex items-center gap-2 pr-2 border-r border-slate-800">
          <div class="text-end hidden lg:block">
            <div class="text-xs font-bold text-slate-200 font-tajawal">{{ authStore.userName }}</div>
            <div class="text-[10px] text-amber-400 font-mono">
              {{ isSuperAdminPanel ? '👑 سوبر أدمن المنصة' : (authStore.roles?.[0] || $t('nav.cashier_role')) }}
            </div>
          </div>

          <button
            type="button"
            @click="handleLogout"
            class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
            :title="$t('nav.logout')"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>
    </header>

    <!-- App Body: Desktop Sidebar + Main Content Stage -->
    <div class="flex-1 flex overflow-hidden relative">
      <!-- 💻 Desktop Sidebar (Visible only on screens >= md) -->
      <aside class="hidden md:flex md:w-64 lg:w-72 bg-slate-950 border-l border-slate-800 flex-col shrink-0 font-tajawal select-none">
        <div class="flex-1 overflow-y-auto p-4 space-y-1.5 custom-scrollbar">

          <!-- ═══════════════════════════════════════════════════════════ -->
          <!-- 👑 MODE 1: SUPER ADMIN CENTRAL PLATFORM SIDEBAR             -->
          <!-- ═══════════════════════════════════════════════════════════ -->
          <template v-if="isSuperAdminPanel">
            <div class="pt-2 pb-2 px-3 text-[11px] font-black text-purple-400 uppercase tracking-wider flex items-center gap-2 border-b border-purple-500/20 mb-3">
              <Crown class="w-4 h-4 text-purple-400" />
              <span>{{ $t('super.central_platform') }}</span>
            </div>

            <router-link
              to="/super-admin/dashboard"
              class="flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all"
              :class="$route.name === 'super_admin.dashboard' || $route.path === '/super-admin/dashboard' ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <LayoutDashboard class="w-4 h-4" :class="($route.name === 'super_admin.dashboard' || $route.path === '/super-admin/dashboard') ? 'text-white' : 'text-purple-400'" />
              <span>{{ $t('super.dashboard') }}</span>
            </router-link>

            <router-link
              to="/super-admin/tenants"
              class="flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('super_admin.tenants') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Building2 class="w-4 h-4" :class="$route.name?.startsWith('super_admin.tenants') ? 'text-white' : 'text-indigo-400'" />
              <span>{{ $t('super.tenants') }}</span>
            </router-link>

            <router-link
              to="/super-admin/plans"
              class="flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('super_admin.plans') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Layers class="w-4 h-4" :class="$route.name?.startsWith('super_admin.plans') ? 'text-white' : 'text-amber-400'" />
              <span>{{ $t('super.plans') }}</span>
            </router-link>

            <router-link
              to="/super-admin/app-versions"
              class="flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('super_admin.app_versions') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/25' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Rocket class="w-4 h-4" :class="$route.name?.startsWith('super_admin.app_versions') ? 'text-white' : 'text-purple-400'" />
              <span>{{ $t('super.app_versions') }}</span>
            </router-link>
          </template>

          <!-- ═══════════════════════════════════════════════════════════ -->
          <!-- 🛒 MODE 2: TENANT CASHIER & STORE ERP SIDEBAR              -->
          <!-- ═══════════════════════════════════════════════════════════ -->
          <template v-else>
            <router-link
              to="/"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name === 'dashboard' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <LayoutDashboard class="w-4 h-4" />
              <span>{{ $t('nav.dashboard') }}</span>
            </router-link>

            <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
              {{ $t('nav.group_sales') }}
            </div>

            <router-link
              to="/pos"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('pos') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <ShoppingCart class="w-4 h-4 text-emerald-400" :class="$route.name?.startsWith('pos') ? 'text-slate-950' : 'text-emerald-400'" />
              <span>{{ $t('nav.pos_fast') }}</span>
            </router-link>

            <router-link
              to="/invoices"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('invoices') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <FileText class="w-4 h-4 text-blue-400" :class="$route.name?.startsWith('invoices') ? 'text-slate-950' : 'text-blue-400'" />
              <span>{{ $t('nav.invoices_log') }}</span>
            </router-link>

            <router-link
              to="/returns"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('returns') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <RotateCcw class="w-4 h-4 text-cyan-400" :class="$route.name?.startsWith('returns') ? 'text-slate-950' : 'text-cyan-400'" />
              <span>{{ $t('nav.returns_adjustments') }}</span>
            </router-link>

            <router-link
              to="/coffee-blender"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('coffee_blender') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Layers class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('coffee_blender') ? 'text-slate-950' : 'text-amber-400'" />
              <span>{{ $t('nav.coffee_blender') }}</span>
            </router-link>

            <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
              {{ $t('nav.group_inventory') }}
            </div>

            <router-link
              to="/stores"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('stores') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <StoreIcon class="w-4 h-4" />
              <span>{{ $t('nav.stores') }}</span>
            </router-link>

            <router-link
              to="/items"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('items') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Package class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('items') ? 'text-slate-950' : 'text-amber-400'" />
              <span>{{ $t('nav.items_catalog') }}</span>
            </router-link>

            <router-link
              to="/stock-transfers"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('stock_transfers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Truck class="w-4 h-4 text-purple-400" :class="$route.name?.startsWith('stock_transfers') ? 'text-slate-950' : 'text-purple-400'" />
              <span>{{ $t('nav.stock_transfers') }}</span>
            </router-link>

            <router-link
              to="/purchases"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('purchases') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Truck class="w-4 h-4 text-emerald-400" :class="$route.name?.startsWith('purchases') ? 'text-slate-950' : 'text-emerald-400'" />
              <span>{{ $t('nav.purchases') }}</span>
            </router-link>

            <router-link
              to="/customers"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('customers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Users class="w-4 h-4 text-cyan-400" :class="$route.name?.startsWith('customers') ? 'text-slate-950' : 'text-cyan-400'" />
              <span>{{ $t('nav.customers') }}</span>
            </router-link>

            <router-link
              to="/suppliers"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('suppliers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Factory class="w-4 h-4 text-indigo-400" :class="$route.name?.startsWith('suppliers') ? 'text-slate-950' : 'text-indigo-400'" />
              <span>{{ $t('nav.suppliers') }}</span>
            </router-link>

            <router-link
              to="/expenses"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('expenses') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Receipt class="w-4 h-4 text-rose-400" :class="$route.name?.startsWith('expenses') ? 'text-slate-950' : 'text-rose-400'" />
              <span>{{ $t('nav.expenses') }}</span>
            </router-link>

            <router-link
              to="/daily-journal"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('daily_journal') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Wallet class="w-4 h-4 text-emerald-400" :class="$route.name?.startsWith('daily_journal') ? 'text-slate-950' : 'text-emerald-400'" />
              <span>{{ $t('nav.daily_journal') }}</span>
            </router-link>

            <router-link
              to="/reports"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('reports') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <BarChart3 class="w-4 h-4 text-purple-400" :class="$route.name?.startsWith('reports') ? 'text-slate-950' : 'text-purple-400'" />
              <span>{{ $t('nav.reports') }}</span>
            </router-link>

            <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">
              {{ $t('nav.group_management') }}
            </div>

            <router-link
              to="/users"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('users') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Users class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('users') ? 'text-slate-950' : 'text-amber-400'" />
              <span>{{ $t('nav.users') }}</span>
            </router-link>

            <router-link
              to="/roles"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('roles') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <ShieldCheck class="w-4 h-4 text-purple-400" :class="$route.name?.startsWith('roles') ? 'text-slate-950' : 'text-purple-400'" />
              <span>{{ $t('nav.roles') }}</span>
            </router-link>

            <router-link
              to="/activity-logs"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('activity_logs') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Activity class="w-4 h-4 text-cyan-400" :class="$route.name?.startsWith('activity_logs') ? 'text-slate-950' : 'text-cyan-400'" />
              <span>{{ $t('nav.audit_logs') }}</span>
            </router-link>

            <router-link
              to="/settings"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('settings') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Sliders class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('settings') ? 'text-slate-950' : 'text-amber-400'" />
              <span>{{ $t('nav.settings') }}</span>
            </router-link>

            <router-link
              to="/trash"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
              :class="$route.name?.startsWith('trash') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
            >
              <Trash2 class="w-4 h-4 text-rose-400" :class="$route.name?.startsWith('trash') ? 'text-slate-950' : 'text-rose-400'" />
              <span>{{ $t('nav.trash') }}</span>
            </router-link>
          </template>

        </div>

        <div class="p-3 border-t border-slate-800/80 text-center">
          <div class="text-[10px] text-slate-500 font-mono">Pure API SPA v1.0.1</div>
        </div>
      </aside>

      <!-- Main Content Stage -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-900/60 pb-24 md:pb-8">
        <slot />
      </main>

      <!-- Fixed Mobile Bottom Navigation Bar -->
      <MobileBottomNav @open-drawer="isSidebarOpen = true" />
    </div>

    <!-- 📱 Teleported Mobile / Tablet Super App Drawer (Renders directly into <body> with top z-index) -->
    <Teleport to="body">
      <!-- Backdrop Overlay -->
      <Transition name="fade">
        <div
          v-if="isSidebarOpen"
          @click="isSidebarOpen = false"
          class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[9998] md:hidden select-none"
        ></div>
      </Transition>

      <!-- Slide-in Drawer Panel -->
      <Transition name="sidebar-drawer">
        <aside
          v-if="isSidebarOpen"
          id="mobile-app-drawer"
          class="fixed inset-y-0 right-0 w-[88vw] max-w-[420px] bg-slate-950 border-l border-slate-800 flex flex-col shadow-2xl z-[9999] font-tajawal md:hidden select-none"
          dir="rtl"
        >
          <!-- 📱 App Drawer Top Profile Header -->
          <div class="p-5 border-b border-slate-800/80 bg-slate-900/95 flex items-start justify-between gap-3 shrink-0">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-600 p-0.5 shadow-lg shadow-amber-500/20">
                <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center text-amber-400 font-black text-lg">
                  {{ authStore.userName?.charAt(0) || 'U' }}
                </div>
              </div>
              <div>
                <h3 class="font-bold text-sm text-white">{{ authStore.userName }}</h3>
                <p class="text-[11px] text-amber-400 font-bold mt-0.5">
                  {{ isSuperAdminPanel ? '👑 سوبر أدمن المنصة' : (authStore.roles?.[0] || $t('nav.cashier_role')) }}
                </p>
                <p v-if="!isSuperAdminPanel" class="text-[10px] text-slate-400 font-bold">
                  {{ authStore.activeStoreName }}
                </p>
              </div>
            </div>

            <button
              @click="isSidebarOpen = false"
              type="button"
              class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition cursor-pointer"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Drawer Navigation Scrollable List -->
          <div class="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar">

            <!-- 👑 SUPER ADMIN DRAWER -->
            <template v-if="isSuperAdminPanel">
              <div class="pt-2 pb-1 px-3 text-[11px] font-black text-purple-400 uppercase tracking-wider flex items-center gap-2">
                <Crown class="w-4 h-4 text-purple-400" />
                <span>{{ $t('super.central_platform') }}</span>
              </div>

              <router-link
                to="/super-admin/dashboard"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name === 'super_admin.dashboard' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name === 'super_admin.dashboard' ? 'bg-slate-950/20 text-white' : 'bg-slate-800 text-purple-400'">
                    <Crown class="w-4 h-4" />
                  </div>
                  <span>{{ $t('super.dashboard') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/super-admin/tenants"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('super_admin.tenants') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('super_admin.tenants') ? 'bg-slate-950/20 text-white' : 'bg-slate-800 text-indigo-400'">
                    <Building2 class="w-4 h-4" />
                  </div>
                  <span>{{ $t('super.tenants') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/super-admin/plans"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('super_admin.plans') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('super_admin.plans') ? 'bg-slate-950/20 text-white' : 'bg-slate-800 text-amber-400'">
                    <Layers class="w-4 h-4" />
                  </div>
                  <span>{{ $t('super.plans') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/super-admin/app-versions"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('super_admin.app_versions') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('super_admin.app_versions') ? 'bg-slate-950/20 text-white' : 'bg-slate-800 text-purple-400'">
                    <Rocket class="w-4 h-4" />
                  </div>
                  <span>{{ $t('super.app_versions') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>
            </template>

            <!-- 🛒 TENANT DRAWER -->
            <template v-else>
              <router-link
                to="/"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name === 'dashboard' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name === 'dashboard' ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-amber-400'">
                    <LayoutDashboard class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.dashboard') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <div class="pt-4 pb-1 px-3 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                {{ $t('nav.group_sales') }}
              </div>

              <router-link
                to="/pos"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('pos') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('pos') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-emerald-400'">
                    <ShoppingCart class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.pos_fast') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/invoices"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('invoices') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('invoices') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-blue-400'">
                    <FileText class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.invoices_log') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/returns"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('returns') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('returns') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-cyan-400'">
                    <RotateCcw class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.returns_adjustments') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/coffee-blender"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('coffee_blender') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('coffee_blender') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-amber-400'">
                    <Layers class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.coffee_blender') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <div class="pt-4 pb-1 px-3 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                {{ $t('nav.group_inventory') }}
              </div>

              <router-link
                to="/stores"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('stores') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('stores') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-amber-400'">
                    <StoreIcon class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.stores') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/items"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('items') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('items') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-amber-400'">
                    <Package class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.items_catalog') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/stock-transfers"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('stock_transfers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('stock_transfers') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-purple-400'">
                    <Truck class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.stock_transfers') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/purchases"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('purchases') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('purchases') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-emerald-400'">
                    <Truck class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.purchases') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/customers"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('customers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('customers') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-cyan-400'">
                    <Users class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.customers') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/suppliers"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('suppliers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('suppliers') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-indigo-400'">
                    <Factory class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.suppliers') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/expenses"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('expenses') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('expenses') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-rose-400'">
                    <Receipt class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.expenses') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/daily-journal"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('daily_journal') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('daily_journal') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-emerald-400'">
                    <Wallet class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.daily_journal') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/reports"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('reports') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('reports') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-purple-400'">
                    <BarChart3 class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.reports') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <div class="pt-4 pb-1 px-3 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                {{ $t('nav.group_management') }}
              </div>

              <router-link
                to="/users"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('users') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('users') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-amber-400'">
                    <Users class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.users') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/roles"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('roles') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('roles') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-purple-400'">
                    <ShieldCheck class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.roles') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/activity-logs"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('activity_logs') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('activity_logs') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-cyan-400'">
                    <Activity class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.audit_logs') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/settings"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('settings') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('settings') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-amber-400'">
                    <Sliders class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.settings') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>

              <router-link
                to="/trash"
                @click="isSidebarOpen = false"
                class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
                :class="$route.name?.startsWith('trash') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
              >
                <div class="flex items-center gap-3.5">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('trash') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-rose-400'">
                    <Trash2 class="w-4 h-4" />
                  </div>
                  <span>{{ $t('nav.trash') }}</span>
                </div>
                <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
              </router-link>
            </template>
          </div>

          <!-- 📱 Mobile App Drawer Bottom Quick Actions Bar -->
          <div class="p-4 border-t border-slate-800/80 bg-slate-900/95 flex items-center justify-between gap-2 shrink-0 pb-safe md:hidden">
            <button
              type="button"
              @click="toggleTheme"
              class="flex-1 py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
            >
              <Sun v-if="appConfigStore.isDark" class="w-4 h-4 text-amber-400" />
              <Moon v-else class="w-4 h-4" />
              <span>{{ appConfigStore.isDark ? $t('nav.switch_to_light') : $t('nav.switch_to_dark') }}</span>
            </button>

            <button
              type="button"
              @click="handleLogout"
              class="py-2.5 px-4 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 hover:text-rose-300 text-xs font-bold flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
            >
              <LogOut class="w-4 h-4" />
              <span>{{ $t('nav.logout') }}</span>
            </button>
          </div>
        </aside>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import MobileBottomNav from '../Components/Navigation/MobileBottomNav.vue';
import {
    Menu,
    X,
    LayoutDashboard,
    ShoppingCart,
    FileText,
    Store as StoreIcon,
    Package,
    Truck,
    Users,
    Factory,
    Receipt,
    Wallet,
    BarChart3,
    RotateCcw,
    ShieldCheck,
    Activity,
    Sliders,
    Trash2,
    Crown,
    Building2,
    Layers,
    Rocket,
    Sun,
    Moon,
    LogOut
} from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const isSidebarOpen = ref(false);

const isSuperAdminPanel = computed(() => {
    return route.path.startsWith('/super-admin') || route.name?.startsWith('super_admin') || (authStore.isSuperAdmin && !appConfigStore.tenant);
});

const toggleTheme = () => {
    const nextTheme = appConfigStore.isDark ? 'light' : 'dark';
    appConfigStore.setTheme(nextTheme);
};

const handleStoreChange = (storeId) => {
    const selected = authStore.stores?.find((s) => s.id === parseInt(storeId, 10));
    if (selected) {
        authStore.switchStore(selected);
        window.location.reload();
    }
};

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'login' });
};
</script>
