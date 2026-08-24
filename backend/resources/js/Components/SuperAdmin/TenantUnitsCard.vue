<template>
  <div class="bg-white dark:bg-slate-900/90 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-6 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-purple-500/10 text-purple-400 flex items-center justify-center text-xl">
          ⚖️
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('super.tenant_units_title') }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('super.tenant_units_subtitle') }}</p>
        </div>
      </div>

      <BaseButton
        type="button"
        variant="primary"
        size="sm"
        :loading="isSavingUnits"
        @click="$emit('save-units')"
        class="bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-black shadow-md"
      >
        {{ isSavingUnits ? $t('common.saving') : $t('super.save_tenant_units_btn') }}
      </BaseButton>
    </div>

    <!-- Active Units Badges -->
    <div class="space-y-2">
      <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
        {{ $t('super.active_units_in_account', { name: tenantName }) }}
      </label>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="(u, idx) in tenantAllowedUnits"
          :key="u"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-purple-500/10 dark:bg-purple-950/40 border border-purple-500/30 text-purple-700 dark:text-purple-300 flex items-center gap-2 shadow-2xs"
        >
          <span>{{ u }}</span>
          <button
            type="button"
            @click="$emit('remove-unit', idx)"
            class="hover:text-rose-500 transition cursor-pointer text-xs"
            :title="$t('common.delete')"
          >
            ✕
          </button>
        </span>
      </div>
    </div>

    <!-- Quick Add from System Presets -->
    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('super.add_from_system_presets') }}</div>
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="gu in globalUnitsList"
          :key="gu"
          type="button"
          @click="$emit('add-unit', gu)"
          :disabled="tenantAllowedUnits.includes(gu)"
          class="px-2.5 py-1 rounded-lg border text-[11px] font-bold transition cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed active:scale-95"
          :class="tenantAllowedUnits.includes(gu) ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-400' : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-purple-500'"
        >
          + {{ gu }}
        </button>
      </div>
    </div>

    <!-- Add Custom Unit for this Tenant -->
    <div class="flex items-center gap-2">
      <BaseInput
        :model-value="customUnit"
        @update:model-value="$emit('update:custom-unit', $event)"
        @keyup.enter="$emit('add-custom-unit')"
        :placeholder="$t('super.custom_unit_placeholder')"
        class="flex-1"
      />
      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        @click="$emit('add-custom-unit')"
      >
        {{ $t('super.add_unit_btn') }}
      </BaseButton>
    </div>
  </div>
</template>

<script setup>
import BaseButton from '../Common/BaseButton.vue';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  tenantName: { type: String, default: '' },
  tenantAllowedUnits: { type: Array, default: () => [] },
  globalUnitsList: { type: Array, default: () => [] },
  customUnit: { type: String, default: '' },
  isSavingUnits: { type: Boolean, default: false },
});

defineEmits(['save-units', 'remove-unit', 'add-unit', 'add-custom-unit', 'update:custom-unit']);
</script>
