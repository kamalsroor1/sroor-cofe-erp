<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans selection:bg-amber-500 selection:text-white" dir="rtl">
    <!-- Top Navigation Header -->
    <header class="h-16 bg-slate-950/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 px-4 flex items-center justify-between shadow-sm">
      <!-- Right: Brand Logo & Mobile Toggle -->
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="isSidebarOpen = !isSidebarOpen"
          class="p-2 text-slate-400 hover:text-white hover:bg-slate-800/80 rounded-xl transition-colors md:hidden"
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
    <div class="flex-1 flex overflow-hidden">
      <!-- Sidebar Navigation -->
      <aside
        :class="[
          'w-64 bg-slate-950 border-l border-slate-800 flex flex-col transition-all duration-300 z-30',
          'fixed inset-y-0 right-0 pt-16 md:static md:pt-0',
          isSidebarOpen ? 'translate-x-0' : 'translate-x-full md:translate-x-0'
        ]"
      >
        <div class="flex-1 overflow-y-auto p-4 space-y-1.5 custom-scrollbar">
          <router-link
            to="/"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all"
            :class="$route.name === 'dashboard' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'"
          >
            <LayoutDashboard class="w-4 h-4" />
            <span class="font-tajawal">لوحة التحكم الرئيسية</span>
          </router-link>

          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            العمليات ونقاط البيع
          </div>

          <a
            href="/pos"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-900 transition-all"
          >
            <ShoppingCart class="w-4 h-4 text-emerald-400" />
            <span class="font-tajawal">نقطة البيع السريعة (POS)</span>
          </a>

          <a
            href="/invoices"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-900 transition-all"
          >
            <FileText class="w-4 h-4 text-blue-400" />
            <span class="font-tajawal">فواتير المبيعات</span>
          </a>

          <div class="pt-3 pb-1 px-3 text-[10px] font-black text-slate-500 uppercase tracking-wider font-tajawal">
            المخزون والجهات
          </div>

          <a
            href="/items"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-900 transition-all"
          >
            <Package class="w-4 h-4 text-amber-400" />
            <span class="font-tajawal">الأصناف والمخزون</span>
          </a>

          <a
            href="/customers"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-900 transition-all"
          >
            <Users class="w-4 h-4 text-cyan-400" />
            <span class="font-tajawal">العملاء وكشوف الحساب</span>
          </a>

          <a
            href="/suppliers"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-900 transition-all"
          >
            <Factory class="w-4 h-4 text-indigo-400" />
            <span class="font-tajawal">الموردين والمشتريات</span>
          </a>

          <a
            href="/daily-journal"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-900 transition-all"
          >
            <Wallet class="w-4 h-4 text-emerald-400" />
            <span class="font-tajawal">دفتر اليومية والخزينة</span>
          </a>

          <a
            href="/reports"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-slate-200 hover:bg-slate-900 transition-all"
          >
            <BarChart3 class="w-4 h-4 text-purple-400" />
            <span class="font-tajawal">التقارير والأرباح</span>
          </a>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-3 border-t border-slate-800/80 text-center">
          <div class="text-[10px] text-slate-500 font-mono">Pure API SPA v1.0</div>
        </div>
      </aside>

      <!-- Main Content Stage -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-900/60">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import {
    Menu,
    LayoutDashboard,
    ShoppingCart,
    FileText,
    Package,
    Users,
    Factory,
    Wallet,
    BarChart3,
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
