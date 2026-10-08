<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Central DB: one gap-free counter per (key, period) (`billing_sequences`, ENTI-1.6).
 *
 * Never increment it directly: App\Services\Billing\BillingSequenceService locks the row
 * and increments it inside the caller's transaction.
 *
 * @property int $id
 * @property string $key
 * @property string $period '' when the counter has no period
 * @property int $last_value last number handed out (0 = none yet)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BillingSequence extends Model
{
    use UsesCentralConnection;

    protected $table = 'billing_sequences';

    protected $fillable = [
        'key',
        'period',
        'last_value',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'period' => '',
        'last_value' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_value' => 'integer',
        ];
    }
}
