<?php

namespace JaOcero\ConversationalForms\Themes;

use InvalidArgumentException;

/**
 * Colour contrast as defined by WCAG 2.2: text needs a ratio of at least 4.5
 * against its background (AA), and buttons and outlines at least 3.
 */
final class Contrast
{
    public const TEXT = 4.5;

    public const NON_TEXT = 3.0;

    /**
     * The contrast ratio of two colours, from 1 (none) to 21 (black on white).
     */
    public static function ratio(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * Relative luminance, from 0 (black) to 1 (white).
     */
    public static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return ($value <= 0.04045) ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * `#abc` or `#aabbcc` as `#aabbcc`.
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $hex): string
    {
        $hex = strtolower(ltrim(trim($hex), '#'));

        if (preg_match('/^[0-9a-f]{3}$/', $hex) === 1) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (preg_match('/^[0-9a-f]{6}$/', $hex) !== 1) {
            throw new InvalidArgumentException("[{$hex}] is not a hex colour such as #4f46e5.");
        }

        return "#{$hex}";
    }

    /**
     * @return array{int, int, int}
     */
    public static function rgb(string $hex): array
    {
        $hex = substr(self::normalize($hex), 1);

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
