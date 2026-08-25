<template>
  <aside
    class="hidden md:flex h-full max-h-full bg-white dark:bg-slate-950 border-l border-slate-200 dark:border-slate-800/80 flex-col shrink-0 font-tajawal select-none transition-all duration-300 shadow-xl z-30 overflow-hidden"
    :class="isCollapsed ? 'w-20' : 'w-80'"
  >
    <!-- 📌 1. HEADER: BRAND LOGO + COLLAPSE/EXPAND TOGGLE -->
    <div class="p-3.5 border-b border-slate-200 dark:border-slate-800/80 shrink-0 bg-slate-50/70 dark:bg-slate-900/70 backdrop-blur-md z-20">
      <!-- Expanded Mode Header -->
      <div v-if="!isCollapsed" class="flex items-center justify-between">
        <div class="flex items-center gap-3 overflow-hidden">
          <div
            class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-950 font-black text-xl shadow-lg shrink-0 transition-transform hover:scale-105"
            :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
          >
            <Coffee class="w-5 h-5 stroke-[2.5]" />
          </div>
          <div class="min-w-0">
            <h2 class="font-black text-sm text-slate-900 dark:text-white tracking-tight truncate">
              {{ appConfigStore.companyName || $t('dashboard.company_title') }}
            </h2>
            <p v-if="appConfigStore.companySubtitle" class="text-[10px] text-slate-500 dark:text-slate-400 font-bold truncate">
              {{ appConfigStore.companySubtitle }}
            </p>
          </div>
        </div>

        <!-- Toggle Button -->
        <button
          type="button"
          @click="toggleCollapse"
          class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition flex items-center justify-center cursor-pointer active:scale-95 shadow-2xs"
          :title="isCollapsed ? 'توسيع القائمة (Ctrl+B)' : 'تصغير القائمة (Ctrl+B)'"
        >
          <ChevronRight class="w-4 h-4 stroke-[2.5]" />
        </button>
      </div>

      <!-- Collapsed Mini Mode Header -->
      <div v-else class="flex flex-col items-center gap-2.5 py-1">
        <div
          class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-950 font-black text-lg shadow-lg shrink-0 transition-transform hover:scale-105"
          :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
        >
          <Coffee class="w-5 h-5 stroke-[2.5]" />
        </div>
        <button
          type="button"
          @click="toggleCollapse"
          class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition flex items-center justify-center cursor-pointer active:scale-95 shadow-2xs"
          title="توسيع القائمة (Ctrl+B)"
        >
          <ChevronLeft class="w-4 h-4 stroke-[2.5]" />
        </button>
      </div>
    </div>

    <!-- 📌 2. QUICK POS ACTION BUTTON (F2) -->
    <div class="px-3 pt-3 shrink-0" v-if="isModuleEnabled('pos_and_sales')">
      <div
        class="relative"
        @mouseenter="handleItemHover($event, '+ نقطة البيع السريعة (POS) - F2')"
        @mouseleave="handleItemLeave"
      >
        <router-link
          to="/pos"
          class="flex items-center justify-center gap-2.5 w-full py-3 rounded-2xl font-black text-xs text-slate-950 transition-all active:scale-95 cursor-pointer shadow-lg hover:brightness-105"
          :class="isCollapsed ? 'px-0' : 'px-4'"
          :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
        >
          <Plus class="w-4 h-4 stroke-[3] shrink-0" />
          <span v-if="!isCollapsed" class="truncate">+ نقطة البيع السريعة (POS)</span>
          <kbd v-if="!isCollapsed" class="font-mono text-[10px] bg-black/15 px-1.5 py-0.5 rounded-md text-slate-950 font-black">F2</kbd>
        </router-link>
      </div>
    </div>

    <!-- 📌 3. CATEGORY CARDS HUB & ACCORDION (Like Mobile ERP App) -->
    <div class="flex-1 overflow-y-auto min-h-0 p-3 space-y-2.5 custom-scrollbar">
      <!-- Section Label (Only in expanded mode) -->
      <div v-if="!isCollapsed" class="px-1 text-[11px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">
        أقسام وموديولات المنظومة
      </div>

      <template v-for="section in navigationSections" :key="section.key">
        <!-- 🌟 Direct Dashboard Card -->
        <div
          v-if="section.isDirect"
          class="relative"
          @mouseenter="handleItemHover($event, section.title)"
          @mouseleave="handleItemLeave"
        >
          <!-- Expanded Mode: Direct Card -->
          <router-link
            v-if="!isCollapsed"
            :to="section.directPath || '/'"
            class="p-3 rounded-2xl border transition-all active:scale-[0.98] cursor-pointer shadow-2xs flex items-center justify-between group"
            :class="isRouteActive(section.directPath)
              ? 'bg-theme-primary/10 border-theme-primary/40 text-theme-primary shadow-sm'
              : 'bg-slate-50 dark:bg-slate-900/80 border-slate-200 dark:border-slate-800/80 hover:border-theme-primary/50 text-slate-800 dark:text-slate-200'"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-2xs" :class="section.iconBg">
                <component :is="section.icon" class="w-4 h-4 stroke-[2.4]" />
              </div>
              <div class="min-w-0">
                <div class="text-xs font-black truncate group-hover:text-theme-primary transition">
                  {{ section.title }}
                </div>
                <div class="text-[10px] text-slate-400 font-bold truncate mt-0.5">
                  {{ section.subtitle || 'الرئيسية' }}
                </div>
              </div>
            </div>
            <ChevronLeft class="w-4 h-4 text-slate-400 group-hover:text-theme-primary transition shrink-0" />
          </router-link>

          <!-- Collapsed Mini Mode: Mini Icon Button -->
          <router-link
            v-else
            :to="section.directPath || '/'"
            class="w-12 h-12 mx-auto rounded-2xl flex items-center justify-center transition-all shadow-2xs"
            :class="isRouteActive(section.directPath)
              ? 'bg-theme-primary text-slate-950 shadow-md font-bold'
              : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-white hover:bg-slate-800'"
          >
            <component :is="section.icon" class="w-5 h-5 stroke-[2.2]" />
          </router-link>
        </div>

        <!-- 🌟 Expandable Category Accordion Card (Like Mobile ERP) -->
        <div v-else class="space-y-1.5">
          <!-- Expanded Mode: Accordion Hub Card -->
          <div v-if="!isCollapsed" class="rounded-2xl border transition-all overflow-hidden"
            :class="isSectionExpanded(section.key)
              ? 'border-theme-primary/30 bg-slate-50/50 dark:bg-slate-900/50 shadow-xs'
              : 'border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900/70 hover:border-slate-300 dark:hover:border-slate-700'"
          >
            <!-- Category Header Button -->
            <button
              type="button"
              @click="toggleSection(section.key)"
              class="w-full p-3 flex items-center justify-between transition-all cursor-pointer select-none text-start group"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-2xs" :class="section.iconBg">
                  <component :is="section.icon" class="w-4 h-4 stroke-[2.4]" />
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-black text-slate-900 dark:text-white group-hover:text-theme-primary transition truncate">
                    {{ section.title }}
                  </div>
                  <div class="text-[10px] text-slate-400 font-bold truncate mt-0.5">
                    {{ section.subtitle || `${section.items?.length || 0} روابط` }}
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <span class="px-2 py-0.5 rounded-lg bg-slate-200/70 dark:bg-slate-800 text-[10px] font-mono text-slate-600 dark:text-slate-400 font-bold">
                  {{ section.items?.length || 0 }} روابط
                </span>
                <ChevronDown
                  class="w-4 h-4 text-slate-400 group-hover:text-theme-primary transition-transform duration-200"
                  :class="{ 'rotate-180 text-theme-primary': isSectionExpanded(section.key) }"
                />
              </div>
            </button>

            <!-- Sub-items List (Expandable Accordion with Slide Transition) -->
            <Transition name="accordion">
              <div v-if="isSectionExpanded(section.key)" class="px-2.5 pb-2.5 pt-1 space-y-1 border-t border-slate-100 dark:border-slate-800/60">
                <router-link
                  v-for="item in section.items"
                  :key="'desktop-sub-' + item.key"
                  :to="item.path"
                  class="group flex items-center justify-between p-2.5 rounded-xl text-xs font-bold transition-all relative overflow-hidden"
                  :class="isItemActive(item)
                    ? 'font-black text-theme-primary bg-theme-primary/10 border border-theme-primary/30 shadow-xs'
                    : 'text-slate-700 dark:text-slate-300 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/80'"
                >
                  <!-- Active Accent Bar -->
                  <span
                    v-if="isItemActive(item)"
                    class="absolute right-0 top-1 bottom-1 w-1 rounded-l-full"
                    :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
                  ></span>

                  <div class="flex items-center gap-2.5 min-w-0">
                    <div
                      class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                      :class="isItemActive(item) ? 'bg-theme-primary/20 text-theme-primary' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 group-hover:text-theme-primary'"
                    >
                      <component :is="item.icon" class="w-3.5 h-3.5 stroke-[2.2]" />
                    </div>
                    <span class="truncate">{{ item.title }}</span>
                  </div>

                  <ChevronLeft class="w-3.5 h-3.5 opacity-40 group-hover:opacity-100 group-hover:text-theme-primary transition shrink-0" />
                </router-link>
              </div>
            </Transition>
          </div>

          <!-- Collapsed Mini Mode: Squircle with Popover Tooltip -->
          <div
            v-else
            class="relative"
            @mouseenter="handleItemHover($event, section.title)"
            @mouseleave="handleItemLeave"
          >
            <button
              type="button"
              @click="toggleSection(section.key)"
              class="w-12 h-12 mx-auto rounded-2xl flex items-center justify-center transition-all shadow-2xs cursor-pointer"
              :class="isSectionHasActiveChild(section)
                ? 'bg-theme-primary text-slate-950 shadow-md font-bold'
                : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-white hover:bg-slate-800'"
            >
              <component :is="section.icon" class="w-5 h-5 stroke-[2.2]" />
            </button>
          </div>
        </div>
      </template>

      <!-- 👑 Super Admin Card Entry -->
      <div
        v-if="canAccessSuperAdmin"
        class="pt-2 border-t border-purple-500/20"
        @mouseenter="handleItemHover($event, 'لوحة السوبر أدمن 👑')"
        @mouseleave="handleItemLeave"
      >
        <router-link
          v-if="!isCollapsed"
          to="/super-admin/dashboard"
          class="p-3 rounded-2xl bg-purple-500/10 border border-purple-500/20 hover:border-purple-500 flex items-center justify-between transition-all active:scale-[0.98] cursor-pointer shadow-2xs group"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center shrink-0 text-base shadow-2xs">
              👑
            </div>
            <div class="min-w-0">
              <div class="text-xs font-black text-purple-400 truncate">لوحة السوبر أدمن</div>
              <div class="text-[10px] text-slate-400 font-bold truncate mt-0.5">إدارة المستأجرين والباقات</div>
            </div>
          </div>
          <ChevronLeft class="w-4 h-4 text-purple-400 shrink-0" />
        </router-link>

        <router-link
          v-else
          to="/super-admin/dashboard"
          class="w-12 h-12 mx-auto rounded-2xl bg-purple-500/20 text-purple-400 flex items-center justify-center text-lg shadow-2xs hover:bg-purple-500/30 transition"
        >
          <ShieldCheck class="w-6 h-6 stroke-[2.2]" />
        </router-link>
      </div>
    </div>

    <!-- 📌 4. BOTTOM FOOTER: SHIFT STATUS & APP VERSION -->
    <div class="p-3 border-t border-slate-200 dark:border-slate-800/80 shrink-0 bg-slate-50/70 dark:bg-slate-900/70 backdrop-blur-md">
      <!-- Expanded Footer -->
      <div v-if="!isCollapsed" class="space-y-2">
        <!-- Live Shift Status Pill -->
        <router-link
          to="/daily-journal"
          class="p-2.5 rounded-xl border flex items-center justify-between transition cursor-pointer text-xs shadow-2xs"
          :class="appConfigStore.hasOpenShift
            ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
            : 'bg-rose-500/10 border-rose-500/20 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'"
        >
          <div class="flex items-center gap-2 min-w-0">
            <span class="w-2.5 h-2.5 rounded-full" :class="appConfigStore.hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500 animate-ping'"></span>
            <span class="font-bold truncate">{{ appConfigStore.hasOpenShift ? `الوردية (#${appConfigStore.currentShiftNumber})` : 'الوردية مغلقة' }}</span>
          </div>
          <span class="font-mono text-[10px] font-black">{{ appConfigStore.hasOpenShift ? 'مفتوحة' : 'فتح ←' }}</span>
        </router-link>

        <!-- Version & Update Button -->
        <div class="flex items-center justify-between text-[11px] font-mono text-slate-400 px-1 pt-1">
          <div class="flex items-center gap-1.5">
            <span class="font-tajawal text-slate-500 dark:text-slate-400">الإصدار</span>
            <span class="px-1.5 py-0.5 rounded-md bg-theme-primary/10 text-theme-primary font-bold">v{{ currentVersionName }}</span>
          </div>
          <button
            type="button"
            @click="checkForUpdates(true)"
            class="px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-sans font-bold text-[10px] border border-slate-200 dark:border-slate-700 transition cursor-pointer active:scale-95 shadow-2xs flex items-center gap-1"
          >
            <Sparkles class="w-3 h-3 text-theme-primary" />
            <span>تحديث</span>
          </button>
        </div>
      </div>

      <!-- Collapsed Mini Mode Footer -->
      <div v-else class="flex flex-col items-center gap-2">
        <router-link
          to="/daily-journal"
          class="w-10 h-10 rounded-xl flex items-center justify-center border transition shadow-2xs"
          :class="appConfigStore.hasOpenShift ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-500'"
          :title="appConfigStore.hasOpenShift ? 'الوردية مفتوحة' : 'الوردية مغلقة'"
        >
          <span class="w-2.5 h-2.5 rounded-full" :class="appConfigStore.hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
        </router-link>

        <button
          type="button"
          @click="checkForUpdates(true)"
          class="text-[9px] font-mono font-bold text-theme-primary opacity-80 hover:opacity-100 transition cursor-pointer"
          :title="'v' + currentVersionName + ' (انقر للفحص)'"
        >
          v{{ currentVersionName }}
        </button>
      </div>
    </div>
  </aside>

  <!-- 🌟 Teleported Tooltip for Mini Mode -->
  <Teleport to="body">
    <Transition name="tooltip-fade">
      <div
        v-if="hoveredTooltip.show && isCollapsed"
        class="fixed z-[999999] pointer-events-none -translate-y-1/2 px-3 py-2 bg-slate-950 text-white text-xs font-black rounded-xl shadow-2xl border border-slate-700 flex items-center gap-2 font-tajawal whitespace-nowrap"
        :style="{ top: `${hoveredTooltip.top}px`, right: `${hoveredTooltip.right}px` }"
      >
        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"></span>
        <span>{{ hoveredTooltip.text }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useNavigation } from '../../Composables/useNavigation';
