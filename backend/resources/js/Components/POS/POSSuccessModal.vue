<script setup>
import { useMoney } from '@/Composables/useMoney';

const props = defineProps({
    show: { type: Boolean, default: false },
    invoice: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const { formatMoney } = useMoney();
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-zoom">
            <div
                v-if="show && invoice"
                @click="emit('close')"
                class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-tajawal select-none"
            >
                <div @click.stop class="w-full max-w-sm bg-white dark:bg-slate-900 border border-emerald-500/30 rounded-3xl p-6 sm:p-7 shadow-2xl text-center space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="w-16 h-16 rounded-3xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-3xl flex items-center justify-center mx-auto shadow-xs">
                        ✓
                    </div>

                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">{{ $t('pos.invoice_saved_success') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5 font-bold">
                            {{ $t('pos.invoice_number') }}: <span class="text-emerald-600 dark:text-emerald-400 font-black">#{{ invoice.invoice_number }}</span>
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 text-xs space-y-2 font-mono">
                        <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                            <span class="font-tajawal">{{ $t('common.total') }}:</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">{{ formatMoney(invoice.net_total) }} {{ $t('common.currency') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                            <span class="font-tajawal">{{ $t('common.paid') }}:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ formatMoney(invoice.paid_amount) }} {{ $t('common.currency') }}</span>
                        </div>
                        <div v-if="invoice.remaining_amount > 0" class="flex items-center justify-between text-rose-600 dark:text-rose-400">
                            <span class="font-tajawal">{{ $t('pos.amount_remaining_on_acc') }}:</span>
                            <span class="font-black">{{ formatMoney(invoice.remaining_amount) }} {{ $t('common.currency') }}</span>
                        </div>
                    </div>

                    <!-- Print & Actions Buttons -->
                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <a
                            :href="invoice.print_thermal_url"
                            target="_blank"
                            class="h-11 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 transition active:scale-95 cursor-pointer"
                        >
                            <span>🖨️</span>
                            <span>{{ $t('pos.print_thermal') }}</span>
                        </a>

                        <a
                            :href="invoice.print_a4_url"
                            target="_blank"
                            class="h-11 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-black flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700 transition active:scale-95 cursor-pointer"
                        >
                            <span>📄</span>
                            <span>{{ $t('pos.print_a4') }}</span>
                        </a>
                    </div>

                    <button
                        @click="emit('close')"
                        type="button"
                        class="w-full h-12 rounded-2xl btn-primary-theme font-black text-xs transition transform active:scale-95 cursor-pointer shadow-theme-primary"
                    >
                        {{ $t('pos.new_sale_invoice_btn') }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
