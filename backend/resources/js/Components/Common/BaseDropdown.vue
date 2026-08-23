<template>
  <div class="relative inline-block text-start select-none font-tajawal" ref="dropdownRef">
    <!-- Trigger Button (Slot or Default Button) -->
    <slot name="trigger" :is-open="isOpen" :toggle="toggle">
      <button
        type="button"
        @click="toggle"
        class="min-h-[44px] px-4 py-2.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 transition-all flex items-center gap-2 cursor-pointer shadow-xs active:scale-[0.98]"
        :class="triggerClass"
      >
        <component v-if="icon" :is="icon" class="w-4 h-4" :class="iconClass" />
        <span>{{ label }}</span>
        <ChevronDown
          class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
          :class="{ 'rotate-180': isOpen }"
        />
      </button>
    </slot>

    <!-- Dropdown Menu Body -->
    <transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="transform opacity-0 scale-95 -translate-y-1"
      enter-to-class="transform opacity-100 scale-100 translate-y-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="transform opacity-100 scale-100 translate-y-0"
      leave-to-class="transform opacity-0 scale-95 -translate-y-1"
    >
      <div
        v-if="isOpen"
        class="absolute top-full mt-2 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-1.5 z-50 space-y-1 min-w-[200px]"
        :class="[
          align === 'start' ? 'start-0' : (align === 'center' ? 'start-1/2 -translate-x-1/2' : 'end-0'),
          menuClass
        ]"
      >
        <!-- Slot for Custom Dropdown Items -->
        <slot :close="close">
          <button
            v-for="(item, idx) in items"
            :key="idx"
            type="button"
            @click="handleItemClick(item)"
            class="min-h-[40px] w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold rounded-xl transition cursor-pointer text-start active:scale-[0.98]"
            :class="[
              item.danger
                ? 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40'
                : 'text-slate-700 dark:text-slate-300 hover:text-theme-primary hover:bg-slate-50 dark:hover:bg-slate-900',
              item.customClass || ''
            ]"
          >
            <component v-if="item.icon" :is="item.icon" class="w-4 h-4 shrink-0" :class="item.iconColor || ''" />
            <span class="flex-1 truncate">{{ item.label }}</span>
            <span v-if="item.badge" class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-theme-light text-theme-primary">
              {{ item.badge }}
            </span>
          </button>
        </slot>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
  label: { type: String, default: '' },
  icon: { type: [Object, Function], default: null },
  iconClass: { type: String, default: 'text-slate-500' },
  triggerClass: { type: String, default: '' },
  menuClass: { type: String, default: 'w-56' },
  align: { type: String, default: 'start' }, // 'start' | 'end' | 'center'
  items: { type: Array, default: () => [] },
});

const emit = defineEmits(['item-click', 'open', 'close']);

const isOpen = ref(false);
const dropdownRef = ref(null);

const toggle = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) emit('open');
  else emit('close');
};

const close = () => {
  if (isOpen.value) {
    isOpen.value = false;
    emit('close');
  }
};

const handleItemClick = (item) => {
  close();
  if (typeof item.onClick === 'function') {
    item.onClick();
  }
  emit('item-click', item);
};

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    close();
  }
};

const handleEscape = (e) => {
  if (e.key === 'Escape' && isOpen.value) {
    close();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleEscape);
});

defineExpose({ isOpen, toggle, close });
</script>
