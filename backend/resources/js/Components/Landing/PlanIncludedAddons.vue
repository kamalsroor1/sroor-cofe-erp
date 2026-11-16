<script setup>
import { Gift } from 'lucide-vue-next';

defineProps({
  /** included_addons of one public plan: [{ key, name, quantity, billing_cycle: null|'yearly' }]. */
  addons: { type: Array, default: () => [] },
  compact: { type: Boolean, default: false },
});
</script>

<template>
  <div v-if="addons.length" class="flex flex-col gap-2 rounded-xl bg-mk-soft/60 p-3" data-testid="plan-included-addons">
    <p class="text-xs font-bold text-mk-soft-ink">{{ $t('landing.pricing.included_addons') }}</p>
    <ul class="flex flex-col gap-1.5">
      <li
        v-for="addon in addons"
        :key="addon.key"
        class="flex items-start gap-2"
        :class="compact ? 'text-[13px]' : 'text-sm'"
      >
        <Gift class="mt-0.5 size-4 shrink-0 text-mk-primary" aria-hidden="true" />
        <span class="font-medium text-mk-ink">
          {{ addon.name }}
          <span v-if="addon.quantity > 1" dir="ltr">&times;{{ addon.quantity }}</span>
          <span v-if="addon.billing_cycle === 'yearly'" class="block text-xs text-mk-muted">
            {{ $t('landing.pricing.included_yearly_only') }}
          </span>
        </span>
      </li>
    </ul>
  </div>
</template>
