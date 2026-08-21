<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\DTOs\Returns\ReturnDocumentDTO;
use App\Models\ReturnDocument;
use App\Services\ReturnService;

final class CreateReturnAction
{
    public function __construct(
        private readonly ReturnService $returnService
    ) {}

    /**
     * Create return document (sales return or purchase return) atomically
     */
    public function execute(ReturnDocumentDTO $dto): ReturnDocument
    {
        return $this->returnService->createReturn($dto->toArray());
    }
}
