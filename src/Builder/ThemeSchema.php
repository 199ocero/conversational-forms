<?php

namespace JaOcero\ConversationalForms\Builder;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use JaOcero\ConversationalForms\Themes\Fonts;
use JaOcero\ConversationalForms\Themes\Theme;

/**
 * The editor's design settings: a preset to start from, then colours, font,
 * corner radius and background image. The editor's preview shows them as
 * they change, with contrast warnings. Edits the form state's `theme`.
 */
final class ThemeSchema
{
    public const FOLLOW_PANEL = 'panel';

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Group::make()
                ->statePath('theme')
                ->extraAttributes(BuilderSchema::shownWhenSelected('design'))
                ->schema([
                    // Picking a preset fills in the fields below on the server: done
                    // in the browser, the preset would reach the server with the next
                    // request and overwrite any colour changed since.
                    ToggleButtons::make('preset')
                        ->label(self::text('preset'))
                        ->options([
                            self::FOLLOW_PANEL => self::text('follow_panel'),
                            ...array_combine(array_keys(Theme::PRESETS), array_map(fn (string $name): string => self::text("presets.{$name}"), array_keys(Theme::PRESETS))),
                        ])
                        ->default(self::FOLLOW_PANEL)
                        ->inline()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $preset = Theme::PRESETS[$state] ?? null;

                            if ($preset === null) {
                                return;
                            }

                            $theme = Theme::preset((string) $state)->toArray();

                            foreach (['accent', 'background', 'text', 'answer', 'font', 'radius'] as $key) {
                                $set($key, $theme[$key]);
                            }
                        }),
                    Grid::make(BuilderSchema::columns(2))
                        ->schema([
                            ColorPicker::make('accent')->label(self::text('accent'))->hex()->required(),
                            ColorPicker::make('background')->label(self::text('background'))->hex()->required(),
                            ColorPicker::make('text')->label(self::text('text'))->hex()->required(),
                            ColorPicker::make('answer')->label(self::text('answer'))->hex()->placeholder(self::text('same_as_text')),
                            Select::make('font')->label(self::text('font'))->options(Fonts::options())->placeholder(self::text('system_font'))->columnSpanFull(),
                            ToggleButtons::make('radius')
                                ->label(self::text('radius'))
                                ->options(array_combine(array_keys(Theme::RADII), array_map(fn (string $radius): string => self::text("radii.{$radius}"), array_keys(Theme::RADII))))
                                ->default('medium')
                                ->inline()
                                ->columnSpanFull(),
                            FileUpload::make('background_image')
                                ->label(self::text('background_image'))
                                ->image()
                                ->disk(config('conversational-forms.images.disk', 'public'))
                                ->directory(config('conversational-forms.images.directory'))
                                ->visibility('public')
                                // The preview needs the uploaded image's address from the server.
                                ->live()
                                ->columnSpanFull(),
                            Slider::make('overlay')
                                ->label(self::text('overlay'))
                                ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: self::text('overlay_help'))
                                ->range(0, 90)
                                ->step(5)
                                ->default(0)
                                ->required(false)
                                ->columnSpanFull(),
                        ])
                        ->visible(fn (Get $get): bool => ($get('preset') ?? self::FOLLOW_PANEL) !== self::FOLLOW_PANEL),
                ]),
        ];
    }

    /**
     * What the editor needs to draw themes in the browser (see `theme.js`).
     *
     * @return array{presets: array<string, array<string, string>>, fonts: array<string, string>, radii: array<string, string>, fontsUrl: string|null}
     */
    public static function browserOptions(): array
    {
        return [
            'presets' => Theme::PRESETS,
            'fonts' => Fonts::FAMILIES,
            'radii' => Theme::RADII,
            'fontsUrl' => config('conversational-forms.fonts.url', 'https://fonts.bunny.net/css') ?: null,
        ];
    }

    /**
     * The theme state for a stored theme (`null` follows the panel).
     *
     * @param  array<string, mixed>|null  $theme
     * @return array<string, mixed>
     */
    public static function toState(?array $theme): array
    {
        if (blank($theme)) {
            return ['preset' => self::FOLLOW_PANEL, 'overlay' => 0, 'radius' => 'medium'];
        }

        return [...Theme::fromArray($theme)->toArray(), 'preset' => $theme['preset'] ?? 'custom'];
    }

    /**
     * The theme to store for the state, or `null` to follow the panel.
     *
     * @param  array<string, mixed>|null  $state
     * @return array<string, mixed>|null
     */
    public static function fromState(?array $state): ?array
    {
        $theme = self::themeFrom($state ?? []);

        return ($theme instanceof Theme) ? [...$theme->toArray(), 'preset' => $state['preset'] ?? null] : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function themeFrom(array $state): ?Theme
    {
        $preset = $state['preset'] ?? null;

        if (blank($preset) || ($preset === self::FOLLOW_PANEL)) {
            return null;
        }

        $image = $state['background_image'] ?? null;

        try {
            return Theme::fromArray([
                ...array_filter($state, filled(...)),
                'preset' => null,
                'answer' => $state['answer'] ?? null,
                'font' => $state['font'] ?? null,
                'background_image' => is_array($image) ? (array_values($image)[0] ?? null) : $image,
                'overlay' => (int) ($state['overlay'] ?? 0),
            ]);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function text(string $key): string
    {
        return __("conversational-forms::conversational-forms.themes.{$key}");
    }
}
