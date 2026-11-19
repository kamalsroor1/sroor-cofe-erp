<?php

declare(strict_types=1);

namespace App\Actions\AccountDeletion;

use App\DTOs\AccountDeletion\CreateAccountDeletionRequestDTO;
use App\Enums\AccountDeletionRequestStatus;
use App\Jobs\NotifyPlatformOfAccountDeletionRequestJob;
use App\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MOB-05: store a public account-deletion request (central DB) and alert the platform owner on
 * the platform Telegram bot. Nothing is deleted here: an operator verifies the requester and
 * carries the deletion out manually.
 *
 * A filled honeypot stores nothing and returns null (the controller still answers 201). A second
 * request for a phone that already has a pending one is accepted without a new row or alert.
 */
final class CreateAccountDeletionRequestAction
{
    public function execute(CreateAccountDeletionRequestDTO $dto): ?AccountDeletionRequest
    {
        if ($dto->honeypotFilled) {
            return null;
        }

        $existing = AccountDeletionRequest::query()
            ->where('phone', $dto->phone)
            ->where('status', AccountDeletionRequestStatus::Pending->value)
            ->first();
        if ($existing instanceof AccountDeletionRequest) {
            return $existing;
        }

        $request = AccountDeletionRequest::query()->create([
            ...$dto->toArray(),
            'status' => AccountDeletionRequestStatus::Pending->value,
        ]);

        try {
            NotifyPlatformOfAccountDeletionRequestJob::dispatch((int) $request->getKey())->afterCommit();
        } catch (Throwable $e) {
            Log::warning('Account deletion request notification could not be queued', ['exception' => $e::class]);
        }

        return $request;
    }
}
