<script setup>
import { computed } from 'vue';
import { Globe, Mail, Monitor, Phone, Smartphone } from 'lucide-vue-next';
import LandingLocaleToggle from './LandingLocaleToggle.vue';
import LandingLogo from './LandingLogo.vue';
import { useLandingNav } from '../../Composables/useLandingNav';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  /** brand block of GET /public/plans: name, support_phone, support_email. */
  brand: { type: Object, default: () => ({}) },
});

const { t } = useTrans();
const { industries, industryRoute, industryLabelKey } = useLandingNav();

const platforms = [
  { key: 'web', icon: Globe },
  { key: 'android', icon: Smartphone },
  { key: 'windows', icon: Monitor },
];

const columns = computed(() => [
  {
    key: 'product',
    links: [
      { key: 'features', label: t('landing.nav.features'), to: { name: 'landing.features' } },
      { key: 'pricing', label: t('landing.nav.pricing'), to: { name: 'landing.pricing' } },
      { key: 'download', label: t('landing.nav.download'), to: { name: 'app.download' } },
      { key: 'login', label: t('landing.nav.login'), to: { name: 'login' } },
    ],
  },
  {
    key: 'industries',
    links: industries.map((item) => ({
      key: item.key,
      label: t(industryLabelKey(item.key)),
      to: industryRoute(item.key),
    })),
  },
  {
    key: 'company',
    links: [
      { key: 'contact', label: t('landing.nav.contact'), to: { name: 'landing.contact' } },
      { key: 'terms', label: t('landing.nav.terms'), to: { name: 'landing.terms' } },
      { key: 'privacy', label: t('landing.nav.privacy'), to: { name: 'landing.privacy' } },
      { key: 'dpa', label: t('landing.nav.dpa'), to: { name: 'landing.dpa' } },
    ],
  },
]);

const brandName = computed(() => props.brand.name || t('brand.name'));
const year = new Date().getFullYear();
</script>

<template>
  <footer class="border-t border-mk-line bg-mk-surface">
    <div class="mx-auto max-w-[1248px] px-5 pt-10 pb-8 sm:px-6 lg:pt-14">
      <div class="flex flex-col gap-10 lg:flex-row lg:justify-between">
        <div class="flex flex-col gap-3.5 lg:w-[340px]">
          <LandingLogo :name="brand.name || ''" :size="34" />
          <p class="text-[15px] leading-[1.6] text-mk-muted">{{ $t('landing.footer.tagline') }}</p>
          <ul class="flex flex-wrap gap-2" :aria-label="$t('landing.footer.platforms_label')">
            <li
              v-for="platform in platforms"
              :key="platform.key"
              class="inline-flex h-[30px] items-center gap-1.5 rounded-full bg-mk-bg px-3 text-xs font-medium text-mk-ink"
            >
              <component :is="platform.icon" class="size-3.5 text-mk-primary" aria-hidden="true" />
              {{ $t(`landing.footer.platforms.${platform.key}`) }}
            </li>
          </ul>
          <div v-if="brand.support_phone || brand.support_email" class="flex flex-col gap-1 pt-1">
            <p class="text-sm font-extrabold text-mk-ink">{{ $t('landing.home.support_title') }}</p>
            <a
              v-if="brand.support_phone"
              :href="`tel:${brand.support_phone}`"
              class="inline-flex min-h-11 w-fit items-center gap-2 text-[15px] font-medium text-mk-muted hover:text-mk-ink"
            >
              <Phone class="size-4 text-mk-primary" aria-hidden="true" />
              <span dir="ltr">{{ brand.support_phone }}</span>
            </a>
            <a
              v-if="brand.support_email"
              :href="`mailto:${brand.support_email}`"
              class="inline-flex min-h-11 w-fit items-center gap-2 text-[15px] font-medium text-mk-muted hover:text-mk-ink"
            >
              <Mail class="size-4 text-mk-primary" aria-hidden="true" />
              <span dir="ltr">{{ brand.support_email }}</span>
            </a>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 lg:flex lg:gap-12">
          <nav
            v-for="column in columns"
            :key="column.key"
            :aria-labelledby="`footer-${column.key}`"
            class="flex flex-col gap-1 lg:w-[180px]"
          >
            <h2 :id="`footer-${column.key}`" class="mb-1 text-[15px] font-extrabold text-mk-ink">
              {{ $t(`landing.footer.${column.key}`) }}
            </h2>
            <router-link
              v-for="link in column.links"
              :key="link.key"
              :to="link.to"
              class="inline-flex min-h-11 w-fit items-center rounded-lg text-[15px] font-medium text-mk-muted transition hover:text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary"
            >
              {{ link.label }}
            </router-link>
          </nav>
        </div>
      </div>

      <div
        class="mt-10 flex flex-col gap-3 border-t border-mk-line pt-5 sm:flex-row sm:items-center sm:justify-between"
      >
        <p class="text-sm text-mk-faint">{{ $t('landing.footer.copyright', { year, name: brandName }) }}</p>
        <LandingLocaleToggle variant="name" class="w-fit" />
      </div>
    </div>
  </footer>
</template>
