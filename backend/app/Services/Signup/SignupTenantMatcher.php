<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Enums\TenantStatus;
use App\Http\Requests\Signup\StoreSignupRequestRequest;
use App\Models\SignupRequest;
use App\Models\Tenant;

/**
 * SELL-06 hardening (trial farming): now that every approved signup gets a trial, the operator
 * must see when a request comes from someone who already has a shop. Lists existing (central)
 * tenants whose phone or email matches the request, normalised (Egyptian phone formats, email
 * case / blanks). Informational only: approval is never refused on it.
 *
 * Operator console only (SignupRequestResource). Never exposed to a tenant or the public API.
 */
final class SignupTenantMatcher
{
    public const LIMIT = 5;

    /**
     * @return list<array{id: string, name: string, status: array{value: string, label: string}, created_at: string|null}>
     */
    public function matchesFor(SignupRequest $signup): array
    {
        $phones = $this->phoneVariants($signup->phone);
        $email = $signup->email === null ? '' : strtolower(trim($signup->email));

        if ($phones === [] && $email === '') {
            return [];
        }

        $tenants = Tenant::query()
            ->where(function ($query) use ($phones, $email): void {
                if ($phones !== []) {
                    $query->whereIn('phone', $phones);
                }
                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'status', 'created_at']);

        $matches = [];
        foreach ($tenants as $tenant) {
            if (! $tenant instanceof Tenant) {
                continue;
            }

            $status = TenantStatus::tryFrom((string) $tenant->status);

            $matches[] = [
                'id' => (string) $tenant->getKey(),
                'name' => (string) $tenant->name,
                'status' => ['value' => (string) $tenant->status, 'label' => $status?->label() ?? (string) $tenant->status],
                'created_at' => $tenant->created_at?->toIso8601String(),
            ];
        }

        return $matches;
    }

    /**
     * The local form (01xxxxxxxxx) plus the international spellings a tenant row may hold.
     *
     * @return list<string>
     */
    private function phoneVariants(?string $phone): array
    {
        if ($phone === null || trim($phone) === '') {
            return [];
        }

        $local = StoreSignupRequestRequest::normalizePhone($phone);
        if (preg_match(StoreSignupRequestRequest::PHONE_PATTERN, $local) !== 1) {
            return [$local];
        }

        $national = substr($local, 1);

        return [$local, '+20'.$national, '0020'.$national, '20'.$national];
    }
}
