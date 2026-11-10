<template>
  <aside
    class="flex flex-col justify-between p-2.5 bg-white dark:bg-slate-900 border-s border-slate-200 dark:border-slate-800 shadow-md space-y-2 font-tajawal select-none"
  >
    <!-- 1. Financial Breakdown Card -->
    <div
      class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-1.5"
    >
      <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
        <span>{{ $t('pos.subtotal_items', { count: cartCount }) }}</span>
        <span class="font-mono font-black text-xs text-slate-900 dark:text-white"
          >{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span
        >
      </div>

      <!-- Invoice discount (POS-07): percent or amount, quick presets, cap warning -->
      <div class="pt-1 border-t border-slate-200 dark:border-slate-800 space-y-1.5" data-testid="pos-discount-box">
        <div class="flex items-center justify-between gap-2 text-[11px] font-bold text-slate-600 dark:text-slate-400">
          <span>{{ $t('pos.discount_label') }}</span>
          <span class="font-mono font-black text-rose-500" data-testid="pos-discount-total"
            >-{{ formatMoney(discountAmount) }}</span
          >
        </div>

        <div class="flex items-center gap-1.5">
          <div
            role="group"
            :aria-label="$t('pos.discount_mode_label')"
            class="flex shrink-0 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700"
          >
            <button
              v-for="mode in discountModes"
              :key="mode.key"
              type="button"
              :data-testid="`pos-discount-mode-${mode.key}`"
              :aria-pressed="discountType === mode.key"
              :aria-label="mode.label"
              :title="mode.label"
              class="min-h-11 min-w-11 px-2 text-xs font-black transition active:scale-95 cursor-pointer"
              :class="
                discountType === mode.key
                  ? 'bg-theme-primary text-slate-950'
                  : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400'
              "
              @click="setDiscountMode(mode.key)"
            >
              {{ mode.symbol }}
            </button>
          </div>
          <input
            type="text"
            inputmode="decimal"
            data-pos-discount-input
            data-testid="pos-discount-input"
            :value="discountInputValue"
            :placeholder="$t('pos.discount_placeholder')"
            :aria-label="$t('pos.discount_input_label')"
            class="flex-1 min-w-0 min-h-11 px-2 text-center font-mono font-black text-sm bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
            @focus="$event.target.select()"
            @input="onDiscountInput"
            @keydown.enter.prevent="focusPosSearch()"
            @keydown.esc.prevent="focusPosSearch()"
          />
        </div>

        <div class="grid grid-cols-3 gap-1.5">
          <button
            v-for="preset in discountPresets"
            :key="preset"
            type="button"
            :data-testid="`pos-discount-preset-${preset}`"
            class="min-h-11 rounded-xl text-xs font-mono font-bold border transition active:scale-95 cursor-pointer"
            :class="
              isPresetActive(preset)
                ? 'bg-theme-primary text-slate-950 font-black border-theme-primary'
                : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'
            "
            @click="$emit('apply-discount', { value: preset, type: 'percentage' })"
          >
            {{ preset }}%
          </button>
        </div>

        <p
          v-if="capExceeded"
          role="status"
          data-testid="pos-discount-cap-warning"
          class="px-2 py-1.5 rounded-lg text-[11px] font-bold text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60"
        >
          {{ $t('pos.discount_cap_warning', { percent: discountPercentText, max: maxDiscountText }) }}
          {{ canOverrideDiscount ? $t('pos.discount_cap_can_override') : $t('pos.discount_cap_needs_approval') }}
        </p>
      </div>

      <!-- Customer Extra Expenses (Shipping / Services) -->
      <div
        class="flex items-center justify-between text-[11px] font-bold text-slate-600 dark:text-slate-400 pt-1 border-t border-slate-200/50 dark:border-slate-800/50"
      >
        <button
          type="button"
          @click="$emit('open-expenses')"
          class="flex items-center gap-1 min-h-11 pointer-coarse:min-h-11 md:min-h-0 text-[11px] font-bold text-theme-primary hover:underline cursor-pointer transition"
        >
          <Truck class="w-3 h-3" />
          <span>{{ $t('pos.extra_expenses_shipping') }}</span>
          <span
            v-if="expensesCount > 0"
            class="px-1.5 py-0.5 rounded-full bg-theme-primary/15 text-[9px] font-black text-theme-primary"
          >
            {{ expensesCount }}
          </span>
        </button>
        <span v-if="customerExpensesTotal > 0" class="font-mono font-black text-emerald-500"
          >+ {{ formatMoney(customerExpensesTotal) }}</span
        >
      </div>

      <!-- GIANT NET TOTAL DUE -->
      <div class="pt-1.5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <span class="text-xs font-black text-slate-900 dark:text-white">{{ $t('pos.giant_net_total') }}</span>
        <span class="text-xl font-black font-mono text-emerald-600 dark:text-emerald-400 tracking-tight">
          {{ formatMoney(netTotal) }}
          <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
        </span>
      </div>
    </div>

    <!-- 2. Payment Type (cash / credit / partial) -->
    <div class="grid grid-cols-3 gap-1.5">
      <button
        v-for="type in paymentTypes"
        :key="type.key"
        type="button"
        :data-testid="`pos-payment-type-${type.key}`"
        :disabled="type.key !== 'cash' && walkIn"
        :title="type.key !== 'cash' && walkIn ? $t('pos.walk_in_pay_full_hint') : undefined"
        @click="$emit('update:paymentType', type.key)"
        class="min-h-11 px-2 rounded-xl border text-center transition active:scale-95 cursor-pointer text-xs font-bold disabled:opacity-40 disabled:cursor-not-allowed"
        :class="paymentType === type.key ? type.activeClass : idleClass"
      >
        {{ type.label }}
      </button>
    </div>

    <p v-if="walkIn" class="text-[11px] text-slate-500 dark:text-slate-400" data-testid="pos-walk-in-hint">
      {{ $t('pos.walk_in_pay_full_hint') }}
    </p>

    <!-- 3. Payment methods, tender and change -->
    <div
      v-if="paymentType !== 'credit'"
      class="space-y-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800"
    >
      <div class="grid grid-cols-3 gap-1.5" role="group" :aria-label="$t('pos.payment_method_label')">
        <button
          v-for="m in methods"
          :key="m.key"
          type="button"
          :data-testid="`pos-method-${m.key}`"
          :aria-pressed="isMethodActive(m.key)"
          @click="$emit('update:paymentMethod', m.key)"
          class="min-h-11 px-1 rounded-lg text-[11px] font-bold border transition text-center cursor-pointer flex items-center justify-center gap-1"
          :class="
            isMethodActive(m.key)
              ? 'bg-theme-primary text-slate-950 font-black border-theme-primary'
              : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800'
          "
        >
          <component :is="m.icon" class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span class="truncate">{{ m.label }}</span>
        </button>
        <button
          type="button"
          data-testid="pos-split-payment-btn"
          :aria-label="$t('pos.multi_payment_title')"
          :title="$t('pos.multi_payment_title')"
          @click="$emit('open-multi-payment')"
          class="min-h-11 px-1 rounded-lg text-[11px] font-bold border transition text-center cursor-pointer flex items-center justify-center gap-1 bg-purple-500/10 border-purple-500/30 text-purple-600 dark:text-purple-400 hover:bg-purple-500/20"
        >
          <Split class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span class="truncate">{{ $t('pos.split_short') }}</span>
        </button>
      </div>

      <!-- Tender: typed for cash and for the partial deposit; non-cash full payments are the exact net -->
      <div v-if="showTender" class="space-y-1.5">
        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400" for="pos-cash-received">
          {{ paymentType === 'partial' ? $t('pos.paid_now_label') : $t('pos.tender_label') }}
        </label>
        <input
          id="pos-cash-received"
          type="text"
          inputmode="decimal"
          autocomplete="off"
          dir="ltr"
          data-testid="pos-cash-received"
          :value="cashReceived"
          @input="onTenderInput"
          @focus="$event.target.select()"
          :placeholder="$t('pos.cash_received_placeholder')"
          class="w-full h-12 px-3 text-center bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xl font-mono font-black text-slate-950 dark:text-white focus:outline-none focus:ring-2 focus:ring-theme-primary"
        />

        <div v-if="isCashMethod" class="grid grid-cols-5 gap-1.5">
          <button
            v-for="d in denominations"
            :key="d"
            type="button"
            :data-testid="`pos-tender-${d}`"
            @click="addDenomination(d)"
            class="min-h-11 rounded-lg border text-sm font-mono font-black transition active:scale-95 cursor-pointer bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-700"
          >
            {{ d }}
          </button>
          <button
            v-if="paymentType === 'cash'"
            type="button"
            data-testid="pos-tender-exact"
            @click="setExact"
            class="min-h-11 rounded-lg border text-[11px] font-black transition active:scale-95 cursor-pointer bg-emerald-500/10 border-emerald-500/40 text-emerald-700 dark:text-emerald-400"
          >
            {{ $t('pos.exact_amount') }}
          </button>
          <button
            v-else
            type="button"
            data-testid="pos-tender-clear"
            :aria-label="$t('pos.tender_clear')"
            @click="$emit('update:cashReceived', '0')"
            class="min-h-11 rounded-lg border transition active:scale-95 cursor-pointer bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 flex items-center justify-center"
          >
            <X class="w-4 h-4" aria-hidden="true" />
          </button>
        </div>
      </div>

      <!-- Partial: what is paid now vs what stays on the customer account -->
      <dl
        v-if="paymentType === 'partial'"
        data-testid="pos-partial-summary"
        class="grid grid-cols-2 gap-1.5 text-center"
      >
        <div class="p-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30">
          <dt class="text-[11px] font-bold text-slate-600 dark:text-slate-400">{{ $t('pos.paid_now_label') }}</dt>
          <dd class="text-lg font-black font-mono text-emerald-600 dark:text-emerald-400" data-testid="pos-paid-now">
            {{ formatMoney(paidNow) }}
          </dd>
        </div>
        <div class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/30">
          <dt class="text-[11px] font-bold text-slate-600 dark:text-slate-400">
            {{ $t('pos.remaining_on_account') }}
          </dt>
          <dd class="text-lg font-black font-mono text-amber-600 dark:text-amber-400" data-testid="pos-remaining">
            {{ formatMoney(remainingOnAccount) }}
          </dd>
        </div>
      </dl>

      <!-- Cash: change to hand back, or what is still due when the tender is short -->
      <div
        v-else-if="paymentType === 'cash' && isCashMethod"
        data-testid="pos-change-box"
        role="status"
        class="flex items-center justify-between gap-2 p-2.5 rounded-lg border"
        :class="
          changeDue < 0
            ? 'bg-rose-500/10 border-rose-500/40'
            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800'
        "
      >
        <span class="text-xs font-black text-slate-700 dark:text-slate-300">
          {{ changeDue < 0 ? $t('pos.tender_short_due') : $t('pos.change_due') }}
        </span>
        <span
          class="text-2xl font-black font-mono"
          data-testid="pos-change-amount"
          :class="
            changeDue < 0 ? 'text-rose-600 dark:text-rose-400' : changeDue > 0 ? 'text-emerald-500' : 'text-slate-400'
          "
        >
          {{ formatMoney(Math.abs(changeDue)) }}
        </span>
      </div>
    </div>

    <!-- 4. Execution Buttons (Always Pinned & Accessible) -->
    <div class="space-y-1.5 pt-1 sticky bottom-0 z-10 bg-white dark:bg-slate-900 pb-[env(safe-area-inset-bottom,0px)]">
      <p
        v-if="offline"
        data-testid="pos-checkout-offline-hint"
        role="status"
        class="flex items-center justify-center gap-1.5 px-2 py-1.5 rounded-lg text-[11px] font-black text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900/60"
      >
        <WifiOff class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
        <span>{{ $t('connectivity.checkout_blocked') }}</span>
      </p>

      <button
        type="button"
        data-testid="pos-checkout-submit"
        @click="$emit('submit', false)"
        :disabled="cartEmpty || isSubmitting || offline"
        class="w-full min-h-12 bg-theme-primary hover:opacity-95 text-slate-950 rounded-xl font-black text-sm transition-all duration-150 active:scale-[0.98] shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-30"
      >
        <span
          v-if="isSubmitting"
          class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"
        ></span>
        <CheckCircle2 v-else class="w-4 h-4 text-slate-950 stroke-[2.5]" />
        <span>{{ $t('pos.confirm_and_approve_f9') }}</span>
      </button>

      <button
        type="button"
        data-testid="pos-checkout-save-print"
        @click="$emit('submit', true)"
        :disabled="cartEmpty || isSubmitting || offline"
        class="w-full min-h-11 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg font-bold text-xs transition border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-30"
      >
        <Printer class="w-3.5 h-3.5" />
        <span>{{ $t('pos.save_and_print_btn') }}</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import {
  Printer,
  CheckCircle2,
  Truck,
  WifiOff,
  Split,
  X,
  Banknote,
  Zap,
  Smartphone,
  CreditCard,
  Building2,
} from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';
import { sanitizeAmountInput } from '../../helpers/posCheckout';
import { normalize, dAdd, dSub, dCmp, dMax0, dRoundTo, isZero, isPositive } from '../../helpers/decimal';
import { focusPosSearch } from '../../helpers/posFocus';
import { DISCOUNT_FIXED, DISCOUNT_PERCENT } from '../../helpers/posDiscount';

const { formatMoney } = useFormatters();
const { t } = useTrans();

const denominations = [50, 100, 200, 500];
const idleClass =
  'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';

// Method keys are the ones the checkout API accepts; e_wallet is what the server stores for smart wallets.
const methods = computed(() => [
  { key: 'cash', label: t('pos.method_cash'), icon: Banknote },
  { key: 'instapay', label: t('pos.instapay'), icon: Zap },
  { key: 'e_wallet', label: t('pos.smart_wallet'), icon: Smartphone },
  { key: 'visa', label: t('pos.visa_card'), icon: CreditCard },
  { key: 'bank_transfer', label: t('pos.bank_transfer'), icon: Building2 },
]);

const paymentTypes = computed(() => [
  {
    key: 'cash',
    label: t('pos.instant_cash'),
    activeClass: 'bg-emerald-500/10 border-emerald-500 text-emerald-600 dark:text-emerald-400 font-black shadow-2xs',
  },
  {
    key: 'credit',
    label: t('pos.credit_on_account'),
    activeClass: 'bg-rose-500/10 border-rose-500 text-rose-600 dark:text-rose-400 font-black shadow-2xs',
  },
  {
    key: 'partial',
    label: t('pos.partial_pay'),
    activeClass: 'bg-amber-500/10 border-amber-500 text-amber-600 dark:text-amber-400 font-black shadow-2xs',
  },
]);

const props = defineProps({
  cartCount: { type: Number, default: 0 },
  subtotal: { type: Number, default: 0 },
  discountAmount: { type: Number, default: 0 },
  discountValue: { type: [Number, String], default: 0 },
  discountType: { type: String, default: 'percentage' },
  customerExpensesTotal: { type: Number, default: 0 },
  netTotal: { type: Number, default: 0 },
  paymentType: { type: String, default: 'cash' },
  paymentMethod: { type: String, default: 'cash' },
  cashReceived: { type: [Number, String], default: 0 },
  changeDue: { type: Number, default: 0 },
  cartEmpty: { type: Boolean, default: true },
  walkIn: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
  offline: { type: Boolean, default: false },
  expensesCount: { type: Number, default: 0 },
  discountSummary: { type: Object, default: null },
  maxDiscountPercent: { type: String, default: null },
  canOverrideDiscount: { type: Boolean, default: false },
});

// POS-07: percent or amount; the presets are the quick percents (0 clears the discount).
const discountPresets = [0, 5, 10];
const discountModes = computed(() => [
  { key: DISCOUNT_PERCENT, symbol: '%', label: t('pos.discount_mode_percent') },
  { key: DISCOUNT_FIXED, symbol: t('common.currency'), label: t('pos.discount_mode_amount') },
]);
const discountInputValue = computed(() =>
  isPositive(String(props.discountValue ?? '0')) ? String(props.discountValue) : ''
);
const isPresetActive = (preset) =>
  props.discountType === DISCOUNT_PERCENT && Number(props.discountValue || 0) === preset;

const capExceeded = computed(() => !!props.discountSummary?.exceedsCap);
const discountPercentText = computed(() => String(Number(dRoundTo(props.discountSummary?.percentOfGross ?? '0', 2))));
const maxDiscountText = computed(() => String(Number(props.maxDiscountPercent ?? 0)));

const setDiscountMode = (type) => {
  const keep = String(props.discountValue ?? '0');
  const value = type === DISCOUNT_PERCENT && Number(keep) > 100 ? '100' : keep;
  emit('apply-discount', { value, type });
};

const onDiscountInput = (event) => {
  let clean = sanitizeAmountInput(event.target.value);
  if (props.discountType === DISCOUNT_PERCENT && Number(clean) > 100) clean = '100';
  if (clean !== event.target.value) event.target.value = clean;
  emit('apply-discount', { value: clean, type: props.discountType });
};

const isMethodActive = (key) => (props.paymentMethod === 'smart_wallet' ? 'e_wallet' : props.paymentMethod) === key;
const isCashMethod = computed(() => props.paymentMethod === 'cash');
const showTender = computed(
  () => props.paymentType === 'partial' || (props.paymentType === 'cash' && isCashMethod.value)
);

const paidNow = computed(() => dMax0(props.cashReceived));
const remainingOnAccount = computed(() => dMax0(dSub(String(props.netTotal), paidNow.value)));

const onTenderInput = (event) => {
  const clean = sanitizeAmountInput(event.target.value);
  // Keep the field in Latin digits even when the keyboard types Arabic-Indic ones.
  event.target.value = clean;
  emit('update:cashReceived', clean);
};

const setExact = () => emit('update:cashReceived', normalize(String(props.netTotal)));

// First tap on an untouched (exact) tender replaces it with the note handed over; later taps add up.
const addDenomination = (amount) => {
  const untouched =
    isZero(props.cashReceived) ||
    (props.paymentType === 'cash' && dCmp(props.cashReceived, String(props.netTotal)) === 0);
  emit('update:cashReceived', untouched ? normalize(String(amount)) : dAdd(props.cashReceived, String(amount)));
};

const emit = defineEmits([
  'apply-discount',
  'update:paymentType',
  'update:paymentMethod',
  'update:cashReceived',
  'submit',
  'open-expenses',
  'open-multi-payment',
]);
</script>
