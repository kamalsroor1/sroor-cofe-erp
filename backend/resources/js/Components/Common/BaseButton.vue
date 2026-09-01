<template>
  <component
    :is="to ? 'router-link' : 'button'"
    :to="to"
    :type="to ? undefined : type"
    :disabled="to ? undefined : (disabled || loading)"
    class="font-tajawal font-bold inline-flex items-center justify-center gap-2 transition-all duration-150 select-none cursor-pointer active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
    :class="[
      sizeClasses[size] || sizeClasses.md,
      active ? activeClasses : (variantClasses[variant] || variantClasses.default),
      fullWidth ? 'w-full' : '',
      customClass
    ]"
    @click="$emit('click', $event)"
  >
    <span
      v-if="loading"
      class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin shrink-0"
    ></span>
    <component
      v-else-if="icon"
      :is="icon"
      class="w-4 h-4 shrink-0"
      :class="iconClass"
    />

    <span v-if="$slots.default || label" class="truncate">
      <slot>{{ label }}</slot>
    </span>

    <span
      v-if="badge !== null && badge !== undefined && badge !== ''"
      class="w-5 h-5 rounded-full bg-white/20 text-current text-[10px] font-black flex items-center justify-center shrink-0"
    >
      {{ badge }}
    </span>

    <component
      v-if="trailingIcon && !loading"
      :is="trailingIcon"
      class="w-3.5 h-3.5 shrink-0"
      :class="trailingIconClass"
    />
  </component>
</template>

<script setup>
defineProps({
  to: { type: [String, Object], default: null },
  type: { type: String, default: 'button' },
  label: { type: String, default: '' },
  variant: {
    type: String,
    default: 'default', // 'primary' | 'secondary' | 'danger' | 'success' | 'outline' | 'ghost' | 'gradient' | 'default'
  },
  size: {
    type: String,
    default: 'md', // 'sm' | 'md' | 'lg' | 'icon'
  },
  icon: { type: [Object, Function], default: null },
  iconClass: { type: String, default: '' },
  trailingIcon: { type: [Object, Function], default: null },
  trailingIconClass: { type: String, default: '' },
  active: { type: Boolean, default: false },
  badge: { type: [Number, String], default: null },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  fullWidth: { type: Boolean, default: false },
  customClass: { type: String, default: '' },
});

defineEmits(['click']);

const sizeClasses = {
  sm: 'min-h-[36px] px-3 py-1.5 text-xs rounded-xl',
  md: 'min-h-[44px] px-4 py-2.5 text-xs rounded-xl',
  lg: 'min-h-[52px] px-6 py-3 text-sm rounded-2xl font-black',
  icon: 'min-h-[44px] min-w-[44px] p-2.5 rounded-xl',
};

const activeClasses = 'bg-theme-primary/10 border border-theme-primary text-theme-primary font-black shadow-xs';

const variantClasses = {
  primary: 'bg-theme-primary hover:opacity-95 text-slate-950 shadow-md shadow-theme-primary/20 font-black',
  gradient: 'bg-theme-gradient text-white shadow-lg shadow-theme-primary font-black',
  secondary: 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
  default: 'bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 shadow-xs',
  danger: 'bg-rose-500 hover:bg-rose-600 text-white shadow-md shadow-rose-500/20 font-black',
  success: 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-600/20 font-black',
  outline: 'bg-transparent hover:bg-theme-primary/10 text-theme-primary border border-theme-primary font-black',
  ghost: 'bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400',
};
</script>
