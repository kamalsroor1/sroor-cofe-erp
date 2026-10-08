<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Branding\PlatformBranding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One platform-wide (CENTRAL) setting, BRND-1. Lives in `platform_settings` on the
 * central connection, even while a tenant is initialized.
 *
 * Read it only through App\Services\Branding\PlatformBranding (cached, typed, with the
 * config/branding.php fallback). Every save/delete invalidates that cache, so a rename
 * reaches tenant requests on the next request.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlatformSetting extends Model
{
    protected $table = 'platform_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'updated_by' => 'integer',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    protected static function booted(): void
    {
        static::saved(static fn (self $setting) => $setting->invalidateBrandingCache());
        static::deleted(static fn (self $setting) => $setting->invalidateBrandingCache());
    }

    /**
     * Bump now (the next read cannot see the old cached value) and again after the
     * surrounding transaction commits (a read that re-cached the pre-commit value
     * in between is discarded too).
     */
    private function invalidateBrandingCache(): void
    {
        PlatformBranding::forget();

        $connection = $this->getConnection();
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(static fn () => PlatformBranding::forget());
        }
    }
}
