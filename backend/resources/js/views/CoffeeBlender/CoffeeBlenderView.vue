<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <PageHeader
      :title="$t('inventory.blender_title')"
      :subtitle="$t('inventory.blender_subtitle')"
      icon="📦"
    >
      <template #actions>
        <router-link
          to="/invoices"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
        >
          <ArrowRight class="w-4 h-4" />
          <span>{{ $t('nav.invoices_log') }}</span>
        </router-link>
      </template>
    </PageHeader>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Left: Blender Studio Workspace (Col span 8) -->
      <div class="lg:col-span-8 space-y-5">
        <CoffeeBlenderSpecsCard
          v-model:blend-name="blendName"
          v-model:target-weight-grams="targetWeightGrams"
          v-model:roast-type="roastType"
          v-model:grind-level="grindLevel"
          :preset-weights="presetWeights"
          :roast-options="roastOptions"
          :grind-options="grindOptions"
          @set-target-weight="setTargetWeight"
        />

        <CoffeeBlenderFormulationCard
          v-model:selected-item-id-to-add="selectedItemIdToAdd"
          v-model:cardamom-grams="cardamomGrams"
          v-model:notes="notes"
          :components="components"
          :calculated-components="calculatedComponents"
          :total-percentage="totalPercentage"
          :item-options="itemOptions"
          @add-component="addComponentRow"
          @remove-component="removeComponentRow"
          @update-percentage="updateComponentPercentage"
        />
      </div>

      <!-- Right: Financial Breakdown & Direct Cashier (Col span 4) -->
      <div class="lg:col-span-4 space-y-5">
        <CoffeeBlenderCostSummary
          v-model:selected-customer-id="selectedCustomerId"
          :target-weight-grams="targetWeightGrams"
          :total-calculated-cost="totalCalculatedCost"
          :total-calculated-price="totalCalculatedPrice"
          :profit-margin="profitMargin"
          :customer-options="customerOptions"
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
import CoffeeBlenderSpecsCard from '../../Components/CoffeeBlender/CoffeeBlenderSpecsCard.vue';
import CoffeeBlenderFormulationCard from '../../Components/CoffeeBlender/CoffeeBlenderFormulationCard.vue';
import CoffeeBlenderCostSummary from '../../Components/CoffeeBlender/CoffeeBlenderCostSummary.vue';
import { useCoffeeBlender } from '../../Composables/useCoffeeBlender';

const {
  blendName,
  targetWeightGrams,
  selectedCustomerId,
  selectedItemIdToAdd,
  roastType,
  grindLevel,
  cardamomGrams,
  notes,
  isSubmitting,
  presetWeights,
  roastOptions,
  grindOptions,
  itemOptions,
  customerOptions,
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
} = useCoffeeBlender();
</script>
