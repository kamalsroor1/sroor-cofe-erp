// Phone POS (< 768px) keeps the cart and checkout inside teleported BottomSheets (see
// POSPhoneCartBar.vue), so the desktop-layout POS flow specs do not apply there.
// Tablet (>= 768px) and desktop render the full split layout.
export const POS_PHONE_MAX_WIDTH = 767;

export function isPhoneViewport(page) {
    const size = page.viewportSize();
    return Boolean(size) && size.width <= POS_PHONE_MAX_WIDTH;
}

// Call inside a test.describe block: skips every test in it on phone viewports.
export function skipOnPhoneViewport(test) {
    test.beforeEach(({ page }) => {
        test.skip(
            isPhoneViewport(page),
            'Desktop POS flow: phone layout (<768px) uses cart/checkout BottomSheets, covered by the phone POS specs'
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
