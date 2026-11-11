<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Self-hosted, offline QR code generator producing scannable SVG and data URIs.
 *
 * SEC-07: avoids leaking invoice URLs or tenant context to third-party QR services
 * (such as api.qrserver.com).
 */
final class QrCode
{
    /**
     * Generate an SVG markup string for the given payload.
     *
     * The XML declaration is stripped so the SVG embeds cleanly into HTML5 Blade documents.
     */
    public static function svg(string $data, int $size = 75, int $margin = 0): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd
        );

        $svg = (new Writer($renderer))->writeString($data);
        $clean = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $svg) ?? $svg;

        // Ensure responsive/centered display when inlined in print layouts
        if (str_contains($clean, '<svg')) {
            $clean = preg_replace('/<svg\b/i', '<svg style="width: '.$size.'px; height: '.$size.'px; margin: 0 auto; display: block;" role="img" aria-label="QR Code"', $clean, 1) ?? $clean;
        }

        return $clean;
    }

    /**
     * Generate a self-contained RFC 2397 Data URI for embedding in an <img> tag.
     */
    public static function dataUri(string $data, int $size = 75, int $margin = 0): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd
        );

        $svg = (new Writer($renderer))->writeString($data);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
