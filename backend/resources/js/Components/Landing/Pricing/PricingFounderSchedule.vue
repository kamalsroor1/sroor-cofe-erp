<script setup>
import { computed } from 'vue';
import { Sparkles } from 'lucide-vue-next';
import SectionTitle from '../SectionTitle.vue';
import { founderSchedule } from '../../../helpers/landingCatalog';
import { catalogCurrencyLabel, formatCatalogPrice } from '../../../helpers/landingPricing';

const props = defineProps({
  plans: { type: Array, default: () => [] },
  billing: { type: String, default: 'monthly' },
  founder: { type: Object, default: () => ({ available: false }) },
  currency: { type: String, default: '' },
});

const currencyLabel = computed(() => catalogCurrencyLabel(props.currency));
const periodKey = computed(() => (props.billing === 'yearly' ? 'landing.per_year' : 'landing.per_month'));
const rows = computed(() =>
  props.plans
    .map((plan) => ({ plan, steps: founderSchedule(plan, props.billing, props.founder) }))
    .filter((row) => row.steps.length > 0)
);
const visible = computed(() => props.founder?.available === true && rows.value.length > 0);
const hasSlots = computed(() => props.founder?.slots_left !== null && props.founder?.slots_left !== undefined);
</script>

<template>
  <section
    v-if="visible"
    id="founder-offer"
    aria-labelledby="founder-offer-title"
    class="bg-mk-bg px-5 py-10 sm:px-6 lg:py-16"
    data-testid="founder-schedule"
  >
    <div class="mx-auto flex max-w-[1200px] flex-col gap-6">
      <SectionTitle
        id="founder-offer-title"
        size="md"
        :title="$t('landing.pricing.founder_schedule.title', { count: founder.slots_total })"
        :subtitle="$t('landing.pricing.founder_schedule.subtitle')"
      />
      <p
        v-if="hasSlots"
        class="mx-auto inline-flex min-h-9 items-center gap-2 rounded-full bg-mk-accent-soft px-4 py-1.5 text-sm font-bold text-mk-warn-ink"
      >
        <Sparkles class="size-4 shrink-0" aria-hidden="true" />
        {{
          $t('landing.pricing.founder_schedule.slots_left', { left: founder.slots_left, total: founder.slots_total })
        }}
      </p>

      <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <li
          v-for="row in rows"
          :key="row.plan.code"
          class="flex flex-col gap-3 rounded-[20px] border border-mk-line bg-mk-surface p-5"
        >
          <h3 class="text-lg font-bold text-mk-ink">{{ row.plan.name }}</h3>
          <ol class="flex flex-col divide-y divide-mk-line">
            <li v-for="step in row.steps" :key="step.step" class="flex items-center justify-between gap-3 py-2.5">
              <div class="min-w-0">
                <p class="text-sm font-bold text-mk-ink">
                  {{ $t(`landing.pricing.founder_schedule.steps.${step.step}`) }}
                </p>
                <p class="text-xs text-mk-muted">
                  <template v-if="step.to">
                    {{ $t('landing.pricing.founder_schedule.months_range', { from: step.from, to: step.to }) }}
                  </template>
                  <template v-else>
                    {{ $t('landing.pricing.founder_schedule.months_from', { from: step.from }) }}
                  </template>
                </p>
              </div>
              <p class="shrink-0 text-end text-sm font-bold text-mk-ink">
                <span dir="ltr" class="text-lg">{{ formatCatalogPrice(step.price) }}</span>
                <span class="block text-xs font-medium text-mk-muted">{{ currencyLabel }} / {{ $t(periodKey) }}</span>
              </p>
            </li>
          </ol>
        </li>
      </ul>
      <p class="text-center text-[13px] text-mk-faint">{{ $t('landing.prices_exclude_vat') }}</p>
    </div>
  </section>
</template>
