<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Items\CreateItemAction;
use App\DTOs\Items\ItemDTO;
use App\Models\Category;
use App\Models\Item;
use App\Services\Import\ImportContext;
use App\Services\Import\NumberNormalizer;
use App\Services\Settings\TenantSettings;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * PRD-06: items. An existing code is a duplicate (skipped, never updated). The category is found
 * by name or created. Opening stock goes to the active store through StockService::addStock as an
 * `opening_balance_in` movement; the item's weighted average cost starts at the opening cost,
 * exactly what a first purchase of a zero-stock item would set.
 */
final class ItemsImportHandler extends AbstractImportHandler
{
    public const OPENING_MOVEMENT = 'opening_balance_in';

    public function __construct(
        private readonly CreateItemAction $createItem,
        private readonly CreateCategoryAction $createCategory,
        private readonly StockService $stock,
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
            'code' => $this->requireText($raw, 'code', 100, $errors, false),
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
        /** @var array<string, Category> $categories */
        $categories = [];
        foreach (Category::query()->get() as $category) {
            $categories[mb_strtolower($category->name)] = $category;
        }

        // Auto codes (ITM-0001...) continue after the highest existing sequence (trashed items included,
        // the unique index covers them) and never reuse a code that the file itself carries. Everything
        // is resolved in memory from ONE locked read; the unique index is the last line of defence
        // (CommitImportAction maps its violation to a 422).
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

        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                do {
                    $sequence++;
                    $code = 'ITM-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                } while (isset($usedCodes[mb_strtolower($code)]));
                $usedCodes[mb_strtolower($code)] = true;
            }

            $categoryName = (string) ($row['category'] ?? '');
            $category = null;
            if ($categoryName !== '') {
                $category = $categories[mb_strtolower($categoryName)] ??= $this->createCategory->execute(['name' => $categoryName]);
            }

            $item = $this->createItem->execute(ItemDTO::fromArray([
                'name' => $row['name'],
                'code' => $code,
                'category' => $category?->name,
                'category_id' => $category?->id,
                'unit' => $row['unit'],
                'cost_price' => $row['cost_price'],
                'min_selling_price' => $row['min_selling_price'],
                'selling_price' => $row['selling_price'],
                'min_stock_level' => $row['min_stock_level'],
                'is_weighted' => $row['is_weighted'],
            ]));

            $this->postOpeningStock($item, $row, $context);
        }

        return count($rows);
    }

    /**
     * @param  array<string, string|bool|null>  $row
     */
    private function postOpeningStock(Item $item, array $row, ImportContext $context): void
    {
        $quantity = (string) $row['opening_quantity'];
        if (bccomp($quantity, '0.000', 3) <= 0 || $context->storeId === null) {
            return;
        }

        $cost = $row['opening_cost'] !== null && $row['opening_cost'] !== '' ? (string) $row['opening_cost'] : (string) $item->cost_price;

        if (bccomp($cost, '0.000', 3) > 0) {
            // Valid ONLY because the item was created a moment ago (stock 0, no history): there is
            // nothing to average with, so the opening cost becomes the WAC outright. This mirrors
            // PurchaseService's first purchase of a zero-stock item. Never do this for an existing item.
            $update = ['weighted_avg_cost' => $cost];
            if (bccomp((string) $item->cost_price, '0.000', 3) === 0) {
                $update['cost_price'] = $cost;
            }
            Item::query()->whereKey($item->id)->update($update);
        }

        $this->stock->addStock(
            item: $item,
            quantity: $quantity,
            unitCost: $cost,
            source: $item,
            documentNumber: 'OPEN-'.$item->code,
            movementType: self::OPENING_MOVEMENT,
            notes: __('import.opening_stock_note'),
            storeId: $context->storeId,
            actorId: $context->userId,
        );
    }
}
