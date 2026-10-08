<?php

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Central DB: registry of plan feature keys.
 */
class PlanFeature extends Model
{
    use HasFactory;
    use UsesCentralConnection;

    protected $fillable = [
        'key',
        'name',
        'description',
        'module',
        'type',
        'default_value',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * تجميع الفيتشرز حسب الموديول (sales, inventory, reports, finance, system, limits)
     */
    public static function groupedByModule(): array
    {
        return static::orderBy('sort_order', 'asc')
            ->get()
            ->groupBy('module')
            ->toArray();
    }
}
