<template>
  <AppModal
    :show="show"
    :title="$t('pos.select_customer')"
    max-width="lg"
    @close="$emit('close')"
  >
    <div class="space-y-4 font-tajawal select-none">
      
      <!-- 🔍 Search & Toggle Form Bar -->
      <div class="flex items-center justify-between gap-2">
        <div class="relative flex-1">
          <input
            ref="searchInputRef"
            :value="searchQuery"
            @input="$emit('update:searchQuery', $event.target.value)"
            type="text"
            :placeholder="$t('pos.search_customer_placeholder')"
            class="w-full h-11 ps-9 pe-9 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-theme-primary transition-all"
          />
          <Search class="w-4 h-4 text-slate-400 absolute start-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          <span v-if="isSearching" class="absolute end-3 top-1/2 -translate-y-1/2">
            <span class="inline-block w-4 h-4 border-2 border-theme-primary border-t-transparent rounded-full animate-spin"></span>
          </span>
        </div>

        <button
          type="button"
          @click="toggleAddNewCustomer"
          class="min-h-[44px] px-3.5 py-2.5 bg-theme-primary hover:brightness-110 text-slate-950 rounded-xl text-xs font-black shrink-0 transition cursor-pointer active:scale-95 shadow-xs"
        >
          {{ isAddingNewCustomer ? $t('common.cancel') : $t('pos.quick_add_customer') }}
        </button>
      </div>

      <!-- ➕ Quick Add Customer Form -->
      <div v-if="isAddingNewCustomer" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-3 animate-in fade-in duration-200">
        <div class="flex items-center justify-between">
          <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
            <Sparkles class="w-4 h-4 text-amber-500" />
            <span>{{ $t('pos.quick_customer_add_title') }}</span>
          </div>
          <span class="text-[11px] text-slate-400 font-bold">{{ $t('pos.save_and_pick_customer') }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <input
            ref="nameInputRef"
            v-model="quickCustomerName"
            type="text"
            :placeholder="$t('pos.customer_name_required')"
            class="h-10 px-3 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-theme-primary"
            @keydown.enter="submitQuickCustomer"
          />
          <input
            ref="phoneInputRef"
            v-model="quickCustomerPhone"
            type="text"
            :placeholder="$t('pos.customer_phone_optional')"
            class="h-10 px-3 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:outline-none focus:ring-1 focus:ring-theme-primary"
            dir="ltr"
            @keydown.enter="submitQuickCustomer"
          />
        </div>

        <button
          type="button"
          @click="submitQuickCustomer"
          :disabled="!quickCustomerName.trim() || isSubmitting"
          class="min-h-[44px] w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition disabled:opacity-50 cursor-pointer shadow-xs active:scale-95 flex items-center justify-center gap-2"
        >
          <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>{{ $t('pos.save_and_pick_customer') }}</span>
        </button>
      </div>

      <!-- ⏳ Searching Shimmer / Indicator -->
      <div v-if="isSearching && customers.length === 0" class="p-8 text-center space-y-2">
        <div class="w-6 h-6 border-2 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-bold">
          {{ $t('pos.searching_database') }}
        </p>
      </div>

      <!-- 🚫 EMPTY STATE: CUSTOMER NOT FOUND WITH SMART PHONE/NAME QUICK ADD BUTTON -->
      <div
        v-else-if="searchQuery.trim().length > 0 && customers.length === 0 && !isSearching"
        class="py-8 px-4 text-center space-y-3 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 animate-in fade-in duration-150"
      >
        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center mx-auto">
          <User class="w-6 h-6" />
        </div>
        <div class="space-y-1">
          <div class="text-sm font-black text-slate-900 dark:text-white">
            {{ $t('pos.no_customers_found_search', { query: searchQuery }) }}
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $t('pos.no_customers_found_hint') }}
          </p>
        </div>
        <button
          type="button"
          @click="openQuickAddWithQuery"
          class="min-h-[44px] px-5 py-2.5 bg-theme-primary hover:brightness-110 text-slate-950 rounded-xl text-xs font-black transition cursor-pointer inline-flex items-center gap-1.5 shadow-xs active:scale-95"
        >
          <Plus class="w-4 h-4" />
          <span v-if="isPhone(searchQuery)">
            {{ $t('pos.quick_add_customer_with_phone_btn', { phone: searchQuery.trim() }) }}
          </span>
          <span v-else>
            {{ $t('pos.quick_add_customer_btn', { name: searchQuery.trim() }) }}
          </span>
        </button>
      </div>

      <!-- 📋 Customers List -->
      <div v-else class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900 custom-scrollbar">
        <!-- Default General Cash Customer Option -->
        <button
          type="button"
          @click="$emit('select-customer', { id: null, name: $t('pos.general_cash_customer'), phone: '' })"
          class="min-h-[52px] w-full p-3 flex items-center justify-between text-start hover:bg-slate-50 dark:hover:bg-slate-800/80 transition cursor-pointer border-b border-slate-200 dark:border-slate-800"
          :class="!selectedCustomerId ? 'bg-theme-light dark:bg-slate-800 text-theme-primary font-black' : 'text-slate-700 dark:text-slate-300'"
        >
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
              <Banknote class="w-4 h-4 text-emerald-500" />
            </div>
            <div>
              <div class="font-black text-xs text-slate-900 dark:text-white">{{ $t('pos.general_cash_customer') }}</div>
              <div class="text-[10px] text-slate-500 font-mono">{{ $t('pos.cash_customer') }}</div>
            </div>
          </div>
          <div class="text-end">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500">
              {{ $t('pos.full_cash') }}
            </span>
          </div>
        </button>

        <button
          v-for="cust in customers"
          :key="cust.id"
          type="button"
          @click="$emit('select-customer', cust)"
          class="min-h-[52px] w-full p-3 flex items-center justify-between text-start hover:bg-slate-50 dark:hover:bg-slate-800/80 transition cursor-pointer"
          :class="selectedCustomerId === cust.id ? 'bg-theme-light dark:bg-slate-800 text-theme-primary font-black' : 'text-slate-700 dark:text-slate-300'"
        >
          <div class="min-w-0 pr-2">
            <div class="font-bold text-xs text-slate-900 dark:text-white truncate">{{ cust.name }}</div>
            <div class="text-[10px] text-slate-500 font-mono mt-0.5">{{ cust.phone || $t('pos.no_phone') }}</div>
          </div>
          <div class="text-end shrink-0">
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
import { ref, watch, nextTick } from 'vue';
import { Search, Sparkles, User, Plus, Banknote } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

