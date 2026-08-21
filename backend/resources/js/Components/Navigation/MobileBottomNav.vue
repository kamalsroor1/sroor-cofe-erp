<template>
  <!-- Fixed Mobile & Tablet Bottom Navigation Bar (Visible on mobile screens < md) -->
  <nav
    aria-label="Mobile Bottom Navigation"
    class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-slate-950/95 backdrop-blur-2xl border-t border-slate-800/90 px-2 pt-1.5 pb-[max(0.6rem,env(safe-area-inset-bottom,0.6rem))] flex items-center justify-around font-tajawal shadow-2xl select-none"
  >
    <!-- 1. Home / Dashboard -->
    <router-link
      to="/"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="isDashboardActive ? 'text-amber-400 font-black' : 'text-slate-400 hover:text-slate-200'"
    >
      <div class="relative flex items-center justify-center">
        <span
          v-if="isDashboardActive"
          class="absolute -top-1 w-6 h-0.5 rounded-full bg-amber-400 animate-pulse"
        />
        <LayoutDashboard class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isDashboardActive ? 'scale-110' : 'group-hover:scale-105'" />
      </div>
      <span class="text-[10px] tracking-tight truncate">الرئيسية</span>
    </router-link>

    <!-- 2. Invoices / Sales -->
    <router-link
      to="/invoices"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="isInvoicesActive ? 'text-amber-400 font-black' : 'text-slate-400 hover:text-slate-200'"
    >
      <div class="relative flex items-center justify-center">
        <span
          v-if="isInvoicesActive"
          class="absolute -top-1 w-6 h-0.5 rounded-full bg-amber-400 animate-pulse"
        />
        <FileText class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isInvoicesActive ? 'scale-110' : 'group-hover:scale-105'" />
      </div>
      <span class="text-[10px] tracking-tight truncate">الفواتير</span>
    </router-link>

    <!-- 3. Primary Center Action: Raised Fast POS Button -->
    <div class="flex-1 flex items-center justify-center">
      <router-link
        to="/pos"
        class="relative -top-4 w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 via-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/30 transition-all duration-200 active:scale-90 cursor-pointer ring-4 ring-slate-950 group"
        :class="isPosActive ? 'scale-110 ring-amber-400/50' : ''"
        title="نقطة البيع السريعة (POS)"
      >
        <ShoppingCart class="w-6 h-6 text-slate-950 fill-current transition-transform group-hover:rotate-12 duration-300" />
      </router-link>
    </div>

    <!-- 4. Items & Inventory -->
    <router-link
      to="/items"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="isItemsActive ? 'text-amber-400 font-black' : 'text-slate-400 hover:text-slate-200'"
    >
      <div class="relative flex items-center justify-center">
        <span
          v-if="isItemsActive"
          class="absolute -top-1 w-6 h-0.5 rounded-full bg-amber-400 animate-pulse"
        />
        <Package class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isItemsActive ? 'scale-110' : 'group-hover:scale-105'" />
      </div>
      <span class="text-[10px] tracking-tight truncate">الأصناف</span>
    </router-link>

    <!-- 5. Shift & Cash Drawer -->
    <router-link
      to="/daily-journal"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="isShiftActive ? 'text-amber-400 font-black' : 'text-slate-400 hover:text-slate-200'"
    >
      <div class="relative flex items-center justify-center">
        <span
          v-if="isShiftActive"
          class="absolute -top-1 w-6 h-0.5 rounded-full bg-amber-400 animate-pulse"
        />
        <Wallet class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isShiftActive ? 'scale-110' : 'group-hover:scale-105'" />
        <!-- Open Shift Indicator Dot -->
        <span
          v-if="hasOpenShift"
          class="absolute -top-0.5 -right-1 w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-slate-950 animate-pulse"
        />
      </div>
      <span class="text-[10px] tracking-tight truncate">الخزينة</span>
    </router-link>

    <!-- 6. More / Drawer Menu -->
    <button
      @click="$emit('open-drawer')"
      type="button"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 active:scale-90 text-slate-400 hover:text-slate-200 cursor-pointer group"
      title="المزيد من القوائم"
    >
      <div class="relative flex items-center justify-center">
        <Menu class="w-5 h-5 mb-0.5 transition-transform duration-200 group-hover:scale-105" />
      </div>
      <span class="text-[10px] tracking-tight truncate">المزيد</span>
    </button>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAppConfigStore } from '../../stores/appConfig';
import {
  LayoutDashboard,
  FileText,
  ShoppingCart,
  Package,
  Wallet,
  Menu,
} from 'lucide-vue-next';

defineEmits(['open-drawer']);

const route = useRoute();
const appConfigStore = useAppConfigStore();

const currentPath = computed(() => route.path || '');

const isDashboardActive = computed(() => currentPath.value === '/' || currentPath.value === '/dashboard');
const isInvoicesActive = computed(() => currentPath.value.startsWith('/invoices'));
const isPosActive = computed(() => currentPath.value.startsWith('/pos'));
const isItemsActive = computed(() => currentPath.value.startsWith('/items'));
const isShiftActive = computed(() => currentPath.value.startsWith('/daily-journal') || currentPath.value.startsWith('/shifts'));
const hasOpenShift = computed(() => appConfigStore.hasOpenShift);
</script>
