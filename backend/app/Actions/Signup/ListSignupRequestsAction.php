<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\Filters\Signup\SearchFilter;
use App\Filters\Signup\StatusFilter;
use App\Models\SignupRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;

/**
 * PRD-05: the console list, newest first, filtered through the Pipeline.
 */
final class ListSignupRequestsAction
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * @return LengthAwarePaginator<int, SignupRequest>
     */
    public function execute(Request $request): LengthAwarePaginator
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', self::DEFAULT_PER_PAGE)));

        /** @var Builder<SignupRequest> $query */
        $query = app(Pipeline::class)
            ->send(SignupRequest::query())
            ->through([StatusFilter::class, SearchFilter::class])
            ->thenReturn();

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}
