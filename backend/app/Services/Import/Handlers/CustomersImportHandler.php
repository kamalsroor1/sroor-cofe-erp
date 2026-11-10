<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Models\Customer;
use App\Models\OpeningBalance;
use App\Services\CustomerBalanceService;
use App\Services\Import\ImportContext;
use App\Services\Import\NumberNormalizer;

/**
 * PRD-06: customers (duplicate by phone). A non-zero opening_balance becomes an OpeningBalance
 * statement entry (positive = the customer owes us, negative = we owe the customer) and the
 * stored balance is recalculated through CustomerBalanceService, never written raw.
 */
final class CustomersImportHandler extends AbstractImportHandler
{
    use ResolvesPartyDuplicates;

    public function __construct(private readonly CustomerBalanceService $balances) {}

    public function key(): string
    {
        return 'customers';
    }

    public function columns(): array
    {
        return ['name', 'phone', 'address', 'tax_number', 'price_tier', 'opening_balance', 'notes'];
    }

    protected function requiredColumns(): array
    {
        return ['name'];
    }

    protected function normalize(array $raw, array &$errors): array
    {
        $tier = mb_strtolower(trim($raw['price_tier']));
        $tier = match ($tier) {
            '' , 'retail', 'تجزئة', 'قطاعي' => 'retail',
            'wholesale', 'جملة' => 'wholesale',
            default => null,
        };
        if ($tier === null) {
            $errors[] = (string) __('import.errors.invalid_price_tier', ['field' => $this->label('price_tier')]);
        }

        return [
            'name' => $this->requireText($raw, 'name', 255, $errors),
            'phone' => $this->phone($raw, $errors),
            'address' => $this->requireText($raw, 'address', 500, $errors, false),
            'tax_number' => $this->requireText($raw, 'tax_number', 50, $errors, false),
            'price_tier' => $tier,
            'opening_balance' => $this->number($raw, 'opening_balance', $errors, allowNegative: true, default: '0.000'),
            'notes' => $this->requireText($raw, 'notes', 1000, $errors, false),
        ];
    }

    protected function existingKeys(array $keys): array
    {
        return $this->existingPhones(Customer::class, $keys);
    }

    public function write(array $rows, ImportContext $context): int
    {
        foreach ($rows as $row) {
            $customer = Customer::query()->create([
                'name' => (string) $row['name'],
                'phone' => $row['phone'],
                'address' => $row['address'],
                'tax_number' => $row['tax_number'],
                'price_tier' => (string) $row['price_tier'],
                'current_balance' => '0.000',
                'is_active' => true,
                'notes' => $row['notes'],
            ]);

            $opening = (string) $row['opening_balance'];
            if (bccomp($opening, '0.000', 3) !== 0) {
                OpeningBalance::query()->create([
                    'party_type' => OpeningBalance::PARTY_CUSTOMER,
                    'party_id' => $customer->id,
                    'amount' => $opening,
                    'balance_date' => $context->businessDate,
                    'notes' => __('import.opening_balance_note'),
                    'user_id' => $context->userId,
                ]);
                $this->balances->updateBalance((int) $customer->id);
            }
        }

        return count($rows);
    }

    /**
     * @param  array<string, string>  $raw
     * @param  list<string>  $errors
     */
    private function phone(array $raw, array &$errors): ?string
    {
        $value = $this->requireText($raw, 'phone', 50, $errors, false);

        return $value === null ? null : NumberNormalizer::phone($value);
    }
}
