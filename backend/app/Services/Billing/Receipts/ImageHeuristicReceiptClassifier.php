<?php

declare(strict_types=1);

namespace App\Services\Billing\Receipts;

use App\Contracts\Billing\TransferReceiptClassifier;
use App\Support\Billing\ReceiptClassificationResult;
use finfo;
use Illuminate\Container\Attributes\Config;

/**
 * #232 spec D: the default, explainable receipt classifier. No OCR / AI: it answers "is this a
 * real phone screenshot / photo of plausible receipt size?", which is what a transfer receipt
 * upload looks like, and says `not_receipt` for everything else.
 *
 * Rules, in order (the first failing one is the reason code stored on the payment):
 *  1. readable file of at least `min_bytes` and at most `max_bytes`      (file_too_small / file_too_large / unreadable)
 *  2. content type sniffed from the BYTES (finfo), never the name:
 *     a PDF is `pdf`, anything that is not jpeg / png / webp is `not_image`;
 *  3. the image header parses (getimagesize) and agrees with the sniffed type (unreadable / unsupported_image_type);
 *  4. short side >= `min_short_side`, long side <= `max_long_side`        (dimensions_too_small / dimensions_too_large)
 *  5. long side / short side <= `max_aspect_ratio` (a receipt, not a banner strip)  (aspect_ratio)
 * All pass => `transfer_receipt_image` (reason `receipt_image`). The thresholds live in
 * config/billing.php `receipt_classifier` (plain values: changing them is a product decision).
 * It never decodes the pixels (no decompression-bomb risk) and never executes the file.
 */
final class ImageHeuristicReceiptClassifier implements TransferReceiptClassifier
{
    /** @var array<string, string> sniffed mime => image type name */
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @var array<int, string> getimagesize() IMAGETYPE_* => image type name */
    private const IMAGE_TYPES = [
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    public function __construct(
        #[Config('billing.receipt_classifier.min_bytes', 8192)]
        private readonly int $minBytes,
        #[Config('billing.receipt_classifier.max_bytes', 5242880)]
        private readonly int $maxBytes,
        #[Config('billing.receipt_classifier.min_short_side', 320)]
        private readonly int $minShortSide,
        #[Config('billing.receipt_classifier.max_long_side', 12000)]
        private readonly int $maxLongSide,
        #[Config('billing.receipt_classifier.max_aspect_ratio', 6)]
        private readonly int $maxAspectRatio,
    ) {}

    public function classify(string $path): ReceiptClassificationResult
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ReceiptClassificationResult::notReceipt('unreadable');
        }

        $bytes = (int) filesize($path);
        $facts = ['bytes' => $bytes];

        if ($bytes < $this->minBytes) {
            return ReceiptClassificationResult::notReceipt('file_too_small', $facts);
        }
        if ($bytes > $this->maxBytes) {
            return ReceiptClassificationResult::notReceipt('file_too_large', $facts);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $mime = is_string($mime) ? strtolower($mime) : '';
        $facts['mime'] = $mime;

        if ($mime === 'application/pdf') {
            return ReceiptClassificationResult::notReceipt('pdf', $facts);
        }
        if (! isset(self::IMAGE_MIMES[$mime])) {
            return ReceiptClassificationResult::notReceipt('not_image', $facts);
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return ReceiptClassificationResult::notReceipt('unreadable', $facts);
        }

        [$width, $height, $type] = [(int) $info[0], (int) $info[1], (int) $info[2]];
        $facts['width'] = $width;
        $facts['height'] = $height;

        if ((self::IMAGE_TYPES[$type] ?? null) !== self::IMAGE_MIMES[$mime]) {
            return ReceiptClassificationResult::notReceipt('unsupported_image_type', $facts);
        }

        $short = min($width, $height);
        $long = max($width, $height);

        if ($short < $this->minShortSide) {
            return ReceiptClassificationResult::notReceipt('dimensions_too_small', $facts);
        }
        if ($long > $this->maxLongSide) {
            return ReceiptClassificationResult::notReceipt('dimensions_too_large', $facts);
        }
        // Integer comparison: long > ratio * short (no float arithmetic).
        if ($long > $this->maxAspectRatio * $short) {
            return ReceiptClassificationResult::notReceipt('aspect_ratio', $facts);
        }

        return ReceiptClassificationResult::receipt($facts);
    }
}
