import { ref, computed, onBeforeUnmount } from 'vue';

/** Whole-second countdown (resend buttons). The timer dies with the component. */
export function useCountdown() {
    const remaining = ref(0);
    let timer = null;

    const stop = () => {
        if (timer) clearInterval(timer);
        timer = null;
    };

    const start = (seconds) => {
        stop();
        remaining.value = Math.max(0, Math.ceil(Number(seconds) || 0));
        if (remaining.value === 0) return;
        timer = setInterval(() => {
            remaining.value -= 1;
            if (remaining.value <= 0) stop();
        }, 1000);
    };

    const label = computed(() => {
        const minutes = Math.floor(remaining.value / 60);
        const seconds = remaining.value % 60;
        return `${minutes}:${String(seconds).padStart(2, '0')}`;
    });

    onBeforeUnmount(stop);

    return { remaining, label, isRunning: computed(() => remaining.value > 0), start, stop };
}
