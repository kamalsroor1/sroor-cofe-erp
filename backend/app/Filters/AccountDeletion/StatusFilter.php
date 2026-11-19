<?php

declare(strict_types=1);

namespace App\Filters\AccountDeletion;

use App\Enums\AccountDeletionRequestStatus;
use App\Models\AccountDeletionRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * MOB-05: `?status=pending|handled`; anything else, or `all`, = no filter.
 */
final class StatusFilter
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
        $status = $this->request->query('status');

        if (is_string($status) && in_array($status, AccountDeletionRequestStatus::values(), true)) {
            $query->where('status', $status);
        }

        return $next($query);
    }
}
