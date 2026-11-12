<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <PageHeader :title="$t('inventory.blender_title')" :subtitle="$t('inventory.blender_subtitle')" icon="package">
      <template #actions>
        <router-link
          to="/invoices"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
        >
          <ArrowRight class="ltr:rotate-180 w-4 h-4" />
          <span>{{ $t('nav.invoices_log') }}</span>
        </router-link>
      </template>
    </PageHeader>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Left: Blender Studio Workspace (Col span 8) -->
      <div class="lg:col-span-8 space-y-5">
        <MixSpecsCard
          v-model:blend-name="blendName"
          v-model:target-weight-grams="targetWeightGrams"
          v-model:roast-type="roastType"
          v-model:grind-level="grindLevel"
          :preset-weights="presetWeights"
          :roast-options="roastOptions"
          :grind-options="grindOptions"
          @set-target-weight="setTargetWeight"
        />

        <MixFormulationCard
          v-model:selected-item-to-add="selectedItemToAdd"
          v-model:cardamom-grams="cardamomGrams"
          v-model:notes="notes"
          :components="components"
          :calculated-components="calculatedComponents"
          :total-percentage="totalPercentage"
          :search-fn="searchItems"
          @add-component="addComponentRow"
          @remove-component="removeComponentRow"
          @update-percentage="updateComponentPercentage"
        />
      </div>

      <!-- Right: Financial Breakdown & Direct Cashier (Col span 4) -->
      <div class="lg:col-span-4 space-y-5">
        <MixCostSummary
          v-model:selected-customer-id="selectedCustomerId"
          :target-weight-grams="targetWeightGrams"
          :total-calculated-cost="totalCalculatedCost"
          :total-calculated-price="totalCalculatedPrice"
          :profit-margin="profitMargin"
          :search-customers="searchCustomers"
          :is-submitting="isSubmitting"
          :components-count="components.length"
          @submit-invoice="submitBlendInvoice"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ArrowRight } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import MixSpecsCard from '../../Components/Mixes/MixSpecsCard.vue';
import MixFormulationCard from '../../Components/Mixes/MixFormulationCard.vue';
import MixCostSummary from '../../Components/Mixes/MixCostSummary.vue';
import { useMixes } from '../../Composables/useMixes';

const {
  blendName,
  targetWeightGrams,
  selectedCustomerId,
  selectedItemToAdd,
  roastType,
  grindLevel,
  cardamomGrams,
  notes,
  isSubmitting,
  presetWeights,
  roastOptions,
  grindOptions,
  searchItems,
  searchCustomers,
  components,
  calculatedComponents,
  totalPercentage,
  totalCalculatedCost,
  totalCalculatedPrice,
  profitMargin,
  setTargetWeight,
  addComponentRow,
  removeComponentRow,
  updateComponentPercentage,
  submitBlendInvoice,
} = useMixes();
</script>
