<script setup>
import { computed } from 'vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps({
  status: { type: String, required: true },
});

const { t } = useTrans();

const VARIANTS = { draft: 'slate', counting: 'info', posted: 'success', cancelled: 'danger' };

const variant = computed(() => VARIANTS[props.status] || 'slate');
</script>

<template>
  <StatusBadge :label="t(`stock_counts.status.${status}`)" :variant="variant" data-testid="stock-count-status" />
</template>
