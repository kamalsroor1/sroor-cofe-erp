<?php

declare(strict_types=1);

namespace App\Enums\Billing\Concerns;

use BackedEnum;

/**
 * Contract shared by every App\Enums\Billing enum (implemented via HasTranslatedLabel).
 */
interface BillingEnum extends BackedEnum
{
    public static function translationGroup(): string;

    public function translationKey(): string;

    public function label(?string $locale = null): string;

    /**
     * @return list<string>
     */
    public static function values(): array;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array;
}
