<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm rtl"
        dir="rtl"
        @click.self="$emit('close')"
      >
        <div
          class="bg-white dark:bg-slate-950 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col font-tajawal border border-slate-200 dark:border-slate-800"
        >
          <!-- Header -->
          <div
            class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50"
          >
            <div class="flex items-center gap-3">
              <div class="p-2 bg-primary/10 text-primary rounded-xl">
                <CreditCard class="w-5 h-5" />
              </div>
              <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">
                {{ $t('pos.multi_payment_title') }}
              </h3>
            </div>
            <button
              @click="$emit('close')"
              class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[60vh] space-y-6">
            <!-- Net Total Display -->
            <div
              class="flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800"
            >
              <span class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                {{ $t('pos.net_total_required') }}
              </span>
              <div
                class="text-4xl font-bold text-green-600 dark:text-green-500 tracking-tight flex items-baseline gap-1"
              >
                {{ formatMoney(net) }}
                <span class="text-lg text-green-600/70 font-normal">{{ $t('common.currency') }}</span>
              </div>
            </div>

            <!-- Payment Entries List -->
            <div class="space-y-3">
              <div
                v-for="(entry, index) in localPayments"
                :key="index"
                data-testid="pos-split-row"
                class="flex items-start gap-3 p-3 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl"
              >
                <div class="flex-1 space-y-1">
                  <label class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ $t('pos.payment_method_label') }}
                  </label>
                  <select
                    v-model="entry.method"
                    data-testid="pos-split-method"
                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary block p-2.5"
                  >
                    <option v-for="method in paymentMethods" :key="method.key" :value="method.key">
                      {{ method.label }}
                    </option>
                  </select>
                </div>

                <div class="flex-1 space-y-1">
                  <label class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ $t('pos.payment_amount') }}
                  </label>
                  <div class="relative">
                    <input
                      type="number"
                      v-model="entry.amount"
                      data-testid="pos-split-amount"
                      min="0"
                      step="0.001"
                      class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary block p-2.5"
                    />
                  </div>
                </div>

                <div class="flex flex-col gap-2 pt-5">
                  <button
                    @click="fillRemaining(index)"
                    class="px-2 py-1.5 text-xs font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors whitespace-nowrap"
                  >
                    {{ $t('pos.fill_remaining') }}
                  </button>
                  <button
                    @click="removePaymentEntry(index)"
                    :disabled="localPayments.length <= 1"
                    class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <Trash2 class="w-4 h-4 mx-auto" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Add Payment Method Row -->
            <div class="space-y-2">
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">
                {{ $t('pos.add_payment_method') }}
              </label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="method in paymentMethods"
                  :key="'add-' + method.key"
                  @click="addPaymentEntry(method.key)"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-full hover:border-primary hover:text-primary dark:hover:border-primary transition-colors"
                >
                  <component :is="method.icon" class="w-4 h-4" />
                  <span>{{ method.label }}</span>
                  <Plus class="w-3.5 h-3.5 ms-1" />
                </button>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-6 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
            <div class="flex items-center justify-between mb-4">
              <div class="text-sm font-medium text-slate-600 dark:text-slate-400 flex items-center gap-1">
                {{ $t('pos.total_paid') }}:
                <span
                  :class="
                    dCmp(totalPaid, net) === 0
                      ? 'text-green-600 dark:text-green-500'
                      : 'text-slate-900 dark:text-slate-100'
                  "
                  class="font-bold ms-1 text-lg"
                >
                  {{ formatMoney(totalPaid) }}
                </span>
              </div>
              <div class="text-sm font-medium text-slate-600 dark:text-slate-400 flex items-center gap-2">
                {{ $t('pos.remaining_amount') }}:
                <span
                  v-if="dCmp(remaining, '0') === 0"
                  class="text-green-600 dark:text-green-500 flex items-center gap-1 font-bold text-lg"
                >
                  {{ formatMoney(0) }}
                  <CheckCircle2 class="w-4 h-4" />
                </span>
                <span v-else class="text-red-500 font-bold text-lg">
                  {{ formatMoney(remaining) }}
                </span>
              </div>
            </div>

            <div
              v-if="showChange"
              class="flex items-center justify-between mb-4 px-3 py-2 rounded-xl bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30"
            >
              <span class="text-sm font-medium text-green-700 dark:text-green-400">
                {{ $t('pos.change_due_label') }}
              </span>
              <span class="font-bold text-lg text-green-700 dark:text-green-400">
                {{ formatMoney(change) }}
              </span>
            </div>

            <p
              v-if="nonCashExceedsDue"
              data-testid="pos-split-non-cash-error"
              role="alert"
              class="mb-4 px-3 py-2 rounded-xl text-sm font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30"
            >
              {{ $t('pos.split_non_cash_exceeds_due', { non_cash: formatMoney(nonCashTotal), net: formatMoney(net) }) }}
            </p>

            <button
              data-testid="pos-split-confirm"
              @click="handleConfirm"
              :disabled="!isValid"
              class="w-full flex items-center justify-center gap-2 py-3 px-4 bg-primary hover:bg-primary/90 text-white rounded-xl font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <CheckCircle2 class="w-5 h-5" />
              {{ $t('pos.confirm_multi_payment') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { X, Plus, Trash2, Banknote, Zap, Smartphone, CreditCard, Building2, CheckCircle2 } from 'lucide-vue-next';
import { useTrans } from '../../Composables/useTrans';
import { useFormatters } from '../../Composables/useFormatters';
import { normalize, dSub, dSum, dCmp, dMax0, isPositive } from '../../helpers/decimal';

const props = defineProps({
  show: { type: Boolean, default: false },
  netTotal: { type: [String, Number], default: '0.000' },
  payments: { type: Array, default: () => [] },
  paymentType: {
    type: String,
    default: 'cash',
    validator: (value) => ['cash', 'partial'].includes(value),
  },
});

const emit = defineEmits(['close', 'confirm']);

const { t } = useTrans();
const { formatMoney } = useFormatters();

const paymentMethods = computed(() => [
  { key: 'cash', label: t('pos.payment_cash'), icon: Banknote },
  { key: 'instapay', label: t('pos.instapay'), icon: Zap },
  { key: 'e_wallet', label: t('pos.smart_wallet'), icon: Smartphone },
  { key: 'visa', label: t('pos.visa_card'), icon: CreditCard },
  { key: 'bank_transfer', label: t('pos.bank_transfer'), icon: Building2 },
]);

const localPayments = ref([]);

const net = computed(() => normalize(props.netTotal));
const isPartial = computed(() => props.paymentType === 'partial');

watch(
  () => props.show,
  (val) => {
    if (val) {
      if (props.payments && props.payments.length > 0) {
        localPayments.value = props.payments.map((p) => ({ method: p.method, amount: normalize(p.amount) }));
      } else {
        localPayments.value = [{ method: 'cash', amount: isPartial.value ? '0.000' : net.value }];
      }
    }
  }
);

const amountOf = (p) => normalize(p.amount);

const totalPaid = computed(() => dSum(localPayments.value.map(amountOf)));
const cashTotal = computed(() => dSum(localPayments.value.filter((p) => p.method === 'cash').map(amountOf)));
const nonCashTotal = computed(() => dSub(totalPaid.value, cashTotal.value));
const remaining = computed(() => dMax0(dSub(net.value, totalPaid.value)));
const change = computed(() => dMax0(dSub(totalPaid.value, net.value)));

const nonCashExceedsDue = computed(() => !isPartial.value && dCmp(nonCashTotal.value, net.value) > 0);
const showChange = computed(() => !isPartial.value && !nonCashExceedsDue.value && isPositive(change.value));

const isValid = computed(() => {
  if (localPayments.value.length === 0) return false;
  if (localPayments.value.some((p) => dCmp(amountOf(p), '0') < 0)) return false;

  if (isPartial.value) {
    return isPositive(totalPaid.value) && dCmp(totalPaid.value, net.value) < 0;
  }

  return dCmp(totalPaid.value, net.value) >= 0 && !nonCashExceedsDue.value;
});

function addPaymentEntry(methodKey) {
  localPayments.value.push({ method: methodKey, amount: remaining.value });
}

function removePaymentEntry(index) {
  if (localPayments.value.length > 1) {
    localPayments.value.splice(index, 1);
  }
}

function fillRemaining(index) {
  const othersTotal = dSum(localPayments.value.filter((_, i) => i !== index).map(amountOf));
  localPayments.value[index].amount = dMax0(dSub(net.value, othersTotal));
}

function handleConfirm() {
  if (isValid.value) {
    emit(
      'confirm',
      localPayments.value.map((p) => ({ method: p.method, amount: amountOf(p) }))
    );
    emit('close');
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
