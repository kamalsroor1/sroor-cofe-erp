<?php

declare(strict_types=1);

namespace App\Exceptions\Stock;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A stock decrement would take an item (or a store's shelf) below zero while negative
 * stock is not allowed. Renders as HTTP 422 with a translated message; the surrounding
 * DB::transaction rolls everything back.
 */
final class InsufficientStockException extends HttpException
{
    public const ERROR_CODE = 'inventory.insufficient_stock';

    private function __construct(string $message)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, $message);
    }

    public static function forItem(string $item, string $available): self
    {
        return new self((string) __('inventory.insufficient_stock_item', [
            'item' => $item,
            'available' => $available,
        ]));
    }

    public static function forStore(string $item, string $store, string $available, string $unit): self
    {
        return new self((string) __('inventory.insufficient_stock_store', [
            'item' => $item,
            'store' => $store,
            'available' => $available,
            'unit' => $unit,
        ]));
    }
}
