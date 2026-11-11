<script setup>
import { ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import LandingSection from './LandingSection.vue';

const questions = ['isolation', 'languages', 'devices', 'branches', 'trial', 'change_plan'];
const openKey = ref(questions[0]);

const toggle = (key) => {
  openKey.value = openKey.value === key ? '' : key;
};
</script>

<template>
  <LandingSection id="faq" :title="$t('landing.home.faq.title')">
    <div
      class="mx-auto max-w-3xl divide-y divide-slate-200 overflow-hidden rounded-3xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900"
    >
      <div v-for="key in questions" :key="key">
        <h3>
          <button
            :id="`faq-${key}-button`"
            type="button"
            :aria-expanded="openKey === key"
            :aria-controls="`faq-${key}-panel`"
            class="flex min-h-14 w-full cursor-pointer items-center justify-between gap-4 px-5 py-3 text-start text-sm font-black text-slate-900 transition hover:bg-slate-50 focus-visible:bg-slate-50 dark:text-white dark:hover:bg-slate-800/60 dark:focus-visible:bg-slate-800/60"
            @click="toggle(key)"
          >
            <span>{{ $t(`landing.home.faq.items.${key}.question`) }}</span>
            <ChevronDown
              class="h-5 w-5 shrink-0 text-slate-400 transition-transform duration-200"
              :class="{ 'rotate-180': openKey === key }"
            />
          </button>
        </h3>
        <div
          v-show="openKey === key"
          :id="`faq-${key}-panel`"
          role="region"
          :aria-labelledby="`faq-${key}-button`"
          class="px-5 pb-5 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
        >
          {{ $t(`landing.home.faq.items.${key}.answer`) }}
        </div>
      </div>
    </div>
  </LandingSection>
</template>
