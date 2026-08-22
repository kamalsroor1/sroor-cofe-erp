<template>
  <div class="space-y-6 max-w-7xl mx-auto">
      <!-- Page Header -->
      <PageHeader
        :title="$t('contacts.customers_title')"
        :subtitle="$t('contacts.customers_subtitle')"
        :icon="'👥'"
      >
        <template #actions>
          <button
            type="button"
            @click="openCreateModal"
            class="px-4 py-2.5 bg-theme-gradient text-white font-black shadow-theme-primary rounded-xl text-xs font-black transition-all flex items-center gap-2 font-tajawal shadow-lg shadow-theme-primary cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('contacts.add_customer') }}</span>
          </button>
        </template>
      </PageHeader>

      <!-- Summary Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Receivables (Debt) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.total_receivables') }}</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
              <TrendingUp class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ formatMoney(metrics.total_debt || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            {{ $t('contacts.total_receivables_sub') }}
          </div>
        </div>

        <!-- Debtors Count -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.debtors_count') }}</span>
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
              <AlertCircle class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-amber-400 font-mono">
            {{ metrics.debtors_count || 0 }} <span class="text-xs text-slate-400">{{ $t('contacts.customer_unit') }}</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            {{ $t('contacts.debtors_count_sub') }}
          </div>
        </div>

        <!-- Total Customers Count -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.total_customers_count') }}</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <Users class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
            {{ metrics.total_customers || 0 }} <span class="text-xs text-slate-400">{{ $t('contacts.customer_unit') }}</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            {{ $t('contacts.total_customers_sub') }}
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
            class="w-full h-10 pr-9 pl-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
            :placeholder="$t('contacts.search_customer_placeholder')"
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Debt Status Filter Pills -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-800 overflow-x-auto">
          <button
            type="button"
            @click="setDebtStatus('all')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="debtStatus === 'all' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-900 dark:text-slate-200'"
          >
            {{ $t('common.all') }}
          </button>

          <button
            type="button"
            @click="setDebtStatus('debtor')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="debtStatus === 'debtor' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-900 dark:text-slate-200'"
          >
            🚨 {{ $t('contacts.debtors_only') }}
          </button>

          <button
            type="button"
            @click="setDebtStatus('zero')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="debtStatus === 'zero' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-900 dark:text-slate-200'"
          >
            ✅ {{ $t('contacts.settled_only') }}
          </button>

          <button
            type="button"
            @click="setDebtStatus('creditor')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="debtStatus === 'creditor' ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-900 dark:text-slate-200'"
          >
            💳 {{ $t('contacts.creditors_only') }}
          </button>
        </div>
      </div>

      <!-- Customers Table -->
      <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <!-- Loading Spinner -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="customers.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 font-tajawal border-b border-slate-200 dark:border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.customer_name') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.phone') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.address') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.current_balance') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr
                v-for="(customer, idx) in customers"
                :key="customer.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-100 dark:hover:bg-slate-900/50 transition-colors"
                :class="customer.current_balance > 0 ? 'bg-rose-500/5' : ''"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ customer.name }}</div>
                  <div v-if="customer.tax_number" class="text-[10px] text-slate-500 font-mono mt-0.5">
                    {{ $t('contacts.tax_number_label') }} {{ customer.tax_number }}
                  </div>
                </td>
                <td class="py-3.5 px-4 font-mono text-slate-300" dir="ltr">
                  {{ customer.phone || '—' }}
                </td>
                <td class="py-3.5 px-4 font-tajawal text-slate-400 max-w-xs truncate">
                  {{ customer.address || '—' }}
                </td>
                <td class="py-3.5 px-4 text-end">
                  <div
                    class="font-mono font-black text-sm"
                    :class="customer.current_balance > 0 ? 'text-rose-400' : (customer.current_balance < 0 ? 'text-cyan-400' : 'text-emerald-400')"
                  >
                    {{ formatMoney(customer.current_balance) }} <span class="text-xs font-normal">{{ $t('common.currency') }}</span>
                  </div>
                  <div class="text-[10px] font-tajawal text-slate-500 mt-0.5">
                    {{ customer.current_balance > 0 ? $t('contacts.debt_due') : (customer.current_balance < 0 ? $t('contacts.credit_balance') : $t('contacts.settled')) }}
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold font-tajawal border"
                    :class="customer.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-slate-800 border-slate-700 text-slate-500'"
                  >
                    {{ customer.is_active ? $t('common.active') : $t('common.inactive') }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Collect Payment Button -->
                    <button
                      type="button"
                      @click="openPaymentModal(customer)"
                      class="px-2.5 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-xl text-xs font-bold transition-all flex items-center gap-1 font-tajawal cursor-pointer"
                      :title="$t('contacts.collect_payment')"
                    >
                      <Receipt class="w-3.5 h-3.5" />
                      <span>{{ $t('contacts.collect_payment') }}</span>
                    </button>

                    <!-- Statement Button -->
                    <router-link
                      :to="`/customers/${customer.id}/statement`"
                      class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition-all"
                      :title="$t('contacts.statement')"
                    >
                      <FileText class="w-4 h-4" />
                    </router-link>

                    <!-- Edit Button -->
                    <button
                      type="button"
                      @click="openEditModal(customer)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="$t('common.edit')"
                    >
                      <Pencil class="w-4 h-4" />
                    </button>

                    <!-- Delete Button -->
                    <button
                      type="button"
                      @click="deleteCustomer(customer)"
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
          :title="$t('contacts.no_customers_found')"
          :description="$t('contacts.no_customers_description')"
          :icon="'👥'"
        >
          <template #action>
            <button
              type="button"
              @click="openCreateModal"
              class="px-5 py-2.5 bg-amber-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary cursor-pointer"
            >
              {{ $t('contacts.add_first_customer') }}
            </button>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400 font-tajawal">
            {{ $t('activity.total_records') }} <span class="font-mono text-amber-400">{{ pagination.total }}</span> {{ $t('contacts.customer_unit') }}
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchCustomers(pagination.current_page - 1)"
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
              @click="fetchCustomers(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Add / Edit Customer Modal -->
      <AppModal
        :show="showCustomerModal"
        :title="editingCustomer ? $t('contacts.edit_customer') : $t('contacts.add_customer')"
        @close="showCustomerModal = false"
      >
        <form @submit.prevent="saveCustomer" class="space-y-4">
          <!-- Name -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
              {{ $t('contacts.customer_name') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
              :placeholder="$t('contacts.customer_name_placeholder')"
            >
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
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
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
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
                :placeholder="$t('contacts.address_placeholder')"
              >
            </div>
          </div>

          <!-- Tax Number & Opening Balance -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.tax_number') }}
              </label>
              <input
                v-model="form.tax_number"
                type="text"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
                :placeholder="$t('contacts.tax_number_placeholder')"
              >
            </div>

            <div v-if="!editingCustomer">
              <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
                {{ $t('contacts.opening_balance') }}
              </label>
              <input
                v-model="form.opening_balance"
                type="number"
                step="0.001"
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
                placeholder="0.000"
              >
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1 font-tajawal">
              {{ $t('common.notes') }}
            </label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full p-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none font-tajawal"
              :placeholder="$t('contacts.notes_placeholder')"
            ></textarea>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="showCustomerModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold font-tajawal cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('common.save') }}</span>
            </button>
          </div>
        </form>
      </AppModal>

      <!-- Collect Payment Modal -->
      <AppModal
        :show="showPaymentModal"
        :title="`${$t('contacts.collect_payment_from')}: ${targetCustomer?.name}`"
        @close="showPaymentModal = false"
      >
        <form @submit.prevent="savePayment" class="space-y-4">
          <!-- Current Debt Alert -->
          <div class="p-3.5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('contacts.current_balance') }}:</span>
            <span class="text-base font-black font-mono" :class="targetCustomer?.current_balance > 0 ? 'text-rose-400' : 'text-emerald-400'">
              {{ formatMoney(targetCustomer?.current_balance || 0) }} {{ $t('common.currency') }}
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
              class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-base font-bold text-emerald-400 font-mono focus:ring-2 focus:ring-emerald-500 focus:outline-none"
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
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none font-tajawal"
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
                class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-emerald-500 focus:outline-none"
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
              class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none font-tajawal"
              :placeholder="$t('contacts.receipt_voucher')"
            >
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
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
              class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('contacts.confirm_collection') }}</span>
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
    TrendingUp,
    AlertCircle,
    Users,
    Receipt,
    FileText,
    Pencil,
    Trash2
} from 'lucide-vue-next';

