<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4 font-tajawal no-print">
    <!-- Breadcrumb & Status Row -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800/80 pb-3">
      <div class="flex items-center gap-2 text-xs">
        <router-link to="/invoices" class="text-slate-500 hover:text-theme-primary font-bold transition flex items-center gap-1">
          <span>{{ $t('invoices.title') }}</span>
          <span>/</span>
        </router-link>
        <span class="font-mono font-black text-theme-primary">#{{ invoice?.invoice_number }}</span>
      </div>

      <!-- Badges -->
      <div class="flex items-center gap-2">
        <span
          class="px-3 py-1 rounded-full text-xs font-black border flex items-center gap-1.5"
          :class="isCancelled ? 'bg-rose-500/10 text-rose-500 border-rose-500/30' : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30'"
        >
          <span>{{ isCancelled ? '🚫 ' + $t('invoices.cancelled_badge') : '✅ ' + $t('invoices.confirmed_badge') }}</span>
        </span>

        <span
          class="px-3 py-1 rounded-full text-xs font-bold border"
          :class="paymentBadgeClass"
        >
          {{ paymentBadgeLabel }}
        </span>
      </div>
    </div>

    <!-- Title & Mode Switcher Row -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-theme-primary/10 text-theme-primary flex items-center justify-center text-lg font-black shrink-0">
            🧾
          </div>
          <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
              <span>{{ $t('invoices.sales_invoice_title', { number: invoice?.invoice_number }) }}</span>
            </h1>
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
              <span>📅 {{ invoice?.invoice_date }}</span>
              <span>⏰ {{ invoiceTime }}</span>
              <span>🏬 {{ invoice?.store_name }}</span>
              <span>👤 {{ invoice?.cashier_name }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- View Mode Tabs Switcher -->
      <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-700 self-stretch md:self-auto overflow-x-auto">
        <button
          type="button"
          data-tab="interactive"
          @click="$emit('set-mode', 'interactive')"
          class="flex-1 md:flex-initial px-3.5 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer select-none active:scale-95"
          :class="activeMode === 'interactive' ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          {{ $t('invoices.view_mode_interactive') }}
        </button>

        <button
          type="button"
          data-tab="thermal"
          @click="$emit('set-mode', 'thermal')"
          class="flex-1 md:flex-initial px-3.5 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer select-none active:scale-95"
          :class="activeMode === 'thermal' ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          {{ $t('invoices.view_mode_thermal') }}
        </button>

        <button
          type="button"
          data-tab="a4"
          @click="$emit('set-mode', 'a4')"
          class="flex-1 md:flex-initial px-3.5 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer select-none active:scale-95"
          :class="activeMode === 'a4' ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          {{ $t('invoices.view_mode_a4') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useTrans } from '../../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  invoice: { type: Object, default: null },
  activeMode: { type: String, default: 'interactive' },
  invoiceTime: { type: String, default: '' },
  isCancelled: { type: Boolean, default: false },
});

defineEmits(['set-mode']);

const paymentBadgeClass = computed(() => {
  if (props.isCancelled) return 'bg-slate-500/10 text-slate-400 border-slate-500/30';
  const rem = parseFloat(props.invoice?.remaining_amount || 0);
  const paid = parseFloat(props.invoice?.paid_amount || 0);
  if (rem <= 0) return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30';
  if (paid > 0) return 'bg-amber-500/10 text-amber-500 border-amber-500/30';
  return 'bg-theme-light text-theme-primary border-theme-border';
});

const paymentBadgeLabel = computed(() => {
  const rem = parseFloat(props.invoice?.remaining_amount || 0);
  const paid = parseFloat(props.invoice?.paid_amount || 0);
  if (rem <= 0) return '💵 ' + t('invoices.payment_cash');
  if (paid > 0) return '⚖️ ' + t('invoices.payment_partial');
  return '📝 ' + t('invoices.payment_credit');
});
</script>
