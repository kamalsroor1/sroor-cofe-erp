<script setup>
import { computed } from 'vue';
import { Ban, CheckCircle2, FileBarChart2, Printer, Save } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import StockCountStatusBadge from './StockCountStatusBadge.vue';
import { useTrans } from '@/Composables/useTrans';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
  count: { type: Object, required: true },
  countedTotal: { type: Number, default: 0 },
  dirtyCount: { type: Number, default: 0 },
  isSaving: { type: Boolean, default: false },
  isPosting: { type: Boolean, default: false },
});

defineEmits(['save', 'post', 'cancel', 'print-sheet', 'print-variance']);

const { t } = useTrans();
const { formatDateTime } = useFormatters();

const total = computed(() => props.count.lines?.length || props.count.lines_count || 0);
const percent = computed(() => (total.value ? Math.round((props.countedTotal / total.value) * 100) : 0));
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-sm space-y-4 font-tajawal"
    data-testid="stock-count-header"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="space-y-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-lg font-black text-slate-800 dark:text-slate-100 font-mono">{{ count.count_number }}</h2>
          <StockCountStatusBadge :status="count.status" />
          <span
            v-if="count.is_blind"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20"
          >
            {{ t('stock_counts.blind') }}
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ count.store_name }} · {{ count.category_name || t('stock_counts.all_categories') }} ·
          {{ formatDateTime(count.created_at) }}
        </p>
        <p v-if="count.notes" class="text-xs text-slate-500 dark:text-slate-400">{{ count.notes }}</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <BaseButton
          variant="secondary"
          size="sm"
          :icon="Printer"
          :label="t('stock_counts.print_sheet')"
          @click="$emit('print-sheet')"
        />
        <BaseButton
          variant="secondary"
          size="sm"
          :icon="FileBarChart2"
          :label="t('stock_counts.variance_report')"
          data-testid="stock-count-variance"
          @click="$emit('print-variance')"
        />
        <template v-if="count.is_open">
          <BaseButton
            variant="outline"
            size="sm"
            :icon="Save"
            :label="isSaving ? t('stock_counts.saving') : t('stock_counts.save')"
            :loading="isSaving"
            :disabled="dirtyCount === 0"
            data-testid="stock-count-save"
            @click="$emit('save')"
          />
          <BaseButton
            variant="danger"
            size="sm"
            :icon="Ban"
            :label="t('stock_counts.cancel_count')"
            @click="$emit('cancel')"
          />
          <BaseButton
            variant="gradient"
            size="sm"
            :icon="CheckCircle2"
            :label="t('stock_counts.post')"
            :loading="isPosting"
            :disabled="isSaving"
            data-testid="stock-count-post"
            @click="$emit('post')"
          />
        </template>
      </div>
    </div>

    <div class="space-y-1.5">
      <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300">
        <span>{{ t('stock_counts.counted_of', { counted: countedTotal, total }) }}</span>
        <span
          v-if="count.is_open"
          :class="dirtyCount ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
        >
          {{ dirtyCount ? t('stock_counts.unsaved_changes', { count: dirtyCount }) : t('stock_counts.all_saved') }}
        </span>
      </div>
      <div
        class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden"
        role="progressbar"
        :aria-valuenow="percent"
        aria-valuemin="0"
        aria-valuemax="100"
      >
        <div
          class="h-full rounded-full bg-[var(--color-primary)] transition-all duration-300"
          :style="{ width: `${percent}%` }"
        />
      </div>
    </div>

    <p
      v-if="!count.is_open"
      class="text-xs rounded-2xl px-3 py-2 bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
    >
      {{ t('stock_counts.read_only_notice', { status: count.status_label }) }}
      <template v-if="count.status === 'posted'"> {{ t('stock_counts.posted_note') }}</template>
    </p>
  </section>
</template>
