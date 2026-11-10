<?php

declare(strict_types=1);

namespace App\Exceptions\Stock;

use App\Exceptions\BusinessRuleException;

/**
 * A stock decrement would take an item (or a store's shelf) below zero while negative
 * stock is not allowed. Renders as HTTP 422 with a translated message and
 * error_code `inventory.insufficient_stock`; the surrounding DB::transaction rolls
 * everything back.
 */
final class InsufficientStockException extends BusinessRuleException
{
    public const ERROR_CODE = 'inventory.insufficient_stock';

    public static function forItem(string $item, string $available): self
    {
        return new self('inventory.insufficient_stock_item', [
            'item' => $item,
            'available' => $available,
        ]);
    }

    public static function forStore(string $item, string $store, string $available, string $unit): self
    {
        return new self('inventory.insufficient_stock_store', [
            'item' => $item,
            'store' => $store,
            'available' => $available,
            'unit' => $unit,
        ]);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
