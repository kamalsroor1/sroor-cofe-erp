<template>
  <!-- Fixed Mobile & Tablet Bottom Navigation Bar (Visible on mobile screens < md) -->
  <nav
    aria-label="Mobile Bottom Navigation"
    class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl border-t border-slate-200 dark:border-slate-800/90 px-2 pt-1.5 pb-[max(0.6rem,env(safe-area-inset-bottom,0.6rem))] flex items-center justify-around font-tajawal shadow-2xl select-none"
  >
    <!-- Tenant cashier & store ERP bottom nav (the platform console has its own layout) -->
    <!-- 1. Home / Dashboard -->
    <router-link
      to="/"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="
        isDashboardActive
          ? 'text-theme-primary font-black'
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
      "
    >
      <div class="relative flex items-center justify-center">
        <span v-if="isDashboardActive" class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse" />
        <LayoutDashboard
          class="w-5 h-5 mb-0.5 transition-transform duration-200"
          :class="isDashboardActive ? 'scale-110' : 'group-hover:scale-105'"
        />
      </div>
      <span class="text-[10px] tracking-tight truncate">{{ $t('nav.dashboard_short') }}</span>
    </router-link>

    <!-- 2. Invoices / Sales (Module 2) -->
    <router-link
      v-if="isModuleEnabled('pos_and_sales')"
      to="/invoices"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="
        isInvoicesActive
          ? 'text-theme-primary font-black'
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
      "
    >
      <div class="relative flex items-center justify-center">
        <span v-if="isInvoicesActive" class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse" />
        <FileText
          class="w-5 h-5 mb-0.5 transition-transform duration-200"
          :class="isInvoicesActive ? 'scale-110' : 'group-hover:scale-105'"
        />
      </div>
      <span class="text-[10px] tracking-tight truncate">{{ $t('nav.invoices_short') }}</span>
    </router-link>

    <!-- 3. Primary Center Action: Raised Fast POS Button (Module 2) -->
    <div v-if="isModuleEnabled('pos_and_sales')" class="flex-1 flex items-center justify-center">
      <router-link
        to="/pos"
        class="relative -top-4 w-12 h-12 rounded-2xl bg-theme-gradient flex items-center justify-center shadow-lg shadow-theme-primary transition-all duration-200 active:scale-90 cursor-pointer ring-4 ring-white dark:ring-slate-900 group"
        :class="isPosActive ? 'scale-110 ring-theme-primary' : ''"
        :title="$t('nav.pos_fast')"
      >
        <ShoppingCart class="w-6 h-6 text-white fill-current transition-transform group-hover:rotate-12 duration-300" />
      </router-link>
    </div>

    <!-- 4. Items & Inventory (Module 3) -->
    <router-link
      v-if="isModuleEnabled('inventory_and_stores')"
      to="/items"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="
        isItemsActive
          ? 'text-theme-primary font-black'
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
      "
    >
      <div class="relative flex items-center justify-center">
        <span v-if="isItemsActive" class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse" />
        <Package
          class="w-5 h-5 mb-0.5 transition-transform duration-200"
          :class="isItemsActive ? 'scale-110' : 'group-hover:scale-105'"
        />
      </div>
      <span class="text-[10px] tracking-tight truncate">{{ $t('nav.items_short') }}</span>
    </router-link>

    <!-- 5. Customers & CRM (Module 6) -->
    <router-link
      v-if="isModuleEnabled('customers')"
      to="/customers"
      class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
      :class="
        isCustomersActive
          ? 'text-theme-primary font-black'
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
      "
    >
      <div class="relative flex items-center justify-center">
        <span v-if="isCustomersActive" class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse" />
        <Users
          class="w-5 h-5 mb-0.5 transition-transform duration-200"
          :class="isCustomersActive ? 'scale-110' : 'group-hover:scale-105'"
        />
      </div>
      <span class="text-[10px] tracking-tight truncate">{{ $t('nav.customers') }}</span>
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
      <span class="text-[10px] tracking-tight truncate">{{ $t('nav.more_short') }}</span>
    </button>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useModules } from '../../Composables/useModules';
import { LayoutDashboard, FileText, ShoppingCart, Package, Users, Menu } from 'lucide-vue-next';

defineEmits(['open-drawer']);

const route = useRoute();
const { isModuleEnabled } = useModules();

const currentPath = computed(() => route.path || '');

// Tenant Active States
const isDashboardActive = computed(() => currentPath.value === '/' || currentPath.value === '/dashboard');
const isInvoicesActive = computed(() => currentPath.value.startsWith('/invoices'));
const isPosActive = computed(() => currentPath.value.startsWith('/pos'));
const isItemsActive = computed(() => currentPath.value.startsWith('/items'));
const isCustomersActive = computed(() => currentPath.value.startsWith('/customers'));
</script>
