<script setup>
import { ShoppingCart } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

defineProps({
  count: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['open-cart', 'checkout']);

const { formatMoney } = useFormatters();
</script>

<template>
  <div
    class="flex items-stretch gap-2 px-3 pt-2 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] pb-[max(0.5rem,env(safe-area-inset-bottom,0.5rem))] font-tajawal select-none"
    data-testid="pos-phone-cart-bar"
  >
    <button
      type="button"
      class="flex-1 min-w-0 min-h-14 px-3 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center gap-3 text-start cursor-pointer active:scale-[0.98] transition"
      data-testid="pos-phone-cart-open"
      aria-haspopup="dialog"
      @click="$emit('open-cart')"
    >
      <span class="relative shrink-0">
        <ShoppingCart class="w-6 h-6 text-theme-primary" aria-hidden="true" />
        <span
          class="absolute -top-2 -end-2 min-w-5 h-5 px-1 rounded-full bg-theme-primary text-slate-950 text-[11px] font-black font-mono flex items-center justify-center"
          data-testid="pos-phone-cart-count"
          >{{ count }}</span
        >
      </span>
      <span class="min-w-0 flex-1 leading-tight">
        <span class="block text-xs font-bold text-slate-500 dark:text-slate-400 truncate">{{
          $t('pos.phone_view_cart')
        }}</span>
        <span
          class="block text-lg font-black font-mono text-emerald-600 dark:text-emerald-400 truncate"
          data-testid="pos-phone-cart-total"
        >
          {{ formatMoney(total) }}
          <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
        </span>
      </span>
    </button>

    <button
      type="button"
      class="shrink-0 min-h-14 px-5 rounded-2xl bg-theme-primary text-slate-950 font-black text-base shadow-md flex items-center justify-center gap-2 cursor-pointer active:scale-[0.98] transition disabled:opacity-40"
      data-testid="pos-phone-checkout-btn"
      :disabled="isSubmitting"
      @click="$emit('checkout')"
    >
      {{ $t('pos.phone_checkout_btn') }}
    </button>
  </div>
</template>
