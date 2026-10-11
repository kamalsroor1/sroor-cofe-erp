<script setup>
import { computed } from 'vue';
import { Palette, Hash } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseSwitch from '../../Form/BaseSwitch.vue';

const HEX_COLOR = /^#[0-9a-f]{6}$/i;

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:field']);

// <input type="color"> only accepts a full #rrggbb value; keep it valid while the hex field is being typed.
const pickerValue = computed(() =>
  HEX_COLOR.test(props.form.primary_color || '') ? props.form.primary_color.toLowerCase() : '#000000'
);

const updateColor = (value) => emit('update:field', 'primary_color', value);
</script>

<template>
  <DashboardSectionCard
    :title="$t('super.platform_settings.sections.appearance_title')"
    :subtitle="$t('super.platform_settings.sections.appearance_subtitle')"
    :icon="Palette"
    icon-bg="bg-violet-100 dark:bg-violet-500/15"
    icon-color="text-violet-600 dark:text-violet-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 items-start">
      <div class="flex items-start gap-3">
        <label
          class="mt-6 w-11 h-11 shrink-0 rounded-xl border border-slate-300 dark:border-slate-700 overflow-hidden shadow-xs"
          :class="readonly ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'"
          :title="$t('super.platform_settings.fields.primary_color_picker')"
        >
          <span class="sr-only">{{ $t('super.platform_settings.fields.primary_color_picker') }}</span>
          <input
            type="color"
            class="w-16 h-16 -m-2.5 border-0 p-0 bg-transparent cursor-[inherit]"
            :value="pickerValue"
            :disabled="readonly"
            data-testid="platform-color-picker"
            @input="updateColor($event.target.value)"
          />
        </label>
        <BaseInput
          :model-value="form.primary_color"
          dir="ltr"
          :leading-icon="Hash"
          :label="$t('super.platform_settings.fields.primary_color')"
          :hint="$t('super.platform_settings.fields.primary_color_hint')"
          :error="errors.primary_color"
          :readonly="readonly"
          maxlength="7"
          input-class="font-mono uppercase"
          data-testid="platform-color-hex"
          @update:model-value="updateColor"
        />
      </div>

      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 px-4 py-2">
        <BaseSwitch
          :model-value="form.powered_by_enabled"
          :label="$t('super.platform_settings.fields.powered_by_enabled')"
          :description="$t('super.platform_settings.fields.powered_by_enabled_desc')"
          :error="errors.powered_by_enabled"
          :disabled="readonly"
          data-testid="platform-powered-by"
          @update:model-value="$emit('update:field', 'powered_by_enabled', $event)"
        />
      </div>
    </div>
  </DashboardSectionCard>
</template>
