// UI-03: the phone POS sheets (cart + payment) at 360 / 375 / 390 / 412px wide. Measurements:
//  - a cart line is one short row (name + total, then qty and price on one line), at least 4 lines fit;
//  - the price field is readable (not clipped) and item actions sit behind a "..." button;
//  - in the payment sheet the save buttons never cover the note buttons or the change line, and nothing
//    interactive sits outside the screen;
//  - the F9 / Enter hint is not shown on touch screens;
//  - on a touch screen every control in the sheets is at least 44px.
// POS data is mocked with page.route; selectors are data-testid, never translated text.
import { test, expect } from '@playwright/test';

const ITEM_NAMES = [
    'UI03 Espresso Blend Dark Roast 250g',
    'UI03 Turkish Coffee Cardamom 100g',
    'UI03 Colombian Supremo Medium Roast',
    'UI03 Ethiopian Yirgacheffe Light Roast',
    'UI03 House Blend Filter Coffee 500g',
    'UI03 Hazelnut Flavoured Coffee 200g',
];

const ITEMS = ITEM_NAMES.map((name, i) => ({
    id: 997001 + i,
    name,
    code: `UI03-${i}`,
    unit: 'piece',
    is_weighted: false,
    selling_price: '1250.500',
    price_retail: '1250.500',
    price_wholesale: '1100.000',
    min_selling_price: '900.000',
    has_min_price: true,
    current_stock: '30.000',
    min_stock_level: '5.000',
    is_pos_pinned: true,
    pos_sales_count: 10 - i,
}));

const SIZES = [
    { width: 360, height: 780 },
    { width: 375, height: 812 },
    { width: 390, height: 844 },
    { width: 412, height: 915 },
];

