<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('expenses.title')"
      :subtitle="$t('expenses.subtitle')"
      icon="💸"
    >
      <template #actions>
        <BaseButton
          type="button"
          variant="primary"
          size="md"
          @click="openCreateModal"
          class="font-black shadow-theme-primary shadow-lg flex items-center gap-2"
        >
          <Plus class="w-4 h-4" />
          <span>{{ $t('expenses.add_expense') }}</span>
        </BaseButton>
      </template>
    </PageHeader>

    <!-- Summary Metrics Grid -->
    <ExpensesMetricsGrid
      :metrics="metrics"
      :loading="isLoading"
    />

    <!-- Filters & Search Bar -->
    <ExpensesFilterBar
      v-model:search-query="searchQuery"
      v-model:selected-cost-center="selectedCostCenter"
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      :selected-category="selectedCategory"
      :cost-center-filter-options="costCenterFilterOptions"
      :quick-categories="quickCategories"
      @search="debounceSearch"
      @filter="fetchExpenses(1)"
      @filter-category="filterByCategory"
    />

    <!-- Expenses Table & Mobile Cards -->
    <ExpensesTable
      :expenses="expenses"
      :pagination="pagination"
      :loading="isLoading"
      @create="openCreateModal"
      @edit="openEditModal"
      @delete="deleteExpense"
      @page-change="fetchExpenses"
    />

    <!-- Add / Edit Expense Modal -->
    <ExpenseFormModal
      :show="showExpenseModal"
      :editing-expense="editingExpense"
      :form="form"
      :cost-center-modal-options="costCenterModalOptions"
      :quick-categories="quickCategories"
      :submitting="isSubmitting"
      @close="showExpenseModal = false"
      @save="saveExpense"
      @update:field="updateFormField"
    />
  </div>
</template>

<script setup>
import { Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import ExpensesMetricsGrid from '../../Components/Expenses/ExpensesMetricsGrid.vue';
import ExpensesFilterBar from '../../Components/Expenses/ExpensesFilterBar.vue';
import ExpensesTable from '../../Components/Expenses/ExpensesTable.vue';
import ExpenseFormModal from '../../Components/Expenses/ExpenseFormModal.vue';
import { useExpenses } from '../../Composables/useExpenses';

const {
  expenses,
  metrics,
  quickCategories,
  searchQuery,
  selectedCostCenter,
  selectedCategory,
  costCenterFilterOptions,
  costCenterModalOptions,
  dateFrom,
  dateTo,
  isLoading,
  isSubmitting,
  pagination,
  showExpenseModal,
  editingExpense,
  form,
  fetchExpenses,
  debounceSearch,
  filterByCategory,
  openCreateModal,
  openEditModal,
  updateFormField,
  saveExpense,
  deleteExpense,
} = useExpenses();
</script>
