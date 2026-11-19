<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landing;

use App\Actions\AccountDeletion\CreateAccountDeletionRequestAction;
use App\DTOs\AccountDeletion\CreateAccountDeletionRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountDeletion\StoreAccountDeletionRequestRequest;
use Illuminate\Http\JsonResponse;

/**
 * MOB-05: POST /api/v1/public/account-deletion-requests (public, no tenancy, throttled
 * `account-deletion-requests`). Answers 201 with a translated message only (no ids), also for a
 * honeypot hit or a repeat of a pending request.
 */
final class PublicAccountDeletionRequestController extends Controller
{
    public function __construct(
        private readonly CreateAccountDeletionRequestAction $createRequest,
    ) {}

    public function store(StoreAccountDeletionRequestRequest $request): JsonResponse
    {
        /** @var array{contact_name: string, phone: string, email?: string|null, shop_code?: string|null, reason?: string|null, website?: string|null} $data */
        $data = $request->validated();

        $this->createRequest->execute(
            CreateAccountDeletionRequestDTO::fromArray($data, app()->getLocale(), $request->ip(), $request->userAgent()),
        );

        return response()->json([
            'success' => true,
            'message' => __('account_deletion.submitted'),
        ], 201);
    }
}
