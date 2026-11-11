<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronDown } from 'lucide-vue-next';
import MarketingIconBadge from './MarketingIconBadge.vue';
import { useLandingNav } from '../../Composables/useLandingNav';

defineProps({
  active: { type: Boolean, default: false },
});

const route = useRoute();
const { industries, industryRoute, industryLabelKey } = useLandingNav();

const isOpen = ref(false);
const root = ref(null);
const button = ref(null);

const close = ({ restoreFocus = false } = {}) => {
  isOpen.value = false;
  if (restoreFocus) button.value?.focus();
};

const onKeydown = (event) => {
  if (event.key === 'Escape') close({ restoreFocus: true });
};
const onPointerDown = (event) => {
  if (!root.value?.contains(event.target)) close();
};
const onFocusOut = (event) => {
  if (event.relatedTarget && !root.value?.contains(event.relatedTarget)) close();
};

const listen = (on) => {
  const method = on ? 'addEventListener' : 'removeEventListener';
  document[method]('keydown', onKeydown);
  document[method]('pointerdown', onPointerDown);
};

watch(isOpen, listen);
watch(
  () => route.fullPath,
  () => close()
);
onBeforeUnmount(() => listen(false));
</script>

<template>
  <div ref="root" class="relative" @focusout="onFocusOut">
    <button
      ref="button"
      type="button"
      class="inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-lg text-base transition hover:text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary"
      :class="active ? 'font-bold text-mk-ink' : 'font-medium text-mk-muted'"
      :aria-expanded="isOpen"
      aria-controls="landing-industries-menu"
      @click="isOpen = !isOpen"
    >
      {{ $t('landing.nav.industries') }}
      <ChevronDown
        class="size-4 text-mk-muted transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        aria-hidden="true"
      />
    </button>

    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="-translate-y-1 opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="-translate-y-1 opacity-0"
    >
      <ul
        v-show="isOpen"
        id="landing-industries-menu"
        class="absolute start-0 top-full z-40 mt-2 w-72 rounded-2xl border border-mk-line bg-mk-surface p-2 mk-shadow-card"
      >
        <li v-for="item in industries" :key="item.key">
          <router-link
            :to="industryRoute(item.key)"
            class="flex min-h-12 items-center gap-3 rounded-xl px-2 py-1.5 text-[15px] font-bold text-mk-ink transition hover:bg-mk-surface-2 focus-visible:outline-2 focus-visible:outline-mk-primary"
            active-class="bg-mk-surface-2"
            @click="close()"
          >
            <MarketingIconBadge :icon="item.icon" :size="36" />
            {{ $t(industryLabelKey(item.key)) }}
          </router-link>
        </li>
      </ul>
    </Transition>
  </div>
</template>
