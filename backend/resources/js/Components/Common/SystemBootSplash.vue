<template>
  <Transition name="splash-fade">
    <div
      v-if="show"
      class="fixed inset-0 z-[999999] flex flex-col items-center justify-center bg-slate-950 text-white font-tajawal select-none px-4"
    >
      <!-- Background Ambient Glow -->
      <div class="absolute w-96 h-96 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none -top-20 -left-20"></div>
      <div class="absolute w-96 h-96 rounded-full bg-amber-500/10 blur-3xl pointer-events-none -bottom-20 -right-20"></div>

      <!-- Main Central Card -->
      <div class="relative z-10 flex flex-col items-center max-w-sm w-full text-center space-y-6">
        <!-- Logo / Icon with Pulse Rings -->
        <div class="relative">
          <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl bg-gradient-to-tr from-slate-900 via-slate-800 to-slate-900 border border-slate-700/80 shadow-2xl flex items-center justify-center text-4xl sm:text-5xl shadow-emerald-500/10">
            <span v-if="appConfigStore.companyLogo">
              <img :src="appConfigStore.companyLogo" :alt="appConfigStore.companyName" class="w-16 h-16 object-contain" />
            </span>
            <span v-else>☕</span>
          </div>
          <!-- Animated glowing halo -->
          <div class="absolute inset-0 rounded-3xl border-2 border-theme-primary animate-ping opacity-25 pointer-events-none"></div>
        </div>

        <!-- Dynamic Tenant Branding & Pure Localization -->
        <div class="space-y-1.5">
          <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center justify-center gap-2">
            <span>{{ appConfigStore.companyName || $t('common.app_title') }}</span>
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-theme-primary/20 text-theme-primary border border-theme-primary/30">{{ $t('common.pos_badge') }}</span>
          </h1>
          <p class="text-xs text-slate-400 font-bold">
            {{ $t('common.app_subtitle') }}
          </p>
        </div>

        <!-- Shimmer Progress Bar -->
        <div class="w-full max-w-xs space-y-2">
          <div class="h-2 w-full bg-slate-800 rounded-full overflow-hidden relative border border-slate-700/60 shadow-inner">
            <div class="h-full bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500 rounded-full animate-indeterminate"></div>
          </div>
          <div class="flex items-center justify-between text-[11px] text-slate-400 font-mono font-medium">
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              <span>{{ $t('common.system_booting') }}</span>
            </span>
            <span class="text-slate-400 font-bold">v1.0</span>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { useAppConfigStore } from '../../stores/appConfig';

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
  transition: opacity 0.35s ease, transform 0.35s ease;
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
