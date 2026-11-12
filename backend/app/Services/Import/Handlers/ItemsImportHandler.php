<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\Import\ImportContext;
use App\Services\Import\NumberNormalizer;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PRD-06: items. An existing code is a duplicate (skipped, never updated). The category is found
 * by name or created. Opening stock goes to the active store as an `opening_balance_in` movement;
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
    }

    protected function normalize(array $raw, array &$errors): array
    {
        $unit = $this->requireText($raw, 'unit', 50, $errors);
        if ($unit !== null && ! in_array($unit, $this->units, true)) {
            $errors[] = (string) __('inventory.unit_not_in_tenant_list', ['unit' => $unit]);
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

        return [
            'code' => $this->requireText($raw, 'code', self::CODE_MAX, $errors, false),
            'name' => $this->requireText($raw, 'name', 255, $errors),
            'category' => $this->requireText($raw, 'category', 100, $errors, false),
            'unit' => $unit,
            'cost_price' => $this->number($raw, 'cost_price', $errors, default: '0.000'),
            'selling_price' => $this->number($raw, 'selling_price', $errors, required: true),
            // null = no price floor ('0.000'); never defaulted to the cost.
            'min_selling_price' => $this->number($raw, 'min_selling_price', $errors),
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

        $floor = $data['min_selling_price'] ?? null;
        if ($floor !== null && bccomp((string) $floor, $selling, 3) > 0) {
            $warnings[] = (string) __('import.warnings.min_above_selling', [
                'min' => $this->label('min_selling_price'),
                'selling' => $this->label('selling_price'),
            ]);
        }

        return $warnings;
    }

    protected function duplicateKey(array $data): ?string
    {
        $code = (string) ($data['code'] ?? '');

        return $code === '' ? null : mb_strtolower($code);
    }

    protected function existingKeys(array $keys): array
    {
        $existing = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            // The column expression is constant; the codes are bound parameters.
            $codes = Item::withTrashed()->whereIn(DB::raw('LOWER(code)'), $chunk)->pluck('code');
            foreach ($codes as $code) {
                $existing[mb_strtolower((string) $code)] = true;
            }
        }

        return $existing;
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
            $category = $categoryName === '' ? null : $categories[mb_strtolower($categoryName)];

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
            $key = mb_strtolower($name);
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
            $categories[mb_strtolower($category->name)] = $category;
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
