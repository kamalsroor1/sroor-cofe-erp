<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MetricCard from '@/Components/Common/MetricCard.vue';
import { useMoney } from '@/Composables/useMoney';

// Atomic Sub-Components
import DashboardWelcomeBanner from '@/Components/Dashboard/DashboardWelcomeBanner.vue';
import DashboardAnalytics from '@/Components/Dashboard/DashboardAnalytics.vue';
import DashboardRecentInvoices from '@/Components/Dashboard/DashboardRecentInvoices.vue';
import DashboardLowStock from '@/Components/Dashboard/DashboardLowStock.vue';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
    analytics: { type: Object, default: () => ({}) },
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
</script>

<template>
    <Head :title="`${$t('dashboard.welcome_banner_title')} | ${tenant?.name || 'سرور كوفي ERP'}`" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Welcome Header Banner -->
            <DashboardWelcomeBanner
                :tenant="tenant"
                :active-store="activeStore"
            />

            <!-- 4 Key Metrics Cards (2-Column Bento Grid on Mobile, 4-Column on Desktop) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
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

            <!-- Interactive Analytics: 7-Day Trend & Peak Hours -->
            <DashboardAnalytics
                v-if="analytics?.daily_trend"
                :analytics="analytics"
            />

            <!-- Two-Column Section: Recent Invoices & Low Stock Radar -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Recent Sales Invoices (2 Columns) -->
                <div class="lg:col-span-2">
                    <DashboardRecentInvoices :recent-invoices="recent_invoices" />
                </div>

                <!-- Right: Low Stock Radar (1 Column) -->
                <div>
                    <DashboardLowStock :low-stock-items="low_stock_items" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