async function mockPos(page) {
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

async function openCartSheet(page, size, count) {
    await page.setViewportSize(size);
    await mockPos(page);
    await page.goto('/pos', { waitUntil: 'networkidle' });
    const tiles = page.getByTestId('pos-product-tile');
    await expect(tiles).toHaveCount(ITEMS.length);
    for (let i = 0; i < count; i += 1) await tiles.nth(i).click();
    await page.getByTestId('pos-phone-cart-open').click();
    await expect(page.getByTestId('pos-phone-cart-sheet')).toBeVisible();
    await settled(page, 'pos-phone-cart-sheet');
    await expect(page.getByTestId('pos-cart-row')).toHaveCount(count);
}

// A sheet slides in: measure only once its panel stopped moving.
async function settled(page, testId) {
    const panel = page.getByTestId(testId).getByRole('dialog');
    let last = null;
    await expect
        .poll(async () => {
            const box = await panel.boundingBox();
            const key = box ? `${box.x},${box.y},${box.width},${box.height}` : null;
            const stable = key !== null && key === last;
            last = key;
            return stable;
        })
        .toBe(true);
}

const isCoarse = (page) => page.evaluate(() => window.matchMedia('(pointer: coarse)').matches);

const contains = (outer, inner) =>
    inner.x >= outer.x - 0.5 &&
    inner.y >= outer.y - 0.5 &&
    inner.x + inner.width <= outer.x + outer.width + 0.5 &&
    inner.y + inner.height <= outer.y + outer.height + 0.5;

const overlaps = (a, b) =>
    a.x < b.x + b.width - 0.5 && b.x < a.x + a.width - 0.5 && a.y < b.y + b.height - 0.5 && b.y < a.y + a.height - 0.5;

// Boxes of the visible interactive elements inside `root`.
const interactiveBoxes = (root) =>
    root.evaluate((el) =>
        Array.from(el.querySelectorAll('button, input, select, a[href]'))
            .filter((node) => node.offsetParent !== null)
            .map((node) => {
                const r = node.getBoundingClientRect();
                return {
                    x: r.x,
                    y: r.y,
                    width: r.width,
                    height: r.height,
                    id: node.dataset.testid || node.getAttribute('aria-label') || node.tagName,
                };
            })
    );

test.describe('UI-03: phone cart sheet', () => {
    for (const size of SIZES) {
        test(`${size.width}x${size.height}: short rows, at least 4 fully visible, price readable`, async ({ page }) => {
            await openCartSheet(page, size, 6);

            const list = await page.getByTestId('pos-cart-list').boundingBox();
            const rows = await page.getByTestId('pos-cart-row').evaluateAll((els) =>
                els.map((el) => {
                    const r = el.getBoundingClientRect();
                    return { x: r.x, y: r.y, width: r.width, height: r.height };
                })
            );
            expect(rows.filter((row) => contains(list, row)).length).toBeGreaterThanOrEqual(4);
            rows.forEach((row) => expect(row.height).toBeLessThanOrEqual(76));

            const sideways = await page.getByTestId('pos-cart-list').evaluate((el) => el.scrollWidth - el.clientWidth);
            expect(sideways).toBeLessThanOrEqual(0);

            // Quantity and price share one line, and the price field is wide enough for its text.
            const first = page.getByTestId('pos-cart-row').first();
            const qty = await first.getByTestId('pos-cart-qty').boundingBox();
            const price = await first.getByTestId('pos-line-price').boundingBox();
            expect(Math.abs(qty.y - price.y)).toBeLessThanOrEqual(2);
            expect(price.width).toBeGreaterThanOrEqual(64);
            const clipped = await first.getByTestId('pos-line-price').evaluate((el) => el.scrollWidth - el.clientWidth);
            expect(clipped).toBeLessThanOrEqual(0);
        });

        test(`${size.width}x${size.height}: nothing interactive leaves the screen, touch targets are 44px`, async ({
            page,
        }) => {
            await openCartSheet(page, size, 4);
            const coarse = await isCoarse(page);
            const boxes = await interactiveBoxes(page.getByTestId('pos-phone-cart-sheet'));
            expect(boxes.length).toBeGreaterThan(10);
            for (const box of boxes) {
                expect(box.x, `${box.id} starts left of the screen`).toBeGreaterThanOrEqual(-0.5);
                expect(box.x + box.width, `${box.id} ends right of the screen`).toBeLessThanOrEqual(size.width + 0.5);
                if (coarse) {
                    expect(box.height, `${box.id} is shorter than 44px`).toBeGreaterThanOrEqual(43.5);
                    expect(box.width, `${box.id} is narrower than 44px`).toBeGreaterThanOrEqual(43.5);
                }
            }
        });
    }

    test('item actions sit behind the "..." button and delete works from there', async ({ page }) => {
        await openCartSheet(page, { width: 390, height: 844 }, 3);
        const row = page.getByTestId('pos-cart-row').first();

        await expect(row.getByTestId('pos-line-discount-toggle')).toHaveCount(0);
        await expect(row.getByTestId('pos-line-remove')).toHaveCount(0);

        await row.getByTestId('pos-line-more').click();
        const discount = row.getByTestId('pos-line-discount-toggle');
        const remove = row.getByTestId('pos-line-remove');
        await expect(discount).toBeVisible();
        for (const box of [await discount.boundingBox(), await remove.boundingBox()]) {
            expect(box.height).toBeGreaterThanOrEqual(43.5);
            expect(box.x + box.width).toBeLessThanOrEqual(390.5);
        }

        await discount.click();
        await expect(row.getByTestId('pos-line-discount-input')).toBeVisible();

        await remove.click();
        await expect(page.getByTestId('pos-cart-row')).toHaveCount(2);
    });
});

test.describe('UI-03: phone payment sheet', () => {
    for (const size of SIZES) {
        test(`${size.width}x${size.height}: save buttons never cover the notes or the change line`, async ({
            page,
        }) => {
            await openCartSheet(page, size, 6);
            await page.getByTestId('pos-phone-sheet-checkout').click();
            const sheet = page.getByTestId('pos-phone-payment-sheet');
            await expect(sheet).toBeVisible();
            await settled(page, 'pos-phone-payment-sheet');

            // The discount block starts collapsed: the sheet is not a third full of discount presets.
            await expect(page.getByTestId('pos-discount-input')).toHaveCount(0);

            const controls = {
                cash: page.getByTestId('pos-cash-received'),
                change: page.getByTestId('pos-change-box'),
                n50: page.getByTestId('pos-tender-50'),
                n100: page.getByTestId('pos-tender-100'),
                n200: page.getByTestId('pos-tender-200'),
                n500: page.getByTestId('pos-tender-500'),
                exact: page.getByTestId('pos-tender-exact'),
                submit: page.getByTestId('pos-checkout-submit'),
                print: page.getByTestId('pos-checkout-save-print'),
            };
            const viewport = { x: 0, y: 0, width: size.width, height: size.height };
            const boxes = {};
            for (const [name, locator] of Object.entries(controls)) {
                await expect(locator, name).toBeVisible();
                boxes[name] = await locator.boundingBox();
                expect(contains(viewport, boxes[name]), `${name} is outside the screen`).toBe(true);
            }

            const names = Object.keys(boxes);
            for (let i = 0; i < names.length; i += 1) {
                for (let j = i + 1; j < names.length; j += 1) {
                    expect(overlaps(boxes[names[i]], boxes[names[j]]), `${names[i]} and ${names[j]} overlap`).toBe(
                        false
                    );
                }
            }
            // The change line sits above the save buttons, always on screen.
            expect(boxes.change.y + boxes.change.height).toBeLessThanOrEqual(boxes.submit.y + 0.5);
            expect(boxes.n200.y + boxes.n200.height).toBeLessThanOrEqual(boxes.submit.y + 0.5);

            const all = await interactiveBoxes(sheet);
            for (const box of all) {
                expect(box.x, `${box.id} starts left of the screen`).toBeGreaterThanOrEqual(-0.5);
                expect(box.x + box.width, `${box.id} ends right of the screen`).toBeLessThanOrEqual(size.width + 0.5);
            }
            if (await isCoarse(page)) {
                for (const name of ['cash', 'n50', 'n100', 'n200', 'n500', 'exact', 'submit', 'print']) {
                    expect(boxes[name].height, `${name} is shorter than 44px`).toBeGreaterThanOrEqual(43.5);
                }
            }
        });
    }

    test('the F9 / Enter hint is shown to a keyboard and hidden on a touch screen', async ({ page }) => {
        await openCartSheet(page, { width: 390, height: 844 }, 1);
        await page.getByTestId('pos-phone-sheet-checkout').click();
        const text = (await page.getByTestId('pos-checkout-submit').innerText()).trim();
        if (await isCoarse(page)) {
            expect(text).not.toMatch(/F9|Enter/);
        } else {
            expect(text).toMatch(/F9/);
        }
        expect(text.length).toBeGreaterThan(0);
    });
});
