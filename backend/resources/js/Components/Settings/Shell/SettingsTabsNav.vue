<template>
  <nav class="font-tajawal">
    <!-- 📱 Mobile Hub View (Cards Grid shown when no tab is selected) -->
    <div v-if="isMobile && !selectedTab" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
      <button
        v-for="tab in visibleTabs"
        :key="tab.id"
        type="button"
        @click="$emit('select-tab', tab.id)"
        class="w-full text-start p-4 sm:p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary/40 dark:hover:border-theme-primary/40 shadow-xs hover:shadow-md transition-all flex items-start gap-4 active:scale-98 cursor-pointer select-none group min-h-[44px]"
      >
        <div
          class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105"
          :class="tab.iconBg"
        >
          <component :is="tab.icon" class="w-6 h-6" :class="tab.iconColor" />
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm sm:text-base font-black text-slate-900 dark:text-white truncate">
              {{ tab.label }}
            </h2>
            <span
              v-if="tab.badge"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
            >
              {{ tab.badge }}
            </span>
          </div>

          <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mt-1 leading-relaxed">
            {{ tab.subtitle }}
          </p>
        </div>
      </button>
    </div>

    <!-- 💻 Desktop Sidebar Menu (Vertical Tab List) -->
    <div
      v-else
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3 sm:p-4 shadow-xs space-y-1.5"
    >
      <button
        v-for="tab in visibleTabs"
        :key="tab.id"
        type="button"
        @click="$emit('select-tab', tab.id)"
        class="w-full text-start p-3 sm:p-3.5 rounded-2xl transition-all flex items-center gap-3.5 cursor-pointer select-none min-h-[44px] active:scale-98"
        :class="
          selectedTab === tab.id
            ? 'bg-theme-light dark:bg-slate-800 text-slate-900 dark:text-white font-black shadow-xs ring-1 ring-theme-primary/30'
            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white font-medium'
        "
      >
        <div
          class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 transition-all"
          :class="selectedTab === tab.id ? 'bg-theme-primary text-white shadow-md shadow-theme-primary/20' : tab.iconBg"
        >
          <component
            :is="tab.icon"
            class="w-5 h-5 transition-colors"
            :class="selectedTab === tab.id ? 'text-white' : tab.iconColor"
          />
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-1.5">
            <span class="text-xs sm:text-sm font-bold truncate">
              {{ tab.label }}
            </span>
            <span
              v-if="tab.badge"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0"
              :class="
                selectedTab === tab.id
                  ? 'bg-white/80 dark:bg-slate-700 text-slate-800 dark:text-slate-200'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
              "
            >
              {{ tab.badge }}
            </span>
          </div>

          <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5">
            {{ tab.subtitle }}
          </p>
        </div>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { Building2, Palette, Printer, Package, Bot, Laptop } from 'lucide-vue-next';
import { useTrans } from '../../../Composables/useTrans';

defineProps({
  selectedTab: {
    type: String,
    default: 'general',
  },
  isMobile: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['select-tab']);

const { t } = useTrans();

// Only tabs that have real, backend-supported settings today (Catalog §4 / Lane A)
const visibleTabs = computed(() => [
  {
    id: 'general',
    label: t('settings.tab_general'),
    subtitle: t('settings.tab_general_sub'),
    icon: Building2,
    iconBg: 'bg-theme-light border border-theme-border',
    iconColor: 'text-theme-primary',
    badge: t('settings.sec_branding_badge'),
  },
  {
    id: 'branding',
    label: t('settings.tab_branding'),
    subtitle: t('settings.branding_section_sub'),
    icon: Palette,
    iconBg: 'bg-amber-500/10 border border-amber-500/20',
    iconColor: 'text-amber-500 dark:text-amber-400',
    badge: t('settings.sec_branding_badge'),
  },
  {
    id: 'printing',
    label: t('settings.sec_printing_label'),
    subtitle: t('settings.tab_printing_sub'),
    icon: Printer,
    iconBg: 'bg-blue-500/10 border border-blue-500/20',
    iconColor: 'text-blue-500 dark:text-blue-400',
    badge: t('settings.sec_printing_badge'),
  },
  {
    id: 'inventory',
    label: t('settings.tab_inventory'),
    subtitle: t('settings.tab_inventory_sub'),
    icon: Package,
    iconBg: 'bg-emerald-500/10 border border-emerald-500/20',
    iconColor: 'text-emerald-500 dark:text-emerald-400',
    badge: t('settings.sec_units_badge'),
  },
  {
    id: 'notifications',
    label: t('settings.tab_notifications'),
    subtitle: t('settings.tab_notifications_sub'),
    icon: Bot,
    iconBg: 'bg-cyan-500/10 border border-cyan-500/20',
    iconColor: 'text-cyan-500 dark:text-cyan-400',
    badge: t('settings.sec_telegram_badge'),
  },
  {
    id: 'device',
    label: t('settings.tab_device'),
    subtitle: t('settings.tab_device_sub'),
    icon: Laptop,
    iconBg: 'bg-purple-500/10 border border-purple-500/20',
    iconColor: 'text-purple-500 dark:text-purple-400',
    badge: t('settings.tab_device_badge'),
  },
]);
</script>
