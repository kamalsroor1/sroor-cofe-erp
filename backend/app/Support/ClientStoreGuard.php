<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Interim guard (until STOR-2 mounts `store.active` everywhere) for a store id the CLIENT sends in
 * the body or query string (`store_id`, `from_store_id`…). ApiTokenAuth only verifies the
 * `X-Store-Id` header, so legacy readers of `$request->input('store_id')` used to trust any
 * branch id: a cashier of branch A could sell from, report on or export branch B.
 *
 * verified() returns the value only after the same access rule as the header
 * (ActiveStore::canAccess / canViewAll); anything else is a translated 403.
 *  - missing, '', '0', 0           → null (treated as "not sent", as before);
 *  - 'all'                         → 'all' when ActiveStore::canViewAll, else 403;
 *  - positive integer / digit str  → that id when ActiveStore::canAccess, else 403;
 *  - anything else (arrays, "1abc", negatives) → 403, never a silent fallback.
 */
final class ClientStoreGuard
{
    /**
     * Machine-readable code of every "store not accessible" 403 (header or client value). The SPA
     * reacts to it by forgetting its stored active store and retrying once without X-Store-Id.
     */
    public const ERROR_CODE = 'store_access_denied';

    /**
     * @param  'input'|'query'  $source
     */
    public static function verified(Request $request, string $key = 'store_id', string $source = 'input'): int|string|null
    {
        $raw = $source === 'query' ? $request->query($key) : $request->input($key);

        if ($raw === null || $raw === '' || $raw === '0' || $raw === 0) {
            return null;
        }

        $user = $request->user();
        if (! $user instanceof User) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401));
        }

        if (is_int($raw)) {
            $raw = (string) $raw;
        }

        $parsed = ActiveStore::parseHeader($raw);

        if ($parsed === ActiveStore::ALL) {
            if (! ActiveStore::canViewAll($user)) {
                self::deny();
            }

            return ActiveStore::ALL;
        }

        if (! is_int($parsed) || ! ActiveStore::canAccess($user, $parsed)) {
            self::deny();
        }

        return $parsed;
    }

    /**
     * One concrete store for endpoints that work on a single branch (writes, single-store
     * reads): the already-verified `X-Store-Id` header and the access-checked client store_id,
     * header first unless $preferClient (a client value that is an explicit branch filter).
     * `all` is never turned into store 0: when no concrete id is given but `all` is, 422 on
     * store_id. Null when neither is sent (the caller falls back to the user's own store).
     */
    public static function concrete(Request $request, bool $preferClient = false): ?int
    {
        $client = self::verified($request);
        $header = ActiveStore::parseHeader($request->header('X-Store-Id'));

        if ($preferClient && $client === ActiveStore::ALL) {
            // An explicit "every store" filter on a single-store endpoint: never silently swap
            // it for the header's store.
            self::notAStore();
        }

        $candidates = $preferClient ? [$client, $header] : [$header, $client];
        foreach ($candidates as $candidate) {
            if (is_int($candidate)) {
                return $candidate;
            }
        }

        if ($client === ActiveStore::ALL || $header === ActiveStore::ALL) {
            self::notAStore();
        }

        return null;
    }

    /**
     * concrete(), falling back to the user's own store (ActiveStore::defaultFor: default store,
     * then first assigned store, then the main store for all-store users), never to a hardcoded
     * id such as 1. A user without any usable store gets the translated 403 instead of data
     * from a branch they may not see (or, worse, unfiltered totals of every branch).
     */
    public static function concreteOrDefault(Request $request, bool $preferClient = false): int
    {
        $storeId = self::concrete($request, $preferClient);

        if ($storeId !== null) {
            return $storeId;
        }

        $user = $request->user();
        if (! $user instanceof User) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401));
        }

        $store = ActiveStore::defaultFor($user);

        return $store instanceof Store ? $store->id : self::deny();
    }

    private static function notAStore(): never
    {
        throw ValidationException::withMessages([
            'store_id' => __('validation.integer', ['attribute' => 'store_id']),
        ]);
    }

    /** Throws the same translated 403 the X-Store-Id check returns. */
    public static function deny(): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('common.store_access_denied'),
            'error_code' => self::ERROR_CODE,
        ], 403));
    }
}
