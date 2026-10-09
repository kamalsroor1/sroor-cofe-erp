<?php

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Central DB: registry of plan feature keys.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string $module
 * @property string $type
 * @property string $default_value
 * @property string|null $icon
 * @property int $sort_order
 * @property string|null $name_key
 * @property bool $is_core
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        'name_key',
        'is_core',
        'is_public',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_core' => 'boolean',
        'is_public' => 'boolean',
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
