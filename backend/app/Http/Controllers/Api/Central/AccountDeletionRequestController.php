<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\AccountDeletion\ListAccountDeletionRequestsAction;
use App\Actions\AccountDeletion\MarkAccountDeletionRequestHandledAction;
use App\DTOs\AccountDeletion\HandleAccountDeletionRequestDTO;
use App\Http\Controllers\Api\Central\Concerns\ResolvesCentralOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountDeletion\HandleAccountDeletionRequestRequest;
use App\Http\Requests\AccountDeletion\ListAccountDeletionRequestsRequest;
use App\Http\Resources\AccountDeletion\AccountDeletionRequestResource;
use Illuminate\Http\JsonResponse;

/**
 * MOB-05: platform console queue of public account-deletion requests (routes/central.php):
 *   GET  /api/v1/super-admin/account-deletion-requests              super_admin.tenants.view
 *   POST /api/v1/super-admin/account-deletion-requests/{id}/handle  super_admin.tenants.manage
 */
final class AccountDeletionRequestController extends Controller
{
    use ResolvesCentralOperator;

    public function __construct(
        private readonly ListAccountDeletionRequestsAction $listAction,
        private readonly MarkAccountDeletionRequestHandledAction $handleAction,
    ) {}

    public function index(ListAccountDeletionRequestsRequest $request): JsonResponse
    {
        return AccountDeletionRequestResource::collection($this->listAction->execute($request))
            ->additional(['success' => true])
            ->response();
    }

    public function handle(HandleAccountDeletionRequestRequest $request, int $id): JsonResponse
    {
        /** @var array{note?: string|null} $data */
        $data = $request->validated();

        $handled = $this->handleAction->execute(
            HandleAccountDeletionRequestDTO::fromArray($data, $id),
            $this->operator($request),
        );

        return (new AccountDeletionRequestResource($handled->load('handler:id,name')))
            ->additional(['success' => true, 'message' => __('account_deletion.console.handled_success')])
            ->response();
    }
}
