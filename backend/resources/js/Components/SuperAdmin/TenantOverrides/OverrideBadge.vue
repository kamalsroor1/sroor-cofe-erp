<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../../Composables/useFormatters';

/** Status chip of one override plus its reason / expiry / author lines. Expired overrides are muted. */
const props = defineProps({
  override: { type: Object, default: null },
  label: { type: String, default: '' },
  tone: { type: String, default: 'indigo' }, // 'indigo' | 'emerald' | 'rose'
});

const { formatDate } = useFormatters();

const TONES = {
  indigo: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/30',
  emerald: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
  rose: 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/30',
};
const MUTED =
  'bg-slate-100 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-700';

const expired = computed(() => !!props.override?.is_expired);
const chipClass = computed(() => (expired.value ? `${MUTED} line-through` : TONES[props.tone] || TONES.indigo));
const expiresDate = computed(() => (props.override?.expires_at ? formatDate(props.override.expires_at) : ''));
const title = computed(() => [props.override?.reason, expiresDate.value].filter(Boolean).join(' - '));
</script>

<template>
  <div v-if="override" class="min-w-0 space-y-1" :class="{ 'opacity-70': expired }" :title="title">
    <div class="flex flex-wrap items-center gap-1.5">
      <span
        class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[11px] font-black"
        :class="chipClass"
        data-testid="override-badge"
      >
        {{ label }}
      </span>
      <span
        v-if="expired"
        class="inline-flex items-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-black text-amber-700 dark:text-amber-300"
      >
        {{ $t('plan_overrides.ui.expired') }}
      </span>
    </div>
    <p class="text-[11px] font-bold leading-relaxed text-slate-600 dark:text-slate-300 break-words">
      {{ override.reason }}
    </p>
    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
      <template v-if="expiresDate">
        {{
          expired
            ? $t('plan_overrides.ui.expired_on', { date: expiresDate })
            : $t('plan_overrides.ui.expires_on', { date: expiresDate })
        }}
      </template>
      <template v-else>{{ $t('plan_overrides.ui.never_expires') }}</template>
      <template v-if="override.created_by?.name">
        - {{ $t('plan_overrides.ui.set_by', { name: override.created_by.name }) }}
      </template>
    </p>
  </div>
</template>