import { useModules } from '../../Composables/useModules';
import { useAppConfigStore } from '../../stores/appConfig';
import { useAuthStore } from '../../stores/auth';
import { useAppUpdate } from '../../Composables/useAppUpdate';
import versionData from '../../version.json';
import {
  ChevronRight,
  ChevronLeft,
  ChevronDown,
  Plus,
  Coffee,
  ShieldCheck,
  Sparkles
} from 'lucide-vue-next';

const props = defineProps({
  isCollapsed: { type: Boolean, default: false }
});

const emit = defineEmits(['toggle-collapse']);

const route = useRoute();
const appConfigStore = useAppConfigStore();
const authStore = useAuthStore();
const { navigationSections } = useNavigation();
const { isModuleEnabled } = useModules();
const { checkForUpdates } = useAppUpdate();

const currentVersionName = ref(versionData?.version || '1.0.10');
const canAccessSuperAdmin = computed(() => authStore.can('view_telescope') || authStore.roles?.includes('Super Admin'));

// 📂 Accordion state for categories
const expandedSections = ref({
  sales: true,
  inventory: false,
  purchases: false,
  reports: false,
  settings: false
});

// Auto-expand the category that contains the active route
const autoExpandActiveCategory = () => {
  for (const section of navigationSections.value || []) {
    if (section.items?.some(item => isItemActive(item))) {
      expandedSections.value[section.key] = true;
    }
  }
};

