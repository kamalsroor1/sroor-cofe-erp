<?php

declare(strict_types=1);

namespace App\DTOs\Treasury;

use App\Support\Money\Decimal;

/**
 * SHOP-06: one treasury transfer request (money as DECIMAL(12,3) strings).
 */
final readonly class TreasuryTransferDTO
{
    public function __construct(
        public string $fromMethod,
        public string $toMethod,
        public string $amount,
        public string $transferFee,
        public int $storeId,
        public ?string $transferDate = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public static function fromArray(array $data, int $storeId): self
    {
        $fee = $data['transfer_fee'] ?? null;
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';
        $date = $data['transfer_date'] ?? null;

        return new self(
            fromMethod: (string) $data['from_method'],
            toMethod: (string) $data['to_method'],
            amount: Decimal::normalize((string) $data['amount']),
            transferFee: $fee === null || $fee === '' ? '0.000' : Decimal::normalize((string) $fee),
            storeId: $storeId,
            transferDate: $date === null || $date === '' ? null : (string) $date,
            notes: $notes === '' ? null : $notes,
        );
    }

    /**
     * The array TreasuryService::transfer() takes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'from_method' => $this->fromMethod,
            'to_method' => $this->toMethod,
            'amount' => $this->amount,
            'transfer_fee' => $this->transferFee,
            'store_id' => $this->storeId,
            'transfer_date' => $this->transferDate,
            'notes' => $this->notes,
        ], fn ($value): bool => $value !== null);
    }
}
