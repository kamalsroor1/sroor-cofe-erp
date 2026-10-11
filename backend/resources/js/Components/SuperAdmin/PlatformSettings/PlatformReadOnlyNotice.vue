<script setup>
import { Eye } from 'lucide-vue-next';
</script>

<template>
  <div
    role="note"
    data-testid="platform-settings-read-only"
    class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-center gap-3 text-xs font-bold"
  >
    <Eye class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
    <span>{{ $t('super.platform_settings.read_only_notice') }}</span>
  </div>
</template>
