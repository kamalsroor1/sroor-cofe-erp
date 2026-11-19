<script setup>
import { computed, reactive, watch } from 'vue';
import { ClipboardList } from 'lucide-vue-next';
import AppModal from '@/Components/Common/AppModal.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import BaseSelect from '@/Components/Form/BaseSelect.vue';
import BaseSwitch from '@/Components/Form/BaseSwitch.vue';
import BaseTextarea from '@/Components/Form/BaseTextarea.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps({
  show: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);
const { t } = useTrans();

const form = reactive({ category_id: null, is_blind: false, notes: '' });

const categoryOptions = computed(() => [
  { value: null, label: t('stock_counts.all_categories') },
  ...props.categories.map((c) => ({ value: c.id, label: c.name })),
]);

watch(
  () => props.show,
  (open) => {
    if (open) Object.assign(form, { category_id: null, is_blind: false, notes: '' });
  }
);

const submit = () => emit('submit', { ...form, notes: form.notes || null });
</script>

<template>
  <AppModal
    :show="show"
    :title="t('stock_counts.start_title')"
    :icon="ClipboardList"
    max-width="lg"
    @close="emit('close')"
  >
    <form class="space-y-4 font-tajawal" data-testid="start-stock-count-form" @submit.prevent="submit">
      <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{{ t('stock_counts.start_hint') }}</p>
      <BaseSelect
        v-model="form.category_id"
        :label="t('stock_counts.category')"
        :options="categoryOptions"
        :error="errors.category_id"
      />
      <BaseSwitch
        v-model="form.is_blind"
        :label="t('stock_counts.blind')"
        :description="t('stock_counts.blind_hint')"
        :error="errors.is_blind"
      />
      <BaseTextarea
        v-model="form.notes"
        :label="t('stock_counts.notes')"
        :placeholder="t('stock_counts.notes_placeholder')"
        :maxlength="500"
        :rows="2"
        :error="errors.notes"
      />
    </form>
    <template #footer>
      <div class="flex flex-wrap justify-end gap-2">
        <BaseButton variant="secondary" :label="t('common.cancel')" @click="emit('close')" />
        <BaseButton
          variant="gradient"
          :icon="ClipboardList"
          :label="t('stock_counts.start')"
          :loading="loading"
          data-testid="start-stock-count-submit"
          @click="submit"
        />
      </div>
    </template>
  </AppModal>
</template>
