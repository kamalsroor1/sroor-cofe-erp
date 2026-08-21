<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans selection:bg-amber-500 selection:text-white" dir="rtl">
    <!-- Top Navigation Header -->
    <header class="h-16 bg-slate-950/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 px-4 flex items-center justify-between shadow-sm">
      <!-- Right: Brand Logo & Mobile Toggle -->
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="isSidebarOpen = !isSidebarOpen"
          class="p-2 text-slate-400 hover:text-white hover:bg-slate-800/80 rounded-xl transition-colors md:hidden cursor-pointer"
          title="القائمة الجانبية"
        >
          <Menu class="w-5 h-5" />
        </button>

        <router-link to="/" class="flex items-center gap-2.5 group">
          <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 p-1 flex items-center justify-center shadow-md">
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

      <!-- Center / Store Context Switcher -->
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

      <!-- Left: Theme, Notifications, User Menu -->
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

        <!-- User Profile Dropdown / Logout -->
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

    <!-- App Body: Sidebar + Main Content -->
    <div class="flex-1 flex overflow-hidden relative">
      <!-- Mobile Backdrop Overlay -->
      <div
        v-if="isSidebarOpen"
        @click="isSidebarOpen = false"
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs z-30 md:hidden"
      ></div>

      <!-- Sidebar Navigation -->
      <aside
        :class="[
          'w-64 bg-slate-950 border-l border-slate-800 flex flex-col transition-all duration-300 z-40',
          'fixed inset-y-0 right-0 pt-16 md:static md:pt-0',
          isSidebarOpen ? 'translate-x-0' : 'translate-x-full md:translate-x-0'
        ]"
      >
        <div class="flex-1 overflow-y-auto p-4 space-y-1.5 custom-scrollbar">
          <router-link
            to="/"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name === 'dashboard' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <LayoutDashboard class="w-4 h-4" />
            <span class="font-tajawal">لوحة التحكم الرئيسية</span>
          </router-link>

          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            العمليات ونقاط البيع
          </div>

          <router-link
            to="/pos"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('pos') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <ShoppingCart class="w-4 h-4 text-emerald-400" :class="$route.name?.startsWith('pos') ? 'text-slate-950' : 'text-emerald-400'" />
            <span class="font-tajawal">نقطة البيع السريعة (POS)</span>
          </router-link>

          <router-link
            to="/invoices"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('invoices') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <FileText class="w-4 h-4 text-blue-400" :class="$route.name?.startsWith('invoices') ? 'text-slate-950' : 'text-blue-400'" />
            <span class="font-tajawal">فواتير المبيعات</span>
          </router-link>

          <router-link
            to="/returns"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('returns') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <RotateCcw class="w-4 h-4 text-cyan-400" :class="$route.name?.startsWith('returns') ? 'text-slate-950' : 'text-cyan-400'" />
            <span class="font-tajawal">مرتجعات المبيعات والمشتريات</span>
          </router-link>

          <router-link
            to="/coffee-blender"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('coffee_blender') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Coffee class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('coffee_blender') ? 'text-slate-950' : 'text-amber-400'" />
            <span class="font-tajawal">استوديو وخلاط البن</span>
          </router-link>

          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            الفروع والمخزون
          </div>

          <router-link
            to="/stores"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('stores') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <StoreIcon class="w-4 h-4" />
            <span class="font-tajawal">الفروع والمخازن</span>
          </router-link>

          <router-link
            to="/items"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('items') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Package class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('items') ? 'text-slate-950' : 'text-amber-400'" />
            <span class="font-tajawal">الأصناف والمخزون</span>
          </router-link>

          <router-link
            to="/stock-transfers"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('stock_transfers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Truck class="w-4 h-4 text-purple-400" :class="$route.name?.startsWith('stock_transfers') ? 'text-slate-950' : 'text-purple-400'" />
            <span class="font-tajawal">التحويلات المخزنية</span>
          </router-link>

          <router-link
            to="/purchases"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('purchases') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Truck class="w-4 h-4 text-emerald-400" :class="$route.name?.startsWith('purchases') ? 'text-slate-950' : 'text-emerald-400'" />
            <span class="font-tajawal">المشتريات والتوريد</span>
          </router-link>

          <router-link
            to="/customers"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('customers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Users class="w-4 h-4 text-cyan-400" :class="$route.name?.startsWith('customers') ? 'text-slate-950' : 'text-cyan-400'" />
            <span class="font-tajawal">العملاء وكشوف الحساب</span>
          </router-link>

          <router-link
            to="/suppliers"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('suppliers') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Factory class="w-4 h-4 text-indigo-400" :class="$route.name?.startsWith('suppliers') ? 'text-slate-950' : 'text-indigo-400'" />
            <span class="font-tajawal">الموردين وكشوف الحساب</span>
          </router-link>

          <router-link
            to="/expenses"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('expenses') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Receipt class="w-4 h-4 text-rose-400" :class="$route.name?.startsWith('expenses') ? 'text-slate-950' : 'text-rose-400'" />
            <span class="font-tajawal">المصروفات والعهد النثرية</span>
          </router-link>

          <router-link
            to="/daily-journal"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('daily_journal') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Wallet class="w-4 h-4 text-emerald-400" :class="$route.name?.startsWith('daily_journal') ? 'text-slate-950' : 'text-emerald-400'" />
            <span class="font-tajawal">دفتر اليومية والخزينة</span>
          </router-link>

          <router-link
            to="/reports"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('reports') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <BarChart3 class="w-4 h-4 text-purple-400" :class="$route.name?.startsWith('reports') ? 'text-slate-950' : 'text-purple-400'" />
            <span class="font-tajawal">التقارير والأرباح</span>
          </router-link>

          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            إدارة النظام والمستخدمين
          </div>

          <router-link
            to="/users"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('users') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Users class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('users') ? 'text-slate-950' : 'text-amber-400'" />
            <span class="font-tajawal">المستخدمين والموظفين</span>
          </router-link>

          <router-link
            to="/roles"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('roles') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <ShieldCheck class="w-4 h-4 text-purple-400" :class="$route.name?.startsWith('roles') ? 'text-slate-950' : 'text-purple-400'" />
            <span class="font-tajawal">مصفوفة الصلاحيات</span>
          </router-link>

          <router-link
            to="/activity-logs"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('activity_logs') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Activity class="w-4 h-4 text-cyan-400" :class="$route.name?.startsWith('activity_logs') ? 'text-slate-950' : 'text-cyan-400'" />
            <span class="font-tajawal">سجل النشاطات</span>
          </router-link>

          <router-link
            to="/settings"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('settings') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Sliders class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('settings') ? 'text-slate-950' : 'text-amber-400'" />
            <span class="font-tajawal">إعدادات النظام</span>
          </router-link>

          <router-link
            to="/trash"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('trash') ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Trash2 class="w-4 h-4 text-rose-400" :class="$route.name?.startsWith('trash') ? 'text-slate-950' : 'text-rose-400'" />
            <span class="font-tajawal">سلة المحذوفات</span>
          </router-link>

          <!-- Super Admin Management Section -->
          <div class="pt-2 pb-1 px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider font-tajawal">
            إدارة المنصة والسوبر أدمن
          </div>

          <router-link
            to="/super-admin/dashboard"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('super_admin.dashboard') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Crown class="w-4 h-4 text-purple-400" :class="$route.name?.startsWith('super_admin.dashboard') ? 'text-white' : 'text-purple-400'" />
            <span class="font-tajawal">لوحة السوبر أدمن</span>
          </router-link>

          <router-link
            to="/super-admin/tenants"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('super_admin.tenants') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Building2 class="w-4 h-4 text-indigo-400" :class="$route.name?.startsWith('super_admin.tenants') ? 'text-white' : 'text-indigo-400'" />
            <span class="font-tajawal">إدارة المستأجرين</span>
          </router-link>

          <router-link
            to="/super-admin/plans"
            @click="isSidebarOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name?.startsWith('super_admin.plans') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <Layers class="w-4 h-4 text-amber-400" :class="$route.name?.startsWith('super_admin.plans') ? 'text-white' : 'text-amber-400'" />
            <span class="font-tajawal">الباقات والأسعار</span>
          </router-link>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-3 border-t border-slate-800/80 text-center">
          <div class="text-[10px] text-slate-500 font-mono">Pure API SPA v1.0</div>
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
