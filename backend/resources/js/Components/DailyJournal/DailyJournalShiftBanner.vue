<template>
  <div>
    <!-- Active Open Shift Banner -->
    <div
      v-if="activeShift"
      class="p-4 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-950 border border-emerald-500/30 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4 font-tajawal"
    >
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0">
          <ShieldCheck class="w-5 h-5" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-black text-white font-mono">{{ activeShift.shift_number }}</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
              {{ $t('treasury.active_open_shift_badge') }}
            </span>
          </div>
          <p class="text-[11px] text-slate-400 mt-0.5">
            {{ $t('treasury.cashier_label') }}: <span class="font-bold text-slate-200">{{ activeShift.user_name || $t('treasury.cashier_label') }}</span> — {{ $t('treasury.opened_at_time') }} <span class="font-mono text-slate-300">{{ activeShift.opened_at }}</span>
          </p>
        </div>
      </div>

      <div class="flex items-center gap-4 self-stretch md:self-auto justify-between md:justify-end border-t md:border-t-0 border-slate-800 pt-2 md:pt-0">
        <div class="text-start md:text-end">
          <span class="text-[10px] text-slate-400 block font-bold">{{ $t('treasury.opening_float_balance') }}</span>
          <span class="text-sm font-black text-theme-primary font-mono">{{ formatMoney(activeShift.opening_cash_balance) }} {{ $t('common.currency') }}</span>
        </div>

        <button
          type="button"
          @click="$emit('print-z')"
          class="min-h-[38px] px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer active:scale-95"
        >
          <Printer class="w-3.5 h-3.5 text-theme-primary" />
          <span>{{ $t('treasury.print_z_report') }}</span>
        </button>
      </div>
    </div>

    <!-- No Open Shift Alert -->
    <div
      v-else
      class="p-4 rounded-2xl bg-theme-light border border-theme-border shadow-md flex items-center justify-between gap-3 font-tajawal"
    >
      <div class="flex items-center gap-3">
        <AlertCircle class="w-5 h-5 text-theme-primary shrink-0" />
        <span class="text-xs font-bold text-theme-primary">
          {{ $t('treasury.no_open_shift_warning') }}
        </span>
      </div>
      <button
        type="button"
        @click="$emit('open-shift')"
        class="min-h-[38px] px-4 py-1.5 bg-theme-primary text-white font-black text-xs rounded-xl shadow-sm cursor-pointer active:scale-95"
      >
        {{ $t('treasury.open_shift_now') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ShieldCheck, AlertCircle, Printer } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  activeShift: { type: Object, default: null },
});

defineEmits(['print-z', 'open-shift']);
</script>
