<script setup>
import { computed, toRef } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import SectionTitle from '../SectionTitle.vue';
import PricingCompareTable from './PricingCompareTable.vue';
import { usePricingCompare } from '../../../Composables/usePricingCompare';

const props = defineProps({
  plans: { type: Array, default: () => [] },
  billing: { type: String, default: 'monthly' },
  founder: { type: Object, default: () => ({ available: false }) },
  currency: { type: String, default: '' },
  isLoading: { type: Boolean, default: false },
});

const { rows, pair, pairPlans, setPair, expanded, canExpand, mobileRows } = usePricingCompare(toRef(props, 'plans'));
const showPicker = computed(() => props.plans.length > 2);
const SLOTS = [0, 1];
</script>

<template>
  <section
    v-if="isLoading || plans.length >= 2"
    id="compare"
    aria-labelledby="compare-title"
    tabindex="-1"
    class="scroll-mt-24 bg-mk-bg px-5 py-14 focus:outline-none sm:px-6 lg:py-24"
  >
    <div class="mx-auto flex max-w-[1200px] flex-col gap-6">
      <SectionTitle
        id="compare-title"
        size="md"
        :title="$t('landing.pricing.compare.title')"
        :subtitle="$t('landing.pricing.compare.subtitle')"
      />

      <div
        v-if="isLoading"
        class="flex flex-col gap-3 rounded-[20px] border border-mk-line bg-mk-surface p-4"
        aria-busy="true"
      >
        <div v-for="n in 8" :key="n" class="flex items-center gap-4">
          <Skeleton width="w-1/3" height="h-5" />
          <Skeleton v-for="m in 3" :key="m" width="flex-1" height="h-5" />
        </div>
      </div>

      <template v-else>
        <div class="hidden lg:block">
          <PricingCompareTable
            :plans="plans"
            :rows="rows"
            :billing="billing"
            :founder="founder"
            :currency="currency"
            :caption="$t('landing.pricing.compare.title')"
          />
        </div>

        <div class="flex flex-col gap-4 lg:hidden">
          <div v-if="showPicker" class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold text-mk-muted">{{ $t('landing.pricing.compare.picker_label') }}</span>
            <label v-for="slot in SLOTS" :key="slot" class="relative min-w-0 flex-1">
              <span class="sr-only">{{ $t(`landing.pricing.compare.pick_${slot === 0 ? 'first' : 'second'}`) }}</span>
              <select
                :value="pair[slot]"
                class="min-h-11 w-full cursor-pointer appearance-none rounded-full scheme-light dark:scheme-dark border border-mk-line bg-mk-surface ps-4 pe-9 text-sm font-bold text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary"
                @change="setPair(slot, $event.target.value)"
              >
                <option
                  v-for="plan in plans"
                  :key="plan.code"
                  :value="plan.code"
                  :disabled="plan.code === pair[1 - slot]"
                >
                  {{ plan.name }}
                </option>
              </select>
              <ChevronDown
                class="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-mk-muted"
                aria-hidden="true"
              />
            </label>
          </div>

          <PricingCompareTable
            :plans="pairPlans"
            :rows="mobileRows"
            :billing="billing"
            :founder="founder"
            :currency="currency"
            :caption="$t('landing.pricing.compare.title')"
          />

          <button
            v-if="canExpand"
            type="button"
            class="mx-auto inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-3 text-[15px] font-bold text-mk-primary focus-visible:outline-2 focus-visible:outline-mk-primary"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
          >
            {{ $t(expanded ? 'landing.pricing.compare.show_less' : 'landing.pricing.compare.show_all') }}
            <ChevronDown class="size-4 transition" :class="{ 'rotate-180': expanded }" aria-hidden="true" />
          </button>
        </div>
      </template>
    </div>
  </section>
</template>
