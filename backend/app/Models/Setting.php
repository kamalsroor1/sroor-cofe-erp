<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'value',
    ];

    public const CACHE_PREFIX = 'app_settings_all';

    public static function allCached(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_PREFIX, function () {
                return static::pluck('value', 'key')->toArray();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    public static function get(string $key, $default = null): ?string
    {
        try {
            $all = static::allCached();
            return $all[$key] ?? $default;
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

        Cache::forget(self::CACHE_PREFIX);
        Cache::forget("app_setting_{$key}");

        return $setting;
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        $val = static::get($key, $default ? '1' : '0');
        return in_array($val, ['1', 'true', 'on', 'yes', true, 1], true);
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX);
    }
}
