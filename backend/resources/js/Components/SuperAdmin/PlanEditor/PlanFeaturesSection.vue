<template>
  <div class="space-y-5">
    <p v-if="errors.features" class="text-xs font-bold text-rose-500 dark:text-rose-400">
      {{ [].concat(errors.features)[0] }}
    </p>

    <section v-for="group in groups" :key="group.module" class="space-y-2">
      <h4 class="text-xs font-black text-slate-700 dark:text-slate-200">
        {{ $t(`super.plan_module_${group.module}`) }}
      </h4>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <div
          v-for="feature in group.features"
          :key="feature.key"
          class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900"
          :class="{ 'opacity-60': !feature.is_public }"
        >
          <BaseSwitch
            :model-value="!!form.features[feature.key]"
            :label="feature.name"
            :description="feature.is_public ? feature.key : $t('super.plan_feature_not_released')"
            :disabled="!feature.is_public"
            @update:model-value="$emit('update:feature', feature.key, $event)"
          />
          <span
            v-if="feature.is_core"
            class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-theme-primary/10 text-theme-primary"
          >
            {{ $t('super.plan_feature_core_badge') }}
          </span>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseSwitch from '../../Form/BaseSwitch.vue';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  registry: { type: Array, default: () => [] },
});

defineEmits(['update:feature']);

const groups = computed(() => {
  const byModule = new Map();
  for (const feature of props.registry) {
    if (!byModule.has(feature.module)) byModule.set(feature.module, []);
    byModule.get(feature.module).push(feature);
  }
  return [...byModule].map(([module, features]) => ({ module, features }));
});
</script>
