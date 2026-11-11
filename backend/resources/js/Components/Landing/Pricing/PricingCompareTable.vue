<script setup>
import { computed } from 'vue';
import { Check, Minus } from 'lucide-vue-next';
import { catalogCurrencyLabel, formatCatalogPrice } from '../../../helpers/landingPricing';
import { planDisplayPrice } from '../../../helpers/landingCatalog';

const props = defineProps({
  /** Plans shown as columns, in display order. */
  plans: { type: Array, required: true },
  /** Rows from usePricingCompare: { type: 'group' | 'row', id, label, description?, cells? }. */
  rows: { type: Array, required: true },
  billing: { type: String, default: 'monthly' },
  founder: { type: Object, default: () => ({ available: false }) },
  currency: { type: String, default: '' },
  caption: { type: String, default: '' },
});

const currencyLabel = computed(() => catalogCurrencyLabel(props.currency));
const periodKey = computed(() => (props.billing === 'yearly' ? 'landing.per_year' : 'landing.per_month'));
const priceOf = (plan) => formatCatalogPrice(planDisplayPrice(plan, props.billing, props.founder).shown);
const popularCell = (plan) => (plan.is_popular ? 'bg-mk-soft/45' : '');
</script>

<template>
  <!-- overflow-clip (not hidden) keeps the rounded corners without breaking the sticky header row. -->
  <div class="overflow-clip rounded-[20px] border border-mk-line bg-mk-surface p-1.5 sm:p-2">
    <table class="w-full table-fixed border-collapse">
      <caption v-if="caption" class="sr-only">
        {{
          caption
        }}
      </caption>
      <colgroup>
        <col class="w-[40%] sm:w-[34%] lg:w-[28%]" />
        <col v-for="plan in plans" :key="plan.code" />
      </colgroup>
      <thead>
        <tr>
          <td class="sticky top-[68px] z-10 border-b border-mk-line bg-mk-surface lg:top-[76px]" />
          <th
            v-for="plan in plans"
            :key="plan.code"
            scope="col"
            class="sticky top-[68px] z-10 rounded-t-[14px] border-b border-mk-line px-1.5 py-3 text-center align-bottom lg:top-[76px]"
            :class="plan.is_popular ? 'bg-mk-soft' : 'bg-mk-surface'"
          >
            <span class="block text-[15px] font-bold text-mk-ink sm:text-[17px]">{{ plan.name }}</span>
            <span class="mt-0.5 block text-xs font-medium text-mk-muted sm:text-[13px]">
              <span dir="ltr">{{ priceOf(plan) }}</span> {{ currencyLabel }} / {{ $t(periodKey) }}
            </span>
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-for="row in rows" :key="row.id">
          <tr v-if="row.type === 'group'">
            <th
              :colspan="plans.length + 1"
              scope="colgroup"
              class="px-3 pt-5 pb-2 text-start text-sm font-bold text-mk-primary"
            >
              {{ row.label }}
            </th>
          </tr>
          <tr v-else class="border-b border-mk-line last:border-b-0">
            <th
              scope="row"
              class="px-3 py-3 text-start text-sm leading-[1.45] font-medium text-mk-ink sm:text-[15px]"
              :title="row.description || undefined"
            >
              {{ row.label }}
            </th>
            <td
              v-for="plan in plans"
              :key="plan.code"
              class="px-1.5 py-3 text-center text-sm font-bold text-mk-ink"
              :class="popularCell(plan)"
            >
              <template v-if="row.cells[plan.code]?.kind === 'yes'">
                <span
                  class="inline-flex size-6 items-center justify-center rounded-full bg-mk-primary text-mk-primary-ink"
                >
                  <Check class="size-3.5" stroke-width="3" aria-hidden="true" />
                </span>
                <span class="sr-only">{{ $t('landing.pricing.compare.included') }}</span>
              </template>
              <template v-else-if="row.cells[plan.code]?.kind === 'text'">{{ row.cells[plan.code].text }}</template>
              <template v-else>
                <Minus class="mx-auto size-4 text-mk-faint" aria-hidden="true" />
                <span class="sr-only">{{ $t('landing.pricing.compare.not_included') }}</span>
              </template>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
