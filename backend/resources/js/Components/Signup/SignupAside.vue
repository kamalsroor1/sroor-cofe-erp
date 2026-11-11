<script setup>
import { computed } from 'vue';
import { Mail, Phone, Sparkles } from 'lucide-vue-next';
import MarketingMotif from '../Landing/MarketingMotif.vue';
import { catalogCurrencyLabel, formatCatalogPrice } from '../../helpers/landingPricing';
import { planDisplayPrice } from '../../helpers/landingCatalog';

const props = defineProps({
  /** The chosen public plan, or null (the plan card is hidden). */
  plan: { type: Object, default: null },
  founder: { type: Object, default: () => ({ available: false }) },
  currency: { type: String, default: '' },
  /** brand block of GET /public/plans (support_phone / support_email are optional). */
  brand: { type: Object, default: () => ({}) },
});

defineEmits(['change-plan']);

const price = computed(() => (props.plan ? planDisplayPrice(props.plan, 'monthly', props.founder) : null));
const currencyLabel = computed(() => catalogCurrencyLabel(props.currency));

// "What happens next": the first step is signup-specific, the other two reuse the homepage steps.
const STEPS = [
  { key: 'review', title: 'signup.aside.review_title', body: 'signup.aside.review_body' },
  { key: 'setup', title: 'landing.home.steps.items.setup.title', body: 'landing.home.steps.items.setup.short' },
  { key: 'start', title: 'landing.home.steps.items.start.title', body: 'landing.home.steps.items.start.short' },
];

const contacts = computed(() =>
  [
    props.brand?.support_phone && {
      key: 'phone',
      icon: Phone,
      value: props.brand.support_phone,
      href: `tel:${props.brand.support_phone}`,
    },
    props.brand?.support_email && {
      key: 'email',
      icon: Mail,
      value: props.brand.support_email,
      href: `mailto:${props.brand.support_email}`,
    },
  ].filter(Boolean)
);
</script>

<template>
  <aside
    :aria-label="$t('signup.aside.label')"
    class="relative flex flex-col gap-[22px] overflow-hidden rounded-3xl bg-mk-hero p-6 text-mk-hero-ink lg:p-9"
  >
    <MarketingMotif class="absolute -start-[140px] top-[300px] size-[420px]" />

    <div v-if="plan" class="relative flex flex-col gap-2.5 rounded-2xl border border-white/12 bg-white/6 p-[18px]">
      <div class="flex items-center justify-between gap-3">
        <p class="text-[13px] font-medium text-mk-hero-muted">{{ $t('signup.aside.selected_plan') }}</p>
        <button
          type="button"
          class="-me-2 inline-flex min-h-11 cursor-pointer items-center rounded-lg px-2 text-[13px] font-bold text-mk-hero-accent hover:underline focus-visible:outline-2 focus-visible:outline-mk-accent"
          @click="$emit('change-plan')"
        >
          {{ $t('signup.aside.change') }}
        </button>
      </div>
      <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <p class="text-[22px] font-bold text-white">{{ plan.name }}</p>
        <p class="text-[15px] font-bold text-white">
          <span dir="ltr">{{ formatCatalogPrice(price.shown) }}</span> {{ currencyLabel }} /
          {{ $t('landing.per_month') }}
        </p>
      </div>
      <div v-if="plan.trial_days || price.hasFounder" class="flex flex-wrap gap-2">
        <span
          v-if="plan.trial_days"
          class="inline-flex min-h-7 items-center gap-1.5 rounded-full bg-mk-accent/15 px-3 text-xs font-medium text-mk-accent"
        >
          <Sparkles class="size-3.5" aria-hidden="true" />
          {{ $t('landing.trial_days', { count: plan.trial_days }) }}
        </span>
        <span
          v-if="price.hasFounder"
          class="inline-flex min-h-7 items-center rounded-full bg-mk-accent/15 px-3 text-xs font-medium text-mk-accent"
        >
          {{ $t('landing.founder_badge') }}
        </span>
      </div>
    </div>

    <h2 class="relative text-lg font-bold text-white">{{ $t('signup.aside.next_title') }}</h2>
    <ol class="relative flex flex-col gap-[22px]">
      <li v-for="(step, index) in STEPS" :key="step.key" class="flex items-start gap-3.5">
        <span
          class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-mk-accent text-[15px] font-bold text-mk-accent-ink"
          aria-hidden="true"
        >
          {{ index + 1 }}
        </span>
        <div class="flex flex-col gap-1">
          <h3 class="text-base font-bold text-white">{{ $t(step.title) }}</h3>
          <p class="text-sm leading-[1.5] text-mk-hero-muted">{{ $t(step.body) }}</p>
        </div>
      </li>
    </ol>

    <div v-if="contacts.length" class="relative flex flex-col gap-2">
      <p class="text-[13px] font-medium text-mk-hero-muted">{{ $t('signup.aside.help') }}</p>
      <a
        v-for="contact in contacts"
        :key="contact.key"
        :href="contact.href"
        class="flex min-h-11 items-center gap-3 rounded-xl text-[15px] font-bold text-white hover:underline focus-visible:outline-2 focus-visible:outline-mk-accent"
      >
        <span
          class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/8 text-mk-hero-accent"
        >
          <component :is="contact.icon" class="size-5" aria-hidden="true" />
        </span>
        <span dir="ltr" class="min-w-0 truncate">{{ contact.value }}</span>
      </a>
    </div>
  </aside>
</template>
