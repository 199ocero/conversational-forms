<?php

namespace JaOcero\ConversationalForms\Themes;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use JaOcero\ConversationalForms\Support\ImageUrl;

/**
 * The look of a conversation: colours, font, background image and corner
 * radius. A theme sets the conversation's `--cf-*` variables, so it applies
 * in light and dark mode alike, and the page follows its background.
 *
 *     Conversation::make([...])->theme(Theme::preset('midnight'));
 *     Conversation::make([...])->theme(Theme::make()->accent('#e11d48')->font('Lora'));
 *
 * Without a theme, conversations follow the Filament panel's colours.
 */
final class Theme
{
    public const RADII = [
        'none' => '0px',
        'small' => '0.25rem',
        'medium' => '0.5rem',
        'large' => '1rem',
        'full' => '1.75rem',
    ];

    public const PRESETS = [
        'paper' => ['accent' => '#2563eb', 'background' => '#fbfaf7', 'text' => '#1f2937', 'font' => 'Inter', 'radius' => 'medium'],
        'midnight' => ['accent' => '#38bdf8', 'background' => '#0f172a', 'text' => '#f1f5f9', 'font' => 'Inter', 'radius' => 'medium'],
        'forest' => ['accent' => '#2f6f3e', 'background' => '#f1f5ef', 'text' => '#1c2b1f', 'font' => 'Lora', 'radius' => 'small'],
        'sunset' => ['accent' => '#c2410c', 'background' => '#fff7f1', 'text' => '#3b1f1a', 'answer' => '#9a3412', 'font' => 'DM Sans', 'radius' => 'large'],
        'mono' => ['accent' => '#0a0a0a', 'background' => '#ffffff', 'text' => '#0a0a0a', 'font' => 'IBM Plex Sans', 'radius' => 'none'],
        'lavender' => ['accent' => '#6d28d9', 'background' => '#f5f3ff', 'text' => '#2e1065', 'font' => 'Nunito', 'radius' => 'full'],
    ];

    private string $accent = '#4f46e5';

    private string $background = '#ffffff';

    private string $text = '#111827';

    private ?string $answer = null;

    private ?string $font = null;

    private ?string $backgroundImage = null;

    private int $overlay = 0;

    private string $radius = 'medium';

    public static function make(): self
    {
        return new self;
    }

    /**
     * One of the bundled themes: `paper`, `midnight`, `forest`, `sunset`,
     * `mono` or `lavender`.
     *
     * @throws InvalidArgumentException
     */
    public static function preset(string $name): self
    {
        $preset = self::PRESETS[$name] ?? throw new InvalidArgumentException('There is no theme preset called [' . $name . ']; use one of [' . implode(', ', array_keys(self::PRESETS)) . '].');

        return self::fromArray($preset);
    }

    /**
     * @param  array<string, mixed>  $data  As made by `toArray()`. Unknown keys are ignored.
     */
    public static function fromArray(array $data): self
    {
        $theme = isset($data['preset']) ? self::preset((string) $data['preset']) : new self;

        foreach (['accent', 'background', 'text'] as $color) {
            if (filled($data[$color] ?? null)) {
                $theme->{$color}((string) $data[$color]);
            }
        }

        if (array_key_exists('answer', $data)) {
            $theme->answer($data['answer']);
        }

        if (array_key_exists('font', $data)) {
            $theme->font($data['font']);
        }

        if (array_key_exists('background_image', $data)) {
            $theme->backgroundImage($data['background_image']);
        }

        if (isset($data['overlay'])) {
            $theme->overlay((int) $data['overlay']);
        }

        if (isset($data['radius'])) {
            $theme->radius((string) $data['radius']);
        }

        return $theme;
    }

    /**
     * Buttons, chosen answers and focus rings.
     */
    public function accent(string $color): self
    {
        $this->accent = Contrast::normalize($color);

        return $this;
    }

    public function background(string $color): self
    {
        $this->background = Contrast::normalize($color);

        return $this;
    }

    public function text(string $color): self
    {
        $this->text = Contrast::normalize($color);

        return $this;
    }

    /**
     * Text typed into answers. Defaults to the text colour.
     */
    public function answer(?string $color): self
    {
        $this->answer = blank($color) ? null : Contrast::normalize($color);

        return $this;
    }

