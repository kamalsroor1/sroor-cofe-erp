import { ref, computed, onBeforeUnmount } from 'vue';

/** Whole-second countdown (resend buttons). The timer dies with the component. */
export function useCountdown() {
    const remaining = ref(0);
    let timer = null;
    let deadline = 0;

    const stop = () => {
        if (timer) clearInterval(timer);
        timer = null;
    };

    const start = (seconds) => {
        stop();
        remaining.value = Math.max(0, Math.ceil(Number(seconds) || 0));
        if (remaining.value === 0) return;
        // Measured against a deadline, not by counting ticks: background tabs throttle timers.
        deadline = Date.now() + remaining.value * 1000;
        timer = setInterval(() => {
            remaining.value = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
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
