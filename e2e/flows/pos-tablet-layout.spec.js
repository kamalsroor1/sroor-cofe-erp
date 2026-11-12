// RESP-02: POS on tablets. Below DESKTOP_MIN (helpers/breakpoints.js) the catalog gets the cart bar + sheets,
// from DESKTOP_MIN up the cart column sits beside the catalog. At every size the cart/pay controls are on the
// first screen (no page scroll), nothing interactive leaves the viewport, and the header search is not overlapped.
import { test, expect } from '@playwright/test';
import { DESKTOP_MIN } from '../../backend/resources/js/helpers/breakpoints.js';

// The specs set their own viewports, so they run once (desktop project) instead of once per project.
test.beforeEach(({ browserName }, testInfo) => {
    void browserName;
    test.skip(testInfo.project.name !== 'desktop', 'Sets its own viewports; runs once in the desktop project.');
});

const NAMES = [
    'RESP02 Espresso Blend Dark Roast 250g',
    'RESP02 Turkish Coffee Cardamom 100g',
    'RESP02 Colombian Supremo Medium Roast',
    'RESP02 Ethiopian Yirgacheffe Light Roast',
    'RESP02 House Blend Filter Coffee 500g',
    'RESP02 Hazelnut Flavoured Coffee 200g',
];

const ITEMS = NAMES.map((name, i) => ({
    id: 996001 + i,
    name,
    code: `62260000${i}`,
    unit: 'piece',
    is_weighted: false,
    selling_price: '125.000',
    price_retail: '125.000',
    price_wholesale: '115.000',
    min_selling_price: '110.000',
    current_stock: '30.000',
    is_pos_pinned: true,
    pos_sales_count: 10 - i,
}));

const SIZES = [
    { width: 768, height: 1024 },
    { width: 820, height: 1180 },
    { width: 1024, height: 768 },
];

async function setupPos(page) {
    await page.addInitScript(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('pos_multi_orders'))
            .forEach((k) => localStorage.removeItem(k));
    });
    await page.route('**/api/v1/pos/items*', (route) =>
        route.fulfill({
            json: { success: true, data: ITEMS, meta: { current_page: 1, last_page: 1, total: ITEMS.length } },
        })
    );
    await page.route('**/api/v1/categories*', (route) =>
        route.fulfill({ json: { success: true, data: [], total_items_count: ITEMS.length } })
    );
    await page.route('**/api/v1/customers*', (route) => route.fulfill({ json: { success: true, data: [] } }));
    await page.route('**/api/v1/shifts/current*', (route) =>
        route.fulfill({
            json: { success: true, active_shift: { id: 1, shift_number: 'SH-1' }, requires_shift: false },
        })
    );
    await page.route('**/api/v1/pos/bootstrap*', (route) =>
        route.fulfill({
            json: { success: true, data: { pos_settings: { effective_max_discount_percent: '10.000' } } },
        })
    );
}

async function openPos(page, size) {
    await page.setViewportSize(size);
    await setupPos(page);
    await page.goto('/pos', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('pos-product-grid')).toBeVisible();
}

async function addItems(page, count) {
    for (const item of ITEMS.slice(0, count)) {
        await page.getByText(item.name, { exact: true }).first().click();
    }
}

const isCompact = (size) => size.width < DESKTOP_MIN;

/** Box of a test id, asserted to sit fully inside the viewport. */
async function expectInViewport(page, testId) {
    const box = await page.getByTestId(testId).boundingBox();
    const size = page.viewportSize();
    expect(box, `${testId} has a box`).not.toBeNull();
    expect(box.x, `${testId} left`).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width, `${testId} right`).toBeLessThanOrEqual(size.width);
    expect(box.y, `${testId} top`).toBeGreaterThanOrEqual(0);
    expect(box.y + box.height, `${testId} bottom`).toBeLessThanOrEqual(size.height);
    return box;
}

/** Every visible button/link/field, skipping the ones inside a horizontal scroller (category chips scroll by design). */
async function interactiveBoxes(page) {
    return page.evaluate(() => {
        const inHorizontalScroller = (el) => {
            for (let node = el.parentElement; node && node !== document.body; node = node.parentElement) {
                const overflowX = getComputedStyle(node).overflowX;
                if ((overflowX === 'auto' || overflowX === 'scroll') && node.scrollWidth > node.clientWidth + 1)
                    return true;
            }
            return false;
        };
        return [...document.querySelectorAll('button, a[href], input, select, textarea')]
            .filter((el) => {
                const style = getComputedStyle(el);
                const box = el.getBoundingClientRect();
                return style.visibility !== 'hidden' && style.display !== 'none' && box.width > 0 && box.height > 0;
            })
            .filter((el) => !inHorizontalScroller(el))
            .map((el) => {
                const box = el.getBoundingClientRect();
                return {
                    name: el.getAttribute('data-testid') || el.getAttribute('aria-label') || el.tagName.toLowerCase(),
                    tag: el.tagName.toLowerCase(),
                    inSearch: Boolean(el.closest('[data-testid="pos-search-field"]')),
                    left: box.left,
                    right: box.right,
                    top: box.top,
                    bottom: box.bottom,
                };
            });
    });
}

