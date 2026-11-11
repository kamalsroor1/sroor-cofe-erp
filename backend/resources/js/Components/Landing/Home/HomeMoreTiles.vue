<script setup>
import {
  FileSpreadsheet,
  FileText,
  History,
  Languages,
  Printer,
  Send,
  ShieldCheck,
  Tags,
  Trash2,
  Undo2,
} from 'lucide-vue-next';
import MarketingIconBadge from '../MarketingIconBadge.vue';
import SectionTitle from '../SectionTitle.vue';

const items = [
  { key: 'returns', icon: Undo2 },
  { key: 'statements', icon: FileText },
  { key: 'price_tiers', icon: Tags },
  { key: 'permissions', icon: ShieldCheck },
  { key: 'activity', icon: History },
  { key: 'trash', icon: Trash2 },
  { key: 'telegram', icon: Send },
  { key: 'excel', icon: FileSpreadsheet },
  { key: 'thermal', icon: Printer },
  { key: 'languages', icon: Languages },
];
</script>

<template>
  <section aria-labelledby="more-title" class="bg-mk-bg px-5 py-16 sm:px-6 lg:py-28">
    <div class="mx-auto flex max-w-[1200px] flex-col gap-8">
      <SectionTitle id="more-title" :title="$t('landing.home.more.title')">
        <p class="text-[15px] text-mk-faint">{{ $t('landing.home.more.note') }}</p>
      </SectionTitle>
      <ul class="grid grid-cols-2 gap-2.5 md:grid-cols-5 lg:gap-4">
        <li
          v-for="item in items"
          :key="item.key"
          class="flex flex-col items-start gap-3 rounded-2xl border border-mk-line bg-mk-surface p-3.5 xl:flex-row xl:items-center"
        >
          <MarketingIconBadge :icon="item.icon" :size="40" />
          <span class="text-sm leading-[1.4] font-bold text-mk-ink lg:text-base">
            {{ $t(`landing.home.more.items.${item.key}`) }}
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>
