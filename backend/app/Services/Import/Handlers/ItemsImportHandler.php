<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\Import\ArabicTextNormalizer;
use App\Services\Import\ImportContext;
use App\Services\Import\NumberNormalizer;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PRD-06: items. An existing code is a duplicate (skipped, never updated); IMP-01: so is a code-less
 * row whose name + unit match a live item. The category is found by normalized name or created
 * (only after the user confirmed the preview's list of new categories). Opening stock goes to the active store as an `opening_balance_in` movement;
 * the item's weighted average cost starts at the opening cost, exactly what a first purchase of a
 * zero-stock item would set.
 *
 * IMP-02: the write is BATCHED (chunked multi-row inserts: categories, items, store_stocks,
 * stock_movements) inside the caller's single transaction, so 5,000 rows cost a few dozen queries
 * instead of ~10 per row. It reproduces, row for row, what CreateItemAction + StoreStockInitializer
 * + StockService::addStock wrote for a brand-new item (none of them fire model events or observers
 * for these models). Every row written here is NEW: no existing stock or balance row is read and
 * changed, so there is nothing to lock beyond the ITM-code read.
 */
final class ItemsImportHandler extends AbstractImportHandler
{
    public const OPENING_MOVEMENT = 'opening_balance_in';

    /** Rows per INSERT (items have ~17 columns: well under MySQL's 65,535 and sqlite's 32,766 placeholders). */
    private const CHUNK = 500;

    /** items.code is VARCHAR(50). */
    private const CODE_MAX = 50;

    public function __construct(
        private readonly TenantSettings $settings,
    ) {}

    /** @var list<string> */
    private array $units = [];

    /** @var array<string, string> IMP-01: normalized unit => the tenant's own spelling */
    private array $unitsByKey = [];

    /** @var array<string, string> IMP-01: normalized category name => the existing category's name */
    private array $categoriesByKey = [];

    public function key(): string
    {
        return 'items';
    }

    public function columns(): array
    {
        return [
            'code', 'name', 'category', 'unit', 'cost_price', 'selling_price', 'min_selling_price',
            'min_stock_level', 'is_weighted', 'opening_quantity', 'opening_cost',
        ];
    }

    protected function requiredColumns(): array
    {
        return ['name', 'unit', 'selling_price'];
    }

    protected function prepare(): void
    {
        $this->units = $this->settings->inventoryUnits();
        $this->unitsByKey = [];
        foreach ($this->units as $unit) {
            $this->unitsByKey[ArabicTextNormalizer::key($unit)] ??= $unit;
        }

        $this->categoriesByKey = [];
        foreach (Category::query()->orderBy('id')->pluck('name') as $name) {
            // Ascending ids: when two live categories collapse to one key the newest wins, as in write().
            $this->categoriesByKey[ArabicTextNormalizer::key((string) $name)] = (string) $name;
        }
    }

    protected function normalize(array $raw, array &$errors): array
    {
        $unit = $this->unit($raw, $errors);
        $code = $this->code($raw, $errors);

        $category = $this->requireText($raw, 'category', 100, $errors, false);
        if ($category !== null) {
            // IMP-01: «مشروبات ساخنه» lands in the existing «مشروبات ساخنة» instead of creating a twin.
            $category = $this->categoriesByKey[ArabicTextNormalizer::key($category)] ?? $category;
        }

        $weighted = null;
        if (trim($raw['is_weighted']) !== '') {
            $weighted = NumberNormalizer::boolean($raw['is_weighted']);
            if ($weighted === null) {
                $errors[] = (string) __('import.errors.invalid_boolean', ['field' => $this->label('is_weighted')]);
            }
        }

        $quantity = $this->number($raw, 'opening_quantity', $errors);
        $openingCost = $this->number($raw, 'opening_cost', $errors);

        $selling = $this->number($raw, 'selling_price', $errors, required: true);
        if ($selling !== null && bccomp($selling, '0.000', 3) <= 0) {
            $errors[] = (string) __('import.errors.must_be_positive', ['field' => $this->label('selling_price')]);
        }

        // null = no price floor ('0.000'); never defaulted to the cost.
        $floor = $this->number($raw, 'min_selling_price', $errors);
        if ($floor !== null && $selling !== null && bccomp($floor, $selling, 3) > 0) {
            $errors[] = (string) __('import.errors.min_above_selling', [
                'min' => $this->label('min_selling_price'),
                'selling' => $this->label('selling_price'),
            ]);
        }

        return [
            'code' => $code,
            'name' => $this->requireText($raw, 'name', 255, $errors),
            'category' => $category,
            'unit' => $unit,
            'cost_price' => $this->number($raw, 'cost_price', $errors, default: '0.000'),
            'selling_price' => $selling,
            'min_selling_price' => $floor,
            'min_stock_level' => $this->number($raw, 'min_stock_level', $errors, default: '0.000'),
            'is_weighted' => $weighted ?? false,
            'opening_quantity' => $quantity ?? '0.000',
            'opening_cost' => $openingCost,
        ];
    }

    /**
     * IMP-02: price sanity remarks. The row stays valid; the user decides.
     */
    protected function warnings(array $data): array
    {
        $warnings = [];
        $selling = (string) $data['selling_price'];

        // The cost the item will carry: the cost column, or the opening cost when the cost is blank/zero.
        $cost = (string) ($data['cost_price'] ?? '0.000');
        if (bccomp($cost, '0.000', 3) === 0 && ($data['opening_cost'] ?? null) !== null) {
            $cost = (string) $data['opening_cost'];
        }

        if (bccomp($cost, '0.000', 3) > 0 && bccomp($selling, $cost, 3) < 0) {
            $warnings[] = (string) __('import.warnings.selling_below_cost', [
                'selling' => $this->label('selling_price'),
                'cost' => $this->label('cost_price'),
            ]);
        }

        return $warnings;
    }

    /**
     * IMP-01: the unit is matched after Arabic normalization («قطعه» = «قطعة») and stored in the
     * tenant's own spelling; an unknown unit names the closest one from the list.
     *
     * @param  array<string, string>  $raw
     * @param  list<string>  $errors
     */
    private function unit(array $raw, array &$errors): ?string
    {
        $unit = $this->requireText($raw, 'unit', 50, $errors);
        if ($unit === null) {
            return null;
        }

        $known = $this->unitsByKey[ArabicTextNormalizer::key($unit)] ?? null;
        if ($known !== null) {
            return $known;
        }

        $message = (string) __('inventory.unit_not_in_tenant_list', ['unit' => $unit]);
        $suggestion = ArabicTextNormalizer::closest($unit, $this->units);
        if ($suggestion !== null) {
            $message .= ' '.__('import.errors.did_you_mean', ['value' => $suggestion]);
        }
        $errors[] = $message;

        return $unit;
    }

    /**
     * IMP-01: a barcode that Excel turned into scientific notation (`1.2E+12`) lost its digits for good;
     * it is refused with a fix-it message instead of being stored as text. Only Excel's own output is
     * refused (one-digit mantissa, optional fraction, `E+`, two-digit-or-longer exponent: `1.2E+12`,
     * `1E+12`): real codes such as `3E4`, `12E05` or `12E+05` are kept.
     *
     * @param  array<string, string>  $raw
     * @param  list<string>  $errors
     */
    private function code(array $raw, array &$errors): ?string
    {
        $code = $this->requireText($raw, 'code', self::CODE_MAX, $errors, false);
        if ($code !== null && preg_match('/^\d(?:[.,]\d+)?E\+\d{2,}$/i', NumberNormalizer::latinDigits($code)) === 1) {
            $errors[] = (string) __('import.errors.scientific_code', ['field' => $this->label('code'), 'value' => $code]);

            return null;
        }

        return $code;
    }

    /**
     * IMP-01: an item is the same item by its code; a row WITHOUT a code is the same item as one with
     * the same name and unit (after Arabic normalization). Re-uploading a file of code-less items
     * therefore adds nothing the second time.
     */
    protected function duplicateKey(array $data): string
    {
        $code = (string) ($data['code'] ?? '');
        if ($code !== '') {
            return 'code:'.mb_strtolower($code);
        }

        return self::nameKey((string) $data['name'], (string) $data['unit']);
    }

    private static function nameKey(string $name, string $unit): string
    {
        return 'name:'.ArabicTextNormalizer::key($name).'|'.ArabicTextNormalizer::key($unit);
    }

    protected function existingKeys(array $keys): array
    {
        $codes = [];
        $names = [];
        foreach ($keys as $key) {
            if (str_starts_with($key, 'code:')) {
                $codes[] = substr($key, 5);
            } else {
                $names[$key] = true;
            }
        }

        $existing = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            // The column expression is constant; the codes are bound parameters.
            $found = Item::withTrashed()->whereIn(DB::raw('LOWER(code)'), $chunk)->pluck('code');
            foreach ($found as $code) {
                $existing['code:'.mb_strtolower((string) $code)] = true;
            }
        }

        if ($names !== []) {
            // Live items only: a deleted item with the same name may be imported again (a new code is generated).
            foreach (Item::query()->select(['id', 'name', 'unit'])->toBase()->lazyById(2000) as $item) {
                $key = self::nameKey((string) $item->name, (string) $item->unit);
                if (isset($names[$key])) {
                    $existing[$key] = true;
                }
            }
        }

        return $existing;
    }

    /**
     * IMP-01: categories that do not exist yet (after normalization). The commit refuses to create
     * them unless the user confirmed this list.
     */
    protected function newCategories(array $validRows): array
    {
        $new = [];
        foreach ($validRows as $row) {
            $name = trim((string) ($row['category'] ?? ''));
            $key = ArabicTextNormalizer::key($name);
            if ($name !== '' && ! isset($this->categoriesByKey[$key]) && ! isset($new[$key])) {
                $new[$key] = $name;
            }
        }

        return array_values($new);
    }

    public function write(array $rows, ImportContext $context): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = Carbon::now();
        $categories = $this->resolveCategories($rows, $now);
        $codes = $this->resolveCodes($rows);

        // 1. items, with the opening stock and cost already applied (the row is new: nothing to average).
        $items = [];
        $openings = [];
        foreach ($rows as $index => $row) {
            $code = $codes[$index];
            $categoryName = (string) ($row['category'] ?? '');
            $category = $categoryName === '' ? null : $categories[ArabicTextNormalizer::key($categoryName)];

            $costPrice = (string) ($row['cost_price'] ?? '0.000');
            $weightedAvgCost = $costPrice;
            $currentStock = '0.000';

            $quantity = (string) $row['opening_quantity'];
            if (bccomp($quantity, '0.000', 3) > 0 && $context->storeId !== null) {
                $unitCost = $row['opening_cost'] !== null && $row['opening_cost'] !== '' ? (string) $row['opening_cost'] : $costPrice;

                // Valid ONLY because the item is new (stock 0, no history): the opening cost becomes
                // the WAC outright, as PurchaseService does on a first purchase of a zero-stock item.
                if (bccomp($unitCost, '0.000', 3) > 0) {
                    $weightedAvgCost = $unitCost;
                    if (bccomp($costPrice, '0.000', 3) === 0) {
                        $costPrice = $unitCost;
                    }
                }

                $currentStock = $quantity;
                $openings[$code] = ['quantity' => $quantity, 'unit_cost' => $unitCost];
            }

            $items[] = [
                'name' => $row['name'],
                'code' => $code,
                'category' => $category?->name,
                'category_id' => $category?->id,
                'unit' => $row['unit'],
                'cost_price' => $costPrice,
                // '0.000' = no price floor; never default to cost (cost leak).
                'min_selling_price' => $row['min_selling_price'] ?? '0.000',
                'weighted_avg_cost' => $weightedAvgCost,
                'selling_price' => $row['selling_price'],
                'wholesale_price' => null,
                'min_stock_level' => $row['min_stock_level'] ?? '0.000',
                'current_stock' => $currentStock,
                'is_active' => true,
                'notes' => null,
                'is_weighted' => (bool) $row['is_weighted'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($items, self::CHUNK) as $chunk) {
            Item::query()->insert($chunk);
        }

        // 2. the new ids, by code (unique index; every code was just written by this transaction).
        /** @var array<string, int> $ids */
        $ids = [];
        foreach (array_chunk(array_column($items, 'code'), self::CHUNK) as $chunk) {
            foreach (Item::query()->whereIn('code', $chunk)->pluck('id', 'code') as $code => $id) {
                $ids[(string) $code] = (int) $id;
            }
        }

        $this->insertStoreStocks($items, $ids, $openings, $context, $now);
        $this->insertOpeningMovements($ids, $openings, $context, $now);

        return count($rows);
    }

    /**
     * Existing categories by lower-cased name; the missing ones are inserted in one batch with
     * CreateCategoryAction's defaults (trimmed name, icon `coffee`, sort 0, active; colours from the DB default).
     *
     * @param  list<array<string, string|bool|null>>  $rows
     * @return array<string, Category>
     */
    private function resolveCategories(array $rows, Carbon $now): array
    {
        $categories = $this->categoriesByName();

        $missing = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['category'] ?? ''));
            $key = ArabicTextNormalizer::key($name);
            if ($name !== '' && ! isset($categories[$key]) && ! isset($missing[$key])) {
                $missing[$key] = ['name' => $name, 'icon' => 'coffee', 'sort_order' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        if ($missing === []) {
            return $categories;
        }

        foreach (array_chunk(array_values($missing), self::CHUNK) as $chunk) {
            Category::query()->insert($chunk);
        }

        return $this->categoriesByName();
    }

    /**
     * @return array<string, Category>
     */
    private function categoriesByName(): array
    {
        $categories = [];
        // Ascending ids: when two live categories share a name the newest wins, as the old per-row lookup did.
        foreach (Category::query()->orderBy('id')->get(['id', 'name']) as $category) {
            $categories[ArabicTextNormalizer::key((string) $category->name)] = $category;
        }

        return $categories;
    }

    /**
     * Auto codes (ITM-0001...) continue after the highest existing sequence (trashed items included,
     * the unique index covers them) and never reuse a code that the file itself carries. Everything is
     * resolved in memory from ONE locked read; the unique index is the last line of defence
     * (CommitImportAction maps its violation to a 422).
     *
     * @param  list<array<string, string|bool|null>>  $rows
     * @return array<int, string> row index => code
     */
    private function resolveCodes(array $rows): array
    {
        $usedCodes = [];
        $sequence = 0;
        foreach (Item::withTrashed()->where('code', 'like', 'ITM-%')->lockForUpdate()->pluck('code') as $existing) {
            $usedCodes[mb_strtolower((string) $existing)] = true;
            if (preg_match('/^ITM-(\d+)$/i', (string) $existing, $m) === 1) {
                $sequence = max($sequence, (int) $m[1]);
            }
        }
        foreach ($rows as $row) {
            if (($row['code'] ?? '') !== '') {
                $usedCodes[mb_strtolower((string) $row['code'])] = true;
            }
        }

        $codes = [];
        foreach ($rows as $index => $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                do {
                    $sequence++;
                    $code = 'ITM-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                } while (isset($usedCodes[mb_strtolower($code)]));
                $usedCodes[mb_strtolower($code)] = true;
            }
            $codes[$index] = $code;
        }

        return $codes;
    }

    /**
     * BE-04 grid (StoreStockInitializer::seedItem): one row per store for every new item, quantity 0
     * except the opening quantity in the active store (StockService::addStock's firstOrCreate + increment,
     * collapsed into the insert because the row cannot exist yet).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, int>  $ids
     * @param  array<string, array{quantity: string, unit_cost: string}>  $openings
     */
    private function insertStoreStocks(array $items, array $ids, array $openings, ImportContext $context, Carbon $now): void
    {
        $storeIds = Store::query()->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        if ($context->storeId !== null && $openings !== [] && ! in_array($context->storeId, $storeIds, true)) {
            $storeIds[] = $context->storeId;
        }

        $batch = [];
        foreach ($items as $item) {
            $code = (string) $item['code'];
            foreach ($storeIds as $storeId) {
                $batch[] = [
                    'store_id' => $storeId,
                    'item_id' => $ids[$code],
                    'quantity' => $storeId === $context->storeId && isset($openings[$code]) ? $openings[$code]['quantity'] : '0.000',
                    'min_stock' => $item['min_stock_level'],
                    'custom_selling_price' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) === self::CHUNK * 2) {
                    StoreStock::query()->insert($batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            StoreStock::query()->insert($batch);
        }
    }

    /**
     * One `opening_balance_in` movement per item with opening stock, exactly as StockService::addStock
     * records it (stock 0 -> quantity, source = the item, document OPEN-<code>).
     *
     * @param  array<string, int>  $ids
     * @param  array<string, array{quantity: string, unit_cost: string}>  $openings
     */
    private function insertOpeningMovements(array $ids, array $openings, ImportContext $context, Carbon $now): void
    {
        if ($openings === [] || $context->storeId === null) {
            return;
        }

        $notes = (string) __('import.opening_stock_note');

        $movements = [];
        foreach ($openings as $code => $opening) {
            $movements[] = [
                'item_id' => $ids[(string) $code],
                'store_id' => $context->storeId,
                'movement_type' => self::OPENING_MOVEMENT,
                'quantity' => $opening['quantity'],
                'stock_before' => '0.000',
                'stock_after' => $opening['quantity'],
                'unit_cost' => $opening['unit_cost'],
                'source_type' => Item::class,
                'source_id' => $ids[(string) $code],
                'document_number' => 'OPEN-'.$code,
                // StockService::addStock's actor: the authenticated user, i.e. the importing user here.
                'user_id' => $context->userId,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($movements, self::CHUNK) as $chunk) {
            StockMovement::query()->insert($chunk);
        }
    }
}
