<script setup>
import { Cpu, RefreshCw, BarChart3 } from 'lucide-vue-next';

defineProps({
    systemInfo: {
        type: Object,
        default: () => ({})
    }
});

defineEmits(['clear-cache']);
</script>

<template>
    <div class="space-y-6 font-tajawal">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-6">
            <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
                <h2 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <Cpu class="w-4 h-4 text-theme-primary" />
                    <span>{{ $t('settings.system_perf_title') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.system_perf_sub') }}</p>
            </div>

            <!-- 2x2 Bento Matrix for System Specs on Mobile -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4 font-mono">
                <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                    <span class="text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('settings.php_version') }}</span>
                    <div class="text-xs sm:text-sm font-black text-theme-primary truncate">{{ systemInfo.php_version }}</div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                    <span class="text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('settings.laravel_version') }}</span>
                    <div class="text-xs sm:text-sm font-black text-slate-900 dark:text-white truncate">{{ systemInfo.laravel_version }}</div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                    <span class="text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('settings.db_engine') }}</span>
                    <div class="text-xs sm:text-sm font-black text-emerald-600 dark:text-emerald-400 truncate">{{ systemInfo.db_driver }}</div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                    <span class="text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('settings.environment') }}</span>
                    <div class="text-xs sm:text-sm font-black text-indigo-600 dark:text-indigo-400 truncate">{{ systemInfo.environment }}</div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <a
                        href="/pulse"
                        target="_blank"
                        class="flex-1 sm:flex-none h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-purple-600 dark:text-purple-400 border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center justify-center gap-1.5 transition active:scale-95 shadow-xs"
                    >
                        <BarChart3 class="w-4 h-4" />
                        <span>{{ $t('settings.pulse_monitoring') }}</span>
                    </a>

                    <a
                        href="/telescope"
                        target="_blank"
                        class="flex-1 sm:flex-none h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-teal-600 dark:text-teal-400 border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center justify-center gap-1.5 transition active:scale-95 shadow-xs"
                    >
                        <Cpu class="w-4 h-4" />
                        <span>{{ $t('settings.telescope') }}</span>
                    </a>
                </div>

                <button
                    type="button"
                    class="w-full sm:w-auto h-11 px-6 rounded-2xl btn-primary-theme font-black text-xs transition transform active:scale-95 cursor-pointer flex items-center justify-center gap-2 shadow-theme-primary"
                    @click="$emit('clear-cache')"
                >
                    <RefreshCw class="w-4 h-4" />
                    <span>{{ $t('settings.clear_cache_now') }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
