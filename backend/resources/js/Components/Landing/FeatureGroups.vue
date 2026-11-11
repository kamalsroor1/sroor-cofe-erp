<script setup>
import {
  BarChart3,
  Boxes,
  CircleCheck,
  Coffee,
  Info,
  PackagePlus,
  ScanBarcode,
  ShieldCheck,
  Users,
  Wallet,
} from 'lucide-vue-next';
import LandingSection from './LandingSection.vue';

// Every bullet maps to a shipped module (see the PR report for the code path of each claim).
const groups = [
  {
    key: 'pos',
    icon: ScanBarcode,
    items: ['barcode', 'quick_keys', 'shortcuts', 'multi_orders', 'payments', 'shifts', 'receipt', 'pin'],
  },
  { key: 'inventory', icon: Boxes, items: ['stores', 'transfers', 'movements', 'adjustments', 'units', 'imports'] },
  { key: 'purchases', icon: PackagePlus, items: ['purchases', 'returns', 'reorder', 'cancel'] },
  { key: 'parties', icon: Users, items: ['statements', 'payments', 'tiers', 'last_price'] },
  { key: 'treasury', icon: Wallet, items: ['treasury', 'expenses', 'journal'] },
  {
    key: 'reports',
    icon: BarChart3,
    items: ['profit_loss', 'items_profit', 'valuation', 'stores', 'customers', 'expenses'],
  },
  { key: 'team', icon: ShieldCheck, items: ['roles', 'stores_access', 'activity', 'trash', 'telegram'] },
  { key: 'blender', icon: Coffee, items: ['blend', 'roast_grind', 'cost'] },
];
</script>

<template>
  <LandingSection
    id="features"
    :title="$t('landing.home.features.title')"
    :subtitle="$t('landing.home.features.subtitle')"
  >
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article
        v-for="group in groups"
        :key="group.key"
        class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="flex items-start gap-3">
          <span
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl"
            style="background: var(--color-primary-light); color: var(--color-primary)"
          >
            <component :is="group.icon" class="h-5 w-5" />
          </span>
          <div class="space-y-1">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">
              {{ $t(`landing.home.features.groups.${group.key}.title`) }}
            </h3>
            <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
              {{ $t(`landing.home.features.groups.${group.key}.body`) }}
            </p>
          </div>
        </div>
        <ul class="space-y-2">
          <li
            v-for="item in group.items"
            :key="item"
            class="flex items-start gap-2 text-xs leading-relaxed text-slate-700 dark:text-slate-300"
          >
            <CircleCheck class="mt-0.5 h-4 w-4 shrink-0" style="color: var(--color-primary)" />
            <span>{{ $t(`landing.home.features.groups.${group.key}.items.${item}`) }}</span>
          </li>
        </ul>
      </article>
    </div>
    <p class="flex items-center justify-center gap-2 text-center text-xs font-bold text-slate-500 dark:text-slate-400">
      <Info class="h-4 w-4 shrink-0" />
      {{ $t('landing.home.features.plan_note') }}
    </p>
  </LandingSection>
</template>
