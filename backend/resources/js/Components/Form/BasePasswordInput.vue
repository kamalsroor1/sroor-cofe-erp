<script setup>
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import BaseInput from './BaseInput.vue';

defineOptions({ inheritAttrs: false });

defineProps({
  label: { type: String, default: '' },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  autocomplete: { type: String, default: 'current-password' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });
const isVisible = ref(false);
</script>

<template>
  <BaseInput
    v-model="model"
    v-bind="$attrs"
    :type="isVisible ? 'text' : 'password'"
    :label="label"
    :error="error"
    :hint="hint"
    :autocomplete="autocomplete"
    :required="required"
    :disabled="disabled"
    dir="ltr"
    :password-toggle="false"
    input-class="rtl:ps-12 ltr:pe-12 text-start"
    wrapper-class="text-start"
  >
    <template #trailing>
      <!-- 44px tap target on the logical end of the field -->
      <button
        type="button"
        class="-me-2 flex h-11 w-11 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-200 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200"
        :aria-label="isVisible ? $t('common.hide_password') : $t('common.show_password')"
        :aria-pressed="isVisible"
        :disabled="disabled"
        data-testid="password-visibility-toggle"
        @click="isVisible = !isVisible"
      >
        <EyeOff v-if="isVisible" class="h-4 w-4" />
        <Eye v-else class="h-4 w-4" />
      </button>
    </template>
  </BaseInput>
</template>
