<template>
  <div v-if="workspaceName" class="flex items-center justify-center font-sans" dir="rtl">
    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 text-xs font-bold text-slate-700 dark:text-slate-200 shadow-sm backdrop-blur-sm transition-all hover:border-theme-primary/50">
      <div class="flex items-center gap-1.5">
        <Store class="w-3.5 h-3.5 text-theme-primary shrink-0" />
        <span class="font-tajawal truncate max-w-[160px] sm:max-w-[220px]">{{ workspaceName }}</span>
      </div>

      <span class="text-slate-300 dark:text-slate-600">|</span>

      <button
        type="button"
        @click="$emit('change')"
        class="text-theme-primary hover:text-amber-400 flex items-center gap-1 text-[11px] font-tajawal cursor-pointer transition-colors"
        :title="$t('auth.change_workspace')"
      >
        <RefreshCw class="w-3 h-3" />
        <span>{{ $t('auth.change_workspace') }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { Store, RefreshCw } from 'lucide-vue-next';

defineProps({
    workspaceName: {
        type: String,
        default: '',
    },
    workspaceCode: {
        type: String,
        default: '',
    },
});

defineEmits(['change']);
</script>
