<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Treasury\CreateTreasuryTransferAction;
use App\Actions\Treasury\ListTreasuryTransfersAction;
use App\DTOs\Treasury\TreasuryTransferDTO;
use App\DTOs\Treasury\TreasuryTransferFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\ListTreasuryTransfersRequest;
use App\Http\Requests\Treasury\StoreTreasuryTransferRequest;
use App\Http\Resources\TreasuryTransferResource;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Http\JsonResponse;

/**
 * SHOP-06: transfers between the treasury accounts (cash drawer, InstaPay, wallet, bank...).
 */
final class TreasuryTransferController extends Controller
{
    public function __construct(
        private readonly ListTreasuryTransfersAction $listTreasuryTransfersAction,
        private readonly CreateTreasuryTransferAction $createTreasuryTransferAction,
    ) {}

    public function index(ListTreasuryTransfersRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2: the active store (or an access-checked store_id filter); every store only for a verified `all`.
        $filters = TreasuryTransferFilterDTO::fromArray($request->validated(), $activeStore->readFilter($request));
        $transfers = $this->listTreasuryTransfersAction->execute($filters);

        return response()->json([
            'success' => true,
            'data' => TreasuryTransferResource::collection($transfers->items())->resolve(),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
            ],
        ], 200);
    }

    public function store(StoreTreasuryTransferRequest $request, ActiveStore $activeStore): JsonResponse
    {
        // STOR-2: booked on the active store only (store.active:concrete); a body store_id is
        // access-checked (403) but never chooses the store.
        ClientStoreGuard::verified($request);

        $dto = TreasuryTransferDTO::fromArray($request->validated(), (int) $activeStore->requireStore()->id);
        $transfer = $this->createTreasuryTransferAction->execute($dto);
        $data = (new TreasuryTransferResource($transfer))->resolve();

        return response()->json([
            'success' => true,
            'message' => __('treasury.transfer_success', [
                'amount' => $data['amount'],
                'from' => $data['from_label'],
                'to' => $data['to_label'],
                'number' => $data['transfer_number'],
            ]),
            'data' => $data,
        ], 201);
    }
}