watch(() => route.path, () => {
  autoExpandActiveCategory();
}, { immediate: true });

const isSectionExpanded = (key) => {
  return expandedSections.value[key] ?? false;
};

const toggleSection = (key) => {
  expandedSections.value[key] = !expandedSections.value[key];
};

const isSectionHasActiveChild = (section) => {
  return section.items?.some(item => isItemActive(item));
};

const toggleCollapse = () => {
  emit('toggle-collapse');
};

const isRouteActive = (path) => {
  if (path === '/') return route.path === '/';
  return route.path.startsWith(path);
};

const isItemActive = (item) => {
  if (item.exact) return route.path === item.path;
  return route.path.startsWith(item.path);
};

// 🌟 Mini Tooltip Tracking
const hoveredTooltip = ref({ show: false, text: '', top: 0, right: 0 });

const handleItemHover = (event, text) => {
  if (!props.isCollapsed) return;
  const rect = event.currentTarget.getBoundingClientRect();
  hoveredTooltip.value = {
    show: true,
    text,
    top: rect.top + rect.height / 2,
    right: window.innerWidth - rect.left + 12,
  };
};

const handleItemLeave = () => {
  hoveredTooltip.value.show = false;
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
  border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.4);
}
.accordion-enter-active,
.accordion-leave-active {
  transition: all 0.2s ease-in-out;
  max-height: 400px;
  opacity: 1;
}
.accordion-enter-from,
.accordion-leave-to {
  max-height: 0;
  opacity: 0;
  overflow: hidden;
}
.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
  opacity: 0;
  transform: translateY(-50%) scale(0.95);
}
</style>
