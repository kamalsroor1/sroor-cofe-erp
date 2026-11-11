<script setup>
import { Boxes, ChartColumn, LayoutDashboard, ScanBarcode, Settings, Users, Wallet } from 'lucide-vue-next';
import BrandMark from '../../Brand/BrandMark.vue';

defineProps({
  active: { type: String, default: 'pos', validator: (value) => ['pos', 'inventory', 'reports'].includes(value) },
});

const items = [
  { key: 'dashboard', icon: LayoutDashboard },
  { key: 'pos', icon: ScanBarcode },
  { key: 'inventory', icon: Boxes },
  { key: 'customers', icon: Users },
  { key: 'treasury', icon: Wallet },
  { key: 'reports', icon: ChartColumn },
  { key: 'settings', icon: Settings },
];
</script>

<template>
  <div class="flex w-14 shrink-0 flex-col items-center gap-2.5 border-e border-mk-app-line bg-mk-app-surface py-3.5">
    <BrandMark :size="26" decorative />
    <span
      v-for="item in items"
      :key="item.key"
      class="flex size-9 items-center justify-center rounded-[10px]"
      :class="item.key === active ? 'bg-mk-primary text-mk-primary-ink' : 'text-mk-app-muted'"
    >
      <component :is="item.icon" class="size-[18px]" />
    </span>
  </div>
</template>
