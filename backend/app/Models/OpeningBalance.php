<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Opening balance of a customer or supplier (PRD-06). It is a statement entry and part of the
 * recalculated balance, so it survives CustomerBalanceService / SupplierBalanceService::updateBalance().
 *
 * @property int $id
 * @property string $party_type customer|supplier
 * @property int $party_id
 * @property string $amount signed; positive = customer owes us / we owe the supplier
 * @property Carbon $balance_date
 * @property string|null $notes
 * @property int|null $user_id
 */
class OpeningBalance extends Model
{
    public const PARTY_CUSTOMER = 'customer';

    public const PARTY_SUPPLIER = 'supplier';

    protected $fillable = [
        'party_type',
        'party_id',
        'amount',
        'balance_date',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'balance_date' => 'date',
        ];
    }

    /**
     * Sum of the signed opening balances of one party, as a bcmath string.
     */
    public static function totalFor(string $partyType, int $partyId): string
    {
        $total = '0.000';
        foreach (self::query()->where('party_type', $partyType)->where('party_id', $partyId)->pluck('amount') as $amount) {
            $total = bcadd($total, (string) $amount, 3);
        }

        return $total;
    }
}
