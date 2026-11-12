// UI-02: POS item cards, category list and header. Measurements, not screenshots:
//  - the search box is at least 280px wide and no header control sits on top of it;
//  - every item card shows ONE price, big and bold, and no text in it is below 12px;
//  - category names are never cut without a title, below 1280px they become a chip row;
//  - the number of cards per row follows the branch setting (3 by default, no images);
//  - the cashier never sees the minimum price.
// POS data is mocked with page.route; selectors are data-testid / roles, never translated text.
import { test, expect } from '@playwright/test';

const MIN_FONT = 12;
const PRICE_FONT = 16;

const CATEGORIES = [
    {
        id: 701,
        name: 'هواتف أيفون بجميع الإصدارات والموديلات الحديثة',
        color: '#2563eb',
        icon: 'Folder',
        items_count: 6,
    },
    {
        id: 702,
        name: 'Samsung Galaxy Smartphones and Accessories Collection',
        color: '#16a34a',
        icon: 'Folder',
        items_count: 6,
    },
];

const ITEM_NAMES = [
    'آيفون 16 برو ماكس 256 جيجا تيتانيوم صحراوي طبيعي اللون مع ضمان سنتين كاملتين',
    'Apple iPhone 16 Pro 256GB Black Titanium',
    'سماعة لاسلكية',
    'Wall charger 20W USB-C fast charging adapter',
    'كابل شحن سريع',
    'Screen protector tempered glass',
];

const ITEMS = ITEM_NAMES.map((name, i) => ({
    id: 996001 + i,
    name,
    code: `UI02-${i}`,
    unit: 'piece',
    is_weighted: false,
    category_id: CATEGORIES[i % 2].id,
    selling_price: '125.000',
    price_retail: '125.000',
    price_wholesale: '110.000',
    min_selling_price: '99.000',
    has_min_price: true,
    // 0 = out, 2 (<= minimum) = running low, the rest are plain "available"
    current_stock: i === 0 ? '0.000' : i === 1 ? '2.000' : '30.000',
    min_stock_level: '5.000',
    is_pos_pinned: true,
    pos_sales_count: 10 - i,
}));

async function mockPos(page, posSettings = {}) {
    await page.addInitScript(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('pos_multi_orders'))
            .forEach((k) => localStorage.removeItem(k));
    });
    await page.route('**/api/v1/pos/items*', (route) =>
        route.fulfill({
            json: {
                success: true,
                data: ITEMS,
                meta: { current_page: 1, last_page: 1, total: ITEMS.length },
            },
        })
    );
    await page.route('**/api/v1/categories*', (route) =>
        route.fulfill({
            json: {
                success: true,
                data: CATEGORIES,
                total_items_count: ITEMS.length,
            },
        })
    );
    await page.route('**/api/v1/customers*', (route) => route.fulfill({ json: { success: true, data: [] } }));
    await page.route('**/api/v1/shifts/current*', (route) =>
        route.fulfill({
            json: {
                success: true,
                active_shift: { id: 1, shift_number: 'SH-1' },
                requires_shift: false,
            },
        })
    );
    await page.route('**/api/v1/pos/bootstrap*', (route) =>
        route.fulfill({
            json: {
                success: true,
                data: {
                    pos_settings: {
                        effective_max_discount_percent: '10.000',
                        ...posSettings,
                    },
                },
            },
        })
    );
}

async function openPos(page, size, posSettings) {
    await page.setViewportSize(size);
    await mockPos(page, posSettings);
    await page.goto('/pos', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('pos-product-tile')).toHaveCount(ITEMS.length);
}

// Smallest rendered font (px) among the visible text nodes inside `root`.
const smallestFont = (locator) =>
    locator.evaluate((root, minFont) => {
        const small = [];
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            if (!node.textContent.trim()) continue;
            const el = node.parentElement;
            const style = getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            if (style.display === 'none' || style.visibility === 'hidden' || !rect.width || !rect.height) continue;
            if (parseFloat(style.fontSize) < minFont) small.push(`${node.textContent.trim()} @${style.fontSize}`);
        }
        return small;
    }, MIN_FONT);

const overlaps = (a, b) =>
    a.x < b.x + b.width - 0.5 && b.x < a.x + a.width - 0.5 && a.y < b.y + b.height - 0.5 && b.y < a.y + a.height - 0.5;

