<script setup>
import { ClipboardList, Rocket, ServerCog } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import LandingSection from './LandingSection.vue';

// PRD-05 assisted signup: request -> approved and provisioned from the platform console -> sign in.
const steps = [
  { key: 'request', icon: ClipboardList },
  { key: 'setup', icon: ServerCog },
  { key: 'start', icon: Rocket },
];
</script>

<template>
  <LandingSection id="how" :title="$t('landing.home.steps.title')" :subtitle="$t('landing.home.steps.subtitle')">
    <ol class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <li
        v-for="(step, index) in steps"
        :key="step.key"
        class="relative space-y-3 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="flex items-center gap-3">
          <span
            class="flex h-10 w-10 items-center justify-center rounded-full border text-sm font-black"
            style="
              background: var(--color-primary-light);
              border-color: var(--color-primary-border);
              color: var(--color-primary);
            "
          >
            {{ index + 1 }}
          </span>
          <component :is="step.icon" class="h-5 w-5" style="color: var(--color-primary)" />
        </div>
        <h3 class="text-sm font-black text-slate-900 dark:text-white">
          {{ $t(`landing.home.steps.items.${step.key}.title`) }}
        </h3>
        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          {{ $t(`landing.home.steps.items.${step.key}.body`) }}
        </p>
      </li>
    </ol>
    <div class="flex justify-center">
      <BaseButton to="/signup" variant="primary" size="lg">{{ $t('landing.request_trial') }}</BaseButton>
    </div>
  </LandingSection>
</template>
