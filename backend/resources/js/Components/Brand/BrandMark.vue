<script setup>
import { computed, onMounted } from 'vue';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  /** Number = px, string = any CSS length (e.g. "2.5rem"). */
  size: { type: [Number, String], default: 40 },
  /** auto follows the app theme; mono paints with currentColor; app-icon is the white-on-green tile. */
  variant: {
    type: String,
    default: 'auto',
    validator: (value) => ['auto', 'light', 'dark', 'mono', 'app-icon'].includes(value),
  },
  /** Plays the 2.3s draw-in animation once and stays on the final frame. */
  animated: { type: Boolean, default: false },
  /** Hide from assistive tech when the brand name is already rendered next to the mark. */
  decorative: { type: Boolean, default: false },
});

const emit = defineEmits(['done']);

const { t } = useTrans();

const cssSize = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size));
const isAppIcon = computed(() => props.variant === 'app-icon');

const prefersReducedMotion = () =>
  typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

// animationend bubbles from the arc/check too: only the last animation (the settle on the group) counts.
const onAnimationEnd = (event) => {
  if (event.target === event.currentTarget) emit('done');
};

onMounted(() => {
  if (props.animated && prefersReducedMotion()) emit('done');
});
</script>

<template>
  <svg
    class="brand-mark"
    :class="[`brand-mark--${variant}`, { 'is-animated': animated }]"
    :style="{ width: cssSize, height: cssSize }"
    viewBox="0 0 100 100"
    fill="none"
    stroke-linecap="round"
    stroke-linejoin="round"
    xmlns="http://www.w3.org/2000/svg"
    :role="decorative ? undefined : 'img'"
    :aria-label="decorative ? undefined : t('brand.mark_label')"
    :aria-hidden="decorative ? 'true' : undefined"
  >
    <rect v-if="isAppIcon" class="bm-tile" width="100" height="100" rx="23" />
    <g class="bm-settle" @animationend="onAnimationEnd">
      <g :transform="isAppIcon ? 'translate(16 16) scale(.68)' : undefined">
        <path class="bm-arc" d="M50 14 A36 36 0 1 1 20 30" stroke-width="9" pathLength="100" />
        <polyline class="bm-head" points="12,22 20,30 30,24" stroke-width="7" />
        <polyline class="bm-check" points="36,51 46,61 66,40" stroke-width="7" pathLength="100" />
      </g>
    </g>
  </svg>
</template>

<style scoped>
.brand-mark {
  --bm-arc: var(--brand-arc);
  --bm-check: var(--brand-check);
  display: inline-block;
  flex-shrink: 0;
}
.brand-mark--light {
  --bm-arc: #0f6e5e;
  --bm-check: #15241f;
}
.brand-mark--dark {
  --bm-arc: #4cc3a8;
  --bm-check: #eef3f0;
}
.brand-mark--mono {
  --bm-arc: currentColor;
  --bm-check: currentColor;
}
.brand-mark--app-icon {
  --bm-arc: #fff;
  --bm-check: #fff;
}

.bm-tile {
  fill: #0f6e5e;
  stroke: none;
}
.bm-arc,
.bm-head {
  stroke: var(--bm-arc);
}
.bm-check {
  stroke: var(--bm-check);
}

/* Draw-in animation: arc 0-1.1s, arrowhead 1.05-1.3s, check 1.35-1.8s, settle 1.85-2.3s. */
.is-animated .bm-arc,
.is-animated .bm-check {
  stroke-dasharray: 100;
}
.is-animated .bm-arc {
  animation:
    bm-draw 1.1s cubic-bezier(0.65, 0, 0.35, 1) both,
    bm-show 0.01s linear both;
}
.is-animated .bm-head {
  transform-box: fill-box;
  transform-origin: center;
  animation: bm-pop 0.25s ease-out 1.05s both;
}
.is-animated .bm-check {
  animation:
    bm-draw 0.45s cubic-bezier(0.65, 0, 0.35, 1) 1.35s both,
    bm-show 0.01s linear 1.35s both;
}
.is-animated .bm-settle {
  transform-box: view-box;
  transform-origin: 50px 50px;
  animation: bm-settle 0.45s ease-in-out 1.85s both;
}

@keyframes bm-draw {
  from {
    stroke-dashoffset: 100;
  }
  to {
    stroke-dashoffset: 0;
  }
}
@keyframes bm-show {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
@keyframes bm-pop {
  0% {
    opacity: 0;
    transform: scale(0.4);
  }
  60% {
    opacity: 1;
    transform: scale(1.2);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}
@keyframes bm-settle {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.07);
  }
}

@media (prefers-reduced-motion: reduce) {
  .is-animated .bm-arc,
  .is-animated .bm-head,
  .is-animated .bm-check,
  .is-animated .bm-settle {
    animation: none;
    stroke-dasharray: none;
  }
}
</style>
