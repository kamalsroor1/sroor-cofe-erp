<template>
  <div class="space-y-6 text-center font-sans py-4" dir="rtl">
    <!-- Animated Glowing Pulse Avatar / Logo -->
    <div class="relative inline-flex items-center justify-center">
      <div class="absolute inset-0 rounded-3xl bg-theme-primary/20 animate-ping"></div>
      <div class="relative w-24 h-24 rounded-3xl bg-white dark:bg-slate-800 border-2 border-theme-primary p-2 shadow-2xl flex items-center justify-center overflow-hidden">
        <img
          v-if="resolvedTenant?.logo_url"
          :src="resolvedTenant.logo_url"
          :alt="resolvedTenant.name"
          class="w-full h-full object-contain"
        />
        <Coffee v-else class="w-12 h-12 text-theme-primary animate-bounce" />
      </div>
    </div>

    <!-- Title and Status Feedback -->
    <div class="space-y-2">
      <h2 class="text-xl font-black text-slate-900 dark:text-white font-tajawal">
        {{ resolvedTenant?.name || $t('auth.workspace_connecting') }}
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 font-bold max-w-xs mx-auto leading-relaxed">
        {{ statusMessage || $t('auth.magic_link_connecting') }}
      </p>
    </div>

    <!-- Facebook-Style Shimmer Skeleton Lines -->
    <div class="space-y-3 max-w-xs mx-auto pt-2">
      <div class="h-3.5 bg-slate-200 dark:bg-slate-800 rounded-full w-3/4 mx-auto animate-pulse"></div>
      <div class="h-3 bg-slate-200 dark:bg-slate-800/60 rounded-full w-1/2 mx-auto animate-pulse"></div>
    </div>

    <!-- Desktop Deep Link Launcher Button (if deep linking is possible) -->
    <div v-if="desktopDeepLink" class="pt-4 border-t border-slate-200 dark:border-slate-800/80 space-y-3">
      <a
        :href="desktopDeepLink"
        class="w-full h-11 px-4 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white font-bold text-xs rounded-2xl flex items-center justify-center gap-2 border border-slate-700 shadow-md transition-all active:scale-[0.98] font-tajawal"
      >
        <Monitor class="w-4 h-4 text-theme-primary" />
        <span>{{ $t('auth.open_in_desktop_app') }}</span>
      </a>

      <button
        type="button"
        @click="$emit('cancel')"
        class="text-[11px] font-bold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer font-tajawal"
      >
        {{ $t('auth.back_to_workspace') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Coffee, Monitor } from 'lucide-vue-next';

const props = defineProps({
    tenantCode: {
        type: String,
        default: '',
    },
    resolvedTenant: {
        type: Object,
        default: null,
    },
    statusMessage: {
        type: String,
        default: '',
    },
});

defineEmits(['cancel', 'retry']);

const desktopDeepLink = computed(() => {
    const code = props.resolvedTenant?.slug || props.resolvedTenant?.tenant_id || props.tenantCode;
    return code ? `sroor://connect?tenant=${encodeURIComponent(code)}` : null;
});
</script>
