<script setup>
import { ref, watch } from 'vue';
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import AppModal from '@/Components/Common/AppModal.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import BaseSwitch from '@/Components/Form/BaseSwitch.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps({
  show: { type: Boolean, default: false },
  number: { type: String, default: '' },
  uncounted: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);
const { t } = useTrans();
const zeroUncounted = ref(false);

watch(
  () => props.show,
  (open) => {
    if (open) zeroUncounted.value = false;
  }
);
</script>

<template>
  <AppModal
    :show="show"
    :title="t('stock_counts.post_confirm_title', { number })"
    :icon="CheckCircle2"
    max-width="md"
    @close="emit('close')"
  >
    <div class="space-y-4 font-tajawal">
      <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
        {{ t('stock_counts.post_confirm_text') }}
      </p>
      <div
        v-if="uncounted > 0"
        class="flex items-start gap-2 rounded-2xl px-3 py-2 bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 text-xs"
      >
        <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
        <span>{{ t('stock_counts.post_uncounted_warning', { count: uncounted }) }}</span>
      </div>
      <BaseSwitch v-if="uncounted > 0" v-model="zeroUncounted" :label="t('stock_counts.zero_uncounted')" />
    </div>
    <template #footer>
      <div class="flex flex-wrap justify-end gap-2">
        <BaseButton variant="secondary" :label="t('common.cancel')" @click="emit('close')" />
        <BaseButton
          variant="gradient"
          :icon="CheckCircle2"
          :label="t('stock_counts.post_confirm_button')"
          :loading="loading"
          data-testid="stock-count-post-confirm"
          @click="emit('confirm', zeroUncounted)"
        />
      </div>
    </template>
  </AppModal>
</template>
