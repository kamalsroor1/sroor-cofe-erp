<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('contacts.suppliers_title')"
        :subtitle="$t('contacts.suppliers_subtitle')"
        :icon="'🏭'"
      >
        <template #actions>
          <button
            type="button"
            @click="openCreateModal"
            class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 font-tajawal shadow-lg shadow-amber-500/20 cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('contacts.add_supplier') }}</span>
          </button>
        </template>
      </PageHeader>

      <!-- Summary Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Payables -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.total_payables') }}</span>
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
              <TrendingDown class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-amber-400 font-mono">
            {{ formatMoney(metrics.total_payable || 0) }} <span class="text-xs text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            {{ $t('contacts.total_payables_sub') }}
          </div>
        </div>

        <!-- Creditors Count -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.creditors_count') }}</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
              <AlertCircle class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ metrics.creditors_count || 0 }} <span class="text-xs text-slate-400 font-tajawal">{{ $t('contacts.supplier_unit') }}</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            {{ $t('contacts.creditors_count_sub') }}
          </div>
        </div>

        <!-- Total Suppliers Count -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.total_suppliers_count') }}</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <Factory class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
            {{ metrics.total_suppliers || 0 }} <span class="text-xs text-slate-400 font-tajawal">{{ $t('contacts.supplier_unit') }}</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            {{ $t('contacts.total_suppliers_sub') }}
          </div>
        </div>
      </div>

      <!-- Filters & Search Bar -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
            :placeholder="$t('contacts.search_supplier_placeholder')"
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Debt Status Filter Pills -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-800 overflow-x-auto">
          <button
            type="button"
            @click="setDebtStatus('all')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="debtStatus === 'all' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
          >
            {{ $t('common.all') }}
          </button>

          <button
            type="button"
            @click="setDebtStatus('creditor')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="debtStatus === 'creditor' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
          >
            🚨 {{ $t('contacts.creditors_only') }}
          </button>

          <button
            type="button"
            @click="setDebtStatus('zero')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="debtStatus === 'zero' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
          >
            ✅ {{ $t('contacts.settled_only') }}
          </button>
        </div>
      </div>

      <!-- Suppliers Table -->
      <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <!-- Loading Spinner -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="suppliers.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 font-tajawal border-b border-slate-200 dark:border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('purchases.supplier') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.company_name') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.phone') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.payable_balance_label') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr
                v-for="(supplier, idx) in suppliers"
                :key="supplier.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-100 dark:hover:bg-slate-900/50 transition-colors"
                :class="supplier.current_balance > 0 ? 'bg-amber-500/5' : ''"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ supplier.name }}</div>
                  <div v-if="supplier.address" class="text-[10px] text-slate-500 font-tajawal mt-0.5 max-w-xs truncate">
                    {{ supplier.address }}
                  </div>
                </td>
                <td class="py-3.5 px-4 font-tajawal text-slate-300">
                  {{ supplier.company_name || '—' }}
                </td>
                <td class="py-3.5 px-4 font-mono text-slate-300" dir="ltr">
                  {{ supplier.phone || '—' }}
                </td>
                <td class="py-3.5 px-4 text-end">
                  <div
                    class="font-mono font-black text-sm"
                    :class="supplier.current_balance > 0 ? 'text-amber-400' : 'text-emerald-400'"
                  >
                    {{ formatMoney(supplier.current_balance) }} <span class="text-xs font-normal font-tajawal">{{ $t('common.currency') }}</span>
                  </div>
                  <div class="text-[10px] font-tajawal text-slate-500 mt-0.5">
                    {{ supplier.current_balance > 0 ? $t('contacts.due_to_supplier') : $t('contacts.fully_settled') }}
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold font-tajawal border"
                    :class="supplier.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-slate-800 border-slate-700 text-slate-500'"
                  >
                    {{ supplier.is_active ? $t('common.active') : $t('common.inactive') }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Pay Supplier Button -->
                    <button
                      type="button"
                      @click="openPaymentModal(supplier)"
                      class="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-xl text-xs font-bold transition-all flex items-center gap-1 font-tajawal cursor-pointer"
                      :title="$t('contacts.pay_supplier')"
                    >
                      <CreditCard class="w-3.5 h-3.5" />
                      <span>{{ $t('contacts.pay_supplier') }}</span>
                    </button>

                    <!-- Statement Button -->
                    <router-link
                      :to="`/suppliers/${supplier.id}/statement`"
                      class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition-all"
                      :title="$t('contacts.statement')"
                    >
                      <FileText class="w-4 h-4" />
                    </router-link>

                    <!-- Edit Button -->
                    <button
                      type="button"
                      @click="openEditModal(supplier)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="$t('common.edit')"
                    >
                      <Pencil class="w-4 h-4" />
                    </button>

                    <!-- Delete Button -->
                    <button
                      type="button"
                      @click="deleteSupplier(supplier)"
                      class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
                      :title="$t('common.delete')"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <EmptyState
          v-else
          :title="$t('contacts.no_suppliers_found')"
          :description="$t('contacts.no_suppliers_description')"
          :icon="'🏭'"
        >
          <template #action>
            <button
              type="button"
              @click="openCreateModal"
              class="px-5 py-2.5 bg-amber-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20 cursor-pointer"
            >
              {{ $t('contacts.add_first_supplier') }}
            </button>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400 font-tajawal">
            {{ $t('contacts.total_results_suppliers', { count: pagination.total }) }}
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchSuppliers(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              {{ $t('common.previous') }}
            </button>
            <span class="px-3 py-1.5 text-xs font-mono text-slate-300 font-bold">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchSuppliers(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Add / Edit Supplier Modal -->
      <AppModal
        :show="showSupplierModal"
        :title="editingSupplier ? $t('contacts.edit_supplier') : $t('contacts.add_supplier')"
        @close="showSupplierModal = false"
      >
        <form @submit.prevent="saveSupplier" class="space-y-4">
          <!-- Name & Company Name Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.supplier_name') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
                :placeholder="$t('contacts.supplier_name_placeholder')"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.company_name') }}
              </label>
              <input
                v-model="form.company_name"
                type="text"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
                :placeholder="$t('contacts.company_name_placeholder')"
              >
            </div>
          </div>

          <!-- Phone & Address Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.phone') }}
              </label>
              <input
                v-model="form.phone"
                type="text"
                dir="ltr"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
                :placeholder="$t('contacts.phone_placeholder')"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.address') }}
              </label>
              <input
                v-model="form.address"
                type="text"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
                :placeholder="$t('contacts.address_placeholder')"
              >
            </div>
          </div>

          <!-- Opening Balance (Create Only) -->
          <div v-if="!editingSupplier">
            <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
              {{ $t('contacts.opening_balance') }}
            </label>
            <input
              v-model="form.opening_balance"
              type="number"
              step="0.001"
              class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="0.000"
            >
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
              {{ $t('common.notes') }}
            </label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full p-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
              :placeholder="$t('contacts.notes_placeholder')"
            ></textarea>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showSupplierModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold font-tajawal cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('common.save') }}</span>
            </button>
          </div>
        </form>
      </AppModal>

      <!-- Pay Supplier Modal -->
      <AppModal
        :show="showPaymentModal"
        :title="`${$t('contacts.pay_supplier')}: ${targetSupplier?.name}`"
        @close="showPaymentModal = false"
      >
        <form @submit.prevent="savePayment" class="space-y-4">
          <!-- Current Payable Alert -->
          <div class="p-3.5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.payable_balance_label') }}:</span>
            <span class="text-base font-black font-mono" :class="targetSupplier?.current_balance > 0 ? 'text-amber-400' : 'text-emerald-400'">
              {{ formatMoney(targetSupplier?.current_balance || 0) }} {{ $t('common.currency') }}
            </span>
          </div>

          <!-- Payment Amount -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
              {{ $t('contacts.amount') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="paymentForm.amount"
              type="number"
              step="0.001"
              required
              autofocus
              class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-base font-bold text-amber-400 font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="0.00"
            >
          </div>

          <!-- Payment Method & Date Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.payment_method') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="paymentForm.payment_method"
                required
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
              >
                <option value="cash">💵 {{ $t('contacts.cash') }}</option>
                <option value="instapay">⚡ {{ $t('contacts.instapay') }}</option>
                <option value="wallet">📱 {{ $t('contacts.wallet') }}</option>
                <option value="bank">🏦 {{ $t('contacts.bank_transfer') }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.payment_date') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="paymentForm.payment_date"
                type="date"
                required
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
              {{ $t('common.notes') }}
            </label>
            <input
              v-model="paymentForm.notes"
              type="text"
              class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
              :placeholder="$t('contacts.payment_voucher_desc')"
            >
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showPaymentModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold font-tajawal cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('contacts.confirm_payment') }}</span>
            </button>
          </div>
        </form>
      </AppModal>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    Plus,
    Search,
    TrendingDown,
    AlertCircle,
    Factory,
    CreditCard,
    FileText,
    Pencil,
    Trash2
} from 'lucide-vue-next';

const suppliers = ref([]);
const metrics = ref({
    total_payable: 0,
    creditors_count: 0,
    total_suppliers: 0,
});

const searchQuery = ref('');
const debtStatus = ref('all');
const isLoading = ref(false);
const isSubmitting = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

let debounceTimeout = null;

// Add / Edit State
const showSupplierModal = ref(false);
const editingSupplier = ref(null);
const form = reactive({
    name: '',
    company_name: '',
    phone: '',
    address: '',
    opening_balance: '0.000',
    notes: '',
});

// Pay Supplier State
const showPaymentModal = ref(false);
const targetSupplier = ref(null);
const paymentForm = reactive({
    amount: '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchSuppliers = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/suppliers', {
            params: {
                search: searchQuery.value,
                debt_status: debtStatus.value,
                page: page,
                per_page: 20,
            },
        });
        suppliers.value = response.data?.data || [];
        metrics.value = response.data?.summary || {
            total_payable: 0,
            creditors_count: 0,
            total_suppliers: 0,
        };
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 20,
            total: suppliers.value.length,
        };
    } catch (error) {
        console.error('Failed to load suppliers:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchSuppliers(1);
    }, 300);
};

