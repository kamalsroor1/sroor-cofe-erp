<?php

declare(strict_types=1);

namespace App\Filters\Signup;

use App\Enums\SignupFlow;
use App\Models\SignupRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * SIGN-12: `?flow=assisted|self_signup` (anything else, or `all`, = no filter).
 */
final class FlowFilter
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
        $flow = $this->request->query('flow');

        if (is_string($flow) && in_array($flow, SignupFlow::values(), true)) {
            $query->where('signup_flow', $flow);
        }

        return $next($query);
    }
}
