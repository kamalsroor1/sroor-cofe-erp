<script setup>
import POSCartTable from './POSCartTable.vue';
import POSOrderTabs from './POSOrderTabs.vue';
import POSCheckoutPanel from './POSCheckoutPanel.vue';
import POSPhoneCartBar from './POSPhoneCartBar.vue';
import BottomSheet from '../Common/BottomSheet.vue';
import { computed, ref, watch } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';

const props = defineProps({
  phone: { type: Boolean, default: false },
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
  discountSummary: { type: Object, default: null },
  maxDiscountPercent: { type: String, default: null },
  canOverrideDiscount: { type: Boolean, default: false },
});

const paymentType = defineModel('paymentType', { type: String, default: 'cash' });
const paymentMethod = defineModel('paymentMethod', { type: String, default: 'cash' });
const cashReceived = defineModel('cashReceived', { type: [String, Number], default: '0.000' });

const emit = defineEmits([
  'increase-qty',
  'decrease-qty',
  'update-qty',
  'update-price',
  'update-discount',
  'remove-item',
  'switch-order',
  'create-order',
  'close-order',
  'apply-discount',
  'submit',
  'open-expenses',
  'open-multi-payment',
]);

const { formatMoney, currencyLabel } = useFormatters();

// Phones: the ticket and the payment are two sheets over the catalog. null = both closed.
const sheet = ref(null);
const closePaymentSheet = () => {
  sheet.value = 'cart';
};

// An empty ticket (last line removed, or the sale just went through) has nothing left to show.
watch(
  () => props.cart.length,
  (length) => {
    if (length === 0) sheet.value = null;
  }
);
watch(
  () => props.phone,
  (isPhone) => {
    if (!isPhone) sheet.value = null;
  }
);

const checkoutProps = computed(() => ({
  cartCount: props.cart.length,
  subtotal: props.subtotal,
  discountAmount: props.discountAmount,
  discountValue: props.discountValue,
  discountType: props.discountType,
  customerExpensesTotal: props.customerExpensesTotal,
  netTotal: props.netTotal,
  changeDue: props.changeDue,
  cartEmpty: props.cart.length === 0,
  walkIn: props.walkIn,
  isSubmitting: props.isSubmitting,
  offline: props.offline,
  expensesCount: props.expensesCount,
  discountSummary: props.discountSummary,
  maxDiscountPercent: props.maxDiscountPercent,
  canOverrideDiscount: props.canOverrideDiscount,
}));
</script>

<template>
  <div v-if="phone" class="order-4 shrink-0">
    <POSPhoneCartBar
      v-if="cart.length > 0"
      :count="cart.length"
      :total="netTotal"
      :is-submitting="isSubmitting"
      @open-cart="sheet = 'cart'"
      @checkout="sheet = 'pay'"
    />

    <BottomSheet
      :show="sheet === 'cart'"
      size="tall"
      :title="$t('pos.phone_cart_sheet_title')"
      test-id="pos-phone-cart-sheet"
      @close="sheet = null"
    >
      <div class="p-3 h-full">
        <POSCartTable
          :cart="cart"
          :total-qty="totalQty"
          @increase-qty="emit('increase-qty', $event)"
          @decrease-qty="emit('decrease-qty', $event)"
          @update-qty="emit('update-qty', $event)"
          @update-price="emit('update-price', $event)"
          @update-discount="emit('update-discount', $event)"
          @remove-item="emit('remove-item', $event)"
        >
          <template #header>
            <POSOrderTabs
              :orders="orders"
              :active-order-id="activeOrderId"
              @switch-order="emit('switch-order', $event)"
              @create-order="emit('create-order')"
              @close-order="emit('close-order', $event)"
            />
          </template>
        </POSCartTable>
      </div>

      <template #footer>
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-sm font-black text-slate-900 dark:text-white">{{ $t('pos.giant_net_total') }}</span>
          <span class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
            {{ formatMoney(netTotal) }}
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ currencyLabel }}</span>
          </span>
        </div>
        <button
          type="button"
          class="w-full min-h-14 rounded-2xl bg-theme-primary text-slate-950 font-black text-base shadow-md cursor-pointer active:scale-[0.98] transition"
          data-testid="pos-phone-sheet-checkout"
          @click="sheet = 'pay'"
        >
          {{ $t('pos.phone_checkout_btn') }}
        </button>
      </template>
    </BottomSheet>

    <BottomSheet
      :show="sheet === 'pay'"
      size="tall"
      :title="$t('pos.phone_payment_sheet_title')"
      test-id="pos-phone-payment-sheet"
      @close="closePaymentSheet"
    >
      <POSCheckoutPanel
        v-model:payment-type="paymentType"
        v-model:payment-method="paymentMethod"
        v-model:cash-received="cashReceived"
        v-bind="checkoutProps"
        class="border-s-0! shadow-none!"
        @apply-discount="emit('apply-discount', $event)"
        @submit="emit('submit', $event)"
        @open-expenses="emit('open-expenses')"
        @open-multi-payment="emit('open-multi-payment')"
      />
    </BottomSheet>
  </div>

  <section
    v-else
    class="flex-1 flex flex-col p-2 bg-slate-50 dark:bg-slate-950 border-e border-slate-200 dark:border-slate-800 overflow-visible lg:overflow-hidden order-2 lg:order-1 min-w-0 transition-all duration-200 min-h-[380px] lg:min-h-0"
  >
    <div class="flex-1 overflow-visible lg:overflow-hidden flex flex-col min-h-[160px] lg:min-h-[96px]">
      <POSCartTable
        :cart="cart"
        :total-qty="totalQty"
        @increase-qty="$emit('increase-qty', $event)"
        @decrease-qty="$emit('decrease-qty', $event)"
        @update-qty="$emit('update-qty', $event)"
        @update-price="$emit('update-price', $event)"
        @update-discount="$emit('update-discount', $event)"
        @remove-item="$emit('remove-item', $event)"
      >
        <template #header>
          <POSOrderTabs
            class="pb-1!"
            :orders="orders"
            :active-order-id="activeOrderId"
            @switch-order="$emit('switch-order', $event)"
            @create-order="$emit('create-order')"
            @close-order="$emit('close-order', $event)"
          />
        </template>
      </POSCartTable>
    </div>

    <!-- Fixed footer: total, payment and the save buttons always sit below the cart, never over it -->
    <div class="shrink-0 pt-1 border-t border-slate-200 dark:border-slate-800" data-testid="pos-cart-footer">
      <POSCheckoutPanel
        compact
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
        :discount-summary="discountSummary"
        :max-discount-percent="maxDiscountPercent"
        :can-override-discount="canOverrideDiscount"
        @apply-discount="$emit('apply-discount', $event)"
        @submit="$emit('submit', $event)"
        @open-expenses="$emit('open-expenses')"
        @open-multi-payment="$emit('open-multi-payment')"
      />
    </div>
  </section>
</template>
