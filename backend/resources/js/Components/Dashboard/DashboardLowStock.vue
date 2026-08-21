<script setup>
import { Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';

defineProps({
    lowStockItems: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 sm:space-y-5 shadow-xs font-tajawal">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2.5">
                <AlertTriangle class="w-5 h-5 text-rose-500" />
                <h2 class="text-base lg:text-lg font-black text-slate-900 dark:text-white">{{ $t('dashboard.low_stock_radar_title') }}</h2>
            </div>
            <Link href="/purchases/smart-reorder" class="text-xs font-black text-theme-primary hover:underline transition active:scale-95">
                {{ $t('dashboard.purchases_assistant') }}
            </Link>
        </div>

        <div class="space-y-3">
            <div
                v-for="item in lowStockItems"
                :key="item.id"
                class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 flex items-center justify-between gap-3 hover:border-amber-500/30 transition"
            >
                <div class="flex-1 truncate font-tajawal">
                    <div class="font-bold text-sm text-slate-900 dark:text-white truncate">{{ item.name }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                        {{ $t('dashboard.min_stock_level') }} {{ Number(item.min_stock_level).toFixed(1) }} {{ item.unit }}
                    </div>
                </div>
                <div class="text-left font-mono shrink-0">
                    <span class="px-3 py-1 rounded-xl text-xs font-black bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                        {{ Number(item.current_stock).toFixed(1) }} {{ item.unit }}
                    </span>
                </div>
            </div>

            <div v-if="lowStockItems.length === 0" class="py-12 text-center text-slate-400 font-bold">
                {{ $t('dashboard.all_items_safe_radar') }}
            </div>
        </div>
    </div>
</template>
