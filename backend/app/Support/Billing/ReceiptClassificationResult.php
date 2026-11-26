<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\Billing\ReceiptClassification;

/**
 * Output of App\Contracts\Billing\TransferReceiptClassifier: the verdict, a short reason code
 * (stored on the payment, e.g. `receipt_image`, `pdf`, `dimensions_too_small`) and the measured
 * facts (mime, bytes, width, height) for the central audit log. No file content, no file name.
 */
final readonly class ReceiptClassificationResult
{
    /**
     * @param  array<string, int|string|null>  $facts
     */
    public function __construct(
        public ReceiptClassification $classification,
        public string $reason,
        public array $facts = [],
    ) {}

    /**
     * @param  array<string, int|string|null>  $facts
     */
    public static function receipt(array $facts = []): self
    {
        return new self(ReceiptClassification::TransferReceiptImage, 'receipt_image', $facts);
    }

    /**
     * @param  array<string, int|string|null>  $facts
     */
    public static function notReceipt(string $reason, array $facts = []): self
    {
        return new self(ReceiptClassification::NotReceipt, $reason, $facts);
    }

    public function isReceipt(): bool
    {
        return $this->classification === ReceiptClassification::TransferReceiptImage;
    }
}
