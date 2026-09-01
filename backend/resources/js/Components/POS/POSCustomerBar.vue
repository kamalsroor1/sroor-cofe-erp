<script setup>
import { useMoney } from '@/Composables/useMoney';
import { User, ChevronDown, UserPlus } from 'lucide-vue-next';

const props = defineProps({
    selectedCustomer: {
        type: Object,
        default: null
    }
});

defineEmits(['open-customer-modal', 'open-new-customer-modal']);

const { formatMoney } = useMoney();
</script>

<template>
    <div class="p-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 bg-slate-50 dark:bg-slate-800/60 shrink-0 font-tajawal">
        <div
            class="flex-1 flex items-center gap-2 p-2.5 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 cursor-pointer transition shadow-xs"
            @click="$emit('open-customer-modal')"
        >
            <User class="w-4 h-4 text-theme-primary shrink-0" />
            <div class="flex-1 truncate">
                <div class="text-xs font-black text-slate-900 dark:text-white truncate">
                    {{ selectedCustomer?.name || $t('pos.cash_customer') }}
                </div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                    {{ selectedCustomer?.phone || '-' }} • {{ $t('contacts.balance') }}: {{ formatMoney(selectedCustomer?.current_balance) }} {{ $t('common.currency') }}
                </div>
            </div>
            <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
        </div>

        <button
            type="button"
            class="h-10 px-3 rounded-xl bg-theme-light hover:bg-theme-hover/30 border border-theme-border text-theme-primary font-black text-xs flex items-center gap-1 transition cursor-pointer active:scale-95 shadow-xs shrink-0"
            :title="$t('pos.add_new_customer')"
            @click="$emit('open-new-customer-modal')"
        >
            <UserPlus class="w-4 h-4" />
        </button>
    </div>
</template>
