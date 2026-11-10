<script setup>
import { Boxes, Scale, ScanBarcode, Truck, Users, BarChart3 } from 'lucide-vue-next';

const points = [
  { key: 'pos', icon: ScanBarcode },
  { key: 'inventory', icon: Boxes },
  { key: 'weighted', icon: Scale },
  { key: 'wholesale', icon: Users },
  { key: 'vans', icon: Truck },
  { key: 'reports', icon: BarChart3 },
];
</script>

<template>
  <section class="space-y-5">
    <h2 class="text-center text-xl font-black text-slate-900 dark:text-white">{{ $t('landing.home.points_title') }}</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="point in points"
        :key="point.key"
        class="space-y-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
      >
        <span
          class="flex h-11 w-11 items-center justify-center rounded-2xl"
          style="background: var(--color-primary-light); color: var(--color-primary)"
        >
          <component :is="point.icon" class="h-5 w-5" />
        </span>
        <h3 class="text-sm font-black text-slate-900 dark:text-white">
          {{ $t(`landing.home.points.${point.key}.title`) }}
        </h3>
        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          {{ $t(`landing.home.points.${point.key}.body`) }}
        </p>
      </article>
    </div>
  </section>
</template>
