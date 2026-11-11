<script setup>
import { ref } from 'vue';
import { MessageCircle, Minus, Plus } from 'lucide-vue-next';

const questions = ['isolation', 'trial', 'branches', 'devices', 'languages', 'change_plan'];
const openKey = ref(questions[0]);

const toggle = (key) => {
  openKey.value = openKey.value === key ? '' : key;
};
</script>

<template>
  <section
    id="faq"
    aria-labelledby="faq-title"
    tabindex="-1"
    class="scroll-mt-24 bg-mk-bg px-5 py-16 focus:outline-none sm:px-6 lg:pt-24 lg:pb-28"
  >
    <div class="mx-auto flex max-w-[1200px] flex-col gap-8 lg:flex-row lg:items-start lg:gap-16">
      <div class="flex flex-col items-center gap-4 text-center lg:w-[360px] lg:shrink-0 lg:items-start lg:text-start">
        <h2 id="faq-title" class="text-[30px] font-extrabold text-mk-ink lg:text-[44px]">
          {{ $t('landing.home.faq.title') }}
        </h2>
        <p class="text-base text-mk-muted lg:text-lg">{{ $t('landing.home.faq.subtitle') }}</p>
        <router-link
          :to="{ name: 'landing.contact' }"
          class="inline-flex min-h-11 items-center gap-2 rounded-full border border-mk-line bg-mk-surface px-4 text-[15px] font-medium text-mk-ink transition hover:bg-mk-surface-2 focus-visible:outline-2 focus-visible:outline-mk-primary"
        >
          <MessageCircle class="size-4 text-mk-primary" aria-hidden="true" />
          {{ $t('landing.nav.contact') }}
        </router-link>
      </div>

      <ul class="flex flex-1 flex-col gap-3">
        <li v-for="key in questions" :key="key" class="rounded-2xl border border-mk-line bg-mk-surface">
          <h3>
            <button
              :id="`faq-${key}-button`"
              type="button"
              :aria-expanded="openKey === key"
              :aria-controls="`faq-${key}-panel`"
              class="flex min-h-14 w-full cursor-pointer items-center justify-between gap-3 rounded-2xl px-[22px] py-5 text-start text-[17px] font-bold text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary"
              @click="toggle(key)"
            >
              <span>{{ $t(`landing.home.faq.items.${key}.question`) }}</span>
              <Minus v-if="openKey === key" class="size-5 shrink-0 text-mk-primary" aria-hidden="true" />
              <Plus v-else class="size-5 shrink-0 text-mk-primary" aria-hidden="true" />
            </button>
          </h3>
          <div
            v-show="openKey === key"
            :id="`faq-${key}-panel`"
            role="region"
            :aria-labelledby="`faq-${key}-button`"
            class="-mt-2.5 px-[22px] pb-5 text-[15px] leading-[1.65] text-mk-muted"
          >
            {{ $t(`landing.home.faq.items.${key}.answer`) }}
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
