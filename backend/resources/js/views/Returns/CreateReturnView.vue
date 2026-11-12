<template>
  <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
    <!-- Page Header -->
    <PageHeader :title="$t('returns.create_title')" :subtitle="$t('returns.create_subtitle')" icon="arrow-left-right">
      <template #actions>
        <router-link
          to="/returns"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
        >
          <ArrowRight class="ltr:rotate-180 w-4 h-4" />
          <span>{{ $t('returns.back_to_returns') }}</span>
        </router-link>
      </template>
    </PageHeader>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_22rem] gap-6">
      <!-- Main Form -->
      <div class="min-w-0 space-y-5">
        <!-- Return Type & Party Info -->
        <ReturnPartySection
          :form="form"
          :search-customers="searchCustomers"
          :search-suppliers="searchSuppliers"
          :hide-party="usingSource"
          @type-change="onTypeChange"
          @update:field="updateField"
        />

        <!-- BE-01: source document (default) or, with permission, the manual form -->
        <div v-if="canWithoutInvoice" class="flex justify-end">
          <button
            type="button"
            @click="setManualMode(!manualMode)"
            class="min-h-[44px] px-3 text-xs font-bold text-theme-primary cursor-pointer"
          >
            {{ manualMode ? $t('returns.return_from_document') : $t('returns.return_manually') }}
          </button>
        </div>

        <template v-if="usingSource">
          <ReturnSourcePicker
            v-model:search="source.search.value"
            :return-type="form.return_type"
            :sources="source.sources.value"
            :selected="source.selected.value"
            :is-loading-sources="source.isLoadingSources.value"
            :error-message="source.errorMessage.value"
            @search="source.loadSources"
            @select="source.selectSource"
            @clear="source.clearSource"
          />
          <ReturnWindowNotice
            v-if="source.selected.value && returnWindow.status.value"
            :status="returnWindow.status.value"
            :max-days="returnWindow.maxDays.value"
            :can-override="returnWindow.canOverride.value"
          />
          <ReturnSourceLinesTable
            v-if="source.selected.value"
            :lines="source.lines.value"
            :is-loading="source.isLoadingLines.value"
            @set-quantity="source.setQuantity"
          />
        </template>

        <!-- Items Selection Table -->
        <ReturnItemsTable
          v-else
          :items-list="form.items"
          :search-items="searchItems"
          :selected-item-to-add="selectedItemToAdd"
          @update:selected-item="selectedItemToAdd = $event"
          @add-item="addItemRow"
          @remove-item="removeItemRow"
        />
      </div>

      <!-- Financial summary: beside the form from 1280px, full width under it below -->
      <div class="space-y-4">
        <ReturnFinancialSummary
          :items-count="itemsCount"
          :net-total="netTotal"
          :is-sales="isSalesReturn"
          :refund-method="refund.method.value"
          :refund-locked="refund.locked.value"
          :refund-error="refund.capError.value"
          :refund-hint="refund.hint.value"
          :is-submitting="isSubmitting"
          @update:refund-method="refund.setMethod"
          @submit-return="submitReturn"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ArrowRight } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import ReturnPartySection from '../../Components/Returns/ReturnPartySection.vue';
import ReturnSourcePicker from '../../Components/Returns/ReturnSourcePicker.vue';
import ReturnWindowNotice from '../../Components/Returns/ReturnWindowNotice.vue';
import ReturnSourceLinesTable from '../../Components/Returns/ReturnSourceLinesTable.vue';
import ReturnItemsTable from '../../Components/Returns/ReturnItemsTable.vue';
import ReturnFinancialSummary from '../../Components/Returns/ReturnFinancialSummary.vue';
import { useCreateReturn } from '../../Composables/useCreateReturn';

const {
  searchCustomers,
  searchSuppliers,
  searchItems,
  isSubmitting,
  selectedItemToAdd,
  form,
  refund,
  returnWindow,
  isSalesReturn,
  netTotal,
  itemsCount,
  canWithoutInvoice,
  manualMode,
  usingSource,
  source,
  setManualMode,
  onTypeChange,
  updateField,
  addItemRow,
  removeItemRow,
  submitReturn,
} = useCreateReturn();
</script>
