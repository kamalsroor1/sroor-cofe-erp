<script setup>
import { computed } from 'vue';
import { Lock } from 'lucide-vue-next';
import { useAppConfigStore } from '@/stores/appConfig';

const props = defineProps({
  // One canonical feature key, or an array (any-of).
  feature: {
    type: [String, Array],
    default: null,
  },
  // Render the fallback slot (or the default note) when the feature is not in the plan.
  showFallback: {
    type: Boolean,
    default: false,
  },
  // Full-page upsell instead of the compact inline note (used for gated routes).
  page: {
    type: Boolean,
    default: false,
  },
});

const appConfigStore = useAppConfigStore();

// Entitlement rule lives in the store: allowed while not loaded / not enforced.
const isAllowed = computed(() => appConfigStore.hasFeature(props.feature));
</script>

<template>
  <template v-if="isAllowed">
    <slot />
  </template>
  <div
    v-else-if="page"
    class="min-h-[60vh] flex items-center justify-center p-4 font-tajawal"
    role="status"
    data-testid="feature-upsell"
  >
    <div
      class="w-full max-w-md text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4"
    >
      <div
        class="mx-auto w-16 h-16 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center"
      >
        <Lock class="w-8 h-8" aria-hidden="true" />
      </div>
      <span
        class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-theme-light text-theme-primary border border-theme-border"
      >
        {{ $t('subscription.upsell.locked_badge') }}
      </span>
      <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $t('subscription.upsell.title') }}</h2>
      <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
        {{ $t('subscription.upsell.description') }}
      </p>
      <router-link
        to="/"
        class="inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl bg-theme-primary text-white text-sm font-bold active:scale-95 transition"
      >
        {{ $t('subscription.upsell.back_home') }}
      </router-link>
    </div>
  </div>
  <template v-else-if="showFallback">
    <slot name="fallback">
      <!-- No upgrade link: the plans screen belongs to the platform console (admin host). -->
      <div
        class="p-3.5 bg-theme-light border border-theme-border rounded-2xl flex items-center gap-2 text-xs text-theme-primary font-tajawal shadow-xs"
        role="note"
      >
        <Lock class="w-4 h-4 shrink-0" aria-hidden="true" />
        <span class="font-bold">{{ $t('super.plan_upgrade_required') }}</span>
      </div>
    </slot>
  </template>
</template>
