<template>
  <AppModal
    :show="show"
    :title="`${$t('inventory.assign_staff_to')} (${targetStore?.name})`"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <p class="text-xs text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('inventory.assign_staff_description') }}
      </p>

      <!-- Users Search Box -->
      <div v-if="allUsers.length > 5" class="relative">
        <input
          v-model="userSearchQuery"
          type="text"
          :placeholder="$t('common.search')"
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-theme-primary/20 font-tajawal"
        />
      </div>

      <!-- Users Checkbox List -->
      <div class="max-h-64 overflow-y-auto space-y-2 p-1 custom-scrollbar">
        <label
          v-for="user in filteredUsers"
          :key="user.id"
          class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 hover:border-theme-primary/40 cursor-pointer select-none transition-all min-h-[44px]"
        >
          <div class="flex items-center gap-2.5">
            <input
              type="checkbox"
              :value="user.id"
              :checked="modelValue.includes(user.id)"
              @change="toggleUser(user.id)"
              class="w-4 h-4 rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-theme-primary/20"
            />
            <div>
              <div class="text-xs font-bold text-slate-900 dark:text-white font-tajawal">{{ user.name }}</div>
              <div class="text-[10px] text-slate-400 font-mono">{{ user.email }}</div>
            </div>
          </div>
        </label>

        <div v-if="filteredUsers.length === 0" class="py-8 text-center text-xs text-slate-400 font-tajawal">
          {{ $t('common.no_data_available') }}
        </div>
      </div>

      <!-- Form Actions Footer -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <button
          type="button"
          @click="$emit('close')"
          class="min-h-[44px] px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs font-bold font-tajawal cursor-pointer transition-all active:scale-95"
        >
          {{ $t('common.cancel') }}
        </button>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="min-h-[44px] px-5 py-2 bg-theme-gradient text-white font-black rounded-2xl text-xs font-tajawal shadow-lg shadow-theme-primary/20 disabled:opacity-50 cursor-pointer flex items-center gap-2 transition-all active:scale-95"
        >
          <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>{{ $t('common.save') }}</span>
        </button>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppModal from '../Common/AppModal.vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  targetStore: {
    type: Object,
    default: null,
  },
  allUsers: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'submit', 'update:modelValue']);

const userSearchQuery = ref('');

const filteredUsers = computed(() => {
  if (!userSearchQuery.value) return props.allUsers;
  const q = userSearchQuery.value.toLowerCase();
  return props.allUsers.filter(u => u.name?.toLowerCase().includes(q) || u.email?.toLowerCase().includes(q));
});

const toggleUser = (userId) => {
  const current = [...props.modelValue];
  const idx = current.indexOf(userId);
  if (idx > -1) {
    current.splice(idx, 1);
  } else {
    current.push(userId);
  }
  emit('update:modelValue', current);
};
</script>
