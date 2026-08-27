<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" dir="rtl">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm dark:bg-slate-950/70" @click="$emit('close')"></div>

        <!-- Modal -->
        <div class="relative w-full max-w-2xl bg-white dark:bg-slate-950 rounded-2xl shadow-xl overflow-hidden flex flex-col font-tajawal">
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
            <div class="flex items-center gap-2 text-slate-800 dark:text-slate-100">
              <Truck class="w-5 h-5 text-primary" />
              <h2 class="text-lg font-bold">{{ $t('pos.expenses_modal_title') }}</h2>
            </div>
            <button @click="$emit('close')" class="p-1 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-200 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition-colors">
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-5 overflow-y-auto max-h-[70vh] flex-1 space-y-6">
            <!-- Quick Presets -->
            <div class="flex flex-wrap gap-2">
              <button @click="addPreset($t('pos.expense_shipping'), 50)" class="flex items-center gap-1.5 px-3 py-2 bg-primary/10 text-primary hover:bg-primary/20 rounded-lg text-sm font-medium transition-colors border border-primary/20">
                <Truck class="w-4 h-4" />
                {{ $t('pos.expense_shipping') }}
              </button>
              <button @click="addPreset($t('pos.expense_packaging'), 20)" class="flex items-center gap-1.5 px-3 py-2 bg-amber-500/10 text-amber-600 dark:text-amber-500 hover:bg-amber-500/20 rounded-lg text-sm font-medium transition-colors border border-amber-500/20">
                <Package class="w-4 h-4" />
                {{ $t('pos.expense_packaging') }}
              </button>
              <button @click="addPreset($t('pos.expense_tip'), 10)" class="flex items-center gap-1.5 px-3 py-2 bg-emerald-500/10 text-emerald-600 dark:text-emerald-500 hover:bg-emerald-500/20 rounded-lg text-sm font-medium transition-colors border border-emerald-500/20">
                <Gift class="w-4 h-4" />
                {{ $t('pos.expense_tip') }}
              </button>
              <button @click="addPreset($t('pos.expense_porter'), 30)" class="flex items-center gap-1.5 px-3 py-2 bg-blue-500/10 text-blue-600 dark:text-blue-500 hover:bg-blue-500/20 rounded-lg text-sm font-medium transition-colors border border-blue-500/20">
                <ArrowUp class="w-4 h-4" />
                {{ $t('pos.expense_porter') }}
              </button>
            </div>

            <!-- Custom Add Form -->
            <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
              <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">{{ $t('pos.expense_add_custom') }}</h3>
              <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                <div class="sm:col-span-5 space-y-1">
                  <label class="text-xs text-slate-500 dark:text-slate-400">{{ $t('pos.expense_title') }}</label>
                  <input v-model="newExpense.title" type="text" class="w-full px-3 py-2 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-primary outline-none text-slate-900 dark:text-white transition-colors" @keyup.enter="addCustomExpense" />
                </div>
                <div class="sm:col-span-3 space-y-1">
                  <label class="text-xs text-slate-500 dark:text-slate-400">{{ $t('pos.expense_amount') }}</label>
                  <input v-model.number="newExpense.amount" type="number" min="0" step="0.001" class="w-full px-3 py-2 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-primary outline-none text-slate-900 dark:text-white transition-colors" @keyup.enter="addCustomExpense" />
                </div>
                <div class="sm:col-span-3 space-y-1">
                  <label class="text-xs text-slate-500 dark:text-slate-400">{{ $t('pos.expense_paid_by') }}</label>
                  <select v-model="newExpense.paid_by" class="w-full px-3 py-2 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-primary outline-none text-slate-900 dark:text-white transition-colors">
                    <option value="customer_account">{{ $t('pos.expense_customer_account') }}</option>
                    <option value="treasury_cash">{{ $t('pos.expense_treasury') }}</option>
                  </select>
                </div>
                <div class="sm:col-span-1">
                  <button @click="addCustomExpense" :disabled="!newExpense.title || !newExpense.amount" class="w-full h-[38px] flex items-center justify-center bg-primary text-white rounded-lg hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <Plus class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Expenses List -->
            <div v-if="localExpenses.length > 0" class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
              <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                  <tr>
                    <th class="px-3 py-2 font-medium w-10">#</th>
                    <th class="px-3 py-2 font-medium">{{ $t('pos.expense_title') }}</th>
                    <th class="px-3 py-2 font-medium w-32">{{ $t('pos.expense_amount') }}</th>
                    <th class="px-3 py-2 font-medium w-40">{{ $t('pos.expense_paid_by') }}</th>
                    <th class="px-3 py-2 font-medium w-10"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                  <tr v-for="(expense, index) in localExpenses" :key="index" class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ index + 1 }}</td>
                    <td class="px-3 py-2">
                      <input v-model="expense.title" type="text" class="w-full bg-transparent border-0 border-b border-transparent hover:border-slate-300 dark:hover:border-slate-600 focus:border-primary focus:ring-0 px-1 py-0.5 text-slate-900 dark:text-white transition-colors" />
                    </td>
                    <td class="px-3 py-2">
                      <input v-model.number="expense.amount" type="number" min="0" step="0.001" class="w-full bg-transparent border-0 border-b border-transparent hover:border-slate-300 dark:hover:border-slate-600 focus:border-primary focus:ring-0 px-1 py-0.5 text-slate-900 dark:text-white transition-colors" />
                    </td>
                    <td class="px-3 py-2">
                      <select v-model="expense.paid_by" class="w-full bg-transparent border-0 border-b border-transparent hover:border-slate-300 dark:hover:border-slate-600 focus:border-primary focus:ring-0 px-1 py-0.5 text-slate-900 dark:text-white transition-colors">
                        <option value="customer_account">{{ $t('pos.expense_customer_account') }}</option>
                        <option value="treasury_cash">{{ $t('pos.expense_treasury') }}</option>
                      </select>
                    </td>
                    <td class="px-3 py-2 text-center">
                      <button @click="removeExpense(index)" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 p-1.5 rounded-md transition-colors">
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 space-y-4">
            <div class="flex flex-col sm:flex-row justify-between gap-3 text-sm">
              <div class="flex items-center gap-2 px-3 py-2 bg-slate-200/50 dark:bg-slate-800 rounded-lg">
                <span class="text-slate-600 dark:text-slate-400">{{ $t('pos.expense_total_customer') }}:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ formatMoney(totalCustomer) }} {{ $t('common.currency') }}</span>
              </div>
              <div class="flex items-center gap-2 px-3 py-2 bg-slate-200/50 dark:bg-slate-800 rounded-lg">
                <span class="text-slate-600 dark:text-slate-400">{{ $t('pos.expense_total_treasury') }}:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ formatMoney(totalTreasury) }} {{ $t('common.currency') }}</span>
              </div>
            </div>
            
            <div class="flex justify-end">
              <button @click="save" class="px-6 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium transition-colors flex items-center gap-2">
                {{ $t('pos.expense_save') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { X, Plus, Trash2, Truck, Package, Gift, ArrowUp } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  show: { type: Boolean, default: false },
  expenses: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'update:expenses']);

