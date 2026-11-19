<?php

declare(strict_types=1);

namespace App\Actions\AccountDeletion;

use App\DTOs\AccountDeletion\HandleAccountDeletionRequestDTO;
use App\Enums\AccountDeletionRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Models\AccountDeletionRequest;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * MOB-05: an operator closes a pending account-deletion request after handling it by hand.
 * The row is locked so two operators cannot both close it (the second gets 409).
 */
final class MarkAccountDeletionRequestHandledAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(HandleAccountDeletionRequestDTO $dto, CentralUser $operator): AccountDeletionRequest
    {
        $connection = DB::connection((string) (new AccountDeletionRequest)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): AccountDeletionRequest {
            $request = AccountDeletionRequest::query()->whereKey($dto->requestId)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw new ConflictHttpException((string) __('account_deletion.console.already_handled'));
            }

            $request->status = AccountDeletionRequestStatus::Handled;
            $request->handled_by = (int) $operator->getKey();
            $request->handled_at = now();
            $request->handled_note = $dto->note;
            $request->save();

            $this->audit->record(
                CentralAuditEvent::AccountDeletionRequestHandled,
                ['request_id' => (int) $request->getKey(), 'shop_code' => $request->shop_code],
                $operator,
                $request,
            );

            return $request;
        });
    }
}
