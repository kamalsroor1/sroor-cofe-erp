<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Exceptions\DiscountCapExceededException;
use App\Models\Customer;
use App\Models\Item;
use App\Models\StorePosSetting;
use App\Models\User;
use App\Support\Money\Decimal;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;

/**
 * POS-07: discounts on a sale (a percent or an amount, on a line and/or on the whole invoice).
 *
 *  - resolveLineDiscounts(): folds a line's `discount_percent` into `discount_amount` using the same
 *    price the server will charge (submitted price, else the tier list price).
 *  - preflight(): BEFORE the invoice transaction, checks the total discount (line discounts + invoice
 *    discount, as a % of the gross) against the store's `max_discount_percent`. Over the cap needs the
 *    acting user's `pos.discount.override` or a manager PIN (SalePriceGuard, limiter hits survive); it
 *    also applies the BE-08 price floor after the invoice discount is spread over the lines.
 *  - assertWithinCap(): the same cap on the exact in-transaction totals (backstop, uses the approver
 *    resolved by preflight).
 *
 * A guard built without an actor (commands, jobs) enforces nothing.
 */
final class SaleDiscountGuard
{
    public function __construct(
        private readonly ?User $actor,
        private readonly SalePriceGuard $priceGuard,
    ) {}

    /**
     * What the invoice-level discount amounts to: a percent of the subtotal or a fixed amount, never above the subtotal.
     */
    public static function invoiceDiscountAmount(string $subtotal, string $type, string $value): string
    {
        $amount = $type === 'percentage' ? Decimal::percent($subtotal, $value) : Decimal::normalize($value);

        return bccomp($amount, $subtotal, 3) > 0 ? Decimal::normalize($subtotal) : $amount;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function resolveLineDiscounts(Customer $customer, ?int $storeId, array $lines): array
    {
        $items = $this->loadItems($lines);

        foreach ($lines as $index => $line) {
            $percent = isset($line['discount_percent']) && $line['discount_percent'] !== '' ? (string) $line['discount_percent'] : null;
            unset($lines[$index]['discount_percent']);

            $item = $items[(int) ($line['item_id'] ?? 0)] ?? null;
            // A percent wins over an amount, and 0 % never wipes an amount that was sent.
            if ($percent === null || bccomp(Decimal::normalize($percent), '0', 3) <= 0 || ! $item instanceof Item) {
                continue;
            }

            $gross = $this->grossOf($item, $customer, $storeId, $line);
            $lines[$index]['discount_amount'] = Decimal::percent($gross, Decimal::normalize($percent));
            unset($lines[$index]['discount']);
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  after resolveLineDiscounts()
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function preflight(Customer $customer, ?int $storeId, array $lines, string $discountType, string $discountValue): void
    {
        if (! $this->actor instanceof User) {
            return;
        }

        $items = $this->loadItems($lines);
        $gross = '0.000';
        $lineDiscounts = '0.000';
        $rows = [];

        foreach ($lines as $index => $line) {
            $item = $items[(int) ($line['item_id'] ?? 0)] ?? null;
            if (! $item instanceof Item) {
                continue;
            }

            $lineGross = $this->grossOf($item, $customer, $storeId, $line);
            $discount = Decimal::normalize((string) ($line['discount_amount'] ?? '0.000'));

            // Malformed discounts are reported by SalePriceGuard::evaluate() inside the transaction.
            if (bccomp($discount, '0', 3) < 0 || bccomp($discount, $lineGross, 3) > 0) {
                continue;
            }

            $gross = bcadd($gross, $lineGross, 3);
            $lineDiscounts = bcadd($lineDiscounts, $discount, 3);
            $rows[] = [
                'index' => (int) $index,
                'net' => bcsub($lineGross, $discount, 3),
                'floor' => Decimal::mul((string) ($line['quantity'] ?? '0'), (string) ($item->min_selling_price ?? '0.000')),
                'has_floor' => bccomp((string) ($item->min_selling_price ?? '0.000'), '0', 3) > 0,
            ];
        }

        $subtotal = bcsub($gross, $lineDiscounts, 3);
        $invoiceDiscount = self::invoiceDiscountAmount($subtotal, $discountType, Decimal::normalize($discountValue));

        $this->checkCap($storeId, $gross, bcadd($lineDiscounts, $invoiceDiscount, 3));

        if (bccomp($invoiceDiscount, '0', 3) <= 0 || bccomp($subtotal, '0', 3) <= 0) {
            return;
        }

        // BE-08 floor after the invoice discount: it is spread over the lines in proportion to their
        // net, so a line is below its floor when net x (subtotal - discount) < floor x subtotal (exact).
        $afterDiscount = bcsub($subtotal, $invoiceDiscount, 3);
        foreach ($rows as $row) {
            if ($row['has_floor'] && bccomp(bcmul($row['net'], $afterDiscount, 6), bcmul($row['floor'], $subtotal, 6), 6) < 0) {
                $this->priceGuard->requireBelowMinApproval($row['index'], $storeId);

                return;
            }
        }
    }

    /**
     * @throws DiscountCapExceededException
     */
    public function assertWithinCap(?int $storeId, string $gross, string $totalDiscount): void
    {
        if (! $this->actor instanceof User || $this->isWithinCap($storeId, $gross, $totalDiscount)) {
            return;
        }

        if ($this->priceGuard->discountApproverId() === null) {
            throw DiscountCapExceededException::forCap($this->capFor($storeId));
        }
    }

    private function checkCap(?int $storeId, string $gross, string $totalDiscount): void
    {
        if ($this->isWithinCap($storeId, $gross, $totalDiscount)) {
            return;
        }

        $this->priceGuard->approveDiscount($this->capFor($storeId), $storeId);
    }

    /** total x 100 <= cap x gross, exact (scale 6 products of scale-3 numbers). */
    private function isWithinCap(?int $storeId, string $gross, string $totalDiscount): bool
    {
        if (bccomp($totalDiscount, '0', 3) <= 0) {
            return true;
        }

        return bccomp(bcmul($totalDiscount, '100', 6), bcmul($this->capFor($storeId), $gross, 6), 6) <= 0;
    }

    private function capFor(?int $storeId): string
    {
        return $storeId === null
            ? StorePosSetting::DEFAULT_MAX_DISCOUNT_PERCENT
            : StorePosSetting::forStore($storeId)->effectiveMaxDiscountPercent();
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function grossOf(Item $item, Customer $customer, ?int $storeId, array $line): string
    {
        $submitted = isset($line['unit_price']) ? trim((string) $line['unit_price']) : '';
        $unitPrice = $submitted === '' ? $this->priceGuard->listPrice($item, $customer, $storeId)['price'] : Decimal::normalize($submitted);

        return Decimal::mul((string) ($line['quantity'] ?? '0'), $unitPrice);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, Item>
     */
    private function loadItems(array $lines): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $line): int => (int) ($line['item_id'] ?? 0), $lines)));

        return Item::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }
}
