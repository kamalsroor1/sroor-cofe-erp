import { ref } from 'vue';

const isSoundEnabled = ref(localStorage.getItem('pos_sound_enabled') !== 'false');
let audioCtx = null;

function getAudioContext() {
    if (!audioCtx && typeof window !== 'undefined') {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
            audioCtx = new AudioContextClass();
        }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
}

export function useAudioFeedback() {
    /**
     * 🛒 Crisp POS Barcode Scan Beep (880Hz, Sine Wave)
     */
    const playScanBeep = () => {
        if (!isSoundEnabled.value) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime); // A5

            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.06);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.06);
        } catch (e) {
            // Audio context silently ignored if blocked
        }
    };

    /**
     * 🎉 Major Success Chime on Invoice Checkout (Two-chord Chime)
     */
    const playSuccessChime = () => {
        if (!isSoundEnabled.value) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;

            // Note 1: E5 (659Hz)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'triangle';
            osc1.frequency.setValueAtTime(659.25, ctx.currentTime);
            gain1.gain.setValueAtTime(0.15, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.15);

            // Note 2: A5 (880Hz)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'triangle';
            osc2.frequency.setValueAtTime(880, ctx.currentTime + 0.08);
            gain2.gain.setValueAtTime(0.18, ctx.currentTime + 0.08);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(ctx.currentTime + 0.08);
            osc2.stop(ctx.currentTime + 0.28);
        } catch (e) {}
    };

    /**
     * 💵 Cash Drawer Mechanical Click Sound
     */
    const playDrawerSound = () => {
        if (!isSoundEnabled.value) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'square';
            osc.frequency.setValueAtTime(220, ctx.currentTime);
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.08);
        } catch (e) {}
    };

    /**
     * ⚠️ Warning / Error Tone (Low Double Beep)
     */
    const playErrorTone = () => {
        if (!isSoundEnabled.value) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(290, ctx.currentTime);
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.12);
        } catch (e) {}
    };

    const toggleSound = () => {
        isSoundEnabled.value = !isSoundEnabled.value;
        localStorage.setItem('pos_sound_enabled', isSoundEnabled.value.toString());
        if (isSoundEnabled.value) playScanBeep();
    };

    return {
        isSoundEnabled,
        toggleSound,
        playScanBeep,
        playSuccessChime,
        playDrawerSound,
        playErrorTone
    };
}
