<template>
  <teleport to="body">
    <transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-hidden font-tajawal select-none"
        :dir="$i18n?.locale === 'en' ? 'ltr' : 'rtl'"
      >
        <!-- Backdrop -->
        <div
          class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
          @click="close"
        />

        <!-- Drawer Panel -->
        <div class="fixed inset-y-0 start-0 max-w-full flex pointer-events-none">
          <transition
            enter-active-class="transform transition ease-out duration-300"
            enter-from-class="-translate-x-full rtl:translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transform transition ease-in duration-200"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full rtl:translate-x-full"
          >
            <div
              v-if="isOpen"
              class="w-screen max-w-sm sm:max-w-md bg-white dark:bg-slate-900 border-e border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col justify-between pointer-events-auto"
            >
              <!-- Drawer Header -->
              <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/50 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-2xl bg-theme-primary/10 text-theme-primary flex items-center justify-center font-bold text-lg shadow-xs">
                    <SlidersHorizontal class="w-5 h-5 text-theme-primary" />
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
                        {{ title || $t('common.filter_search') }}
                      </h3>
                      <span
                        v-if="activeCount > 0"
                        class="px-2 py-0.5 rounded-full text-[10px] font-black bg-theme-primary text-white"
                      >
                        {{ activeCount }}
                      </span>
                    </div>
                    <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                      {{ subtitle }}
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  @click="close"
                  class="min-h-[44px] min-w-[44px] rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center justify-center transition active:scale-95 cursor-pointer shadow-xs"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>

              <!-- Drawer Body (Scrollable filters) -->
              <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-5">
                <slot />
              </div>

              <!-- Drawer Footer (Sticky Actions) -->
              <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/50 flex items-center justify-between gap-2.5 shrink-0">
                <BaseButton
                  type="button"
                  variant="secondary"
                  size="md"
                  :icon="RotateCcw"
                  :label="$t('common.reset_filters')"
                  @click="reset"
                />

                <div class="flex items-center gap-2">
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="md"
                    :label="$t('common.cancel')"
                    @click="close"
                  />
                  <BaseButton
                    type="button"
                    variant="primary"
                    size="md"
                    :icon="Check"
                    :label="$t('common.apply_filters')"
                    @click="apply"
                  />
                </div>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue';
import { SlidersHorizontal, X, RotateCcw, Check } from 'lucide-vue-next';
import BaseButton from './BaseButton.vue';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  activeCount: { type: Number, default: 0 },
});

const emit = defineEmits(['close', 'apply', 'reset']);

const close = () => emit('close');
const apply = () => emit('apply');
const reset = () => emit('reset');

const handleEscape = (e) => {
  if (e.key === 'Escape' && props.isOpen) {
    close();
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleEscape);
});
</script>
