<template>
  <!-- Fixed Mobile & Tablet Bottom Navigation Bar (Visible on mobile screens < md) -->
  <nav
    aria-label="Mobile Bottom Navigation"
    class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl border-t border-slate-200 dark:border-slate-800/90 px-2 pt-1.5 pb-[max(0.6rem,env(safe-area-inset-bottom,0.6rem))] flex items-center justify-around font-tajawal shadow-2xl select-none"
  >
    <!-- 👑 MODE 1: SUPER ADMIN CENTRAL PLATFORM BOTTOM NAV -->
    <template v-if="isSuperAdminPanel">
      <!-- 1. Super Admin Dashboard -->
      <router-link
        to="/super-admin/dashboard"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
        :class="isSuperDashboardActive ? 'text-purple-400 font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200'"
      >
        <div class="relative flex items-center justify-center">
          <span
            v-if="isSuperDashboardActive"
            class="absolute -top-1 w-6 h-0.5 rounded-full bg-purple-400 animate-pulse"
          />
          <Crown class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isSuperDashboardActive ? 'scale-110' : 'group-hover:scale-105'" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('super.dashboard') }}</span>
      </router-link>

      <!-- 2. Tenants -->
      <router-link
        to="/super-admin/tenants"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
        :class="isTenantsActive ? 'text-purple-400 font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200'"
      >
        <div class="relative flex items-center justify-center">
          <span
            v-if="isTenantsActive"
            class="absolute -top-1 w-6 h-0.5 rounded-full bg-purple-400 animate-pulse"
          />
          <Building2 class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isTenantsActive ? 'scale-110' : 'group-hover:scale-105'" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('super.tenants') }}</span>
      </router-link>

      <!-- 3. Primary Center Action: Raised Plans/Subscriptions Button -->
      <div class="flex-1 flex items-center justify-center">
        <router-link
          to="/super-admin/plans"
          class="relative -top-4 w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 via-indigo-600 to-purple-400 flex items-center justify-center shadow-lg shadow-purple-600/40 transition-all duration-200 active:scale-90 cursor-pointer ring-4 ring-white dark:ring-slate-900 group"
          :class="isPlansActive ? 'scale-110 ring-purple-400/50' : ''"
          :title="$t('super.plans')"
        >
          <Layers class="w-6 h-6 text-white transition-transform group-hover:rotate-12 duration-300" />
        </router-link>
      </div>

      <!-- 4. App Releases (APK) -->
      <router-link
        to="/super-admin/app-versions"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
        :class="isAppVersionsActive ? 'text-purple-400 font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200'"
      >
        <div class="relative flex items-center justify-center">
          <span
            v-if="isAppVersionsActive"
            class="absolute -top-1 w-6 h-0.5 rounded-full bg-purple-400 animate-pulse"
          />
          <Rocket class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isAppVersionsActive ? 'scale-110' : 'group-hover:scale-105'" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('super.app_versions') }}</span>
      </router-link>

      <!-- 5. More / Drawer Menu -->
      <button
        @click="$emit('open-drawer')"
        type="button"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 active:scale-90 text-slate-400 hover:text-slate-900 dark:text-slate-200 cursor-pointer group"
        :title="$t('nav.more_menu')"
      >
        <div class="relative flex items-center justify-center">
          <Menu class="w-5 h-5 mb-0.5 transition-transform duration-200 group-hover:scale-105" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('nav.more_short') || 'المزيد' }}</span>
      </button>
    </template>

    <!-- 🛒 MODE 2: TENANT CASHIER & STORE ERP BOTTOM NAV -->
    <template v-else>
      <!-- 1. Home / Dashboard -->
      <router-link
        to="/"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
        :class="isDashboardActive ? 'text-theme-primary font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200'"
      >
        <div class="relative flex items-center justify-center">
          <span
            v-if="isDashboardActive"
            class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
          />
          <LayoutDashboard class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isDashboardActive ? 'scale-110' : 'group-hover:scale-105'" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('nav.dashboard_short') || 'الرئيسية' }}</span>
      </router-link>

      <!-- 2. Invoices / Sales -->
      <router-link
        to="/invoices"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
        :class="isInvoicesActive ? 'text-theme-primary font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200'"
      >
        <div class="relative flex items-center justify-center">
          <span
            v-if="isInvoicesActive"
            class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
          />
          <FileText class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isInvoicesActive ? 'scale-110' : 'group-hover:scale-105'" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('nav.invoices_short') || 'الفواتير' }}</span>
      </router-link>

      <!-- 3. Primary Center Action: Raised Fast POS Button -->
      <div class="flex-1 flex items-center justify-center">
        <router-link
          to="/pos"
          class="relative -top-4 w-12 h-12 rounded-2xl bg-theme-gradient flex items-center justify-center shadow-lg shadow-theme-primary transition-all duration-200 active:scale-90 cursor-pointer ring-4 ring-white dark:ring-slate-900 group"
          :class="isPosActive ? 'scale-110 ring-theme-primary' : ''"
          :title="$t('nav.pos_fast')"
        >
          <ShoppingCart class="w-6 h-6 text-white fill-current transition-transform group-hover:rotate-12 duration-300" />
        </router-link>
      </div>

      <!-- 4. Items & Inventory -->
      <router-link
        to="/items"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
        :class="isItemsActive ? 'text-theme-primary font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200'"
      >
        <div class="relative flex items-center justify-center">
          <span
            v-if="isItemsActive"
            class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
          />
          <Package class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isItemsActive ? 'scale-110' : 'group-hover:scale-105'" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('nav.items_short') || 'الأصناف' }}</span>
      </router-link>

      <!-- 5. Shift & Cash Drawer -->
      <router-link
        to="/daily-journal"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
        :class="isShiftActive ? 'text-theme-primary font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200'"
      >
        <div class="relative flex items-center justify-center">
          <span
            v-if="isShiftActive"
            class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
          />
          <Wallet class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isShiftActive ? 'scale-110' : 'group-hover:scale-105'" />
          <!-- Open Shift Indicator Dot -->
          <span
            v-if="hasOpenShift"
            class="absolute -top-0.5 -right-1 w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-slate-950 animate-pulse"
          />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('nav.treasury_short') || 'الخزينة' }}</span>
      </router-link>

      <!-- 6. More / Drawer Menu -->
      <button
        @click="$emit('open-drawer')"
        type="button"
        class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 active:scale-90 text-slate-400 hover:text-slate-900 dark:text-slate-200 cursor-pointer group"
        :title="$t('nav.more_menu')"
      >
        <div class="relative flex items-center justify-center">
          <Menu class="w-5 h-5 mb-0.5 transition-transform duration-200 group-hover:scale-105" />
        </div>
        <span class="text-[10px] tracking-tight truncate">{{ $t('nav.more_short') || 'المزيد' }}</span>
      </button>
    </template>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useAppConfigStore } from '../../stores/appConfig';
