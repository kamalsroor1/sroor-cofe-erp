<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm rtl" dir="rtl" @click.self="$emit('close')">
        <div class="bg-white dark:bg-slate-950 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col font-tajawal border border-slate-200 dark:border-slate-800">
          
          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-primary/10 text-primary rounded-xl">
                <CreditCard class="w-5 h-5" />
              </div>
              <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">
                {{ $t('pos.multi_payment_title') }}
              </h3>
            </div>
            <button @click="$emit('close')" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors">
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 overflow-y-auto max-h-[60vh] space-y-6">
            
            <!-- Net Total Display -->
            <div class="flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800">
              <span class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                {{ $t('pos.net_total_required') }}
              </span>
              <div class="text-4xl font-bold text-green-600 dark:text-green-500 tracking-tight flex items-baseline gap-1">
                {{ formatMoney(netTotal) }} <span class="text-lg text-green-600/70 font-normal">{{ $t('common.currency') }}</span>
              </div>
            </div>

            <!-- Payment Entries List -->
            <div class="space-y-3">
              <div v-for="(entry, index) in localPayments" :key="index" 
                   class="flex items-start gap-3 p-3 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl">
                
                <div class="flex-1 space-y-1">
                  <label class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ $t('pos.payment_method_label') }}
                  </label>
                  <select v-model="entry.method" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary block p-2.5">
                    <option v-for="method in paymentMethods" :key="method.key" :value="method.key">
                      {{ method.emoji }} {{ method.label }}
                    </option>
                  </select>
                </div>

                <div class="flex-1 space-y-1">
                  <label class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ $t('pos.payment_amount') }}
                  </label>
                  <div class="relative">
                    <input type="number" v-model="entry.amount" min="0" step="0.001"
                           class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-sm rounded-lg focus:ring-2 focus:ring-primary focus:border-primary block p-2.5" />
                  </div>
                </div>

                <div class="flex flex-col gap-2 pt-5">
                  <button @click="fillRemaining(index)" 
                          class="px-2 py-1.5 text-xs font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors whitespace-nowrap">
                    {{ $t('pos.fill_remaining') }}
                  </button>
                  <button @click="removePaymentEntry(index)" 
                          :disabled="localPayments.length <= 1"
                          class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
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
                <button v-for="method in paymentMethods" :key="'add-'+method.key"
                        @click="addPaymentEntry(method.key)"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-full hover:border-primary hover:text-primary dark:hover:border-primary transition-colors">
                  <span>{{ method.emoji }}</span>
                  <span>{{ method.label }}</span>
                  <Plus class="w-3.5 h-3.5 ml-1" />
                </button>
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="p-6 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
            <div class="flex items-center justify-between mb-4">
              <div class="text-sm font-medium text-slate-600 dark:text-slate-400 flex items-center gap-1">
                {{ $t('pos.total_paid') }}:
                <span :class="{'text-green-600 dark:text-green-500': totalPaid === netTotal, 'text-slate-900 dark:text-slate-100': totalPaid !== netTotal}" class="font-bold ml-1 text-lg">
                  {{ formatMoney(totalPaid) }}
                </span>
              </div>
              <div class="text-sm font-medium text-slate-600 dark:text-slate-400 flex items-center gap-2">
                {{ $t('pos.remaining_amount') }}:
                <span v-if="remaining === 0" class="text-green-600 dark:text-green-500 flex items-center gap-1 font-bold text-lg">
                  {{ formatMoney(0) }}
                  <CheckCircle2 class="w-4 h-4" />
                </span>
                <span v-else class="text-red-500 font-bold text-lg">
                  {{ formatMoney(remaining) }}
                </span>
              </div>
            </div>

            <button @click="handleConfirm" :disabled="!isValid"
                    class="w-full flex items-center justify-center gap-2 py-3 px-4 bg-primary hover:bg-primary/90 text-white rounded-xl font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
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

const props = defineProps({
  show: { type: Boolean, default: false },
  netTotal: { type: Number, default: 0 },
  payments: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'confirm']);

const { t } = useTrans();
const { formatMoney } = useFormatters();

const paymentMethods = [
  { key: 'cash', label: t('pos.payment_cash'), icon: 'Banknote', emoji: '💵' },
  { key: 'instapay', label: t('pos.instapay'), icon: 'Zap', emoji: '⚡' },
  { key: 'e_wallet', label: t('pos.smart_wallet'), icon: 'Smartphone', emoji: '📱' },
  { key: 'visa', label: t('pos.visa_card'), icon: 'CreditCard', emoji: '💳' },
  { key: 'bank_transfer', label: t('pos.bank_transfer'), icon: 'Building2', emoji: '🏦' },
];

const localPayments = ref([]);

watch(() => props.show, (val) => {
  if (val) {
    if (props.payments && props.payments.length > 0) {
      localPayments.value = JSON.parse(JSON.stringify(props.payments));
    } else {
      localPayments.value = [{ method: 'cash', amount: props.netTotal }];
    }
  }
});

const totalPaid = computed(() => localPayments.value.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0));
const remaining = computed(() => Math.max(0, props.netTotal - totalPaid.value));
const isValid = computed(() => totalPaid.value >= props.netTotal && localPayments.value.length > 0);

function addPaymentEntry(methodKey) {
  localPayments.value.push({ method: methodKey, amount: remaining.value > 0 ? Number(remaining.value.toFixed(3)) : 0 });
}

function removePaymentEntry(index) {
  if (localPayments.value.length > 1) {
    localPayments.value.splice(index, 1);
  }
}

function fillRemaining(index) {
  const othersTotal = localPayments.value.reduce((sum, p, i) => i === index ? sum : sum + (parseFloat(p.amount) || 0), 0);
  const rem = Math.max(0, props.netTotal - othersTotal);
  localPayments.value[index].amount = Number(rem.toFixed(3));
}

function handleConfirm() {
  if (isValid.value) {
    emit('confirm', JSON.parse(JSON.stringify(localPayments.value)));
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