const props = defineProps({
  show: { type: Boolean, default: false },
  searchQuery: { type: String, default: '' },
  customers: { type: Array, default: () => [] },
  selectedCustomerId: { type: [Number, String], default: null },
  isSearching: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'update:searchQuery', 'select-customer', 'create-customer']);

const searchInputRef = ref(null);
const nameInputRef = ref(null);
const phoneInputRef = ref(null);

const isAddingNewCustomer = ref(false);
const quickCustomerName = ref('');
const quickCustomerPhone = ref('');

const isPhone = (val) => {
  if (!val) return false;
  const cleaned = val.replace(/[\s+\-()]/g, '');
  return /^\d{5,}$/.test(cleaned);
};

const toggleAddNewCustomer = () => {
  isAddingNewCustomer.value = !isAddingNewCustomer.value;
  if (isAddingNewCustomer.value) {
    applySearchQueryToInputs();
  }
};

const applySearchQueryToInputs = () => {
  const query = props.searchQuery.trim();
  if (query) {
    if (isPhone(query)) {
      quickCustomerPhone.value = query;
      quickCustomerName.value = '';
      nextTick(() => nameInputRef.value?.focus());
    } else {
      quickCustomerName.value = query;
      quickCustomerPhone.value = '';
      nextTick(() => phoneInputRef.value?.focus());
    }
  } else {
    nextTick(() => nameInputRef.value?.focus());
  }
};

const openQuickAddWithQuery = () => {
  isAddingNewCustomer.value = true;
  applySearchQueryToInputs();
};

const submitQuickCustomer = () => {
  if (!quickCustomerName.value.trim()) return;
  emit('create-customer', {
    name: quickCustomerName.value.trim(),
    phone: quickCustomerPhone.value.trim(),
  });
  quickCustomerName.value = '';
  quickCustomerPhone.value = '';
  isAddingNewCustomer.value = false;
};

watch(() => props.show, (newVal) => {
  if (newVal) {
    nextTick(() => searchInputRef.value?.focus());
  } else {
    isAddingNewCustomer.value = false;
    quickCustomerName.value = '';
    quickCustomerPhone.value = '';
  }
});
</script>
