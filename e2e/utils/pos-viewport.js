import { DESKTOP_MIN } from '../../backend/resources/js/helpers/breakpoints.js';

// Compact POS (phones and tablets, < DESKTOP_MIN) keeps the cart and checkout inside teleported BottomSheets
// (see POSPhoneCartBar.vue), so the desktop-layout POS flow specs do not apply there. The switch point is the
// app's own breakpoint (helpers/breakpoints.js), the same one the layout shell uses.
export const POS_COMPACT_MAX_WIDTH = DESKTOP_MIN - 1;

export function isPhoneViewport(page) {
    const size = page.viewportSize();
    return Boolean(size) && size.width <= POS_COMPACT_MAX_WIDTH;
}

// Call inside a test.describe block: skips every test in it on phone/tablet-sized viewports.
export function skipOnPhoneViewport(test) {
    test.beforeEach(({ page }) => {
        test.skip(
            isPhoneViewport(page),
            `Desktop POS flow: viewports below ${DESKTOP_MIN}px use cart/checkout BottomSheets, covered by the compact POS specs`
        );
    });
}

// The customer name sits in a `hidden sm:block` wrapper (<640px shows only a dot indicator).
export async function expectSelectedCustomer(page, expect, name) {
    const size = page.viewportSize();
    const nameEl = page.getByTestId('pos-selected-customer-name');
    if (size && size.width < 640) {
        await expect(nameEl).toBeHidden();
        return;
    }
    await expect(nameEl).toHaveText(name);
}
