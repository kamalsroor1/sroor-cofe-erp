const FORWARD = ['ArrowRight', 'ArrowDown'];
const BACK = ['ArrowLeft', 'ArrowUp'];

/**
 * Index to move to for an arrow key inside a radiogroup, or null for any other key.
 * In RTL the horizontal arrows are mirrored (Left goes to the next option); Up/Down never are.
 */
export const nextRadioIndex = (key, index, length, rtl = false) => {
    if (length < 1 || ![...FORWARD, ...BACK].includes(key)) return null;
    const horizontal = key === 'ArrowLeft' || key === 'ArrowRight';
    const mirror = rtl && horizontal ? -1 : 1;
    const step = (FORWARD.includes(key) ? 1 : -1) * mirror;
    return (index + step + length) % length;
};