const overlaps = (a, b) =>
    a.left < b.right - 0.5 && b.left < a.right - 0.5 && a.top < b.bottom - 0.5 && b.top < a.bottom - 0.5;

async function expectNothingOutsideOrOverlapping(page) {
    const size = page.viewportSize();
    const boxes = await interactiveBoxes(page);
    const outside = boxes.filter((b) => b.left < -0.5 || b.right > size.width + 0.5);
    expect(
        outside.map((b) => `${b.name} ${Math.round(b.left)}..${Math.round(b.right)}`),
        'controls outside the viewport'
    ).toEqual([]);

    const search = await page.getByTestId('pos-search-input').boundingBox();
    const searchBox = {
        left: search.x,
        right: search.x + search.width,
        top: search.y,
        bottom: search.y + search.height,
    };
    const hit = boxes.filter((b) => !b.inSearch && b.name !== 'pos-search-input' && overlaps(searchBox, b));
    expect(
        hit.map((b) => b.name),
        'controls overlapping the search field'
    ).toEqual([]);
    expect(search.width, 'search field keeps a usable width').toBeGreaterThanOrEqual(200);
}

for (const size of SIZES) {
    test.describe(`RESP-02: POS at ${size.width}x${size.height}`, () => {
        test('cart and pay controls are on the first screen, nothing leaves the viewport', async ({ page }) => {
            await openPos(page, size);
            await addItems(page, 3);

            if (isCompact(size)) {
                await expect(page.getByTestId('pos-phone-cart-bar')).toBeVisible();
                await expect(page.getByTestId('pos-phone-cart-count')).toHaveText('3');
                await expect(page.getByTestId('pos-phone-cart-total')).toContainText('375');
                await expectInViewport(page, 'pos-phone-cart-bar');
                await expectInViewport(page, 'pos-phone-checkout-btn');
                await expect(page.getByTestId('pos-cart-column')).toHaveCount(0);
            } else {
                await expect(page.getByTestId('pos-cart-row')).toHaveCount(3);
                await expect(page.getByTestId('pos-net-total')).toContainText('375');
                await expectInViewport(page, 'pos-checkout-submit');
                await expectInViewport(page, 'pos-checkout-save-print');
                await expectInViewport(page, 'pos-net-total');

                // Two columns side by side: the cart is 360-400px wide and level with the catalog, never below it.
                const cart = await page.getByTestId('pos-cart-column').boundingBox();
                const grid = await page.getByTestId('pos-product-grid').boundingBox();
                expect(cart.width).toBeGreaterThanOrEqual(359.5);
                expect(cart.width).toBeLessThanOrEqual(400.5);
                expect(cart.y, 'the cart starts at the top of the workspace').toBeLessThan(200);
                expect(cart.y < grid.y + grid.height && grid.y < cart.y + cart.height, 'columns are level').toBe(true);
                expect(cart.x + cart.width <= grid.x || grid.x + grid.width <= cart.x, 'columns do not overlap').toBe(
                    true
                );
            }

            const scroll = await page.evaluate(() => ({
                top: document.scrollingElement.scrollTop,
                overflow: document.scrollingElement.scrollHeight - innerHeight,
            }));
            expect(scroll.overflow, 'the page itself does not scroll').toBeLessThanOrEqual(0);
            expect(scroll.top).toBe(0);

            await expectNothingOutsideOrOverlapping(page);
        });

        test('the back link is reachable', async ({ page }) => {
            await openPos(page, size);
            await expectInViewport(page, 'pos-back-link');
            const box = await page.getByTestId('pos-back-link').boundingBox();
            expect(Math.min(box.width, box.height)).toBeGreaterThanOrEqual(40);
        });
    });
}

