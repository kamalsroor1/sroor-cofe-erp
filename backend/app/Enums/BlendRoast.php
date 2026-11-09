<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roast / preparation degree of a custom blend. The API and the SPA speak these codes.
 * Older clients (and old data) sent the Arabic label, so fromInput() maps those too.
 */
enum BlendRoast: string
{
    case Light = 'light';
    case Medium = 'medium';
    case Dark = 'dark';
    case Double = 'double';

    /** @return list<string> */
    private function legacyLabels(): array
    {
        return match ($this) {
            self::Light => ['فاتح'],
            self::Medium => ['وسط'],
            self::Dark => ['غامق'],
            self::Double => ['محروق / دبل'],
        };
    }

    /** Accepts a code or a legacy Arabic label; null when it is neither (free text). */
    public static function fromInput(?string $value): ?self
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->value === $value || in_array($value, $case->legacyLabels(), true)) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return __('inventory.blend_roast.'.$this->value);
    }
}
