<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto">
      <!-- Page Header -->
      <PageHeader
        :title="$t('expenses.title')"
        :subtitle="$t('expenses.subtitle')"
        :icon="'💸'"
      >
        <template #actions>
          <button
            type="button"
            @click="openCreateModal"
            class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 font-tajawal shadow-lg shadow-amber-500/20 cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('expenses.add_expense') }}</span>
          </button>
        </template>
      </PageHeader>

      <!-- Summary Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Month Expenses -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('expenses.total_month_expenses') }}</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
              <TrendingDown class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ formatMoney(metrics.total_month || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            إجمالي مصروفات ونثريات الشهر الحالي
          </div>
        </div>

        <!-- Total Cash Expenses -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('expenses.cash_expenses') }}</span>
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
              <Wallet class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-amber-400 font-mono">
            {{ formatMoney(metrics.total_cash || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            المصروفات المنصرفة نقداً من درج الكاشير
          </div>
        </div>

        <!-- Total Filtered Period Expenses -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('expenses.filtered_total') }}</span>
            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
              <Receipt class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-white font-mono">
            {{ formatMoney(metrics.total_filtered || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            إجمالي نتائج الفلترة والبحث الحالي
          </div>
        </div>
      </div>

      <!-- Filters & Search Bar -->
      <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
            :placeholder="$t('expenses.search_placeholder')"
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Cost Center Dropdown -->
        <div class="w-full md:w-56">
          <select
            v-model="selectedCostCenter"
            @change="fetchExpenses(1)"
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
          >
            <option value="all">{{ $t('expenses.all_cost_centers') }}</option>
            <option v-for="(label, key) in costCenters" :key="key" :value="key">
              {{ label }}
            </option>
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="flex items-center gap-2">
          <input
            v-model="dateFrom"
            @change="fetchExpenses(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
            :title="$t('common.from')"
          >
          <span class="text-xs text-slate-500 font-bold">—</span>
          <input
            v-model="dateTo"
            @change="fetchExpenses(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
            :title="$t('common.to')"
          >
        </div>
      </div>

      <!-- Quick Category Chips -->
      <div v-if="quickCategories.length > 0" class="flex items-center gap-2 overflow-x-auto pb-1">
        <span class="text-xs font-bold text-slate-400 shrink-0 font-tajawal">تصنيفات سريعة:</span>
        <button
          v-for="cat in quickCategories"
          :key="cat"
          type="button"
          @click="filterByCategory(cat)"
          class="px-3 py-1 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer border"
          :class="selectedCategory === cat ? 'bg-amber-500 text-slate-950 border-amber-500 shadow-sm' : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200'"
        >
          {{ cat }}
        </button>
        <button
          v-if="selectedCategory !== 'all'"
          type="button"
          @click="filterByCategory('all')"
          class="px-2.5 py-1 rounded-xl text-xs font-bold text-rose-400 bg-rose-500/10 border border-rose-500/30 transition-all whitespace-nowrap cursor-pointer"
        >
          ✕ إلغاء الفلتر
        </button>
      </div>

      <!-- Expenses Table -->
      <div class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <!-- Loading Spinner -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="expenses.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 font-tajawal border-b border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('expenses.expense_item') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('expenses.cost_center') }} & التصنيف</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('common.amount') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('invoices.payment_method') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
              <tr
                v-for="(expense, idx) in expenses"
                :key="expense.id"
                class="hover:bg-slate-900/50 transition-colors"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                  {{ expense.expense_number }}
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-white font-tajawal text-sm">{{ expense.title }}</div>
                  <div v-if="expense.notes" class="text-[10px] text-slate-500 font-tajawal mt-0.5 max-w-xs truncate">
                    {{ expense.notes }}
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <div class="text-xs font-bold text-slate-300 font-tajawal">{{ expense.cost_center_label || expense.cost_center }}</div>
                  <div class="text-[10px] text-slate-500 font-tajawal mt-0.5">{{ expense.category }}</div>
                </td>
                <td class="py-3.5 px-4 font-mono text-slate-300">
                  {{ expense.expense_date }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-sm text-rose-400">
                  {{ formatMoney(expense.amount) }} <span class="text-xs font-normal">ج.م</span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-tajawal bg-slate-800 border border-slate-700 text-slate-300">
                    {{ formatPaymentMethod(expense.payment_method) }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Edit Button -->
                    <button
                      type="button"
                      @click="openEditModal(expense)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="$t('common.edit')"
                    >
                      <Pencil class="w-4 h-4" />
                    </button>

                    <!-- Delete Button -->
                    <button
                      type="button"
                      @click="deleteExpense(expense)"
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
          :title="$t('expenses.no_expenses_found')"
          :description="$t('expenses.no_expenses_description')"
          :icon="'💸'"
        >
          <template #action>
            <button
              type="button"
              @click="openCreateModal"
              class="px-5 py-2.5 bg-amber-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20 cursor-pointer"
            >
              {{ $t('expenses.add_first_expense') }}
            </button>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400 font-tajawal">
            إجمالي النتائج: <span class="font-mono text-amber-400">{{ pagination.total }}</span> مصروف
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchExpenses(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              السابق
            </button>
            <span class="px-3 py-1.5 text-xs font-mono text-slate-300 font-bold">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchExpenses(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              التالي
            </button>
          </div>
        </div>
      </div>

      <!-- Add / Edit Expense Modal -->
      <AppModal
        :show="showExpenseModal"
        :title="editingExpense ? $t('expenses.edit_expense') : $t('expenses.add_expense')"
        @close="showExpenseModal = false"
      >
        <form @submit.prevent="saveExpense" class="space-y-4 font-tajawal">
          <!-- Title -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              {{ $t('expenses.expense_item') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              required
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              :placeholder="$t('expenses.title_placeholder')"
            >
          </div>

          <!-- Cost Center & Category Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('expenses.cost_center') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.cost_center"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
                <option v-for="(label, key) in costCenters" :key="key" :value="key">
                  {{ label }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('expenses.category') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.category"
                type="text"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                :placeholder="$t('expenses.category_placeholder')"
              >
            </div>
          </div>

          <!-- Quick Category Tags in Modal -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[11px] text-slate-500 font-bold">اقتراحات:</span>
            <button
              v-for="cat in quickCategories"
              :key="cat"
              type="button"
              @click="form.category = cat"
              class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 cursor-pointer"
            >
              {{ cat }}
            </button>
          </div>

          <!-- Amount & Date Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('common.amount') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.amount"
                type="number"
                step="0.001"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-base font-bold text-rose-400 font-mono focus:ring-2 focus:ring-rose-500 focus:outline-none"
                placeholder="0.00"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('common.date') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.expense_date"
                type="date"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
            </div>
          </div>

          <!-- Payment Method -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              {{ $t('invoices.payment_method') }} <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.payment_method"
              required
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
              <option value="cash">💵 نقداً (درج الكاشير)</option>
              <option value="instapay">⚡ إنستاباي</option>
              <option value="e_wallet">📱 محفظة إلكترونية</option>
              <option value="visa">💳 فيزا / بطاقة بنكية</option>
              <option value="bank_transfer">🏦 تحويل بنكي</option>
              <option value="check">📄 شيك</option>
            </select>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              {{ $t('common.notes') }}
            </label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full p-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              :placeholder="$t('expenses.notes_placeholder')"
            ></textarea>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showExpenseModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black shadow-lg shadow-amber-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('common.save') }}</span>
            </button>
          </div>
        </form>
      </AppModal>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    Plus,
    Search,
    TrendingDown,
    Wallet,
    Receipt,
    Pencil,
    Trash2
} from 'lucide-vue-next';

const expenses = ref([]);
const metrics = ref({
    total_month: 0,
    total_cash: 0,
    total_filtered: 0,
});

const costCenters = ref({});
const quickCategories = ref([]);

const searchQuery = ref('');
const selectedCostCenter = ref('all');
const selectedCategory = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
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
const showExpenseModal = ref(false);
const editingExpense = ref(null);
const form = reactive({
    title: '',
    category: '',
    cost_center: 'operational',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    notes: '',
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatPaymentMethod = (method) => {
    const map = {
        cash: '💵 نقداً',
        instapay: '⚡ إنستاباي',
        e_wallet: '📱 محفظة',
        visa: '💳 فيزا',
        bank_transfer: '🏦 تحويل بنكي',
        check: '📄 شيك',
    };
    return map[method] || method;
};

const fetchExpenses = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/expenses', {
            params: {
                search: searchQuery.value,
                cost_center: selectedCostCenter.value,
                category: selectedCategory.value,
                from: dateFrom.value || undefined,
                to: dateTo.value || undefined,
                page: page,
                per_page: 20,
            },
        });
        expenses.value = response.data?.data || [];
        metrics.value = response.data?.summary || {
            total_month: 0,
            total_cash: 0,
            total_filtered: 0,
        };
        costCenters.value = response.data?.cost_centers || {};
        quickCategories.value = response.data?.quick_categories || [];
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 20,
            total: expenses.value.length,
        };
    } catch (error) {
        console.error('Failed to load expenses:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchExpenses(1);
    }, 300);
};

const filterByCategory = (cat) => {
    selectedCategory.value = cat;
    fetchExpenses(1);
};

onMounted(() => {
    fetchExpenses(1);
});

const openCreateModal = () => {
    editingExpense.value = null;
    form.title = '';
    form.category = 'نثريات ومصاريف تشغيل';
    form.cost_center = 'operational';
    form.amount = '';
    form.expense_date = new Date().toISOString().split('T')[0];
    form.payment_method = 'cash';
    form.notes = '';
    showExpenseModal.value = true;
};

const openEditModal = (e) => {
    editingExpense.value = e;
    form.title = e.title;
    form.category = e.category;
    form.cost_center = e.cost_center || 'operational';
    form.amount = e.amount;
    form.expense_date = e.expense_date;
    form.payment_method = e.payment_method || 'cash';
    form.notes = e.notes || '';
    showExpenseModal.value = true;
};

const saveExpense = async () => {
    isSubmitting.value = true;
    try {
        if (editingExpense.value) {
            await api.put(`/expenses/${editingExpense.value.id}`, form);
            Swal.fire({
                icon: 'success',
                title: 'تم التعديل',
                text: 'تم تعديل بيانات المصروف بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
        } else {
            await api.post('/expenses', form);
            Swal.fire({
                icon: 'success',
                title: 'تم التسجيل',
                text: 'تم تسجيل المصروف بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
        }
        showExpenseModal.value = false;
        await fetchExpenses(pagination.value.current_page);
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.userMessage || 'تعذر حفظ المصروف',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const deleteExpense = async (e) => {
    const result = await Swal.fire({
        title: `حذف المصروف (${e.title})؟`,
        text: 'هل أنت متأكد من حذف هذا المصروف؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/expenses/${e.id}`);
            Swal.fire({
                icon: 'success',
                title: 'تم الحذف',
                text: 'تم حذف المصروف بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchExpenses(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: error.userMessage || 'تعذر حذف المصروف',
            });
        }
    }
};
</script>
