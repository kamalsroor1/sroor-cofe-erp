<template>
  <div class="flex items-center gap-1.5 py-1 text-sm font-bold text-amber-600 dark:text-amber-400">
    <Lock class="w-4 h-4 shrink-0" aria-hidden="true" />
    <span>{{ $t('reports.locked_title') }}</span>
  </div>
</template>

<script setup>
import { Lock } from 'lucide-vue-next';
</script>
