<?php

declare(strict_types=1);

namespace App\Enums\Billing\Concerns;

/**
 * Translated labels for SaaS billing enums.
 *
 * Every case maps to `lang/{ar,en}/billing.php` → `<translationGroup()>.<value>`.
 * Only for string-backed enums.
 */
trait HasTranslatedLabel
{
    /**
     * The group inside lang/{locale}/billing.php holding this enum's labels.
     */
    abstract public static function translationGroup(): string;

    public function translationKey(): string
    {
        return 'billing.'.static::translationGroup().'.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) __($this->translationKey(), [], $locale);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Value/label pairs for selects and API payloads, in declaration order.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
