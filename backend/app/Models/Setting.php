<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Setting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getCacheKey(): string
    {
        $tenantId = function_exists('tenant') && tenant('id') ? (string) tenant('id') : 'central';

        return "app_settings_{$tenantId}";
    }

    public static function allCached(): array
    {
        try {
            return Cache::rememberForever(self::getCacheKey(), function () {
                return static::pluck('value', 'key')->toArray();
            });
        } catch (\Throwable) {
            return static::pluck('value', 'key')->toArray();
        }
    }

    public static function get(string $key, $default = null): ?string
    {
        try {
            $all = static::allCached();

            return array_key_exists($key, $all) ? (string) $all[$key] : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, $value): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Invalidate only once the write is visible to other connections: forgetting inside an open
        // transaction lets a concurrent read re-cache the stale pre-commit value. On rollback the
        // cache is dropped too, since a read inside the transaction may have cached uncommitted data.
        $cacheKey = self::getCacheKey();
        $forget = static function () use ($cacheKey, $key): void {
            Cache::forget($cacheKey);
            Cache::forget("app_setting_{$key}");
        };
        $connection = $setting->getConnection();
        $connection->afterCommit($forget);
        $connection->afterRollBack($forget);

        return $setting;
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        $val = static::get($key, $default ? '1' : '0');

        return in_array($val, ['1', 'true', 'on', 'yes', true, 1], true);
    }

    /**
     * Drop the cached dictionary after the surrounding transaction commits (immediately when none is open).
     */
    public static function clearCache(): void
    {
        $cacheKey = self::getCacheKey();
        $forget = static function () use ($cacheKey): void {
            Cache::forget($cacheKey);
        };
        $connection = (new static)->getConnection();
        $connection->afterCommit($forget);
        $connection->afterRollBack($forget);
    }
}
