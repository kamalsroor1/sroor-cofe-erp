<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\DTOs\Suppliers\SupplierDTO;
use App\Models\OpeningBalance;
use App\Models\Supplier;
use App\Services\SupplierBalanceService;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSupplierAction
{
    public function __construct(
        private readonly SupplierBalanceService $balances,
        private readonly TenantClock $clock,
    ) {}

    /**
     * Create supplier inside DB transaction. A non-zero opening balance is stored as an
     * OpeningBalance entry (like the PRD-06 import) and the balance is recalculated through
     * SupplierBalanceService: writing current_balance directly would be dropped by the next updateBalance().
     */
    public function execute(SupplierDTO $dto): Supplier
    {
        return DB::transaction(function () use ($dto) {
            $supplier = Supplier::create([
                'name' => $dto->name,
                'company_name' => $dto->company_name,
                'phone' => $dto->phone,
                'address' => $dto->address,
                'current_balance' => '0.000',
                'is_active' => $dto->is_active,
                'notes' => $dto->notes,
            ]);

            $opening = $this->openingAmount($dto->opening_balance);
            if (bccomp($opening, '0.000', 3) !== 0) {
                OpeningBalance::query()->create([
                    'party_type' => OpeningBalance::PARTY_SUPPLIER,
                    'party_id' => $supplier->id,
                    'amount' => $opening,
                    'balance_date' => $this->clock->businessDate(),
                    'notes' => __('import.opening_balance_note_manual'),
                    'user_id' => Auth::id(),
                ]);
                $this->balances->updateBalance((int) $supplier->id);
                $supplier->refresh();
            }

            return $supplier;
        });
    }

    private function openingAmount(string $value): string
    {
        if (preg_match('/^-?\d+(\.\d+)?$/', trim($value)) !== 1) {
            throw ValidationException::withMessages(['opening_balance' => __('validation.numeric', ['attribute' => 'opening_balance'])]);
        }

        return bcadd(trim($value), '0', 3);
    }
}