const customers = ref([]);
const metrics = ref({
    total_debt: 0,
    debtors_count: 0,
    total_customers: 0,
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
const showCustomerModal = ref(false);
const editingCustomer = ref(null);
const form = reactive({
    name: '',
    phone: '',
    address: '',
    tax_number: '',
    opening_balance: '0.000',
    notes: '',
});

// Collect Payment State
const showPaymentModal = ref(false);
const targetCustomer = ref(null);
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

const fetchCustomers = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/customers', {
            params: {
                search: searchQuery.value,
                debt_status: debtStatus.value,
                page: page,
                per_page: 20,
            },
        });
        customers.value = response.data?.data || [];
        metrics.value = response.data?.summary || {
            total_debt: 0,
            debtors_count: 0,
            total_customers: 0,
        };
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 20,
            total: customers.value.length,
        };
    } catch (error) {
        console.error('Failed to load customers:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchCustomers(1);
    }, 300);
};

const setDebtStatus = (status) => {
    debtStatus.value = status;
    fetchCustomers(1);
};

onMounted(() => {
    fetchCustomers(1);
});

const openCreateModal = () => {
    editingCustomer.value = null;
    form.name = '';
    form.phone = '';
    form.address = '';
    form.tax_number = '';
    form.opening_balance = '0.000';
    form.notes = '';
    showCustomerModal.value = true;
};

