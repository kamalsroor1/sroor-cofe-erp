<script setup>
import { ref, computed } from 'vue';
import { Store as StoreIcon, ChevronDown, Check } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import { useAppConfigStore } from '../../stores/appConfig';

const props = defineProps({
  // inline: desktop header | chip: compact header chip with dropdown | drawer: stacked block for the mobile drawer | titlebar: compact frameless titlebar
  variant: {
    type: String,
    default: 'inline',
    validator: (v) => ['inline', 'chip', 'drawer', 'titlebar'].includes(v),
  },
});
const emit = defineEmits(['navigate']);

const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();

const isOpen = ref(false);
const isSwitching = ref(false);

const hasMultipleStores = computed(() => (authStore.stores?.length ?? 0) > 1);
const hasOpenShift = computed(() => appConfigStore.hasOpenShift);
const isChip = computed(() => props.variant === 'chip');
const isDrawer = computed(() => props.variant === 'drawer');
const isTitlebar = computed(() => props.variant === 'titlebar');

const switchTo = async (storeId) => {
  const store = authStore.stores?.find((s) => String(s.id) === String(storeId));
  if (!store || String(store.id) === String(authStore.currentStore?.id)) {
    isOpen.value = false;
    return;
  }
  isSwitching.value = true;
  authStore.switchStore(store);
  // Never show the previous branch's journal while the new one loads.
  appConfigStore.activeShift = null;
  try {
    await appConfigStore.fetchBootstrapContext();
  } catch {
    // api interceptor already surfaced the error
  } finally {
    isSwitching.value = false;
    isOpen.value = false;
    emit('navigate');
  }
};

const onShiftLinkClick = () => {
  isOpen.value = false;
  emit('navigate');
};

const shiftBadgeClass = computed(() =>
  hasOpenShift.value
    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
    : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'
);
</script>

