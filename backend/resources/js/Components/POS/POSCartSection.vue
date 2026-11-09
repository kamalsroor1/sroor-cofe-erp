<script setup>
import POSCartTable from './POSCartTable.vue';
import POSOrderTabs from './POSOrderTabs.vue';
import POSCheckoutPanel from './POSCheckoutPanel.vue';

defineProps({
  cart: { type: Array, required: true },
  totalQty: { type: Number, default: 0 },
  orders: { type: Array, default: () => [] },
  activeOrderId: { type: String, default: '' },
  subtotal: { type: Number, default: 0 },
  discountAmount: { type: Number, default: 0 },
  discountValue: { type: [String, Number], default: '0' },
  discountType: { type: String, default: 'percentage' },
  customerExpensesTotal: { type: Number, default: 0 },
  netTotal: { type: Number, default: 0 },
  changeDue: { type: Number, default: 0 },
  walkIn: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
  offline: { type: Boolean, default: false },
  expensesCount: { type: Number, default: 0 },
});

const paymentType = defineModel('paymentType', { type: String, default: 'cash' });
const paymentMethod = defineModel('paymentMethod', { type: String, default: 'cash' });
const cashReceived = defineModel('cashReceived', { type: [String, Number], default: '0.000' });

defineEmits([
  'increase-qty',
  'decrease-qty',
  'update-qty',
  'update-price',
  'remove-item',
  'switch-order',
  'create-order',
  'close-order',
  'apply-discount',
  'submit',
  'open-expenses',
  'open-multi-payment',
]);
</script>

<template>
  <section
    class="flex-1 flex flex-col justify-between p-3.5 bg-slate-50 dark:bg-slate-950 border-e border-slate-200 dark:border-slate-800 overflow-visible lg:overflow-hidden order-2 lg:order-1 min-w-0 transition-all duration-200 min-h-[380px] lg:min-h-0"
  >
    <div class="flex-1 overflow-visible lg:overflow-hidden flex flex-col min-h-[160px] lg:min-h-[220px]">
      <POSCartTable
        :cart="cart"
        :total-qty="totalQty"
        @increase-qty="$emit('increase-qty', $event)"
        @decrease-qty="$emit('decrease-qty', $event)"
        @update-qty="$emit('update-qty', $event)"
        @update-price="$emit('update-price', $event)"
        @remove-item="$emit('remove-item', $event)"
      >
        <template #header>
          <POSOrderTabs
            :orders="orders"
            :active-order-id="activeOrderId"
            @switch-order="$emit('switch-order', $event)"
            @create-order="$emit('create-order')"
            @close-order="$emit('close-order', $event)"
          />
        </template>
      </POSCartTable>
    </div>

    <div class="shrink-0 pt-2 border-t border-slate-200 dark:border-slate-800">
      <POSCheckoutPanel
        v-model:payment-type="paymentType"
        v-model:payment-method="paymentMethod"
        v-model:cash-received="cashReceived"
        :cart-count="cart.length"
        :subtotal="subtotal"
        :discount-amount="discountAmount"
        :discount-value="discountValue"
        :discount-type="discountType"
        :customer-expenses-total="customerExpensesTotal"
        :net-total="netTotal"
        :change-due="changeDue"
        :cart-empty="cart.length === 0"
        :walk-in="walkIn"
        :is-submitting="isSubmitting"
        :offline="offline"
        :expenses-count="expensesCount"
        @apply-discount="$emit('apply-discount', $event)"
        @submit="$emit('submit', $event)"
        @open-expenses="$emit('open-expenses')"
        @open-multi-payment="$emit('open-multi-payment')"
      />
    </div>
  </section>
</template>
