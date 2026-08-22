<script setup>
import DatePicker from '@/Components/DatePicker.vue';

const props = defineProps({
    filterForm: {
        type: Object,
        required: true
    },
    stores: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['apply', 'set-period']);
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-xs space-y-3 font-tajawal">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- Period Buttons -->
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-bold text-[11px] px-1">{{ $t('common.date') }}:</span>
                <button
                    v-for="p in [
                        { id: 'today', label: $t('common.today') },
                        { id: 'yesterday', label: $t('common.yesterday') },
                        { id: 'this_week', label: $t('common.this_week') },
                        { id: 'this_month', label: $t('common.this_month') },
                        { id: 'this_year', label: $t('common.this_year') },
                    ]"
                    :key="p.id"
                    type="button"
                    class="h-9 px-3 rounded-xl font-bold transition active:scale-95 cursor-pointer text-xs"
                    :class="filterForm.period === p.id ? 'tab-theme-active' : 'bg-slate-100 text-slate-600 border border-slate-200 hover:text-slate-900 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-800 dark:hover:text-white'"
                    @click="$emit('set-period', p.id)"
                >
                    {{ p.label }}
                </button>
            </div>

            <!-- Store Filter -->
            <div class="flex items-center gap-2">
                <span class="text-slate-500 dark:text-slate-400 font-bold text-xs hidden sm:inline">{{ $t('inventory.store') }}:</span>
                <select
                    v-model="filterForm.store_id"
                    class="w-full sm:w-auto h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-theme-primary focus:outline-none shadow-inner"
                    @change="$emit('apply')"
                >
                    <option value="all">{{ $t('common.all_stores') }}</option>
                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
        </div>

        <!-- Custom Date Range Pickers -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 pt-2 border-t border-slate-200 dark:border-slate-800/80 items-end">
            <DatePicker v-model="filterForm.from" :label="$t('common.date_from')" />
            <DatePicker v-model="filterForm.to" :label="$t('common.date_to')" />
            <div>
                <button
                    type="button"
                    class="w-full h-11 rounded-2xl btn-primary-theme font-bold text-xs transition active:scale-95 cursor-pointer shadow-theme-primary flex items-center justify-center gap-1.5"
                    @click="$emit('apply')"
                >
                    <span>🔄</span>
                    <span>{{ $t('reports.refresh_report') }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
