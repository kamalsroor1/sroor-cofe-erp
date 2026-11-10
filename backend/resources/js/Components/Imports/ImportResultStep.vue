<script setup>
import { CheckCircle2, RefreshCw, List } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  result: { type: Object, required: true },
  listPath: { type: String, required: true },
});

defineEmits(['restart']);
</script>

<template>
  <section class="space-y-5 font-tajawal text-center">
    <CheckCircle2 class="w-14 h-14 mx-auto text-emerald-500" />
    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $t('import.ui.result.title') }}</h2>

    <div class="grid grid-cols-3 gap-3">
      <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <p class="text-2xl font-black tabular-nums text-emerald-600 dark:text-emerald-400">{{ result.created }}</p>
        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('import.ui.result.created') }}</p>
      </div>
      <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <p class="text-2xl font-black tabular-nums text-rose-600 dark:text-rose-400">{{ result.skipped }}</p>
        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('import.ui.result.skipped') }}</p>
      </div>
      <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <p class="text-2xl font-black tabular-nums text-amber-600 dark:text-amber-400">{{ result.duplicates }}</p>
        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('import.ui.result.duplicates') }}</p>
      </div>
    </div>

    <div
      v-if="result.errors?.length"
      class="text-start p-4 rounded-2xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10"
    >
      <p class="text-sm font-black text-rose-700 dark:text-rose-300 mb-2">{{ $t('import.ui.result.errors_title') }}</p>
      <ul class="space-y-1 text-xs font-bold text-rose-700 dark:text-rose-300">
        <li v-for="item in result.errors" :key="item.row_number">
          {{ $t('import.ui.row_number', { number: item.row_number }) }}: {{ [].concat(item.errors).join(' - ') }}
        </li>
      </ul>
    </div>

    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
      <BaseButton variant="primary" size="lg" :to="listPath" :icon="List" :label="$t('import.ui.result.go_to_list')" />
      <BaseButton
        variant="outline"
        size="lg"
        :icon="RefreshCw"
        :label="$t('import.ui.result.import_another')"
        @click="$emit('restart')"
      />
    </div>
  </section>
</template>
