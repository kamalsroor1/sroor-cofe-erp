<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 font-tajawal">
    <div
      v-for="(mod, modKey) in permissionModules"
      :key="modKey"
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-3"
    >
      <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5">
        <div class="flex items-center gap-2">
          <span class="text-lg">{{ mod.icon }}</span>
          <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ mod.title }}</h3>
        </div>

        <div v-if="!isAdmin" class="flex items-center gap-2 text-[10px]">
          <button
            type="button"
            @click="$emit('toggle-module', mod.permissions, true)"
            class="text-theme-primary hover:underline cursor-pointer"
          >
            {{ $t('roles.select_all') }}
          </button>
          <span class="text-slate-400 dark:text-slate-600">|</span>
          <button
            type="button"
            @click="$emit('toggle-module', mod.permissions, false)"
            class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-400 cursor-pointer"
          >
            {{ $t('roles.deselect_all') }}
          </button>
        </div>
      </div>

      <!-- Permission Items -->
      <div class="space-y-2 pt-1">
        <label
          v-for="(label, permKey) in mod.permissions"
          :key="permKey"
          class="min-h-[40px] flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-900 cursor-pointer transition text-xs select-none"
        >
          <span class="text-slate-700 dark:text-slate-300 font-medium">{{ label }}</span>
          <input
            type="checkbox"
            :value="permKey"
            :checked="activePermissions.includes(permKey)"
            @change="$emit('toggle-permission', permKey, $event.target.checked)"
            :disabled="isAdmin"
            class="w-4 h-4 rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-emerald-600 focus:ring-emerald-500 cursor-pointer disabled:opacity-50"
          />
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  permissionModules: { type: Object, default: () => ({}) },
  activePermissions: { type: Array, default: () => [] },
  isAdmin: { type: Boolean, default: false },
});

defineEmits(['toggle-module', 'toggle-permission']);
</script>
