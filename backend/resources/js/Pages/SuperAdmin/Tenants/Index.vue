<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { trans } from '@/helpers/trans';

const props = defineProps({
    tenants: Object,
    plans: Array,
    filters: Object,
});

const tenantColumns = computed(() => [
    { key: 'name', label: trans('super.tenant_name') || 'اسم المتجر', sortable: true },
    { key: 'domain', label: trans('super.subdomain') || 'النطاق الفرعي', mono: true },
    { key: 'email', label: trans('super.admin_email') || 'بريد المسؤول', mono: true },
    { key: 'plan', label: trans('super.plans') || 'الباقة' },
    { key: 'status', label: trans('common.status') || 'الحالة', align: 'center' },
    { key: 'actions', label: trans('common.actions') || 'الإجراءات', align: 'center' },
]);

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const planId = ref(props.filters.plan_id || 'all');

const applyFilter = () => {
    router.get('/admin/super/tenants', {
        search: search.value,
        status: status.value,
        plan_id: planId.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const impersonate = (tenantId) => {
    router.post(`/admin/super/tenants/${tenantId}/impersonate`);
};
</script>

<template>
    <Head :title="$t('super.tenants')" />

    <SuperAdminLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-white">{{ $t('super.tenants') }}</h1>
                    <p class="text-xs text-slate-400 font-bold mt-0.5">
                        {{ $t('super.total_tenants') }}: {{ tenants?.total || 0 }}
                    </p>
                </div>

                <Link
                    href="/admin/super/tenants/create"
                    class="w-full sm:w-auto h-11 px-5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/25 transition active:scale-95 cursor-pointer"
                >
                    <span>➕</span>
                    <span>{{ $t('super.create_tenant') }}</span>
                </Link>
            </div>

            <!-- Filters Bar -->
            <div class="p-4 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col md:flex-row items-center gap-3 shadow-xs">
                <div class="flex-1 w-full relative">
                    <input
                        v-model="search"
                        @keyup.enter="applyFilter"
                        type="text"
                        :placeholder="$t('super.search_tenants')"
                        class="w-full h-11 bg-slate-800 border border-slate-700 rounded-2xl px-4 text-xs sm:text-sm text-white placeholder:text-slate-500 focus:outline-none focus:border-indigo-500 shadow-inner"
                    />
                </div>

                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full md:w-auto">
                    <select
                        v-model="status"
                        @change="applyFilter"
                        class="flex-1 sm:flex-none h-11 bg-slate-800 border border-slate-700 rounded-2xl px-3.5 text-xs sm:text-sm text-slate-300 focus:outline-none focus:border-indigo-500 shadow-inner"
                    >
                        <option value="all">{{ $t('super.all_statuses') }}</option>
                        <option value="active">{{ $t('super.status_active') }}</option>
                        <option value="trial">{{ $t('super.status_trial') }}</option>
                        <option value="suspended">{{ $t('super.status_suspended') }}</option>
                    </select>

                    <select
                        v-model="planId"
                        @change="applyFilter"
                        class="flex-1 sm:flex-none h-11 bg-slate-800 border border-slate-700 rounded-2xl px-3.5 text-xs sm:text-sm text-slate-300 focus:outline-none focus:border-indigo-500 shadow-inner"
                    >
                        <option value="all">{{ $t('super.all_plans') }}</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>

                    <button
                        @click="applyFilter"
                        type="button"
                        class="w-full sm:w-auto h-11 px-5 rounded-2xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-bold text-slate-300 active:scale-95 transition cursor-pointer flex items-center justify-center gap-1.5 shadow-xs"
                    >
                        <span>🔍</span>
                        <span>{{ $t('common.filter') }}</span>
                    </button>
                </div>
            </div>

            <!-- Tenants Data Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xs font-tajawal">
                <DataTable
                    :columns="tenantColumns"
                    :rows="tenants.data"
                    :pagination="tenants"
                    :empty-title="$t('common.no_data')"
                    empty-icon="🏢"
                >
                    <!-- Tenant Name -->
                    <template #cell-name="{ row }">
                        <Link :href="`/admin/super/tenants/${row.id}`" class="font-black text-white font-tajawal hover:text-indigo-400 transition">
                            {{ row.name }}
                        </Link>
                    </template>

                    <!-- Subdomain -->
                    <template #cell-domain="{ row }">
                        <span class="font-mono text-indigo-400 font-bold">{{ row.domain }}</span>
                    </template>

                    <!-- Email -->
                    <template #cell-email="{ row }">
                        <span class="text-slate-300 font-mono">{{ row.email }}</span>
                    </template>

                    <!-- Plan -->
                    <template #cell-plan="{ row }">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-tajawal">
                            {{ row.plan?.name || '—' }}
                        </span>
                    </template>

                    <!-- Status -->
                    <template #cell-status="{ row }">
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-black font-tajawal"
                            :class="row.status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'"
                        >
                            {{ row.status }}
                        </span>
                    </template>

                    <!-- Actions -->
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-2 font-tajawal">
                            <button
                                @click="impersonate(row.id)"
                                type="button"
                                class="h-8 px-3 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-400 border border-amber-500/30 font-bold transition flex items-center gap-1 cursor-pointer active:scale-95 text-[11px]"
                                title="تسجيل الدخول المباشر كمسؤول المتجر"
                            >
                                <span>⚡</span>
                                <span>دخول للمتجر</span>
                            </button>
                            <Link
                                :href="`/admin/super/tenants/${row.id}`"
                                class="h-8 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold transition flex items-center justify-center active:scale-95 text-[11px]"
                            >
                                {{ $t('common.view') }}
                            </Link>
                        </div>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-950/70 border border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                            <div class="flex items-start justify-between gap-2 border-b border-slate-800 pb-2">
                                <div>
                                    <Link :href="`/admin/super/tenants/${row.id}`" class="font-black text-xs text-white hover:text-indigo-400">
                                        {{ row.name }}
                                    </Link>
                                    <div class="text-[10px] text-indigo-400 font-mono font-bold">{{ row.domain }}</div>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black"
                                    :class="row.status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'"
                                >
                                    {{ row.status }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-400 font-mono text-[11px]">{{ row.email }}</span>
                                <span class="px-2 py-0.5 rounded-lg bg-indigo-600/20 text-indigo-400 text-[10px] font-bold">
                                    {{ row.plan?.name || '—' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                                <button
                                    @click="impersonate(row.id)"
                                    type="button"
                                    class="h-9 px-3 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-400 border border-amber-500/30 font-bold transition flex items-center gap-1 cursor-pointer active:scale-95 text-xs"
                                >
                                    <span>⚡</span>
                                    <span>دخول للمتجر</span>
                                </button>
                                <Link
                                    :href="`/admin/super/tenants/${row.id}`"
                                    class="h-9 px-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold transition flex items-center justify-center active:scale-95 text-xs"
                                >
                                    {{ $t('common.view') }}
                                </Link>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </SuperAdminLayout>
</template>