test.describe('RESP-02: sheets on a tablet', () => {
    test('cart sheet and payment sheet stay inside the viewport and pay works from them', async ({ page }) => {
        const size = SIZES[0];
        await openPos(page, size);
        await addItems(page, 3);

        await page.getByTestId('pos-phone-cart-open').click();
        const cartSheet = page.getByTestId('pos-phone-cart-sheet');
        await expect(cartSheet).toBeVisible();
        await expect(cartSheet.getByTestId('pos-cart-row')).toHaveCount(3);
        const sheetPanel = await cartSheet.getByRole('dialog').boundingBox();
        expect(sheetPanel.width).toBeLessThanOrEqual(576);
        expect(sheetPanel.x).toBeGreaterThan(0);
        await expectNothingOutsideOrOverlappingInside(page, cartSheet);

        await page.getByTestId('pos-phone-sheet-checkout').click();
        const paySheet = page.getByTestId('pos-phone-payment-sheet');
        await expect(paySheet).toBeVisible();
        const submitButton = paySheet.getByTestId('pos-checkout-submit');
        // The sheet slides in: wait until it has settled before measuring.
        await expect
            .poll(async () => {
                const box = await submitButton.boundingBox();
                return box.y + box.height;
            })
            .toBeLessThanOrEqual(size.height);
        const submit = await submitButton.boundingBox();
        expect(submit.x).toBeGreaterThanOrEqual(0);
        expect(submit.x + submit.width).toBeLessThanOrEqual(size.width);
        await expectNothingOutsideOrOverlappingInside(page, paySheet);
    });

    test('payment method buttons show readable names', async ({ page }) => {
        await openPos(page, SIZES[2]);
        await addItems(page, 1);
        for (const key of ['cash', 'instapay', 'e_wallet', 'visa', 'bank_transfer']) {
            const button = page.getByTestId(`pos-method-${key}`);
            const clipped = await button.evaluate((el) => {
                const label = el.querySelector('span');
                return label.scrollWidth > label.clientWidth + 1;
            });
            expect(clipped, `${key} label is cut off`).toBe(false);
        }
    });

    test('the save button text is not truncated at 1024', async ({ page }) => {
        await openPos(page, SIZES[2]);
        await addItems(page, 1);
        for (const id of ['pos-checkout-submit', 'pos-checkout-save-print']) {
            const clipped = await page.getByTestId(id).evaluate((el) => {
                const label = el.querySelector('span:last-child');
                const text = label.getBoundingClientRect();
                const button = el.getBoundingClientRect();
                // Wrapped, not truncated: no horizontal overflow, and every line sits inside the button.
                return (
                    label.scrollWidth > label.clientWidth + 1 ||
                    text.top < button.top - 0.5 ||
                    text.bottom > button.bottom + 0.5
                );
            });
            expect(clipped, `${id} label is cut off`).toBe(false);
        }
    });
});

async function expectNothingOutsideOrOverlappingInside(page, sheet) {
    const size = page.viewportSize();
    const outside = await sheet.evaluate((root, width) => {
        const bad = [];
        const panel = root.querySelector('[role="dialog"]');
        for (const el of panel.querySelectorAll('button, input, select, a[href]')) {
            const box = el.getBoundingClientRect();
            if (box.width === 0 || box.height === 0) continue;
            let scroller = el.parentElement;
            let horizontal = false;
            while (scroller && scroller !== root) {
                const style = getComputedStyle(scroller);
                if (
                    (style.overflowX === 'auto' || style.overflowX === 'scroll') &&
                    scroller.scrollWidth > scroller.clientWidth + 1
                )
                    horizontal = true;
                scroller = scroller.parentElement;
            }
            if (horizontal) continue;
            const clip = panel.getBoundingClientRect();
            if (box.left < Math.max(0, clip.left) - 0.5 || box.right > Math.min(width, clip.right) + 0.5) {
                bad.push(
                    `${el.getAttribute('data-testid') || el.getAttribute('aria-label') || el.tagName} ${Math.round(box.left)}..${Math.round(box.right)}`
                );
            }
        }
        return bad;
    }, size.width);
    expect(outside, 'sheet controls outside the sheet/viewport').toEqual([]);
}

test.describe('RESP-02: rotation', () => {
    test('items added in portrait are still in the cart after rotating to landscape and back', async ({ page }) => {
        await openPos(page, SIZES[0]);
        await addItems(page, 3);
        await expect(page.getByTestId('pos-phone-cart-count')).toHaveText('3');

        await page.setViewportSize(SIZES[2]);
        await expect(page.getByTestId('pos-phone-cart-bar')).toHaveCount(0);
        await expect(page.getByTestId('pos-cart-row')).toHaveCount(3);
        await expect(page.getByTestId('pos-net-total')).toContainText('375');

        await page.setViewportSize(SIZES[0]);
        await expect(page.getByTestId('pos-phone-cart-count')).toHaveText('3');
        await expect(page.getByTestId('pos-phone-cart-total')).toContainText('375');
    });

    test('an open cart sheet closes cleanly when the viewport becomes desktop-sized', async ({ page }) => {
        await openPos(page, SIZES[0]);
        await addItems(page, 2);
        await page.getByTestId('pos-phone-cart-open').click();
        await expect(page.getByTestId('pos-phone-cart-sheet')).toBeVisible();

        await page.setViewportSize(SIZES[2]);
        await expect(page.getByTestId('pos-phone-cart-sheet')).toHaveCount(0);
        await expect(page.getByTestId('pos-cart-row')).toHaveCount(2);
        const lock = await page.evaluate(() => getComputedStyle(document.body).overflow);
        expect(lock).not.toBe('hidden');
    });
});