const setDebtStatus = (status) => {
    debtStatus.value = status;
    fetchSuppliers(1);
};

onMounted(() => {
    fetchSuppliers(1);
});

const openCreateModal = () => {
    editingSupplier.value = null;
    form.name = '';
    form.company_name = '';
    form.phone = '';
    form.address = '';
    form.opening_balance = '0.000';
    form.notes = '';
    showSupplierModal.value = true;
};

const openEditModal = (s) => {
    editingSupplier.value = s;
    form.name = s.name;
    form.company_name = s.company_name || '';
    form.phone = s.phone || '';
    form.address = s.address || '';
    form.notes = s.notes || '';
    showSupplierModal.value = true;
};

const saveSupplier = async () => {
    isSubmitting.value = true;
    try {
        if (editingSupplier.value) {
            await api.put(`/suppliers/${editingSupplier.value.id}`, form);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('contacts.supplier_updated_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        } else {
            await api.post('/suppliers', form);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('contacts.supplier_added_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        }
        showSupplierModal.value = false;
        await fetchSuppliers(pagination.value.current_page);
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('contacts.supplier_save_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const openPaymentModal = (s) => {
    targetSupplier.value = s;
    paymentForm.amount = s.current_balance > 0 ? s.current_balance : '';
    paymentForm.payment_method = 'cash';
    paymentForm.payment_date = new Date().toISOString().split('T')[0];
    paymentForm.notes = trans('contacts.payment_voucher_desc');
    showPaymentModal.value = true;
};

const savePayment = async () => {
    if (!paymentForm.amount || parseFloat(paymentForm.amount) <= 0) {
        Swal.fire({
            icon: 'warning',
            title: trans('common.warning'),
            text: trans('contacts.enter_valid_amount'),
        });
        return;
    }

    isSubmitting.value = true;
    try {
        await api.post(`/suppliers/${targetSupplier.value.id}/pay`, paymentForm);
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('contacts.supplier_payment_success'),
            timer: 1500,
            showConfirmButton: false,
        });
        showPaymentModal.value = false;
        await fetchSuppliers(pagination.value.current_page);
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('contacts.supplier_payment_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const deleteSupplier = async (s) => {
    if (!s.can_be_deleted) {
        const blockers = s.deletion_blockers?.join('\n- ') || '';
        Swal.fire({
            icon: 'warning',
            title: trans('contacts.cannot_delete_supplier'),
            text: `${trans('contacts.deletion_blockers_found')}\n- ${blockers}`,
        });
        return;
    }

    const result = await Swal.fire({
        title: trans('contacts.delete_supplier_confirm_title', { name: s.name }),
        text: trans('contacts.delete_supplier_confirm_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('common.yes'),
        cancelButtonText: trans('common.cancel'),
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/suppliers/${s.id}`);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('contacts.supplier_deleted_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchSuppliers(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: error.userMessage || trans('contacts.supplier_delete_failed'),
            });
        }
    }
};
</script>
