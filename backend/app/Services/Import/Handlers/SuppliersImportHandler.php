<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Models\OpeningBalance;
use App\Models\Supplier;
use App\Services\Import\ImportContext;
use App\Services\Import\NumberNormalizer;
use App\Services\SupplierBalanceService;

/**
 * PRD-06: suppliers (duplicate by phone). A non-zero opening_balance becomes an OpeningBalance
 * statement entry (positive = we owe the supplier, negative = the supplier owes us) and the
 * stored balance is recalculated through SupplierBalanceService, never written raw.
 */
final class SuppliersImportHandler extends AbstractImportHandler
{
    use ResolvesPartyDuplicates;

    public function __construct(private readonly SupplierBalanceService $balances) {}

    public function key(): string
    {
        return 'suppliers';
    }

    public function columns(): array
    {
        return ['name', 'company_name', 'phone', 'address', 'opening_balance', 'notes'];
    }

    protected function requiredColumns(): array
    {
        return ['name'];
    }

    protected function normalize(array $raw, array &$errors): array
    {
        $phone = $this->requireText($raw, 'phone', 50, $errors, false);

        return [
            'name' => $this->requireText($raw, 'name', 255, $errors),
            'company_name' => $this->requireText($raw, 'company_name', 255, $errors, false),
            'phone' => $phone === null ? null : NumberNormalizer::phone($phone),
            'address' => $this->requireText($raw, 'address', 500, $errors, false),
            'opening_balance' => $this->number($raw, 'opening_balance', $errors, allowNegative: true, default: '0.000'),
            'notes' => $this->requireText($raw, 'notes', 1000, $errors, false),
        ];
    }

    protected function existingKeys(array $keys): array
    {
        return $this->existingPhones(Supplier::class, $keys);
    }

    public function write(array $rows, ImportContext $context): int
    {
        foreach ($rows as $row) {
            $supplier = Supplier::query()->create([
                'name' => (string) $row['name'],
                'company_name' => $row['company_name'],
                'phone' => $row['phone'],
                'address' => $row['address'],
                'current_balance' => '0.000',
                'is_active' => true,
                'notes' => $row['notes'],
            ]);

            $opening = (string) $row['opening_balance'];
            if (bccomp($opening, '0.000', 3) !== 0) {
                OpeningBalance::query()->create([
                    'party_type' => OpeningBalance::PARTY_SUPPLIER,
                    'party_id' => $supplier->id,
                    'amount' => $opening,
                    'balance_date' => $context->businessDate,
                    'notes' => __('import.opening_balance_note'),
                    'user_id' => $context->userId,
                ]);
                $this->balances->updateBalance((int) $supplier->id);
            }
        }

        return count($rows);
    }
}