const openEditModal = (c) => {
    editingCustomer.value = c;
    form.name = c.name;
    form.phone = c.phone || '';
    form.address = c.address || '';
    form.tax_number = c.tax_number || '';
    form.notes = c.notes || '';
    showCustomerModal.value = true;
};

const saveCustomer = async () => {
    isSubmitting.value = true;
    try {
        if (editingCustomer.value) {
            await api.put(`/customers/${editingCustomer.value.id}`, form);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('contacts.customer_updated'),
                timer: 1500,
                showConfirmButton: false,
            });
        } else {
            await api.post('/customers', form);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('contacts.customer_added'),
                timer: 1500,
                showConfirmButton: false,
            });
        }
        showCustomerModal.value = false;
        await fetchCustomers(pagination.value.current_page);
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('common.error'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const openPaymentModal = (c) => {
    targetCustomer.value = c;
    paymentForm.amount = c.current_balance > 0 ? c.current_balance : '';
    paymentForm.payment_method = 'cash';
    paymentForm.payment_date = new Date().toISOString().split('T')[0];
    paymentForm.notes = trans('contacts.receipt_voucher');
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
        await api.post(`/customers/${targetCustomer.value.id}/collect-payment`, paymentForm);
        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('contacts.payment_recorded'),
            timer: 1500,
            showConfirmButton: false,
        });
        showPaymentModal.value = false;
        await fetchCustomers(pagination.value.current_page);
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: error.userMessage || trans('common.error'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const deleteCustomer = async (c) => {
    if (!c.can_be_deleted) {
        const blockers = c.deletion_blockers?.join('\n- ') || '';
        Swal.fire({
            icon: 'warning',
            title: trans('contacts.cannot_delete_customer'),
            text: `${trans('contacts.deletion_blockers_found')}\n- ${blockers}`,
        });
        return;
    }

    const result = await Swal.fire({
        title: trans('contacts.delete_customer_confirm_title', { name: c.name }),
        text: trans('contacts.delete_customer_confirm_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('common.delete'),
        cancelButtonText: trans('common.cancel'),
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/customers/${c.id}`);
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('contacts.customer_deleted'),
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchCustomers(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: error.userMessage || trans('common.error'),
            });
        }
    }
};
</script>
