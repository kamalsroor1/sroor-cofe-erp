<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans selection:bg-amber-500 selection:text-white" dir="rtl">
    <!-- Top Navigation Header (Desktop & Mobile) -->
    <header class="h-16 bg-slate-950/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 px-4 flex items-center justify-between shadow-sm">
      <!-- Right: Brand Logo & Mobile Toggle -->
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="isSidebarOpen = true"
          class="p-2.5 text-slate-300 hover:text-white hover:bg-slate-800/80 rounded-2xl transition-all md:hidden cursor-pointer active:scale-90"
          title="القائمة الرئيسية"
        >
          <Menu class="w-6 h-6" />
        </button>

        <router-link to="/" class="flex items-center gap-2.5 group">
          <div class="w-10 h-10 rounded-2xl bg-slate-800 border border-slate-700 p-1.5 flex items-center justify-center shadow-md">
            <img :src="appConfigStore.branding?.logo || '/logo.png'" alt="Logo" class="w-full h-full object-contain">
          </div>
          <div>
            <h1 class="font-black text-sm text-white group-hover:text-amber-400 transition-colors font-tajawal">
              {{ appConfigStore.companyName || 'سرور كوفي ERP' }}
            </h1>
            <p class="text-[10px] text-slate-400 font-bold -mt-0.5">
              {{ authStore.activeStoreName }}
            </p>
          </div>
        </router-link>
      </div>

      <!-- Center / Store Context Switcher (Desktop) -->
      <div class="hidden sm:flex items-center gap-2">
        <div v-if="authStore.stores?.length > 1" class="relative">
          <select
            :value="authStore.currentStore?.id"
            @change="handleStoreChange($event.target.value)"
            class="h-9 pr-8 pl-4 bg-slate-900 border border-slate-700 hover:border-slate-600 rounded-xl text-xs font-bold text-slate-200 focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all cursor-pointer font-tajawal"
          >
            <option v-for="store in authStore.stores" :key="store.id" :value="store.id">
              🏬 {{ store.name }} {{ store.is_main ? '(الرئيسي)' : '' }}
            </option>
          </select>
        </div>

        <!-- Active Shift Badge -->
        <div v-if="appConfigStore.hasOpenShift" class="px-2.5 py-1 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-[11px] font-bold text-emerald-400 flex items-center gap-1.5 font-tajawal">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>وردية مفتوحة: {{ appConfigStore.currentShiftNumber }}</span>
        </div>
      </div>

      <!-- Left: Theme, User Profile, Logout -->
      <div class="flex items-center gap-2">
        <!-- Theme Switcher -->
        <button
          type="button"
          @click="toggleTheme"
          class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-800 rounded-xl transition-all cursor-pointer"
          :title="appConfigStore.isDark ? 'تفعيل الوضع الفاتح' : 'تفعيل الوضع الداكن'"
        >
          <Sun v-if="appConfigStore.isDark" class="w-4 h-4" />
          <Moon v-else class="w-4 h-4" />
        </button>

        <!-- User Profile -->
        <div class="flex items-center gap-2 pr-2 border-r border-slate-800">
          <div class="text-end hidden lg:block">
            <div class="text-xs font-bold text-slate-200 font-tajawal">{{ authStore.userName }}</div>
            <div class="text-[10px] text-amber-400 font-mono">{{ authStore.roles?.[0] || 'كاشير' }}</div>
          </div>

          <button
            type="button"
            @click="handleLogout"
            class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
            title="تسجيل الخروج"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>
    </header>

    <!-- App Body: Sidebar / Drawer + Main Content -->
    <div class="flex-1 flex overflow-hidden relative">
      <!-- 📱 Mobile & Tablet Backdrop Blur Overlay -->
      <transition name="fade">
        <div
          v-if="isSidebarOpen"
          @click="isSidebarOpen = false"
          class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 md:hidden select-none"
        ></div>
      </transition>

      <!-- 📱 Native-Style Full App Navigation Drawer (Mobile & Tablet) / Desktop Sidebar -->
      <aside
        :class="[
          'bg-slate-950 border-l border-slate-800 flex flex-col transition-transform duration-300 ease-out z-50 font-tajawal',
          // Desktop Styles (>= md)
          'hidden md:flex md:w-64 lg:w-72 md:static md:translate-x-0',
          // Mobile & Tablet Styles (< md)
          isSidebarOpen ? 'flex fixed inset-y-0 right-0 w-[88vw] max-w-[420px] shadow-2xl translate-x-0' : 'fixed inset-y-0 right-0 w-[88vw] max-w-[420px] translate-x-full'
        ]"
      >
        <!-- 📱 Mobile App Drawer Top Profile Header (Visible on Mobile / Tablet Drawer) -->
        <div class="p-5 border-b border-slate-800/80 bg-slate-900/90 flex items-start justify-between gap-3 shrink-0 md:hidden">
          <div class="flex items-center gap-3.5 min-w-0">
            <div class="w-13 h-13 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-600 p-0.5 shadow-lg shadow-amber-500/20 shrink-0 flex items-center justify-center text-slate-950 font-black text-lg font-tajawal">
              {{ authStore.userName?.charAt(0) || 'U' }}
            </div>
            <div class="min-w-0">
              <h2 class="font-black text-sm text-white truncate leading-snug">
                {{ authStore.userName }}
              </h2>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-400 font-bold text-[10px]">
                  {{ authStore.roles?.[0] || 'كاشير' }}
                </span>
                <span class="text-[11px] text-slate-400 truncate">
                  🏬 {{ authStore.activeStoreName }}
                </span>
              </div>
            </div>
          </div>

          <!-- Tactile Close Button -->
          <button
            type="button"
            @click="isSidebarOpen = false"
            class="w-10 h-10 rounded-2xl bg-slate-800/90 hover:bg-slate-700 border border-slate-700/80 text-slate-300 hover:text-white flex items-center justify-center transition-all active:scale-90 cursor-pointer shadow-md shrink-0"
            title="إغلاق القائمة"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Scrollable Navigation Groups -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-2 custom-scrollbar">
          <!-- Primary POS Fast Action Button -->
          <div class="pb-2">
            <router-link
              to="/pos"
              @click="isSidebarOpen = false"
              class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-black text-sm flex items-center justify-between shadow-lg shadow-emerald-500/20 transition-all active:scale-95 cursor-pointer group"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-slate-950/20 flex items-center justify-center">
                  <ShoppingCart class="w-4.5 h-4.5 text-slate-950" />
                </div>
                <span>نقطة البيع السريعة (POS)</span>
              </div>
              <span class="text-xs opacity-75 font-mono">F2 ←</span>
            </router-link>
          </div>

          <!-- Section 1: Dashboard -->
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
              <span>لوحة التحكم الرئيسية</span>
            </div>
            <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
          </router-link>

          <!-- Group Header: Sales -->
          <div class="pt-4 pb-1 px-3 text-[11px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            العمليات ونقاط البيع
          </div>

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
              <span>فواتير المبيعات</span>
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
              <span>مرتجعات المبيعات والمشتريات</span>
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
                <Coffee class="w-4 h-4" />
              </div>
              <span>استوديو وخلاط البن</span>
            </div>
            <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
          </router-link>

          <!-- Group Header: Inventory -->
          <div class="pt-4 pb-1 px-3 text-[11px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            الفروع والمخزون
          </div>

          <router-link
            to="/stores"
            @click="isSidebarOpen = false"
            class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
            :class="$route.name?.startsWith('stores') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
          >
            <div class="flex items-center gap-3.5">
              <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('stores') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-emerald-400'">
                <StoreIcon class="w-4 h-4" />
              </div>
              <span>الفروع والمخازن</span>
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
              <span>الأصناف والمخزون</span>
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
              <span>التحويلات المخزنية</span>
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
              <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('purchases') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-teal-400'">
                <Truck class="w-4 h-4" />
              </div>
              <span>المشتريات والتوريد</span>
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
              <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('customers') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-sky-400'">
                <Users class="w-4 h-4" />
              </div>
              <span>العملاء وكشوف الحساب</span>
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
              <span>الموردين وكشوف الحساب</span>
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
              <span>المصروفات والعهد النثرية</span>
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
              <span>دفتر اليومية والخزينة</span>
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
              <span>التقارير والأرباح</span>
            </div>
            <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
          </router-link>

          <!-- Group Header: Management -->
          <div class="pt-4 pb-1 px-3 text-[11px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            إدارة النظام والمستخدمين
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
              <span>المستخدمين والموظفين</span>
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
              <span>مصفوفة الصلاحيات</span>
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
              <span>سجل النشاطات والتدقيق</span>
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
              <span>إعدادات النظام</span>
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
              <span>سلة المحذوفات</span>
            </div>
            <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
          </router-link>

          <!-- Group Header: Super Admin -->
          <div class="pt-4 pb-1 px-3 text-[11px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            إدارة المنصة والسوبر أدمن
          </div>

          <router-link
            to="/super-admin/dashboard"
            @click="isSidebarOpen = false"
            class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all group"
            :class="$route.name?.startsWith('super_admin.dashboard') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-900/90'"
          >
            <div class="flex items-center gap-3.5">
              <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="$route.name?.startsWith('super_admin.dashboard') ? 'bg-slate-950/20 text-white' : 'bg-slate-800 text-purple-400'">
                <Crown class="w-4 h-4" />
              </div>
              <span>لوحة السوبر أدمن</span>
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
              <span>إدارة المستأجرين</span>
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
              <span>الباقات والأسعار</span>
            </div>
            <span class="text-xs opacity-40 group-hover:opacity-100 transition-opacity">←</span>
          </router-link>
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
            <span>{{ appConfigStore.isDark ? 'نهاري' : 'ليلي' }}</span>
          </button>

          <button
            type="button"
            @click="handleLogout"
            class="py-2.5 px-4 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 hover:text-rose-300 text-xs font-bold flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
          >
            <LogOut class="w-4 h-4" />
            <span>خروج</span>
          </button>
        </div>
      </aside>

      <!-- Main Content Stage -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-900/60 pb-24 md:pb-8">
        <slot />
      </main>

      <!-- Fixed Mobile Bottom Navigation Bar -->
      <MobileBottomNav @open-drawer="isSidebarOpen = true" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
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
    Coffee,
    ShieldCheck,
    Activity,
    Sliders,
    Trash2,
    Crown,
    Building2,
    Layers,
    Sun,
    Moon,
    LogOut
} from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const isSidebarOpen = ref(false);

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
