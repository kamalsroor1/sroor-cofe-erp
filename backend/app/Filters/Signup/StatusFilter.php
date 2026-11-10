<?php

declare(strict_types=1);

namespace App\Filters\Signup;

use App\Enums\SignupRequestStatus;
use App\Models\SignupRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * PRD-05: `?status=pending|approved|rejected` (anything else, or `all`, = no filter).
 */
final class StatusFilter
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  Builder<SignupRequest>  $query
     * @param  Closure(Builder<SignupRequest>): Builder<SignupRequest>  $next
     * @return Builder<SignupRequest>
     */
    public function handle(Builder $query, Closure $next): Builder
    {
        $status = $this->request->query('status');

        if (is_string($status) && in_array($status, SignupRequestStatus::values(), true)) {
            $query->where('status', $status);
        }

        return $next($query);
    }
}
