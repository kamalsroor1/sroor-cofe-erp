<?php

declare(strict_types=1);

namespace App\Filters\AccountDeletion;

use App\Models\AccountDeletionRequest;
use App\Support\Database\LikePattern;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * MOB-05: `?search=` matches the name, phone, email or shop code (bound LIKE, wildcards escaped).
 */
final class SearchFilter
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  Builder<AccountDeletionRequest>  $query
     * @param  Closure(Builder<AccountDeletionRequest>): Builder<AccountDeletionRequest>  $next
     * @return Builder<AccountDeletionRequest>
     */
    public function handle(Builder $query, Closure $next): Builder
    {
        $search = $this->request->query('search');

        if (is_string($search) && trim($search) !== '') {
            $term = mb_substr(trim($search), 0, 100);
            LikePattern::whereContainsAny($query, ['contact_name', 'phone', 'email', 'shop_code'], $term);
        }

        return $next($query);
    }
}
