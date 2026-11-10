// Ref-counted page scroll lock shared by every overlay (AppModal, BottomSheet): the body unlocks only when the last one releases it.
let locks = 0;

export function useBodyScrollLock() {
    let held = false;

    const lock = () => {
        if (held) return;
        held = true;
        locks += 1;
        document.body.classList.add('overflow-hidden');
    };

    const unlock = () => {
        if (!held) return;
        held = false;
        locks = Math.max(0, locks - 1);
        if (locks === 0) document.body.classList.remove('overflow-hidden');
    };

    return { lock, unlock };
}
