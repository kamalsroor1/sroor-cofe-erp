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

    public static function getCacheKey(): string
    {
        $tenantId = function_exists('tenant') && tenant('id') ? (string)tenant('id') : 'central';
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
            return array_key_exists($key, $all) ? (string)$all[$key] : $default;
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

        Cache::forget(self::getCacheKey());
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
        Cache::forget(self::getCacheKey());
    }
}
