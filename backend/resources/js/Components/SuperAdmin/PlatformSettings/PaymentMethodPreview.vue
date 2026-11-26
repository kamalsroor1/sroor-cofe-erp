<script setup>
import { computed } from 'vue';
import { Copy, Info } from 'lucide-vue-next';
import { methodPreviewRows } from '../../../helpers/billingReview';

const props = defineProps({
  method: { type: String, required: true },
  form: { type: Object, required: true },
});

const preview = computed(() => methodPreviewRows(props.method, props.form));
</script>

<template>
  <section
    class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
    :aria-label="$t('billing.console.settings.preview_title')"
    data-testid="payment-method-preview"
  >
    <header class="space-y-0.5">
      <h3 class="text-sm font-black text-slate-900 dark:text-white">
        {{ $t('billing.console.settings.preview_title') }}
      </h3>
      <p class="text-xs font-bold text-slate-500 dark:text-slate-400">
        {{ $t('billing.console.settings.preview_hint') }}
      </p>
    </header>

    <p class="text-base font-black text-slate-900 dark:text-white">
      {{ $t(`billing.console.settings.method_${method}`) }}
    </p>

    <p
      v-if="!preview.rows.length"
      class="rounded-xl bg-slate-50 p-3 text-xs font-bold text-slate-500 dark:bg-slate-950 dark:text-slate-400"
    >
      {{ $t('billing.console.settings.preview_empty') }}
    </p>

    <ul v-else class="space-y-2">
      <li
        v-for="row in preview.rows"
        :key="row.key"
        class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-2 ps-3 dark:bg-slate-800"
      >
        <div class="min-w-0">
          <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t(row.label) }}</p>
          <p
            class="break-all text-base font-black"
            :class="row.copyable ? 'text-slate-950 dark:text-white' : 'text-slate-700 dark:text-slate-300'"
            dir="ltr"
          >
            {{ row.value }}
          </p>
        </div>
        <span
          v-if="row.copyable"
          class="inline-flex min-h-11 min-w-[76px] shrink-0 items-center justify-center gap-1.5 rounded-lg bg-theme-primary px-3 text-sm font-bold text-slate-950 opacity-60"
          aria-hidden="true"
        >
          <Copy class="h-4 w-4" />
          {{ $t('billing.console.settings.preview_copy') }}
        </span>
      </li>
    </ul>

    <p
      v-if="preview.note"
      class="flex items-start gap-2 rounded-xl border border-amber-400 bg-amber-50 p-3 text-sm font-bold text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
    >
      <Info class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
      <span>{{ preview.note }}</span>
    </p>
  </section>
</template>