<template>
  <!-- 1. Compact chip (sm & md tablet header) with dropdown -->
  <div v-if="isChip" class="relative">
    <button
      type="button"
      class="min-h-[38px] sm:min-h-[40px] max-w-[130px] sm:max-w-[170px] px-2.5 sm:px-3 flex items-center gap-1.5 sm:gap-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-xl sm:rounded-2xl text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer font-tajawal transition active:scale-95 shadow-2xs"
      :aria-label="$t('nav.layout.store_and_shift')"
      :aria-expanded="isOpen"
      @click="isOpen = !isOpen"
    >
      <StoreIcon class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-theme-primary shrink-0" />
      <span class="truncate">{{ authStore.activeStoreName || $t('common.main_store_default') }}</span>
      <span
        class="w-2 h-2 rounded-full shrink-0"
        :class="hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"
      ></span>
      <ChevronDown
        class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-slate-400 transition-transform duration-200 shrink-0"
        :class="{ 'rotate-180': isOpen }"
      />
    </button>

    <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>
    <div
      v-if="isOpen"
      class="absolute end-0 top-full mt-2 w-64 max-w-[calc(100vw-2rem)] z-50 p-2 space-y-1 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl font-tajawal"
    >
      <template v-if="hasMultipleStores">
        <p class="px-2 pt-1 text-[10px] font-black text-slate-400 dark:text-slate-500">{{ $t('nav.switch_store') }}</p>
        <button
          v-for="s in authStore.stores"
          :key="s.id"
          type="button"
          :disabled="isSwitching"
          class="w-full min-h-[44px] px-3 flex items-center justify-between gap-2 rounded-xl text-sm font-bold text-start text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800/80 disabled:opacity-60 cursor-pointer transition"
          @click="switchTo(s.id)"
        >
          <span class="truncate">{{ s.name }}</span>
          <Check
            v-if="String(s.id) === String(authStore.currentStore?.id)"
            class="w-4 h-4 shrink-0 text-theme-primary"
          />
        </button>
        <div class="border-t border-slate-200 dark:border-slate-800 my-1"></div>
      </template>
      <router-link
        to="/daily-journal"
        class="min-h-[44px] px-3 rounded-xl text-sm font-bold flex items-center gap-2 border transition cursor-pointer"
        :class="shiftBadgeClass"
        @click="onShiftLinkClick"
      >
        <span class="w-2 h-2 rounded-full" :class="hasOpenShift ? 'bg-emerald-500' : 'bg-rose-500'"></span>
        <span>{{
          hasOpenShift
            ? $t('nav.layout.journal_number', { number: appConfigStore.currentShiftNumber })
            : $t('nav.layout.open_journal')
        }}</span>
      </router-link>
    </div>
  </div>

  <!-- 2. Frameless Desktop Titlebar variant (h-6 height) -->
  <div v-else-if="isTitlebar" class="relative no-drag flex items-center gap-1.5">
    <div v-if="hasMultipleStores" class="relative flex items-center">
      <select
        :value="authStore.currentStore?.id"
        :disabled="isSwitching"
        :aria-label="$t('nav.switch_store')"
        class="h-6 ps-2 pe-6 bg-theme-primary/10 hover:bg-theme-primary/20 border border-theme-primary/30 text-theme-primary text-[10px] font-bold rounded-lg focus:outline-none focus:ring-1 focus:ring-theme-primary cursor-pointer font-tajawal appearance-none transition disabled:opacity-60"
        @change="switchTo($event.target.value)"
      >
        <option v-for="s in authStore.stores" :key="s.id" :value="s.id" class="bg-slate-900 text-white">
          {{ s.name }}
        </option>
      </select>
      <ChevronDown class="w-3 h-3 text-theme-primary absolute end-1.5 pointer-events-none" />
    </div>
    <router-link
      v-else
      to="/stores"
      class="px-2 py-0.5 rounded-lg bg-theme-primary/10 hover:bg-theme-primary/20 border border-theme-primary/20 text-theme-primary text-[10px] font-bold flex items-center gap-1 transition no-drag cursor-pointer"
      :title="$t('desktop.titlebar.active_branch_hint')"
    >
      <StoreIcon class="w-3 h-3" />
      <span>{{ authStore.activeStoreName || $t('desktop.titlebar.main_branch') }}</span>
    </router-link>

    <!-- Shift Status Pill in Titlebar -->
    <router-link
      to="/daily-journal"
      class="px-2 py-0.5 rounded-lg text-[10px] font-bold flex items-center gap-1 border transition no-drag cursor-pointer"
      :class="shiftBadgeClass"
      :title="hasOpenShift ? $t('nav.layout.journal_open') : $t('nav.layout.journal_open_cta_hint')"
      @click="onShiftLinkClick"
    >
      <span
        class="w-1.5 h-1.5 rounded-full"
        :class="hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"
      ></span>
      <span>{{
        hasOpenShift
          ? $t('nav.layout.journal_number', { number: appConfigStore.currentShiftNumber })
          : $t('nav.layout.open_journal')
      }}</span>
    </router-link>
  </div>

  <!-- 3. Mobile Drawer variant (stacked, touch-friendly 44px blocks) -->
  <div v-else-if="isDrawer" class="space-y-2.5">
    <div v-if="hasMultipleStores" class="relative">
      <label class="block mb-1.5 text-[11px] font-black text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
        <StoreIcon class="w-3.5 h-3.5 text-theme-primary" />
        <span>{{ $t('nav.switch_store') }}</span>
      </label>
      <div class="relative flex items-center">
        <select
          :value="authStore.currentStore?.id"
          :disabled="isSwitching"
          :aria-label="$t('nav.switch_store')"
          class="w-full min-h-[44px] ps-9 pe-9 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-theme-primary cursor-pointer font-tajawal shadow-2xs disabled:opacity-60 appearance-none transition"
          @change="switchTo($event.target.value)"
        >
          <option
            v-for="s in authStore.stores"
            :key="s.id"
            :value="s.id"
            class="bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 py-1"
          >
            {{ s.name }}
          </option>
        </select>
        <StoreIcon class="w-4 h-4 text-theme-primary absolute start-3 pointer-events-none" />
        <ChevronDown class="w-4 h-4 text-slate-400 absolute end-3 pointer-events-none" />
      </div>
    </div>
    <div
      v-else
      class="w-full min-h-[44px] px-3.5 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between shadow-2xs"
    >
      <div class="flex items-center gap-2 min-w-0">
        <StoreIcon class="w-4 h-4 text-theme-primary shrink-0" />
        <span class="truncate">{{ authStore.activeStoreName || $t('common.main_store_default') }}</span>
      </div>
      <span
        class="text-[10px] text-slate-500 dark:text-slate-400 font-normal px-2 py-0.5 rounded-md bg-slate-200 dark:bg-slate-800/80"
      >
        {{ $t('common.main_branch') }}
      </span>
    </div>

    <!-- Shift Status Pill in Mobile Drawer -->
    <router-link
      to="/daily-journal"
      class="w-full min-h-[44px] px-3.5 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-between border transition cursor-pointer shadow-2xs"
      :class="shiftBadgeClass"
      :title="hasOpenShift ? $t('nav.layout.journal_open') : $t('nav.layout.journal_open_cta_hint')"
      @click="onShiftLinkClick"
    >
      <div class="flex items-center gap-2">
        <span
          class="w-2.5 h-2.5 rounded-full shrink-0"
          :class="hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"
        ></span>
        <span>{{
          hasOpenShift
            ? $t('nav.layout.journal_number', { number: appConfigStore.currentShiftNumber })
            : $t('nav.layout.open_journal')
        }}</span>
      </div>
      <span class="text-[11px] font-bold opacity-80 flex items-center gap-1">
        <span>{{ hasOpenShift ? $t('nav.layout.shift_open_badge') : $t('nav.layout.open_shift_cta') }}</span>
      </span>
    </router-link>
  </div>

  <!-- 4. Desktop Header inline row (lg:flex) -->
  <div v-else class="flex items-center gap-2">
    <div v-if="hasMultipleStores" class="relative flex items-center">
      <select
        :value="authStore.currentStore?.id"
        :disabled="isSwitching"
        :aria-label="$t('nav.switch_store')"
        class="h-8 ps-7 pe-7 bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-theme-primary cursor-pointer font-tajawal shadow-2xs disabled:opacity-60 appearance-none transition"
        @change="switchTo($event.target.value)"
      >
        <option
          v-for="s in authStore.stores"
          :key="s.id"
          :value="s.id"
          class="bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100"
        >
          {{ s.name }}
        </option>
      </select>
      <StoreIcon class="w-3.5 h-3.5 text-theme-primary absolute start-2 pointer-events-none" />
      <ChevronDown class="w-3 h-3 text-slate-400 absolute end-2 pointer-events-none" />
    </div>
    <div
      v-else
      class="h-8 px-2.5 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5 shadow-2xs"
    >
      <StoreIcon class="w-3.5 h-3.5 text-theme-primary" />
      <span>{{ authStore.activeStoreName || $t('common.main_store_default') }}</span>
    </div>

    <!-- Shift Status Pill in Desktop Header -->
    <router-link
      to="/daily-journal"
      class="h-8 px-2.5 rounded-xl text-xs font-bold flex items-center gap-1.5 border transition cursor-pointer shadow-2xs"
      :class="shiftBadgeClass"
      :title="hasOpenShift ? $t('nav.layout.journal_open') : $t('nav.layout.journal_open_cta_hint')"
      @click="onShiftLinkClick"
    >
      <span class="w-2 h-2 rounded-full" :class="hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
      <span>{{
        hasOpenShift
          ? $t('nav.layout.journal_number', { number: appConfigStore.currentShiftNumber })
          : $t('nav.layout.open_journal')
      }}</span>
    </router-link>
  </div>
</template>
