<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Contracts\Billing\TransferReceiptClassifier;
use App\Enums\Billing\ReceiptClassification;
use App\Services\Billing\Receipts\ImageHeuristicReceiptClassifier;
use Tests\TestCase;

/**
 * #232 spec D: the default receipt classifier. A real jpg/png/webp (sniffed from the bytes) of
 * plausible receipt size is a transfer receipt image; everything else is `not_receipt` with a reason.
 */
final class ImageHeuristicReceiptClassifierTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function file(string $bytes): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'rcpt');
        file_put_contents($path, $bytes);
        $this->files[] = $path;

        return $path;
    }

    private function image(int $width, int $height, string $format = 'png', int $lines = 3000): string
    {
        $image = imagecreatetruecolor($width, $height);
        mt_srand(7);
        for ($i = 0; $i < $lines; $i++) {
            $color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imageline($image, mt_rand(0, $width - 1), mt_rand(0, $height - 1), mt_rand(0, $width - 1), mt_rand(0, $height - 1), (int) $color);
        }
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image, null, 90),
            'webp' => imagewebp($image, null, 90),
            default => imagepng($image),
        };
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $this->file($bytes);
    }

    private function classifier(): ImageHeuristicReceiptClassifier
    {
        return new ImageHeuristicReceiptClassifier(8192, 5 * 1024 * 1024, 320, 12000, 6);
    }

    public function test_the_container_binds_the_heuristic_classifier(): void
    {
        $this->assertInstanceOf(ImageHeuristicReceiptClassifier::class, app(TransferReceiptClassifier::class));
    }

    public function test_phone_sized_images_are_receipts_in_every_supported_format(): void
    {
        foreach (['png', 'jpeg', 'webp'] as $format) {
            $result = $this->classifier()->classify($this->image(720, 1280, $format));

            $this->assertSame(ReceiptClassification::TransferReceiptImage, $result->classification, $format);
            $this->assertSame('receipt_image', $result->reason);
            $this->assertSame(720, $result->facts['width']);
        }
    }

    public function test_everything_else_is_not_a_receipt_with_a_reason(): void
    {
        $pdf = $this->file("%PDF-1.4\n".str_repeat('x', 9000));
        $svgAsPng = $this->file('<svg xmlns="http://www.w3.org/2000/svg">'.str_repeat(' ', 9000).'</svg>');

        $cases = [
            'pdf' => $pdf,
            'not_image' => $svgAsPng,
            'file_too_small' => $this->file('tiny'),
            'dimensions_too_small' => $this->image(200, 1200, 'png', 6000),
            'aspect_ratio' => $this->image(330, 2400, 'png', 6000),
            'unreadable' => '/nonexistent/receipt.png',
        ];

        foreach ($cases as $reason => $path) {
            $result = $this->classifier()->classify($path);

            $this->assertSame(ReceiptClassification::NotReceipt, $result->classification, $reason);
            $this->assertSame($reason, $result->reason);
        }
    }

    public function test_a_truncated_image_header_is_not_a_receipt(): void
    {
        $bytes = (string) file_get_contents($this->image(720, 1280));
        $broken = $this->file(substr($bytes, 0, 8).str_repeat("\0", 9000));

        $this->assertSame(ReceiptClassification::NotReceipt, $this->classifier()->classify($broken)->classification);
    }
}
