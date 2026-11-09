<template>
  <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
    <!-- Page Header -->
    <PageHeader :title="$t('returns.create_title')" :subtitle="$t('returns.create_subtitle')" icon="🔄">
      <template #actions>
        <router-link
          to="/returns"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
        >
          <ArrowRight class="w-4 h-4" />
          <span>{{ $t('returns.back_to_returns') }}</span>
        </router-link>
      </template>
    </PageHeader>

    <InlineErrorBar v-if="error" :message="errorMessage" @retry="retry" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Form (Col Span 2) -->
      <div class="lg:col-span-2 space-y-5">
        <!-- Return Type & Party Info -->
        <ReturnPartySection
          :form="form"
          :customers="customers"
          :suppliers="suppliers"
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
          :items="items"
          :selected-item-to-add="selectedItemToAdd"
          @update:selected-item="selectedItemToAdd = $event"
          @add-item="addItemRow"
          @remove-item="removeItemRow"
        />
      </div>

      <!-- Sidebar Financial Summary (Col Span 1) -->
      <div class="space-y-4">
        <ReturnFinancialSummary
          :items-count="itemsCount"
          :net-total="netTotal"
          :refund-amount="form.refund_amount"
          :is-submitting="isSubmitting"
          @update:refund-amount="updateField('refund_amount', $event)"
          @submit-return="submitReturn"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ArrowRight } from 'lucide-vue-next';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import ReturnPartySection from '../../Components/Returns/ReturnPartySection.vue';
import ReturnSourcePicker from '../../Components/Returns/ReturnSourcePicker.vue';
import ReturnSourceLinesTable from '../../Components/Returns/ReturnSourceLinesTable.vue';
import ReturnItemsTable from '../../Components/Returns/ReturnItemsTable.vue';
import ReturnFinancialSummary from '../../Components/Returns/ReturnFinancialSummary.vue';
import { useCreateReturn } from '../../Composables/useCreateReturn';

const {
  error,
  errorMessage,
  retry,
  customers,
  suppliers,
  items,
  isSubmitting,
  selectedItemToAdd,
  form,
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
