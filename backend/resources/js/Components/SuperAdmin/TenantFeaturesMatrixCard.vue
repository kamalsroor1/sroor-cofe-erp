<template>
  <div class="bg-white dark:bg-slate-900/90 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-6 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xl">
          ⚡
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('super.custom_features_title') }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('super.custom_features_subtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Feature Matrix List -->
    <div class="space-y-2.5 max-h-[420px] overflow-y-auto pr-1">
      <div
        v-for="feat in features"
        :key="feat.key"
        class="p-3.5 rounded-2xl border transition flex items-center justify-between gap-3"
        :class="isFeatureActive(feat.key) ? 'bg-purple-500/5 dark:bg-purple-950/20 border-purple-500/30' : 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800'"
      >
        <div class="flex items-center gap-3 min-w-0">
          <span class="text-lg">{{ feat.icon || '✨' }}</span>
          <div>
            <div class="text-xs font-black text-slate-900 dark:text-white truncate">{{ feat.name }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ feat.key }}</div>
          </div>
        </div>

        <button
          type="button"
          @click="$emit('toggle-feature', feat.key)"
          class="px-3 py-1 rounded-xl text-xs font-black border transition cursor-pointer active:scale-95"
          :class="isFeatureActive(feat.key) ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-700'"
        >
          {{ isFeatureActive(feat.key) ? $t('super.feature_enabled_badge') : $t('super.feature_disabled_badge') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  features: { type: Array, default: () => [] },
  enabledFeatures: { type: Array, default: () => [] },
});

defineEmits(['toggle-feature']);

const isFeatureActive = (key) => props.enabledFeatures.includes(key);
</script>
