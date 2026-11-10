<template>
  <Transition name="splash-fade">
    <div
      v-if="show"
      class="fixed inset-0 z-[999999] flex flex-col items-center justify-center bg-[var(--brand-surface)] text-slate-900 dark:text-white select-none px-4"
    >
      <!-- Background Ambient Glow -->
      <div
        class="absolute w-96 h-96 rounded-full blur-3xl pointer-events-none -top-20 -start-20"
        style="background: var(--brand-arc); opacity: 0.1"
      ></div>
      <div
        class="absolute w-96 h-96 rounded-full blur-3xl pointer-events-none -bottom-20 -end-20"
        style="background: var(--brand-arc); opacity: 0.1"
      ></div>

      <div class="relative z-10 flex flex-col items-center max-w-sm w-full text-center space-y-6">
        <!-- Tenant logo overrides the product mark when the shop uploaded one -->
        <img
          v-if="appConfigStore.companyLogo"
          :src="appConfigStore.companyLogo"
          :alt="appConfigStore.companyName"
          class="w-24 h-24 sm:w-28 sm:h-28 object-contain"
        />
        <BrandMark v-else :size="112" animated decorative />

        <div class="space-y-1.5">
          <h1 class="text-2xl sm:text-3xl font-black tracking-tight flex items-center justify-center gap-2">
            <span :class="appConfigStore.companyLogo ? '' : 'font-brand font-semibold'">{{
              appConfigStore.companyLogo ? appConfigStore.companyName : $t('brand.name')
            }}</span>
            <span
              class="text-xs font-mono font-bold px-2 py-0.5 rounded-lg border"
              style="background: var(--brand-primary); color: #fff; border-color: var(--brand-primary)"
              >{{ $t('common.pos_badge') }}</span
            >
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-bold">
            {{ $t('common.app_subtitle') }}
          </p>
        </div>

        <!-- Shimmer Progress Bar -->
        <div class="w-full max-w-xs space-y-2">
          <div
            class="h-2 w-full bg-slate-200 dark:bg-white/10 rounded-full overflow-hidden relative border border-slate-300/60 dark:border-white/10 shadow-inner"
          >
            <div class="h-full rounded-full animate-indeterminate" style="background: var(--brand-arc)"></div>
          </div>
          <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 font-medium">
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full animate-pulse" style="background: var(--brand-arc)"></span>
              <span>{{ $t('common.system_booting') }}</span>
            </span>
            <span class="font-mono font-bold">v{{ versionData.version }}</span>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { useAppConfigStore } from '../../stores/appConfig';
import versionData from '../../version.json';
import BrandMark from '../Brand/BrandMark.vue';

const appConfigStore = useAppConfigStore();

defineProps({
  show: {
    type: Boolean,
    default: true,
  },
});
</script>

<style scoped>
.splash-fade-leave-active {
  transition:
    opacity 0.35s ease,
    transform 0.35s ease;
}
.splash-fade-leave-to {
  opacity: 0;
  transform: scale(1.03);
}

@keyframes indeterminate {
  0% {
    width: 0%;
    margin-left: 0%;
  }
  50% {
    width: 70%;
    margin-left: 30%;
  }
  100% {
    width: 100%;
    margin-left: 100%;
  }
}

.animate-indeterminate {
  animation: indeterminate 1.4s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}
</style>
