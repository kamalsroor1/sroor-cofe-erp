<script setup>
import { computed } from 'vue';
import { Head, usePage, Deferred, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MetricCard from '@/Components/Common/MetricCard.vue';
import { useMoney } from '@/Composables/useMoney';

// Skeleton Components
import StatCardSkeleton from '@/Components/Common/Skeletons/StatCardSkeleton.vue';
import CardSkeleton from '@/Components/Common/Skeletons/CardSkeleton.vue';

// Atomic Sub-Components
import DashboardWelcomeBanner from '@/Components/Dashboard/DashboardWelcomeBanner.vue';
import DashboardAnalytics from '@/Components/Dashboard/DashboardAnalytics.vue';
import DashboardRecentInvoices from '@/Components/Dashboard/DashboardRecentInvoices.vue';
import DashboardLowStock from '@/Components/Dashboard/DashboardLowStock.vue';

const props = defineProps({
    metrics: { type: Object, default: () => null },
    analytics: { type: Object, default: () => null },
    recent_invoices: { type: Array, default: () => [] },
    low_stock_items: { type: Array, default: () => [] },
    top_selling_items: { type: Array, default: () => [] },
    active_shift: { type: Object, default: null },
    active_store: { type: Object, default: null },
});

const page = usePage();
const tenant = computed(() => page.props.tenant);
const activeStore = computed(() => props.active_store || page.props.activeStore);
const summary = computed(() => props.metrics || {});
const analytics = computed(() => props.analytics || {});

const { formatMoney } = useMoney();

// Live Auto-Refresh Dashboard metrics and invoices every 30 seconds smoothly in background
usePoll(30000, {
    only: ['metrics', 'recent_invoices', 'low_stock_items'],
    autoStart: true,
});
</script>

<template>
    <Head :title="`${$t('dashboard.welcome_banner_title')} | ${tenant?.name || 'سرور كوفي ERP'}`" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Welcome Header Banner (Loads Immediately) -->
            <DashboardWelcomeBanner
                :tenant="tenant"
                :active-store="activeStore"
            />

            <!-- 4 Key Metrics Cards (Deferred with Skeleton Fallback) -->
            <Deferred data="metrics">
                <template #fallback>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
                        <StatCardSkeleton v-for="i in 4" :key="i" />
                    </div>
                </template>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4 animate-in fade-in duration-500">
                    <MetricCard
                        :title="$t('dashboard.today_sales_card')"
                        :value="formatMoney(summary?.total_sales)"
                        :currency="$t('common.currency')"
                        :subtitle="`${summary?.invoices_count || 0} ${$t('dashboard.today_invoices_count', { count: '' }).replace(':count', '')}`"
                        variant="success"
                    />

                    <MetricCard
                        :title="$t('dashboard.monthly_gross_profit_card')"
                        :value="formatMoney(summary?.monthly_gross_profit)"
                        :currency="$t('common.currency')"
                        :subtitle="`${$t('dashboard.profit_margin_label')}: ${summary?.monthly_margin || '0.00'}%`"
                        variant="primary"
                    />

                    <MetricCard
                        :title="$t('dashboard.customers_debt_card')"
                        :value="formatMoney(summary?.total_customers_debt)"
                        :currency="$t('common.currency')"
                        :subtitle="$t('dashboard.due_collections_label')"
                        variant="danger"
                    />

                    <MetricCard
                        :title="$t('dashboard.monthly_sales_card')"
                        :value="formatMoney(summary?.monthly_sales)"
                        :currency="$t('common.currency')"
                        :subtitle="$t('dashboard.monthly_net_operations')"
                        variant="slate"
                    />
                </div>
            </Deferred>

            <!-- Interactive Analytics: 7-Day Trend & Peak Hours (Deferred with Chart Skeleton) -->
            <Deferred data="analytics">
                <template #fallback>
                    <CardSkeleton :has-chart="true" />
                </template>

                <div v-if="analytics?.daily_trend" class="animate-in fade-in duration-500">
                    <DashboardAnalytics :analytics="analytics" />
                </div>
            </Deferred>

            <!-- Two-Column Section: Recent Invoices & Low Stock Radar (Deferred) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Recent Sales Invoices (2 Columns) -->
                <div class="lg:col-span-2">
                    <Deferred data="recent_invoices">
                        <template #fallback>
                            <CardSkeleton :rows="5" />
                        </template>

                        <div class="animate-in fade-in duration-500">
                            <DashboardRecentInvoices :recent-invoices="recent_invoices" />
                        </div>
                    </Deferred>
                </div>

                <!-- Right: Low Stock Radar (1 Column) -->
                <div>
                    <Deferred data="low_stock_items">
                        <template #fallback>
                            <CardSkeleton :rows="4" />
                        </template>

                        <div class="animate-in fade-in duration-500">
                            <DashboardLowStock :low-stock-items="low_stock_items" />
                        </div>
                    </Deferred>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
