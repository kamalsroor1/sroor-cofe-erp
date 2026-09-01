<template>
  <div class="flex items-center gap-1.5 pb-2.5 overflow-x-auto custom-scrollbar select-none font-tajawal shrink-0">
    <!-- List of Open / Held Orders -->
    <div
      v-for="order in orders"
      :key="order.id"
      class="group relative flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all cursor-pointer shrink-0"
      :class="order.id === activeOrderId
        ? 'bg-white dark:bg-slate-900 border-theme-primary text-theme-primary shadow-xs ring-1 ring-theme-primary/30'
        : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-800 border-slate-200 dark:border-slate-700/80 text-slate-600 dark:text-slate-400'"
      @click="$emit('switch-order', order.id)"
    >
      <!-- Icon Indicator -->
      <div class="flex items-center gap-1.5">
        <Receipt v-if="order.id === activeOrderId" class="w-3.5 h-3.5 text-theme-primary" />
        <Clock v-else class="w-3.5 h-3.5 text-slate-400" />
        <span>{{ order.title || $t('pos.order_tab_title', { number: order.number || 1 }) }}</span>
      </div>

      <!-- Items Count & Subtotal Badges (If cart has items) -->
      <div v-if="order.cart && order.cart.length > 0" class="flex items-center gap-1 text-[10px]">
        <span class="px-1.5 py-0.2 rounded-md bg-theme-primary/10 text-theme-primary font-mono font-bold">
          {{ order.cart.length }}
        </span>
        <span class="text-slate-400 font-mono text-[10px]">
          {{ formatMoney(getOrderSubtotal(order)) }}
        </span>
      </div>

      <!-- Close Order Button -->
      <button
        type="button"
        @click.stop="$emit('close-order', order)"
        class="w-4 h-4 rounded-md flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer ms-1 shrink-0"
        :title="$t('pos.confirm_close_order_title')"
      >
        <X class="w-3 h-3" />
      </button>
    </div>

    <!-- ➕ New Order Tab Button -->
    <button
      type="button"
      @click="$emit('create-order')"
      class="flex items-center gap-1 px-3 py-1.5 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 hover:border-theme-primary bg-slate-50 hover:bg-theme-light dark:bg-slate-900/50 text-slate-600 hover:text-theme-primary text-xs font-bold transition-all cursor-pointer shrink-0 shadow-2xs"
      :title="$t('pos.new_order_btn')"
    >
      <Plus class="w-3.5 h-3.5 text-theme-primary" />
      <span>{{ $t('pos.new_order_btn') }}</span>
    </button>
  </div>
</template>

<script setup>
import { Receipt, Clock, Plus, X } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const props = defineProps({
  orders: {
    type: Array,
    required: true,
  },
  activeOrderId: {
    type: String,
    required: true,
  },
});

defineEmits(['switch-order', 'create-order', 'close-order']);

const { formatMoney } = useFormatters();

const getOrderSubtotal = (order) => {
  if (!order || !Array.isArray(order.cart)) return 0;
  return order.cart.reduce((sum, item) => {
    const qty = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.unit_price) || 0;
    return sum + (qty * price);
  }, 0);
};
</script>
