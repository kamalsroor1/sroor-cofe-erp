<?php

declare(strict_types=1);

namespace App\Services\Pos;

/**
 * POSB-2: pure decoder for scale (label-printing balance) barcodes. No DB, no I/O.
 *
 * Layout: <prefix><PLU: pluLength digits><value: valueLength digits>[check digit]
 *  - weight label: quantity (kg) = value / weightDivisor  (1000 → grams to kg)
 *  - price label:  price         = value / priceDivisor   (100  → piasters to pounds)
 *  - check digit:  GS1 mod-10 over every preceding digit (the EAN-13 algorithm).
 *
 * Returns null when the code is not a scale label for this store (disabled, unknown prefix,
 * non-digits or a different length) so the caller falls back to a normal barcode lookup.
 * Throws InvalidScaleBarcodeException when it is a scale label that fails the check digit.
 *
 * The same contract is pinned by tests/Fixtures/scale-barcodes.json for the frontend parser.
 */
final class ScaleBarcodeParser
{
    public function parse(string $barcode, ScaleBarcodeConfig $config): ?ScaleBarcodeResult
    {
        $code = trim($barcode);

        if (! $config->enabled || $code === '' || ! ctype_digit($code)) {
            return null;
        }

        $prefix = $this->matchPrefix($code, $config->prefixes);
        if ($prefix === null) {
            return null;
        }

        $prefixLength = strlen($prefix);
        $expectedLength = $prefixLength + $config->pluLength + $config->valueLength + ($config->checkDigit ? 1 : 0);
        if (strlen($code) !== $expectedLength) {
            return null;
        }

        if ($config->checkDigit) {
            $body = substr($code, 0, -1);
            if ((string) self::checkDigit($body) !== substr($code, -1)) {
                throw InvalidScaleBarcodeException::invalidCheckDigit($code);
            }
        }

        $plu = substr($code, $prefixLength, $config->pluLength);
        $rawValue = substr($code, $prefixLength + $config->pluLength, $config->valueLength);
        // ltrim keeps bcmath input canonical; an all-zero field becomes "0".
        $value = ltrim($rawValue, '0') === '' ? '0' : ltrim($rawValue, '0');

        if ($config->valueType === ScaleBarcodeConfig::VALUE_TYPE_PRICE) {
            return new ScaleBarcodeResult(
                prefix: $prefix,
                plu: $plu,
                valueType: ScaleBarcodeConfig::VALUE_TYPE_PRICE,
                quantity: null,
                price: bcdiv($value, (string) max(1, $config->priceDivisor), 3),
            );
        }

        return new ScaleBarcodeResult(
            prefix: $prefix,
            plu: $plu,
            valueType: ScaleBarcodeConfig::VALUE_TYPE_WEIGHT,
            quantity: bcdiv($value, (string) max(1, $config->weightDivisor), 3),
            price: null,
        );
    }

    /**
     * GS1 mod-10 check digit of a digit string (weights 3,1,3,… from the rightmost digit).
     */
    public static function checkDigit(string $digits): int
    {
        $sum = 0;
        $length = strlen($digits);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];
            $sum += $i % 2 === 0 ? $digit * 3 : $digit;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Longest configured prefix the code starts with (so "2" and "21" can coexist).
     *
     * @param  list<string>  $prefixes
     */
    private function matchPrefix(string $code, array $prefixes): ?string
    {
        $match = null;

        foreach ($prefixes as $prefix) {
            if ($prefix === '' || ! str_starts_with($code, $prefix)) {
                continue;
            }

            if ($match === null || strlen($prefix) > strlen($match)) {
                $match = $prefix;
            }
        }

        return $match;
    }
}