const HEADER_SIZES = [
    { name: '1024x768', size: { width: 1024, height: 768 } },
    { name: '1366x768', size: { width: 1366, height: 768 } },
    { name: '1920x1080', size: { width: 1920, height: 1080 } },
];

test.describe('UI-02: POS header', () => {
    for (const { name, size } of HEADER_SIZES) {
        test(`${name}: the search box is at least 280px wide and nothing in the header covers it`, async ({ page }) => {
            await openPos(page, size);

            const input = page.getByTestId('pos-search-input');
            await expect(input).toBeVisible();
            const search = await page.getByTestId('pos-search-field').boundingBox();
            expect(search.width).toBeGreaterThanOrEqual(280);

            const header = page.locator('header').first();
            const controls = await header.locator('button:visible, a:visible, select:visible').evaluateAll((els) =>
                els
                    .filter((el) => !el.closest('[data-testid="pos-search-field"]'))
                    .map((el) => {
                        const r = el.getBoundingClientRect();
                        return { x: r.x, y: r.y, width: r.width, height: r.height };
                    })
            );
            expect(controls.length).toBeGreaterThan(3);
            for (const box of controls) {
                expect(overlaps(box, search), 'a header control sits on the search box').toBe(false);
                expect(box.x).toBeGreaterThanOrEqual(-0.5);
                expect(box.x + box.width).toBeLessThanOrEqual(size.width + 0.5);
            }
            const overflow = await header.evaluate((el) => el.scrollWidth - el.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
        });
    }

    test('the header has no version badge and no disabled "coming soon" button', async ({ page }) => {
        await openPos(page, { width: 1920, height: 1080 });
        const header = page.locator('header').first();
        await expect(header).not.toContainText(/\bv\d+\.\d+/);
        // The only disabled control is the icon-only "clear cart" of an empty ticket: no disabled text button remains.
        const disabled = await header
            .locator('button:disabled')
            .evaluateAll((els) => els.map((el) => el.textContent.trim()));
        expect(disabled.every((text) => text === '')).toBe(true);
    });

    test('the minimum price is never shown in the search results', async ({ page }) => {
        await openPos(page, { width: 1366, height: 768 });
        await page.getByTestId('pos-search-input').fill('Apple');
        const dropdown = page.locator('[data-testid="pos-search-field"] .shadow-2xl').first();
        await expect(dropdown).toContainText('125');
        await expect(dropdown).not.toContainText('99');
    });
});

test.describe('UI-02: POS item cards', () => {
    test('1366x768: one big bold price per card, no text under 12px, name on two lines at most', async ({ page }) => {
        await openPos(page, { width: 1366, height: 768 });
        const tiles = page.getByTestId('pos-product-tile');

        for (let i = 0; i < ITEMS.length; i += 1) {
            const tile = tiles.nth(i);
            await expect(tile.locator('[data-price-slot]'), `tile ${i} has exactly one price`).toHaveCount(1);
            expect(await smallestFont(tile), `tile ${i} has text under ${MIN_FONT}px`).toEqual([]);

            const price = await tile.locator('[data-price-slot]').evaluate((el) => {
                const style = getComputedStyle(el);
                return {
                    size: parseFloat(style.fontSize),
                    weight: parseInt(style.fontWeight, 10),
                };
            });
            expect(price.size).toBeGreaterThanOrEqual(PRICE_FONT);
            expect(price.weight).toBeGreaterThanOrEqual(700);

            const name = await tile
                .locator('span[dir="auto"]')
                .first()
                .evaluate((el) => {
                    const style = getComputedStyle(el);
                    return {
                        size: parseFloat(style.fontSize),
                        lines: Math.round(el.getBoundingClientRect().height / parseFloat(style.lineHeight)),
                    };
                });
            expect(name.size).toBeGreaterThanOrEqual(13);
            expect(name.lines).toBeLessThanOrEqual(2);
        }
    });

    test('stock is a coloured badge with a meaning: out, low, available', async ({ page }) => {
        await openPos(page, { width: 1366, height: 768 });
        const levels = await page
            .getByTestId('pos-stock-badge')
            .evaluateAll((els) => els.map((el) => el.dataset.stockLevel));
        expect(levels.slice(0, 3)).toEqual(['out', 'low', 'ok']);
    });

    test('the price follows the selected tier and stays a single price', async ({ page }) => {
        await openPos(page, { width: 1366, height: 768 });
        const tile = page.getByTestId('pos-product-tile').nth(2);
        await expect(tile.getByTestId('pos-price-retail')).toContainText('125');
        await expect(tile.getByTestId('pos-price-wholesale')).toHaveCount(0);

        await page.getByTestId('pos-tier-wholesale').click();
        await expect(tile.getByTestId('pos-price-wholesale')).toContainText('110');
        await expect(tile.getByTestId('pos-price-retail')).toHaveCount(0);
        await expect(tile.locator('[data-price-slot]')).toHaveCount(1);
        await expect(tile).not.toContainText('99');
    });

    test('3 cards per row without images by default; the branch setting changes it', async ({ page }) => {
        await openPos(page, { width: 1366, height: 768 });
        await expect(page.getByTestId('pos-product-image')).toHaveCount(0);
        const columns = () =>
            page
                .getByTestId('pos-product-tile')
                .evaluateAll((els) => new Set(els.map((el) => Math.round(el.getBoundingClientRect().x))).size);
        expect(await columns()).toBe(3);

        await page.unrouteAll({ behavior: 'ignoreErrors' });
        await mockPos(page, { catalog_cards_per_row: '2' });
        await page.reload({ waitUntil: 'networkidle' });
        await expect(page.getByTestId('pos-product-tile')).toHaveCount(ITEMS.length);
        expect(await columns()).toBe(2);
    });
});

test.describe('UI-02: POS categories', () => {
    test('1366x768: the side list shows full names (wrapped or with a title) and the chip row is hidden', async ({
        page,
    }) => {
        await openPos(page, { width: 1366, height: 768 });
        await expect(page.getByTestId('pos-category-chips')).toBeHidden();

        const buttons = page.locator('aside nav button[title]:visible');
        const checked = await buttons.evaluateAll(
            (els, names) => {
                return els
                    .filter((el) => names.includes(el.getAttribute('title')))
                    .map((el) => {
                        const label = el.querySelector('span[dir="auto"]');
                        return {
                            title: el.getAttribute('title'),
                            fits: label.scrollWidth <= label.clientWidth + 1,
                            lines: Math.round(
                                label.getBoundingClientRect().height / parseFloat(getComputedStyle(label).lineHeight)
                            ),
                        };
                    });
            },
            CATEGORIES.map((c) => c.name)
        );
        expect(checked).toHaveLength(CATEGORIES.length);
        for (const entry of checked) {
            expect(entry.title).toBeTruthy();
            expect(entry.fits, `${entry.title} is cut horizontally`).toBe(true);
            expect(entry.lines).toBeLessThanOrEqual(2);
        }
    });

    for (const size of [
        { width: 1024, height: 768 },
        { width: 768, height: 1024 },
    ]) {
        test(`${size.width}x${size.height}: categories become a chip row above the grid`, async ({ page }) => {
            await openPos(page, size);
            const chips = page.getByTestId('pos-category-chips');
            await expect(chips).toBeVisible();
            await expect(page.locator('aside nav button[title]:visible')).toHaveCount(0);

            const chipBoxes = await chips.getByRole('button').evaluateAll((els) =>
                els.map((el) => ({
                    height: el.getBoundingClientRect().height,
                    text: el.textContent.trim(),
                }))
            );
            expect(chipBoxes.length).toBeGreaterThan(CATEGORIES.length);
            chipBoxes.forEach((chip) => expect(chip.height).toBeGreaterThanOrEqual(44));
        });
    }
});

test.describe('UI-02: POS navigation rail', () => {
    test('1366x768: rail icons are named for assistive tech and the label shows on keyboard focus', async ({
        page,
    }) => {
        await openPos(page, { width: 1366, height: 768 });
        const rail = page.locator('aside').first();
        const icons = rail.locator('a[aria-label], button[aria-label]');
        expect(await icons.count()).toBeGreaterThanOrEqual(6);

        // The first section button after the quick-POS link and the dashboard link.
        const section = rail.locator('button[aria-label]:not([data-testid])').first();
        await section.focus();
        const label = (await section.getAttribute('aria-label')) || '';
        expect(label.length).toBeGreaterThan(0);
        await expect(page.locator('body > div.fixed.pointer-events-none')).toContainText(label);
    });
});
