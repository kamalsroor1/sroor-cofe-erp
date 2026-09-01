<template>
  <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('returns.create_title')"
      :subtitle="$t('returns.create_subtitle')"
      icon="🔄"
    >
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Form (Col Span 2) -->
      <div class="lg:col-span-2 space-y-5">
        <!-- Return Type & Party Info -->
        <ReturnPartySection
          :form="form"
          :customers="customers"
          :suppliers="suppliers"
          @type-change="onTypeChange"
          @update:field="updateField"
        />

        <!-- Items Selection Table -->
        <ReturnItemsTable
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
          :items-count="form.items.length"
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
import PageHeader from '../../Components/Common/PageHeader.vue';
import ReturnPartySection from '../../Components/Returns/ReturnPartySection.vue';
import ReturnItemsTable from '../../Components/Returns/ReturnItemsTable.vue';
import ReturnFinancialSummary from '../../Components/Returns/ReturnFinancialSummary.vue';
import { useCreateReturn } from '../../Composables/useCreateReturn';

const {
  customers,
  suppliers,
  items,
  isSubmitting,
  selectedItemToAdd,
  form,
  netTotal,
  onTypeChange,
  updateField,
  addItemRow,
  removeItemRow,
  submitReturn,
} = useCreateReturn();
</script>
