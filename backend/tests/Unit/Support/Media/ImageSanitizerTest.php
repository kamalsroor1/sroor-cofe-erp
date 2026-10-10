<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\BrandAssetSpec;
use App\Support\Media\ImageSanitizer;
use App\Support\Media\SanitizedImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * All fixtures are generated with GD at runtime; no binary fixtures are committed.
 */
final class ImageSanitizerTest extends TestCase
{
    private ImageSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd') || ! extension_loaded('fileinfo')) {
            $this->fail('ImageSanitizer requires the gd and fileinfo extensions.');
        }

        app()->setLocale('en');
        $this->sanitizer = new ImageSanitizer;
    }

    public function test_valid_png_passes_and_keeps_alpha(): void
    {
        $result = $this->sanitizer->sanitize($this->upload('logo.png', $this->png(128, 96, withTransparentCorner: true)), BrandAssetSpec::logo());

        $this->assertSame('png', $result->extension);
        $this->assertSame('image/png', $result->mime);
        $this->assertSame(128, $result->width);
        $this->assertSame(96, $result->height);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($result->binary, 0, 8));

        // IHDR colour type 6 = truecolour with alpha.
        $this->assertSame(6, ord($result->binary[25]));

        $decoded = imagecreatefromstring($result->binary);
        $this->assertInstanceOf(GdImage::class, $decoded);
        $corner = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
        $middle = imagecolorsforindex($decoded, imagecolorat($decoded, 64, 48));
        $this->assertSame(127, $corner['alpha'], 'transparent pixel must stay fully transparent');
        $this->assertSame(0, $middle['alpha'], 'opaque pixel must stay opaque');
        $this->assertSame(255, $middle['red']);
    }

    public function test_valid_jpeg_and_webp_pass_for_logo(): void
    {
        $jpeg = $this->sanitizer->sanitize($this->upload('logo.jpeg', $this->jpeg(100, 80)), BrandAssetSpec::logo());
        $this->assertSame('jpg', $jpeg->extension);
        $this->assertSame('image/jpeg', $jpeg->mime);
        $this->assertSame("\xFF\xD8", substr($jpeg->binary, 0, 2));

        $webp = $this->sanitizer->sanitize($this->upload('logo.webp', $this->webp(100, 80)), BrandAssetSpec::logo());
        $this->assertSame('webp', $webp->extension);
        $this->assertSame('image/webp', $webp->mime);
        $this->assertSame('RIFF', substr($webp->binary, 0, 4));
        $this->assertSame('WEBP', substr($webp->binary, 8, 4));
    }

    public function test_extension_comes_from_magic_bytes_not_client_name(): void
    {
        // A real JPEG uploaded as "logo.png" is stored as .jpg.
        $result = $this->sanitizer->sanitize($this->upload('logo.png', $this->jpeg(100, 100)), BrandAssetSpec::logo());

        $this->assertSame('jpg', $result->extension);
        $this->assertSame('image/jpeg', $result->mime);
    }

    public function test_php_payload_appended_to_png_is_stripped(): void
    {
        $bytes = $this->png(64, 64).'<?php echo 1; ?>';
        $this->assertStringContainsString('<?php', $bytes);

        $result = $this->sanitizer->sanitize($this->upload('logo.png', $bytes), BrandAssetSpec::logo());

        $this->assertStringNotContainsString('<?php', $result->binary);
    }

    public function test_php_payload_in_png_text_chunk_is_stripped(): void
    {
        $bytes = $this->insertPngChunkBeforeIend($this->png(64, 64), 'tEXt', "Comment\0<?php system(\$_GET['c']); ?>");
        $this->assertNotFalse(getimagesizefromstring($bytes));

        $result = $this->sanitizer->sanitize($this->upload('logo.png', $bytes), BrandAssetSpec::logo());

        $this->assertStringNotContainsString('<?php', $result->binary);
        $this->assertStringNotContainsString('tEXt', $result->binary);
    }

    public function test_jpeg_exif_segment_is_stripped(): void
    {
        $bytes = $this->injectExif($this->jpeg(100, 100));
        $this->assertStringContainsString('Exif', $bytes);
        $this->assertNotFalse(getimagesizefromstring($bytes));

        $result = $this->sanitizer->sanitize($this->upload('photo.jpg', $bytes), BrandAssetSpec::logo());

        $this->assertStringNotContainsString('Exif', $result->binary);
        $this->assertStringNotContainsString('SECRET-GPS', $result->binary);
    }

    public function test_svg_is_rejected(): void
    {
        $this->assertRejected($this->upload('logo.svg', $this->svg()), BrandAssetSpec::logo(), __('media.svg_forbidden'));
    }

    public function test_svg_renamed_to_png_is_rejected(): void
    {
        $this->assertRejected($this->upload('logo.png', $this->svg()), BrandAssetSpec::logo(), __('media.svg_forbidden'));
    }

    public function test_svg_without_xml_prolog_renamed_to_png_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><script>alert(1)</script></svg>';

        $this->assertRejected($this->upload('logo.png', $svg), BrandAssetSpec::logo(), __('media.svg_forbidden'));
    }

    public function test_gif_is_rejected_even_if_named_png(): void
    {
        $this->assertRejected($this->upload('logo.png', $this->gif(64, 64)), BrandAssetSpec::logo(), $this->typeMessage('PNG, JPG, WEBP'));
    }

    public function test_jpeg_is_rejected_for_png_only_favicon(): void
    {
        $this->assertRejected($this->upload('favicon.png', $this->jpeg(64, 64)), BrandAssetSpec::favicon(), $this->typeMessage('PNG'));
    }

    public function test_non_image_is_rejected(): void
    {
        $this->assertRejected($this->upload('logo.png', "%PDF-1.4\n%fake pdf\n"), BrandAssetSpec::logo(), $this->typeMessage('PNG, JPG, WEBP'));
    }

    public function test_truncated_png_is_rejected_as_invalid(): void
    {
        $bytes = substr($this->png(64, 64), 0, 40);

        $this->assertRejected($this->upload('logo.png', $bytes), BrandAssetSpec::logo(), __('media.invalid_image'));
    }

    public function test_too_large_dimensions_are_rejected(): void
    {
        $this->assertRejected($this->upload('logo.png', $this->png(2100, 100)), BrandAssetSpec::logo(), $this->dimensionsMessage(BrandAssetSpec::logo()));
    }

    public function test_too_small_dimensions_are_rejected(): void
    {
        $this->assertRejected($this->upload('logo.png', $this->png(32, 32)), BrandAssetSpec::logo(), $this->dimensionsMessage(BrandAssetSpec::logo()));
    }

    public function test_decompression_bomb_header_is_rejected_before_decoding(): void
    {
        // A spec that would allow the size, so only the hard pixel ceiling can reject it.
        $lenient = new BrandAssetSpec(['image/png'], ['png'], 2048, 1, 1, 100000, 100000);
        $bytes = $this->pngHeaderOnly(20000, 20000);
        $this->assertSame([20000, 20000], array_slice((array) getimagesizefromstring($bytes), 0, 2));

        $this->assertRejected($this->upload('bomb.png', $bytes), $lenient, $this->dimensionsMessage($lenient));
    }

    public function test_non_square_favicon_is_rejected(): void
    {
        $this->assertRejected($this->upload('favicon.png', $this->png(64, 32)), BrandAssetSpec::favicon(), __('media.must_be_square'));
    }

    public function test_square_favicon_and_app_icon_pass(): void
    {
        $this->assertSame(64, $this->sanitizer->sanitize($this->upload('favicon.png', $this->png(64, 64)), BrandAssetSpec::favicon())->width);
        $this->assertSame(192, $this->sanitizer->sanitize($this->upload('icon.png', $this->png(192, 192)), BrandAssetSpec::appIcon())->height);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $bytes = $this->png(64, 64).str_repeat("\0", 257 * 1024);

        $this->assertRejected($this->upload('favicon.png', $bytes), BrandAssetSpec::favicon(), __('media.too_large', ['max' => 256]));
    }

    public function test_sha256_matches_output_bytes(): void
    {
        $result = $this->sanitizer->sanitize($this->upload('logo.png', $this->png(80, 80)), BrandAssetSpec::logo());

        $this->assertSame(hash('sha256', $result->binary), $result->sha256);
        $this->assertSame(64, strlen($result->sha256));
    }

    public function test_error_is_keyed_by_the_given_attribute_and_translated_in_arabic(): void
    {
        app()->setLocale('ar');

        try {
            $this->sanitizer->sanitize($this->upload('logo.svg', $this->svg()), BrandAssetSpec::logo(), 'logo');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['logo'], array_keys($e->errors()));
            $this->assertSame(trans('media.svg_forbidden', [], 'ar'), $e->errors()['logo'][0]);
            $this->assertNotSame('media.svg_forbidden', $e->errors()['logo'][0]);
        }
    }

    public function test_brand_asset_specs_match_the_contract(): void
    {
        $logo = BrandAssetSpec::logo();
        $this->assertSame(['image/png', 'image/jpeg', 'image/webp'], $logo->allowedMimes);
        $this->assertSame([2048, 64, 64, 2048, 2048, false], [$logo->maxKb, $logo->minW, $logo->minH, $logo->maxW, $logo->maxH, $logo->square]);

        $favicon = BrandAssetSpec::favicon();
        $this->assertSame(['image/png'], $favicon->allowedMimes);
        $this->assertSame([256, 32, 32, 512, 512, true], [$favicon->maxKb, $favicon->minW, $favicon->minH, $favicon->maxW, $favicon->maxH, $favicon->square]);

        $appIcon = BrandAssetSpec::appIcon();
        $this->assertSame(['image/png'], $appIcon->allowedMimes);
        $this->assertSame([1024, 192, 192, 1024, 1024, true], [$appIcon->maxKb, $appIcon->minW, $appIcon->minH, $appIcon->maxW, $appIcon->maxH, $appIcon->square]);
    }

    // ---------------------------------------------------------------- helpers

    private function assertRejected(UploadedFile $file, BrandAssetSpec $spec, string $expectedMessage): void
    {
        try {
            $result = $this->sanitizer->sanitize($file, $spec);
        } catch (ValidationException $e) {
            $this->assertSame(['file'], array_keys($e->errors()));
            $this->assertSame($expectedMessage, $e->errors()['file'][0]);

            return;
        }

        $this->fail('Expected rejection, got a '.$result->mime.' '.$result->width.'x'.$result->height.' '.SanitizedImage::class);
    }

    private function typeMessage(string $types): string
    {
        return __('media.type_not_allowed', ['types' => $types]);
    }

    private function dimensionsMessage(BrandAssetSpec $spec): string
    {
        return __('media.bad_dimensions', ['min_w' => $spec->minW, 'min_h' => $spec->minH, 'max_w' => $spec->maxW, 'max_h' => $spec->maxH]);
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function canvas(int $width, int $height, bool $withTransparentCorner = false): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocatealpha($image, 255, 0, 0, 0));

        if ($withTransparentCorner) {
            imagefilledrectangle($image, 0, 0, 9, 9, (int) imagecolorallocatealpha($image, 0, 0, 255, 127));
        }

        return $image;
    }

    private function capture(callable $writer): string
    {
        ob_start();
        $writer();

        return (string) ob_get_clean();
    }

    private function png(int $width, int $height, bool $withTransparentCorner = false): string
    {
        $image = $this->canvas($width, $height, $withTransparentCorner);

        return $this->capture(static fn () => imagepng($image));
    }

    private function jpeg(int $width, int $height): string
    {
        $image = $this->canvas($width, $height);

        return $this->capture(static fn () => imagejpeg($image, null, 90));
    }

    private function webp(int $width, int $height): string
    {
        $image = $this->canvas($width, $height);

        return $this->capture(static fn () => imagewebp($image, null, 90));
    }

    private function gif(int $width, int $height): string
    {
        $image = $this->canvas($width, $height);

        return $this->capture(static fn () => imagegif($image));
    }

    private function svg(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><script>alert(1)</script></svg>';
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    private function insertPngChunkBeforeIend(string $png, string $type, string $data): string
    {
        $iend = strrpos($png, 'IEND');
        $this->assertNotFalse($iend);
        $at = $iend - 4; // length field precedes the type

        return substr($png, 0, $at).$this->pngChunk($type, $data).substr($png, $at);
    }

    /** A PNG whose IHDR claims huge dimensions but carries almost no pixel data. */
    private function pngHeaderOnly(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', $ihdr)
            .$this->pngChunk('IDAT', (string) gzcompress(str_repeat("\0", 64)))
            .$this->pngChunk('IEND', '');
    }

    /** Inserts an APP1 Exif segment (big-endian TIFF, one ASCII ImageDescription tag) right after SOI. */
    private function injectExif(string $jpeg): string
    {
        $text = "SECRET-GPS\0";
        $tiff = 'MM'.pack('n', 42).pack('N', 8)            // header, IFD0 at offset 8
            .pack('n', 1)                                   // one entry
            .pack('nnNN', 0x010E, 2, strlen($text), 26)     // ImageDescription, ASCII, count, offset
            .pack('N', 0)                                   // no next IFD
            .$text;
        $payload = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