    /**
     * A font from `Fonts::FAMILIES`, or `null` for the system font.
     */
    public function font(?string $family): self
    {
        if (filled($family) && (! Fonts::exists($family))) {
            throw new InvalidArgumentException('Unknown font [' . $family . ']; use one of [' . implode(', ', array_keys(Fonts::FAMILIES)) . '].');
        }

        $this->font = blank($family) ? null : $family;

        return $this;
    }

    /**
     * An image URL, or the path of an image uploaded in the builder.
     */
    public function backgroundImage(?string $image): self
    {
        $this->backgroundImage = blank($image) ? null : $image;

        return $this;
    }

    /**
     * How strongly the background colour covers the background image, from
     * 0 to 90 percent, to keep text readable.
     */
    public function overlay(int $percent): self
    {
        $this->overlay = max(0, min(90, $percent));

        return $this;
    }

    public function radius(string $radius): self
    {
        if (! array_key_exists($radius, self::RADII)) {
            throw new InvalidArgumentException('A radius must be one of [' . implode(', ', array_keys(self::RADII)) . "]; [{$radius}] was given.");
        }

        $this->radius = $radius;

        return $this;
    }

    public function getFont(): ?string
    {
        return $this->font;
    }

    /**
     * Whether the theme is dark, so the page around it switches to dark mode.
     */
    public function isDark(): bool
    {
        return Contrast::luminance($this->background) < 0.18;
    }

    /**
     * Black or white, whichever reads better on the accent colour.
     */
    public function accentContrast(): string
    {
        return (Contrast::ratio($this->accent, '#ffffff') >= Contrast::ratio($this->accent, '#0a0a0a')) ? '#ffffff' : '#0a0a0a';
    }

    /**
     * The conversation's CSS variables.
     *
     * @return array<string, string>
     */
    public function toCssVariables(): array
    {
        [$red, $green, $blue] = Contrast::rgb($this->background);
        $image = ImageUrl::resolve($this->backgroundImage);

        return array_filter([
            '--cf-accent' => $this->accent,
            '--cf-accent-contrast' => $this->accentContrast(),
            '--cf-bg' => $this->background,
            '--cf-fg' => $this->text,
            '--cf-muted' => "color-mix(in oklab, {$this->text} 65%, {$this->background})",
            '--cf-surface' => "color-mix(in oklab, {$this->background} 94%, {$this->text})",
            '--cf-answer-color' => $this->answer ?? $this->text,
            '--cf-font-family' => Fonts::stack($this->font),
            '--cf-radius' => self::RADII[$this->radius],
            '--cf-bg-image' => ($image === null) ? null : 'url("' . addslashes($image) . '")',
            '--cf-bg-overlay' => ($image === null) ? null : "rgb({$red} {$green} {$blue} / " . ($this->overlay / 100) . ')',
        ], fn (?string $value): bool => $value !== null);
    }

    public function toStyle(): string
    {
        return implode('; ', Arr::map($this->toCssVariables(), fn (string $value, string $name): string => "{$name}: {$value}"));
    }

    /**
     * Colour pairs that fall short of WCAG AA, as readable sentences.
     *
     * @return list<string>
     */
    public function contrastIssues(): array
    {
        $checks = [
            'text' => [$this->text, $this->background, Contrast::TEXT],
            'answer' => [$this->answer ?? $this->text, $this->background, Contrast::TEXT],
            'accent' => [$this->accent, $this->background, Contrast::NON_TEXT],
            'button' => [$this->accentContrast(), $this->accent, Contrast::TEXT],
        ];

        $issues = [];

        foreach ($checks as $check => [$foreground, $background, $minimum]) {
            $ratio = Contrast::ratio($foreground, $background);

            if ($ratio < $minimum) {
                $issues[] = __("conversational-forms::conversational-forms.themes.contrast.{$check}", ['ratio' => number_format($ratio, 1), 'minimum' => $minimum]);
            }
        }

        return $issues;
    }

    /**
     * @return array{accent: string, background: string, text: string, answer: string|null, font: string|null, background_image: string|null, overlay: int, radius: string}
     */
    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
            'background' => $this->background,
            'text' => $this->text,
            'answer' => $this->answer,
            'font' => $this->font,
            'background_image' => $this->backgroundImage,
            'overlay' => $this->overlay,
            'radius' => $this->radius,
        ];
    }
}
