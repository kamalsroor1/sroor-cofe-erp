<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Support\Billing\ReceiptClassificationResult;

/**
 * #232 spec D: decides whether an uploaded payment proof is a "transfer receipt image". The answer
 * ONLY gates the 48-hour temporary access (amount, recipient and time are verified by hand at
 * Verify), so an implementation must be conservative: anything unclear is `not_receipt` (manual
 * review, never an automatic reject).
 *
 * Bound in AppServiceProvider to ImageHeuristicReceiptClassifier (no OCR / AI in the stack; a paid
 * external classifier is a product decision). Swap the binding to change it.
 */
interface TransferReceiptClassifier
{
    /**
     * @param  string  $path  absolute path of the uploaded file (read only, never executed)
     */
    public function classify(string $path): ReceiptClassificationResult;
}
