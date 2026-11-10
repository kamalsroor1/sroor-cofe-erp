<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use Illuminate\Database\Eloquent\Model;

/**
 * PRD-06: duplicate detection shared by customers and suppliers (by phone, blank phone never duplicates).
 */
trait ResolvesPartyDuplicates
{
    protected function duplicateKey(array $data): ?string
    {
        $phone = (string) ($data['phone'] ?? '');

        return $phone === '' ? null : $phone;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $phones
     * @return array<string, true>
     */
    private function existingPhones(string $model, array $phones): array
    {
        $existing = [];
        foreach (array_chunk($phones, 500) as $chunk) {
            foreach ($model::query()->whereIn('phone', $chunk)->pluck('phone') as $phone) {
                $existing[(string) $phone] = true;
            }
        }

        return $existing;
    }
}
