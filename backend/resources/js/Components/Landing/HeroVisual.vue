<script setup>
import { BarChart3, Boxes, Printer, Scale, ScanBarcode, Store, Truck, Wallet } from 'lucide-vue-next';

// Decorative overview of the real modules (the same groups are described in FeatureGroups).
const modules = [
  { key: 'pos', icon: ScanBarcode },
  { key: 'inventory', icon: Boxes },
  { key: 'treasury', icon: Wallet },
  { key: 'reports', icon: BarChart3 },
];
const devices = [Scale, Printer, Store, Truck];
</script>

<template>
  <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
    <div
      class="absolute inset-0 rounded-[2.5rem] opacity-70 blur-2xl sm:-inset-6"
      style="background: radial-gradient(circle at 30% 20%, var(--color-primary-light), transparent 70%)"
    ></div>

    <div
      class="relative space-y-4 rounded-3xl border bg-white p-5 shadow-xl dark:bg-slate-900"
      style="border-color: var(--color-primary-border)"
    >
      <div class="flex items-center gap-1.5">
        <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-700"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-700"></span>
        <span class="h-2.5 w-2.5 rounded-full" style="background: var(--color-primary)"></span>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div
          v-for="module in modules"
          :key="module.key"
          class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950"
        >
          <span
            class="flex h-10 w-10 items-center justify-center rounded-xl"
            style="background: var(--color-primary-light); color: var(--color-primary)"
          >
            <component :is="module.icon" class="h-5 w-5" />
          </span>
          <p class="text-xs font-black text-slate-800 dark:text-slate-100">
            {{ $t(`landing.home.features.groups.${module.key}.title`) }}
          </p>
          <div class="space-y-1.5">
            <span class="block h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-800"></span>
            <span class="block h-1.5 w-2/3 rounded-full bg-slate-200 dark:bg-slate-800"></span>
          </div>
        </div>
      </div>

      <div
        class="flex items-center justify-between gap-2 rounded-2xl px-4 py-3"
        style="background: var(--color-primary-light)"
      >
        <component
          :is="icon"
          v-for="(icon, index) in devices"
          :key="index"
          class="h-5 w-5"
          style="color: var(--color-primary)"
        />
      </div>
    </div>
  </div>
</template>
