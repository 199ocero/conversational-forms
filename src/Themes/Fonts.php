<?php

namespace JaOcero\ConversationalForms\Themes;

use Illuminate\Support\Str;

/**
 * The fonts a theme can use. They're loaded from Bunny Fonts (a privacy-
 * friendly host with Google Fonts' families and API) unless
 * `conversational-forms.fonts.url` is set to `null`, in which case the
 * system font is used instead.
 */
final class Fonts
{
    /**
     * Font families and their fallback.
     */
    public const FAMILIES = [
        'Inter' => 'sans-serif',
        'DM Sans' => 'sans-serif',
        'Nunito' => 'sans-serif',
        'Poppins' => 'sans-serif',
        'Space Grotesk' => 'sans-serif',
        'IBM Plex Sans' => 'sans-serif',
        'Lora' => 'serif',
        'Merriweather' => 'serif',
        'Playfair Display' => 'serif',
        'Source Serif 4' => 'serif',
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(array_keys(self::FAMILIES), array_keys(self::FAMILIES));
    }

    public static function exists(?string $family): bool
    {
        return array_key_exists((string) $family, self::FAMILIES);
    }

    /**
     * The `font-family` value: the font, then a system fallback.
     */
    public static function stack(?string $family): ?string
    {
        if (! self::exists($family)) {
            return null;
        }

        $fallback = (self::FAMILIES[$family] === 'serif') ? 'ui-serif, Georgia, serif' : 'ui-sans-serif, system-ui, sans-serif';

        return "'{$family}', {$fallback}";
    }

    /**
     * The stylesheet that loads the font, or `null` when external fonts are off.
     */
    public static function stylesheetUrl(?string $family): ?string
    {
        $base = config('conversational-forms.fonts.url', 'https://fonts.bunny.net/css');

        if (blank($base) || (! self::exists($family))) {
            return null;
        }

        return $base . '?family=' . Str::slug((string) $family) . ':400,500,600,700&display=swap';
    }
}
