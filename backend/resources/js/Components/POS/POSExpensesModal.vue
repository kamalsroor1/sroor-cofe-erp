<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" dir="rtl">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs dark:bg-slate-950/80 transition-opacity" @click="$emit('close')"></div>

        <!-- Modal Card -->
        <div class="relative w-full max-w-2xl bg-white dark:bg-slate-950 rounded-2xl shadow-2xl overflow-hidden flex flex-col font-tajawal border border-slate-200 dark:border-slate-800 animate-in fade-in zoom-in-95 duration-200">
          <!-- Header -->
          <div class="flex items-center justify-between p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800/80 bg-slate-50/80 dark:bg-slate-900/60">
            <div class="flex items-center gap-2.5 text-slate-800 dark:text-slate-100">
              <div class="w-9 h-9 rounded-xl bg-theme-primary/10 text-theme-primary flex items-center justify-center shrink-0">
                <Truck class="w-5 h-5" />
              </div>
              <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">{{ $t('pos.expenses_modal_title') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('pos.expense_subtitle') }}</p>
              </div>
            </div>
            <button
              type="button"
              @click="$emit('close')"
              class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-200/70 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-5 overflow-y-auto max-h-[65vh] flex-1 space-y-5">
            <!-- Quick Presets -->
            <div class="space-y-1.5">
              <label class="text-xs font-black text-slate-700 dark:text-slate-300">{{ $t('pos.quick_presets') }}</label>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  @click="addPreset($t('pos.expense_shipping'), 50)"
                  class="flex items-center gap-1.5 px-3 py-2 bg-theme-primary/10 text-theme-primary hover:bg-theme-primary/20 rounded-xl text-xs font-bold transition-all border border-theme-primary/25 cursor-pointer active:scale-95 shadow-2xs"
                >
                  <Truck class="w-4 h-4 shrink-0" />
                  {{ $t('pos.expense_shipping') }} (50 {{ $t('common.currency') }})
                </button>
                <button
                  type="button"
                  @click="addPreset($t('pos.expense_packaging'), 20)"
                  class="flex items-center gap-1.5 px-3 py-2 bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 rounded-xl text-xs font-bold transition-all border border-amber-500/25 cursor-pointer active:scale-95 shadow-2xs"
                >
                  <Package class="w-4 h-4 shrink-0" />
                  {{ $t('pos.expense_packaging') }} (20 {{ $t('common.currency') }})
                </button>
                <button
                  type="button"
                  @click="addPreset($t('pos.expense_tip'), 10)"
                  class="flex items-center gap-1.5 px-3 py-2 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 rounded-xl text-xs font-bold transition-all border border-emerald-500/25 cursor-pointer active:scale-95 shadow-2xs"
                >
                  <Gift class="w-4 h-4 shrink-0" />
                  {{ $t('pos.expense_tip') }} (10 {{ $t('common.currency') }})
                </button>
                <button
                  type="button"
                  @click="addPreset($t('pos.expense_porter'), 30)"
                  class="flex items-center gap-1.5 px-3 py-2 bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 rounded-xl text-xs font-bold transition-all border border-blue-500/25 cursor-pointer active:scale-95 shadow-2xs"
                >
                  <ArrowUp class="w-4 h-4 shrink-0" />
                  {{ $t('pos.expense_porter') }} (30 {{ $t('common.currency') }})
                </button>
              </div>
            </div>

            <!-- Custom Add Form with Standard Components -->
            <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800/80 space-y-3">
              <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                <Plus class="w-4 h-4 text-theme-primary" />
                {{ $t('pos.expense_add_custom') }}
              </h3>
              <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                <div class="sm:col-span-5">
                  <BaseInput
                    v-model="newExpense.title"
                    :label="$t('pos.expense_title')"
                    :placeholder="$t('pos.expense_title_placeholder')"
                    @keydown.enter="addCustomExpense"
                  />
                </div>
                <div class="sm:col-span-3">
                  <BaseInput
                    v-model="newExpense.amount"
                    type="number"
                    min="0"
                    step="0.001"
                    :label="$t('pos.expense_amount')"
                    :placeholder="'0.00'"
                    @keydown.enter="addCustomExpense"
                  />
                </div>
                <div class="sm:col-span-3">
                  <BaseSelect
                    v-model="newExpense.paid_by"
                    :label="$t('pos.expense_paid_by')"
                    :options="paidByOptions"
                    :searchable="false"
                  />
                </div>
                <div class="sm:col-span-1">
                  <button
                    type="button"
                    @click="addCustomExpense"
                    :disabled="!newExpense.title || !newExpense.amount"
                    class="w-full min-h-[44px] flex items-center justify-center bg-theme-primary text-white rounded-xl hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-bold cursor-pointer active:scale-95 shadow-xs"
                    :title="$t('common.add')"
                  >
                    <Plus class="w-5 h-5 stroke-[2.5]" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Expenses List Table -->
            <div v-if="localExpenses.length > 0" class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs">
              <table class="w-full text-xs text-right">
                <thead class="bg-slate-100/80 dark:bg-slate-900 text-slate-600 dark:text-slate-300 text-[11px] font-black uppercase border-b border-slate-200 dark:border-slate-800">
                  <tr>
                    <th class="px-3 py-2.5 w-10 text-center">#</th>
                    <th class="px-3 py-2.5">{{ $t('pos.expense_title') }}</th>
                    <th class="px-3 py-2.5 w-28">{{ $t('pos.expense_amount') }}</th>
                    <th class="px-3 py-2.5 w-44">{{ $t('pos.expense_paid_by') }}</th>
                    <th class="px-3 py-2.5 w-12 text-center">{{ $t('common.actions') }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-slate-950 font-bold">
                  <tr v-for="(expense, index) in localExpenses" :key="index" class="hover:bg-slate-50/70 dark:hover:bg-slate-900/40 transition-colors">
                    <td class="px-3 py-2.5 text-center text-slate-400 font-mono text-[11px]">{{ index + 1 }}</td>
                    <td class="px-3 py-2.5">
                      <input
                        v-model="expense.title"
                        type="text"
                        class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 dark:text-white font-bold focus:border-theme-primary focus:ring-1 focus:ring-theme-primary outline-hidden transition-colors"
                      />
                    </td>
                    <td class="px-3 py-2.5">
                      <input
                        v-model.number="expense.amount"
                        type="number"
                        min="0"
                        step="0.001"
                        class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 dark:text-white font-mono font-bold focus:border-theme-primary focus:ring-1 focus:ring-theme-primary outline-hidden transition-colors"
                      />
                    </td>
                    <td class="px-3 py-2.5">
                      <BaseSelect
                        v-model="expense.paid_by"
                        :options="paidByOptions"
                        :searchable="false"
                      />
                    </td>
                    <td class="px-3 py-2.5 text-center">
                      <button
                        type="button"
                        @click="removeExpense(index)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors cursor-pointer active:scale-95"
                        :title="$t('common.delete')"
                      >
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-8 text-slate-400 dark:text-slate-500 space-y-2 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl">
              <Truck class="w-8 h-8 mx-auto opacity-40 text-slate-400" />
              <p class="text-xs font-bold">{{ $t('pos.no_expenses_added') }}</p>
            </div>
          </div>

          <!-- Footer Summary & Actions -->
          <div class="p-4 sm:p-5 border-t border-slate-200 dark:border-slate-800/80 bg-slate-50/80 dark:bg-slate-900/60 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
              <div class="flex items-center justify-between px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xs">
                <span class="text-slate-600 dark:text-slate-400 font-bold">{{ $t('pos.expense_total_customer') }}:</span>
                <span class="font-black text-emerald-600 dark:text-emerald-400 font-mono text-sm">{{ formatMoney(totalCustomer) }} {{ $t('common.currency') }}</span>
              </div>
              <div class="flex items-center justify-between px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xs">
                <span class="text-slate-600 dark:text-slate-400 font-bold">{{ $t('pos.expense_total_treasury') }}:</span>
                <span class="font-black text-blue-600 dark:text-blue-400 font-mono text-sm">{{ formatMoney(totalTreasury) }} {{ $t('common.currency') }}</span>
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
              <button
                type="button"
                @click="$emit('close')"
                class="min-h-[44px] px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
              >
                {{ $t('common.cancel') }}
              </button>
              <button
                type="button"
                @click="save"
                class="min-h-[44px] px-6 py-2.5 bg-theme-primary text-white rounded-xl hover:opacity-90 font-black text-xs transition-all flex items-center gap-2 cursor-pointer shadow-xs active:scale-95"
              >
                <Check class="w-4 h-4 stroke-[2.5]" />
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
import { X, Plus, Trash2, Truck, Package, Gift, ArrowUp, Check } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
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

const paidByOptions = computed(() => [
  { value: 'customer_account', label: t('pos.expense_customer_account') },
  { value: 'treasury_cash', label: t('pos.expense_treasury') },
]);

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
    .filter(e => (e.paid_by || '').startsWith('treasury_'))
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
