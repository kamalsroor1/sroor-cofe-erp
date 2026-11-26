<script setup>
import { computed } from 'vue';
import { Clock, FileSearch, TimerOff } from 'lucide-vue-next';
import { CHIP_CLASSES, reviewChip } from '../../../helpers/billingReview';

const props = defineProps({
  payment: { type: Object, required: true },
  now: { type: Number, default: () => Date.now() },
});

const chip = computed(() => reviewChip(props.payment, props.now));
const icon = computed(() => ({ manual: FileSearch, ended: TimerOff })[chip.value.kind] || Clock);
const labelKey = computed(
  () =>
    ({
      grace: 'billing.console.verify.chip_grace',
      urgent: 'billing.console.verify.chip_grace',
      ended: 'billing.console.verify.chip_grace_ended',
      manual: 'billing.console.verify.chip_manual',
    })[chip.value.kind]
);
</script>

<template>
  <span
    v-if="chip.kind !== 'none'"
    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-black"
    :class="CHIP_CLASSES[chip.kind]"
    :data-testid="`review-chip-${chip.kind}`"
  >
    <component :is="icon" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
    <span>{{ $t(labelKey, { hours: chip.hours }) }}</span>
  </span>
</template>