const { t } = useTrans();
const { formatMoney } = useFormatters();

const localExpenses = ref([]);
const newExpense = ref({ title: '', amount: '', paid_by: 'customer_account' });

watch(() => props.show, (val) => {
  if (val) {
    localExpenses.value = JSON.parse(JSON.stringify(props.expenses));
  }
});

const addPreset = (title, amount) => {
  localExpenses.value.push({
    title,
    amount: Number(amount),
    paid_by: 'customer_account'
  });
};

const addCustomExpense = () => {
  if (!newExpense.value.title || !newExpense.value.amount) return;
  
  localExpenses.value.push({
    title: newExpense.value.title,
    amount: Number(newExpense.value.amount),
    paid_by: newExpense.value.paid_by
  });
  
  newExpense.value = { title: '', amount: '', paid_by: 'customer_account' };
};

const removeExpense = (index) => {
  localExpenses.value.splice(index, 1);
};

const totalCustomer = computed(() => {
  return localExpenses.value
    .filter(e => e.paid_by === 'customer_account')
    .reduce((sum, e) => sum + (Number(e.amount) || 0), 0);
});

const totalTreasury = computed(() => {
  return localExpenses.value
    .filter(e => e.paid_by.startsWith('treasury_'))
    .reduce((sum, e) => sum + (Number(e.amount) || 0), 0);
});

const save = () => {
  emit('update:expenses', JSON.parse(JSON.stringify(localExpenses.value)));
  emit('close');
};
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
