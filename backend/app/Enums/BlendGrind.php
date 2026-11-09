<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Grind / processing level of a custom blend. The API and the SPA speak these codes.
 * Older clients (and old data) sent the Arabic label, so fromInput() maps those too.
 */
enum BlendGrind: string
{
    case Turkish = 'turkish';
    case Espresso = 'espresso';
    case FrenchPress = 'french_press';
    case Beans = 'beans';

    /** @return list<string> */
    private function legacyLabels(): array
    {
        return match ($this) {
            self::Turkish => ['تركي ناعم'],
            self::Espresso => ['إسبريسو'],
            self::FrenchPress => ['فرينش بريس'],
            self::Beans => ['حبوب بدون طحن'],
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
        return __('inventory.blend_grind.'.$this->value);
    }
}
