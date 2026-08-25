<script setup>
import { computed } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import { useMoney } from '@/Composables/useMoney';

const props = defineProps({
    subtotal: {
        type: Number,
        default: 0
    },
    discountType: {
        type: String,
        default: 'fixed'
    },
    discountValue: {
        type: Number,
        default: 0
    },
    discountAmount: {
        type: Number,
        default: 0
    },
    netTotal: {
        type: Number,
        default: 0
    },
    paymentType: {
        type: String,
        default: 'cash'
    },
    paymentMethod: {
        type: String,
        default: 'cash'
    },
    paidAmount: {
        type: Number,
        default: 0
    },
    remainingAmount: {
        type: Number,
        default: 0
    },
    cartLength: {
        type: Number,
        default: 0
    },
    isSubmitting: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits([
    'update:discountType',
    'update:discountValue',
    'update:paymentType',
    'update:paymentMethod',
    'update:paidAmount',
    'checkout'
]);

const { formatMoney } = useMoney();

const toggleDiscountType = () => {
    emit('update:discountType', props.discountType === 'fixed' ? 'percentage' : 'fixed');
};

const onDiscountValueInput = (e) => {
    emit('update:discountValue', Number(e.target.value) || 0);
};

const onPaidAmountInput = (e) => {
    emit('update:paidAmount', Number(e.target.value) || 0);
};
</script>

<template>
    <div class="p-3.5 sm:p-4 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 space-y-2.5 shrink-0 font-tajawal">
        <!-- Subtotal & Discount -->
        <div class="space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-center justify-between">
                <span>{{ $t('common.subtotal') }}:</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
            </div>

            <div class="flex items-center justify-between gap-2">
                <span>{{ $t('common.discount') }}:</span>
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="h-7 px-2 rounded-lg bg-slate-200 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs font-black text-theme-primary text-theme-primary cursor-pointer"
                        @click="toggleDiscountType"
                    >
                        {{ discountType === 'fixed' ? $t('common.currency') : '%' }}
                    </button>
                    <input
                        :value="discountValue"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        class="w-16 h-7 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-center text-xs font-mono font-bold text-slate-900 dark:text-white"
                        @input="onDiscountValueInput"
                    />
                    <span class="font-mono font-bold text-rose-600 dark:text-rose-400">-{{ formatMoney(discountAmount) }}</span>
                </div>
            </div>

            <!-- Net Total -->
            <div class="flex items-center justify-between pt-1.5 border-t border-slate-200 dark:border-slate-800 text-sm">
                <span class="font-black text-slate-900 dark:text-white">{{ $t('common.net') }}:</span>
                <span class="font-mono font-black text-lg sm:text-xl text-emerald-600 dark:text-emerald-400">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
            </div>
        </div>

        <!-- Payment Type Selector (Finger Friendly Min 40px) -->
        <div class="grid grid-cols-3 gap-1.5 text-xs">
            <button
                type="button"
                class="h-10 sm:h-9 rounded-xl font-black transition text-center cursor-pointer flex items-center justify-center active:scale-95 shadow-xs"
                :class="paymentType === 'cash' ? 'bg-emerald-600 text-white font-bold shadow-md' : 'bg-slate-200 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-800'"
                @click="$emit('update:paymentType', 'cash')"
            >
                {{ $t('pos.payment_cash') }} (F4)
            </button>
            <button
                type="button"
                class="h-10 sm:h-9 rounded-xl font-black transition text-center cursor-pointer flex items-center justify-center active:scale-95 shadow-xs"
                :class="paymentType === 'partial' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'bg-slate-200 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-800'"
                @click="$emit('update:paymentType', 'partial')"
            >
                {{ $t('pos.payment_partial') }} (F8)
            </button>
            <button
                type="button"
                class="h-10 sm:h-9 rounded-xl font-black transition text-center cursor-pointer flex items-center justify-center active:scale-95 shadow-xs"
                :class="paymentType === 'credit' ? 'bg-rose-500 text-white shadow-md' : 'bg-slate-200 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-800'"
                @click="$emit('update:paymentType', 'credit')"
            >
                {{ $t('pos.payment_credit') }} (F9)
            </button>
        </div>

        <!-- Payment Method Selector (Cash, InstaPay, Visa, Wallet) -->
        <div class="grid grid-cols-4 gap-1 text-[11px] font-bold">
            <button
                type="button"
                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                :class="paymentMethod === 'cash' ? 'bg-theme-light border-theme-primary text-theme-primary font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                @click="$emit('update:paymentMethod', 'cash')"
            >
                {{ $t('treasury.cash_drawer') }}
            </button>
            <button
                type="button"
                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                :class="paymentMethod === 'instapay' ? 'bg-purple-500/15 border-purple-500 text-purple-600 dark:text-purple-400 font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                @click="$emit('update:paymentMethod', 'instapay')"
            >
                {{ $t('treasury.instapay') }}
            </button>
            <button
                type="button"
                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                :class="paymentMethod === 'visa' ? 'bg-cyan-500/15 border-cyan-500 text-cyan-600 dark:text-cyan-400 font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                @click="$emit('update:paymentMethod', 'visa')"
            >
                {{ $t('treasury.visa') }}
            </button>
            <button
                type="button"
                class="h-8.5 rounded-xl border transition text-center cursor-pointer flex items-center justify-center active:scale-95"
                :class="paymentMethod === 'e_wallet' ? 'bg-indigo-500/15 border-indigo-500 text-indigo-600 dark:text-indigo-400 font-black' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
                @click="$emit('update:paymentMethod', 'e_wallet')"
            >
                {{ $t('treasury.e_wallet') }}
            </button>
        </div>

        <!-- Dynamic Paid Amount Inputs for Partial/Credit -->
        <div v-if="paymentType === 'partial'" class="grid grid-cols-2 gap-2 text-xs">
            <div class="space-y-1">
                <label class="font-bold text-slate-500">{{ $t('invoices.paid') }}</label>
                <input
                    :value="paidAmount"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    class="w-full h-9 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-2 text-center font-mono font-bold text-slate-900 dark:text-white"
                    @input="onPaidAmountInput"
                />
            </div>
            <div class="space-y-1">
                <label class="font-bold text-slate-500">{{ $t('invoices.remaining') }}</label>
                <div class="h-9 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 font-mono font-black flex items-center justify-center">
                    {{ formatMoney(remainingAmount) }}
                </div>
            </div>
        </div>

        <!-- Quick Submit Checkout Action Button -->
        <button
            type="button"
            :disabled="cartLength === 0 || isSubmitting"
            class="w-full h-13 rounded-2xl btn-primary-theme font-black text-sm sm:text-base flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-theme-md"
            @click="$emit('checkout')"
        >
            <RefreshCw v-if="isSubmitting" class="w-5 h-5 animate-spin" />
            <span>{{ isSubmitting ? $t('pos.saving_in_progress') : $t('pos.checkout_instant_btn') }} (Enter / F2)</span>
        </button>
    </div>
</template>
