<script setup>
import { ref, computed } from 'vue';
import { Store as StoreIcon, ChevronDown, Check } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import { useAppConfigStore } from '../../stores/appConfig';

const props = defineProps({
  // inline: desktop header | chip: compact header chip with dropdown | drawer: stacked block for the mobile drawer
  variant: { type: String, default: 'inline', validator: (v) => ['inline', 'chip', 'drawer'].includes(v) },
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
  <!-- Compact chip (md) with dropdown -->
  <div v-if="isChip" class="relative">
    <button
      type="button"
      class="min-h-[44px] max-w-[160px] px-3 flex items-center gap-1.5 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-slate-800 dark:text-cyan-400 cursor-pointer font-tajawal"
      :aria-label="$t('nav.layout.store_and_shift')"
      :aria-expanded="isOpen"
      @click="isOpen = !isOpen"
    >
      <StoreIcon class="w-4 h-4 shrink-0" />
      <span class="truncate">{{ authStore.activeStoreName || $t('common.main_store_default') }}</span>
      <span
        class="w-2 h-2 rounded-full shrink-0"
        :class="hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"
      ></span>
      <ChevronDown class="w-3.5 h-3.5 shrink-0" />
    </button>

    <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>
    <div
      v-if="isOpen"
      class="absolute end-0 top-full mt-2 w-64 z-50 p-2 space-y-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl font-tajawal"
    >
      <template v-if="hasMultipleStores">
        <p class="px-2 pt-1 text-[10px] font-black text-slate-400">{{ $t('nav.switch_store') }}</p>
        <button
          v-for="s in authStore.stores"
          :key="s.id"
          type="button"
          :disabled="isSwitching"
          class="w-full min-h-[44px] px-3 flex items-center justify-between gap-2 rounded-xl text-sm font-bold text-start text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-60 cursor-pointer"
          @click="switchTo(s.id)"
        >
          <span class="truncate">{{ s.name }}</span>
          <Check
            v-if="String(s.id) === String(authStore.currentStore?.id)"
            class="w-4 h-4 shrink-0"
            style="color: var(--color-primary)"
          />
        </button>
        <div class="border-t border-slate-200 dark:border-slate-800 my-1"></div>
      </template>
      <router-link
        to="/daily-journal"
        class="min-h-[44px] px-3 rounded-xl text-sm font-bold flex items-center gap-2 border"
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

  <!-- Stacked block (mobile drawer) / inline row (desktop header) -->
  <div v-else :class="isDrawer ? 'space-y-2' : 'flex items-center gap-2'">
    <div v-if="hasMultipleStores" class="relative">
      <label v-if="isDrawer" class="block mb-1 text-[10px] font-black text-slate-400">{{
        $t('nav.switch_store')
      }}</label>
      <select
        :value="authStore.currentStore?.id"
        :disabled="isSwitching"
        :aria-label="$t('nav.switch_store')"
        class="ps-3 pe-7 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-slate-800 dark:text-cyan-400 focus:outline-none focus:ring-2 focus:ring-theme-primary cursor-pointer font-tajawal shadow-2xs disabled:opacity-60"
        :class="isDrawer ? 'w-full min-h-[44px] text-sm' : 'h-8'"
        @change="switchTo($event.target.value)"
      >
        <option v-for="s in authStore.stores" :key="s.id" :value="s.id">
          {{ s.name }}
        </option>
      </select>
    </div>
    <div
      v-else
      class="px-3 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl text-xs font-bold text-cyan-600 dark:text-cyan-400 flex items-center gap-1.5 shadow-2xs"
      :class="isDrawer ? 'min-h-[44px]' : 'py-1.5'"
    >
      <StoreIcon class="w-3.5 h-3.5" />
      <span>{{ authStore.activeStoreName || $t('common.main_store_default') }}</span>
    </div>

    <router-link
      to="/daily-journal"
      class="px-3 rounded-2xl text-xs font-bold flex items-center gap-1.5 border transition cursor-pointer shadow-2xs"
      :class="[shiftBadgeClass, isDrawer ? 'min-h-[44px]' : 'py-1.5']"
      :title="hasOpenShift ? $t('nav.layout.journal_open') : $t('nav.layout.journal_open_cta_hint')"
      @click="emit('navigate')"
    >
      <span
        class="w-2 h-2 rounded-full"
        :class="hasOpenShift ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500 animate-ping'"
      ></span>
      <span>{{
        hasOpenShift
          ? $t('nav.layout.journal_number', { number: appConfigStore.currentShiftNumber })
          : $t('nav.layout.open_journal')
      }}</span>
    </router-link>
  </div>
</template>