import {
  LayoutDashboard,
  FileText,
  ShoppingCart,
  Package,
  Wallet,
  Menu,
  Crown,
  Building2,
  Layers,
  Rocket
} from 'lucide-vue-next';

defineEmits(['open-drawer']);

const route = useRoute();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();

const isSuperAdminPanel = computed(() => {
  return route.path.startsWith('/super-admin') || route.name?.startsWith('super_admin') || (authStore.isSuperAdmin && !appConfigStore.tenant);
});

const currentPath = computed(() => route.path || '');

// Super Admin Active States
const isSuperDashboardActive = computed(() => currentPath.value.startsWith('/super-admin/dashboard'));
const isTenantsActive = computed(() => currentPath.value.startsWith('/super-admin/tenants'));
const isPlansActive = computed(() => currentPath.value.startsWith('/super-admin/plans'));
const isAppVersionsActive = computed(() => currentPath.value.startsWith('/super-admin/app-versions'));

// Tenant Active States
const isDashboardActive = computed(() => currentPath.value === '/' || currentPath.value === '/dashboard');
const isInvoicesActive = computed(() => currentPath.value.startsWith('/invoices'));
const isPosActive = computed(() => currentPath.value.startsWith('/pos'));
const isItemsActive = computed(() => currentPath.value.startsWith('/items'));
const isShiftActive = computed(() => currentPath.value.startsWith('/daily-journal') || currentPath.value.startsWith('/shifts'));
const hasOpenShift = computed(() => appConfigStore.hasOpenShift);
</script>
