<script setup>
import { LayoutDashboard, Zap, Truck } from 'lucide-vue-next';

defineProps({
    tenant: {
        type: Object,
        default: null
    },
    activeStore: {
        type: Object,
        default: null
    }
});
</script>

<template>
    <div class="bg-gradient-to-l from-slate-100 via-white to-slate-50 dark:from-slate-900/90 dark:via-slate-900 dark:to-slate-950 rounded-2xl sm:rounded-3xl p-4 sm:p-6 lg:p-8 border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4 sm:gap-6 transition-colors font-tajawal">
        <div class="space-y-1.5 sm:space-y-2">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-theme-light border border-theme-light text-theme-primary flex items-center justify-center shadow-xs shrink-0">
                    <LayoutDashboard class="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
                <h1 class="text-base sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ $t('dashboard.welcome_banner_title') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm lg:text-base text-slate-600 dark:text-slate-300 font-bold flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span>{{ $t('dashboard.current_branch_label') }}</span>
                <span class="px-2 sm:px-2.5 py-0.5 rounded-xl bg-theme-light text-theme-primary border border-theme-light font-black text-xs sm:text-sm">
                    {{ activeStore?.name || $t('common.main_store_default') }}
                </span>
                <span class="text-slate-400 dark:text-slate-500 hidden sm:inline">•</span>
                <span class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm hidden sm:inline">{{ $t('dashboard.overview_subtitle') }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2.5 w-full md:w-auto">
            <router-link
                to="/pos"
                class="flex-1 md:flex-none h-10 sm:h-12 px-4 sm:px-6 rounded-2xl btn-primary-theme font-black text-xs sm:text-sm flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-primary"
            >
                <Zap class="w-4 h-4 fill-current" />
                <span>{{ $t('dashboard.pos_fast_btn') }}</span>
                <span class="px-1.5 py-0.5 rounded-lg bg-black/20 text-[10px] sm:text-xs font-mono font-black">F2</span>
            </router-link>

            <router-link
                to="/purchases/create"
                class="h-10 sm:h-12 px-3.5 sm:px-5 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-black text-xs sm:text-sm flex items-center justify-center gap-2 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs shrink-0"
            >
                <Truck class="w-4 h-4" />
                <span class="hidden sm:inline">{{ $t('dashboard.supply_invoice_btn') }}</span>
            </router-link>
        </div>
    </div>
</template>
