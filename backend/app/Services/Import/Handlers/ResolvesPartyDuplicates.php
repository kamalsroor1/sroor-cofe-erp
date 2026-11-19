<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Services\Import\ArabicTextNormalizer;
use App\Services\Import\NumberNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * PRD-06: duplicate detection shared by customers and suppliers: by phone (normalized, IMP-01:
 * `+2010...` = `010...`). IMP-01: a row WITHOUT a phone is the same party as a live one with the
 * same name (after Arabic normalization), so re-uploading the file adds nothing the second time.
 */
trait ResolvesPartyDuplicates
{
    protected function duplicateKey(array $data): string
    {
        $phone = (string) ($data['phone'] ?? '');
        if ($phone !== '') {
            return 'phone:'.$phone;
        }

        return 'name:'.ArabicTextNormalizer::key((string) ($data['name'] ?? ''));
    }

    /**
     * IMP-01: an existing party may have been saved before phones were normalized (`+2010...`,
     * `2010...`, `002010...`, `010 1234 5678`, Arabic digits). Every stored phone is therefore run
     * through the SAME normalizer as the file, so a re-import of a known party is a duplicate and
     * never a second record with a second opening balance. One pass covers phones and names.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>  $keys
     * @return array<string, true>
     */
    private function existingParties(string $model, array $keys): array
    {
        $wanted = array_fill_keys($keys, true);
        if ($wanted === []) {
            return [];
        }

        $existing = [];
        foreach ($model::query()->select(['id', 'name', 'phone'])->toBase()->lazyById(2000) as $party) {
            $phone = NumberNormalizer::phone((string) ($party->phone ?? ''));
            if ($phone !== '' && isset($wanted['phone:'.$phone])) {
                $existing['phone:'.$phone] = true;
            }

            $name = 'name:'.ArabicTextNormalizer::key((string) $party->name);
            if (isset($wanted[$name])) {
                $existing[$name] = true;
            }
        }

        return $existing;
    }
}
