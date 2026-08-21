<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Models\ReturnDocument;

final class DeleteReturnAction
{
    /**
     * Delete/archive return document
     */
    public function execute(int $returnId): bool
    {
        $returnDoc = ReturnDocument::findOrFail($returnId);
        return (bool)$returnDoc->delete();
    }
}
