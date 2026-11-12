// UI-01: desktop POS cart column. Rows stay readable and fully visible, nothing scrolls sideways,
// and the payment footer (methods, tender, save buttons) never overlaps itself or leaves the viewport.
import { test, expect } from '@playwright/test';

const ITEM_NAMES = [
    'UI01 Espresso Blend Dark Roast 250g',
    'UI01 Turkish Coffee Cardamom 100g',
    'UI01 Colombian Supremo Medium Roast',
    'UI01 Ethiopian Yirgacheffe Light Roast',
    'UI01 House Blend Filter Coffee 500g',
    'UI01 Hazelnut Flavoured Coffee 200g',
    'UI01 Brazil Santos Natural Process',
    'UI01 Decaf Swiss Water Process 250g',
    'UI01 French Roast Extra Dark 1kg',
];

const ITEMS = ITEM_NAMES.map((name, i) => ({
    id: 995001 + i,
    name,
    code: `62250000${i}`,
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

async function openPosWithItems(page, size, count) {
    await page.setViewportSize(size);
    await setupPos(page);
    await page.goto('/pos', { waitUntil: 'networkidle' });
    await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });
    for (const item of ITEMS.slice(0, count)) {
        await page.getByText(item.name, { exact: true }).first().click();
    }
    await expect(page.getByTestId('pos-cart-row')).toHaveCount(count);
}

const contains = (outer, inner) =>
    inner.x >= outer.x - 0.5 &&
    inner.y >= outer.y - 0.5 &&
    inner.x + inner.width <= outer.x + outer.width + 0.5 &&
    inner.y + inner.height <= outer.y + outer.height + 0.5;

const overlaps = (a, b) =>
    a.x < b.x + b.width - 0.5 && b.x < a.x + a.width - 0.5 && a.y < b.y + b.height - 0.5 && b.y < a.y + a.height - 0.5;

async function fullyVisibleRows(page) {
    const list = await page.getByTestId('pos-cart-list').boundingBox();
    const boxes = await page.getByTestId('pos-cart-row').evaluateAll((rows) =>
        rows.map((row) => {
            const r = row.getBoundingClientRect();
            return { x: r.x, y: r.y, width: r.width, height: r.height };
        })
    );
    return { visible: boxes.filter((box) => contains(list, box)).length, tallest: Math.max(...boxes.map((b) => b.height)) };
}

// On a touch screen (pointer: coarse) every control in a row is 44px tall, so a row is taller than the mouse
// one (36px controls) and fewer rows fit. The thresholds follow the pointer, not just the viewport width.
const isCoarse = (page) => page.evaluate(() => window.matchMedia('(pointer: coarse)').matches);

const SIZES = [
    { name: '1366x768', size: { width: 1366, height: 768 }, minRows: 5, minRowsTouch: 3 },
    { name: '1024x768', size: { width: 1024, height: 768 }, minRows: 4, minRowsTouch: 3 },
    { name: '1920x1080', size: { width: 1920, height: 1080 }, minRows: 5, minRowsTouch: 5 },
];

const MAX_ROW_HEIGHT = 64;
const MAX_ROW_HEIGHT_TOUCH = 72;

test.describe('UI-01: POS cart column layout', () => {
    for (const { name, size, minRows, minRowsTouch } of SIZES) {
        test(`${name}: rows visible, no sideways scroll, footer controls clear of each other`, async ({ page }) => {
            await openPosWithItems(page, size, 5);

            const coarse = await isCoarse(page);
            const { visible } = await fullyVisibleRows(page);
            expect(visible).toBeGreaterThanOrEqual(coarse ? minRowsTouch : minRows);

            const metrics = await page
                .getByTestId('pos-cart-list')
                .evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth }));
            expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth);

            const controls = [
                page.getByTestId('pos-checkout-submit'),
                page.getByTestId('pos-checkout-save-print'),
                page.getByTestId('pos-cash-received'),
                page.getByTestId('pos-split-payment-btn'),
                page.getByTestId('pos-tender-50'),
                page.getByTestId('pos-tender-exact'),
                ...(await page.locator('[data-testid^="pos-method-"]').all()),
            ];
            const boxes = [];
            for (const control of controls) {
                await expect(control).toBeVisible();
                boxes.push(await control.boundingBox());
            }
            expect(boxes.length).toBeGreaterThanOrEqual(10);
            const viewport = { x: 0, y: 0, width: size.width, height: size.height };
            boxes.forEach((box) => expect(contains(viewport, box)).toBe(true));
            for (let i = 0; i < boxes.length; i += 1) {
                for (let j = i + 1; j < boxes.length; j += 1) {
                    expect(overlaps(boxes[i], boxes[j]), `controls ${i} and ${j} overlap`).toBe(false);
                }
            }
        });
    }

    test('1920x1080: eight lines are fully visible and each row stays within 64px (72px on touch)', async ({ page }) => {
        await openPosWithItems(page, { width: 1920, height: 1080 }, 9);
        const coarse = await isCoarse(page);
        const { visible, tallest } = await fullyVisibleRows(page);
        expect(visible).toBeGreaterThanOrEqual(coarse ? 7 : 8);
        expect(tallest).toBeLessThanOrEqual(coarse ? MAX_ROW_HEIGHT_TOUCH : MAX_ROW_HEIGHT);
    });

    test('collapsed discount line keeps showing the applied value and F7 opens the field', async ({ page }) => {
        await openPosWithItems(page, { width: 1366, height: 768 }, 1);

        await expect(page.getByTestId('pos-discount-input')).toHaveCount(0);
        await page.keyboard.press('F7');
        await expect(page.getByTestId('pos-discount-input')).toBeFocused();

        await page.getByTestId('pos-discount-preset-10').click();
        await expect(page.getByTestId('pos-discount-total')).toContainText('12.5');

        await page.getByTestId('pos-discount-toggle').click();
        await expect(page.getByTestId('pos-discount-input')).toHaveCount(0);
        await expect(page.getByTestId('pos-discount-total')).toContainText('12.5');
    });

    test('line discount opens from the row icon and from Alt+D', async ({ page }) => {
        await openPosWithItems(page, { width: 1366, height: 768 }, 2);
        const firstRow = page.getByTestId('pos-cart-row').first();

        await expect(firstRow.getByTestId('pos-line-discount-input')).toHaveCount(0);
        await firstRow.getByTestId('pos-line-discount-toggle').click();
        await firstRow.getByTestId('pos-line-discount-input').fill('10');
        await expect(firstRow.getByTestId('pos-line-gross')).toBeVisible();

        const lastRow = page.getByTestId('pos-cart-row').last();
        await lastRow.getByTestId('pos-cart-qty').focus();
        await page.keyboard.press('Alt+KeyD');
        await expect(lastRow.getByTestId('pos-line-discount-input')).toBeFocused();
    });
});
