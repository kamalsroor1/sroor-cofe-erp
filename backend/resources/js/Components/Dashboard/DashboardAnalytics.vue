<script setup>
import { computed } from 'vue';
import { BarChart3, Zap, CreditCard } from 'lucide-vue-next';
import { useMoney } from '@/Composables/useMoney';

const props = defineProps({
    analytics: {
        type: Object,
        default: () => ({})
    }
});

const { formatMoney } = useMoney();

const maxDailySales = computed(() => {
    if (!props.analytics?.daily_trend?.length) return 1;
    return Math.max(...props.analytics.daily_trend.map(d => d.sales), 1);
});
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 font-tajawal">
        <!-- 7-Day Trend (2 Cols) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-base lg:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <BarChart3 class="w-5 h-5 text-theme-primary" />
                        <span>{{ $t('dashboard.seven_days_trend_title') }}</span>
                    </h3>
                    <p class="text-xs lg:text-sm text-slate-500 dark:text-slate-400 font-bold mt-1">{{ $t('dashboard.seven_days_trend_desc') }}</p>
                </div>
                <div class="text-start sm:text-left bg-slate-50 dark:bg-slate-900/90 px-3.5 py-2 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold block">{{ $t('dashboard.avg_invoice_val') }}</span>
                    <span class="text-base font-black font-mono text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(analytics?.period?.basket_size) }} {{ $t('common.currency') }}
                    </span>
                </div>
            </div>

            <!-- Bar Chart -->
            <div class="grid grid-cols-7 gap-1 sm:gap-3 items-end h-48 pt-4 pb-2">
                <div
                    v-for="(day, dIdx) in analytics.daily_trend"
                    :key="dIdx"
                    class="flex flex-col items-center gap-1.5 sm:gap-2 h-full justify-end group relative"
                >
                    <span class="text-[10px] sm:text-xs font-mono font-bold text-slate-700 dark:text-slate-200">
                        {{ day.sales > 0 ? Number(day.sales).toFixed(0) : '0' }}
                    </span>

                    <div class="w-full bg-slate-100 dark:bg-slate-800/80 rounded-xl sm:rounded-2xl overflow-hidden flex items-end h-32">
                        <div
                            :style="{ height: `${Math.max(8, Math.round((day.sales / maxDailySales) * 100))}%` }"
                            class="w-full rounded-xl sm:rounded-2xl transition-all duration-500 bg-theme-gradient shadow-theme-sm"
                        ></div>
                    </div>

                    <span class="text-[10px] sm:text-xs font-bold text-slate-600 dark:text-slate-300 truncate w-full text-center">
                        {{ day.label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Peak Hours & Payment Split (1 Col) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-5 flex flex-col justify-between">
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <Zap class="w-5 h-5 text-theme-primary" />
                        <span>{{ $t('dashboard.peak_hours_title') }}</span>
                    </h3>
                    <span v-if="analytics?.peak_hour?.label" class="text-xs font-black px-2.5 py-1 rounded-xl bg-theme-light text-theme-primary border border-theme-light">
                        {{ $t('dashboard.peak_hour_badge', { hour: analytics.peak_hour.label }) }}
                    </span>
                </div>

                <!-- 24-Hour Micro Heatmap -->
                <div class="grid grid-cols-12 gap-1 sm:gap-1.5 pt-1">
                    <div
                        v-for="(h, hIdx) in (analytics.hourly_sales || [])"
                        :key="hIdx"
                        class="h-8 sm:h-9 rounded-md sm:rounded-lg bg-slate-100 dark:bg-slate-800 flex items-end overflow-hidden border border-slate-200 dark:border-slate-700/50"
                        :title="`${h.label}: ${h.sales_formatted}`"
                    >
                        <div
                            v-if="h.intensity > 0"
                            class="w-full bg-theme-primary rounded-b-md sm:rounded-b-lg"
                            :style="{ height: `${Math.max(20, h.intensity)}%` }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Payment Split -->
            <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <span class="text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <CreditCard class="w-4 h-4 text-slate-500 dark:text-slate-400" />
                    <span>{{ $t('dashboard.collection_methods') }}</span>
                </span>
                <div class="space-y-2">
                    <template v-for="(pm, pIdx) in (analytics.payment_distribution || [])" :key="pIdx">
                        <div v-if="pm.percentage > 0">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">
                                <span>{{ pm.label }}</span>
                                <span class="font-mono text-slate-900 dark:text-white font-black">{{ pm.percentage }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-theme-gradient rounded-full"
                                    :style="{ width: `${pm.percentage}%` }"
                                ></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
