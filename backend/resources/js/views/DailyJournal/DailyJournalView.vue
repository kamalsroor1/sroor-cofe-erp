<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Action Controls -->
    <PageHeader :title="$t('treasury.journal_title')" :subtitle="$t('treasury.journal_subtitle')" icon="book-open">
      <template #actions>
        <div class="flex items-center gap-2 flex-wrap">
          <div class="w-44">
            <BaseDatePicker v-model="selectedDate" @change="fetchDailyJournal" :clearable="false" />
          </div>

          <!-- Shift status pill: the banner below already says it on phones -->
          <div
            v-if="!hasError && !isLoading && !activeShift"
            class="hidden sm:flex min-h-[44px] px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 items-center gap-1.5 select-none"
          >
            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
            <span>{{ $t('treasury.shift_closed_now') }}</span>
          </div>

          <PageActions :items="headerActions" />
        </div>
      </template>
    </PageHeader>

    <!-- Active Shift Status Banner (Hidden during initial load or error) -->
    <DailyJournalShiftBanner
      v-if="!hasError && !isLoading"
      :active-shift="activeShift"
      :can-print="canPrint"
      @print-z="printActiveZReport"
      @open-shift="showOpenShiftModal = true"
    />

    <!-- Error State Display when server error occurs -->
    <div
      v-if="hasError && !isLoading"
      class="p-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl"
    >
      <ErrorState :message="errorMessage" @retry="fetchDailyJournal" />
    </div>

    <template v-else>
      <p
        v-if="isOwnShiftScope && !isLoading"
        class="px-4 py-3 rounded-2xl bg-theme-light border border-theme-border text-xs font-bold text-theme-primary"
        data-testid="journal-own-notice"
        role="note"
      >
        {{ $t('treasury.own_documents_notice') }}
      </p>

      <!-- Financial Metrics Grid -->
      <DailyJournalMetricsGrid :summary="summary" :loading="isLoading" />

      <!-- Journal Tabs (Invoices vs Expenses) -->
      <DailyJournalTabs v-model:active-tab="activeTab" :invoices="invoices" :expenses="expenses" :loading="isLoading" />
    </template>

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

    <ShiftZReportPrint :report="zReport" />

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
import { computed } from 'vue';
import { MinusCircle, Lock } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import PageActions from '../../Components/Common/PageActions.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import BaseDatePicker from '../../Components/Form/BaseDatePicker.vue';
import DailyJournalShiftBanner from '../../Components/DailyJournal/DailyJournalShiftBanner.vue';
import DailyJournalMetricsGrid from '../../Components/DailyJournal/DailyJournalMetricsGrid.vue';
import DailyJournalTabs from '../../Components/DailyJournal/DailyJournalTabs.vue';
import OpenShiftModal from '../../Components/DailyJournal/OpenShiftModal.vue';
import ShiftZReportPrint from '../../Components/DailyJournal/ShiftZReportPrint.vue';
import CloseShiftModal from '../../Components/DailyJournal/CloseShiftModal.vue';
import QuickExpenseModal from '../../Components/DailyJournal/QuickExpenseModal.vue';
import { useDailyJournal } from '../../Composables/useDailyJournal';
import { useTrans } from '../../Composables/useTrans';
import { useRoutePermission } from '../../Composables/useRoutePermission';

const {
  selectedDate,
  activeTab,
  isLoading,
  hasError,
  errorMessage,
  isSubmitting,
  activeShift,
  isOwnShiftScope,
  canPrint,
  zReport,
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

const { t } = useTrans();
const { canAccessPath } = useRoutePermission();
// Creating an expense needs expenses.manage (and the plan feature): same rule as opening the Expenses screen.
const canRecordExpense = computed(() => canAccessPath('/expenses'));
const canCloseShift = computed(() => !hasError.value && !isLoading.value && Boolean(activeShift.value));
const headerActions = computed(() => [
  {
    key: 'expense',
    label: t('treasury.record_journal_expense'),
    icon: MinusCircle,
    primary: !canCloseShift.value,
    show: canRecordExpense.value,
    iconClass: 'text-rose-500',
    onClick: () => (showExpenseModal.value = true),
  },
  {
    key: 'close-shift',
    label: t('treasury.close_shift'),
    icon: Lock,
    variant: 'danger',
    primary: canCloseShift.value,
    show: canCloseShift.value,
    onClick: openCloseShiftModal,
  },
]);
</script>
