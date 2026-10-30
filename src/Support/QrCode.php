<?php

namespace JaOcero\ConversationalForms\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code as SVG, when `bacon/bacon-qr-code` is installed (it's suggested,
 * not required).
 */
final class QrCode
{
    public static function isAvailable(): bool
    {
        return class_exists(Writer::class);
    }

    public static function svg(string $text, int $size = 192): ?string
    {
        if (! self::isAvailable()) {
            return null;
        }

        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($text);

        // Drop the XML declaration so the SVG can be placed inside HTML.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
