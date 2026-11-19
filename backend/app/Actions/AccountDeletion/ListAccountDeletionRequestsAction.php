<?php

declare(strict_types=1);

namespace App\Actions\AccountDeletion;

use App\Filters\AccountDeletion\SearchFilter;
use App\Filters\AccountDeletion\StatusFilter;
use App\Models\AccountDeletionRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;

/**
 * MOB-05: the console list of account-deletion requests, newest first, filtered through the Pipeline.
 */
final class ListAccountDeletionRequestsAction
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * @return LengthAwarePaginator<int, AccountDeletionRequest>
     */
    public function execute(Request $request): LengthAwarePaginator
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', self::DEFAULT_PER_PAGE)));

        /** @var Builder<AccountDeletionRequest> $query */
        $query = app(Pipeline::class)
            ->send(AccountDeletionRequest::query()->with('handler:id,name'))
            ->through([StatusFilter::class, SearchFilter::class])
            ->thenReturn();

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}
