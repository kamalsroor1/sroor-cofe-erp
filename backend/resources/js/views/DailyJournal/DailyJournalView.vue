<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Action Controls -->
    <PageHeader
      :title="$t('treasury.journal_title')"
      :subtitle="$t('treasury.journal_subtitle')"
      icon="📖"
    >
      <template #actions>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Date Filter Picker -->
          <div class="flex items-center gap-1.5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-1.5 shadow-sm">
            <Calendar class="w-4 h-4 text-theme-primary" />
            <input
              v-model="selectedDate"
              @change="fetchDailyJournal"
              type="date"
              class="bg-transparent border-0 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-0 cursor-pointer"
            >
          </div>

          <!-- Quick Add Expense in Journal Button -->
          <button
            type="button"
            @click="showExpenseModal = true"
            class="min-h-[38px] px-3.5 py-2 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
          >
            <MinusCircle class="w-4 h-4" />
            <span>{{ $t('treasury.record_journal_expense') }}</span>
          </button>

          <!-- Shift Control Button -->
          <BaseButton
            v-if="!activeShift"
            type="button"
            variant="primary"
            size="md"
            @click="showOpenShiftModal = true"
            class="font-black shadow-theme-primary shadow-lg flex items-center gap-2"
          >
            <Play class="w-4 h-4 fill-white text-white" />
            <span>{{ $t('treasury.open_shift') }}</span>
          </BaseButton>

          <BaseButton
            v-else
            type="button"
            variant="danger"
            size="md"
            @click="openCloseShiftModal"
            class="font-black shadow-rose-500/20 shadow-lg flex items-center gap-2"
          >
            <Lock class="w-4 h-4" />
            <span>{{ $t('treasury.close_shift') }} (Z-Report)</span>
          </BaseButton>
        </div>
      </template>
    </PageHeader>

    <!-- Active Shift Status Banner -->
    <DailyJournalShiftBanner
      :active-shift="activeShift"
      @print-z="printActiveZReport"
      @open-shift="showOpenShiftModal = true"
    />

    <!-- Financial Metrics Grid -->
    <DailyJournalMetricsGrid
      :summary="summary"
      :loading="isLoading"
    />

    <!-- Journal Tabs (Invoices vs Expenses) -->
    <DailyJournalTabs
      v-model:active-tab="activeTab"
      :invoices="invoices"
      :expenses="expenses"
      :loading="isLoading"
    />

    <!-- Open Shift Modal -->
    <OpenShiftModal
      :show="showOpenShiftModal"
      :form="openShiftForm"
      :submitting="isSubmitting"
      @close="showOpenShiftModal = false"
      @submit="submitOpenShift"
      @update:field="updateOpenShiftField"
    />

    <!-- Close Shift Modal (Z-Report) -->
    <CloseShiftModal
      :show="showCloseShiftModal"
      :shift-number="activeShift?.shift_number"
      :opening-cash-balance="activeShift?.opening_cash_balance"
      :expected-cash-in-drawer="summary?.expected_cash_in_drawer"
      :form="closeShiftForm"
      :submitting="isSubmitting"
      @close="showCloseShiftModal = false"
      @submit="submitCloseShift"
      @update:field="updateCloseShiftField"
    />

    <!-- Quick Journal Expense Modal -->
    <QuickExpenseModal
      :show="showExpenseModal"
      :form="expenseForm"
      :submitting="isSubmitting"
      @close="showExpenseModal = false"
      @submit="submitExpense"
      @update:field="updateExpenseField"
    />
  </div>
</template>

<script setup>
import { Calendar, MinusCircle, Play, Lock } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import DailyJournalShiftBanner from '../../Components/DailyJournal/DailyJournalShiftBanner.vue';
import DailyJournalMetricsGrid from '../../Components/DailyJournal/DailyJournalMetricsGrid.vue';
import DailyJournalTabs from '../../Components/DailyJournal/DailyJournalTabs.vue';
import OpenShiftModal from '../../Components/DailyJournal/OpenShiftModal.vue';
import CloseShiftModal from '../../Components/DailyJournal/CloseShiftModal.vue';
import QuickExpenseModal from '../../Components/DailyJournal/QuickExpenseModal.vue';
import { useDailyJournal } from '../../Composables/useDailyJournal';

const {
  selectedDate,
  activeTab,
  isLoading,
  isSubmitting,
  activeShift,
  summary,
  invoices,
  expenses,
  showOpenShiftModal,
  openShiftForm,
  showCloseShiftModal,
  closeShiftForm,
  showExpenseModal,
  expenseForm,
  fetchDailyJournal,
  updateOpenShiftField,
  updateCloseShiftField,
  updateExpenseField,
  submitOpenShift,
  openCloseShiftModal,
  submitCloseShift,
  submitExpense,
  printActiveZReport,
} = useDailyJournal();
</script>
