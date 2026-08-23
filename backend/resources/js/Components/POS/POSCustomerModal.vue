<template>
  <AppModal
    :show="show"
    :title="$t('pos.select_customer')"
    max-width="lg"
    @close="$emit('close')"
  >
    <div class="space-y-4 font-tajawal select-none">
      <div class="flex items-center justify-between gap-2">
        <input
          :value="searchQuery"
          @input="$emit('update:searchQuery', $event.target.value)"
          type="text"
          :placeholder="$t('pos.search_customer_placeholder')"
          class="flex-1 h-11 px-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-theme-primary"
        />
        <button
          type="button"
          @click="isAddingNewCustomer = !isAddingNewCustomer"
          class="min-h-[44px] px-3.5 py-2.5 bg-theme-primary text-slate-950 rounded-xl text-xs font-black shrink-0 transition cursor-pointer"
        >
          {{ isAddingNewCustomer ? $t('common.cancel') : $t('pos.quick_add_customer') }}
        </button>
      </div>

      <!-- Quick Add Customer Form -->
      <div v-if="isAddingNewCustomer" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="text-xs font-black text-slate-900 dark:text-white">{{ $t('pos.quick_customer_add_title') }}</div>
        <div class="grid grid-cols-2 gap-2">
          <input v-model="quickCustomerName" type="text" :placeholder="$t('pos.customer_name_required')" class="h-10 px-3 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-xs" />
          <input v-model="quickCustomerPhone" type="text" :placeholder="$t('pos.customer_phone_optional')" class="h-10 px-3 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-mono" dir="ltr" />
        </div>
        <button
          type="button"
          @click="submitQuickCustomer"
          :disabled="!quickCustomerName || isSubmitting"
          class="min-h-[44px] w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition disabled:opacity-50 cursor-pointer"
        >
          {{ $t('pos.save_and_pick_customer') }}
        </button>
      </div>

      <!-- Customers List -->
      <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl">
        <button
          v-for="cust in customers"
          :key="cust.id"
          type="button"
          @click="$emit('select-customer', cust)"
          class="min-h-[52px] w-full p-3 flex items-center justify-between text-start hover:bg-slate-50 dark:hover:bg-slate-800/80 transition cursor-pointer"
          :class="selectedCustomerId === cust.id ? 'bg-theme-light dark:bg-slate-800 text-theme-primary font-black' : 'text-slate-700 dark:text-slate-300'"
        >
          <div>
            <div class="font-bold text-xs text-slate-900 dark:text-white">{{ cust.name }}</div>
            <div class="text-[10px] text-slate-500 font-mono">{{ cust.phone || $t('pos.no_phone') }}</div>
          </div>
          <div class="text-end">
            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold" :class="cust.current_balance > 0 ? 'bg-rose-500/10 text-rose-500' : 'bg-emerald-500/10 text-emerald-500'">
              {{ $t('pos.balance_label') }} {{ formatMoney(cust.current_balance || 0) }} {{ $t('common.currency') }}
            </span>
          </div>
        </button>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue';
import AppModal from '../Common/AppModal.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

const props = defineProps({
  show: { type: Boolean, default: false },
  searchQuery: { type: String, default: '' },
  customers: { type: Array, default: () => [] },
  selectedCustomerId: { type: [Number, String], default: null },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'update:searchQuery', 'select-customer', 'create-customer']);

const isAddingNewCustomer = ref(false);
const quickCustomerName = ref('');
const quickCustomerPhone = ref('');

const submitQuickCustomer = () => {
  emit('create-customer', {
    name: quickCustomerName.value,
    phone: quickCustomerPhone.value,
  });
  quickCustomerName.value = '';
  quickCustomerPhone.value = '';
  isAddingNewCustomer.value = false;
};
</script>
