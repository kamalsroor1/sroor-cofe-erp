<script setup>
import { useMoney } from '@/Composables/useMoney';

defineProps({
    treasuryData: {
        type: Object,
        default: () => ({})
    }
});

const { formatMoney } = useMoney();
</script>

<template>
    <div class="space-y-6 font-tajawal">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.treasury_liquidity_title') }}</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-1">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.cash_collections') }}</span>
                    <div class="text-xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(treasuryData?.inflows?.cash || 0) }} {{ $t('common.currency') }}
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-1">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.instapay_collections') }}</span>
                    <div class="text-xl font-black font-mono text-purple-600 dark:text-purple-400">
                        {{ formatMoney(treasuryData?.inflows?.instapay || 0) }} {{ $t('common.currency') }}
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-1">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.wallet_collections') }}</span>
                    <div class="text-xl font-black font-mono text-theme-primary">
                        {{ formatMoney(treasuryData?.inflows?.e_wallet || 0) }} {{ $t('common.currency') }}
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-1">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.visa_collections') }}</span>
                    <div class="text-xl font-black font-mono text-cyan-600 dark:text-cyan-400">
                        {{ formatMoney(treasuryData?.inflows?.visa || 0) }} {{ $t('common.currency') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
