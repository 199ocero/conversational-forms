<?php

namespace JaOcero\ConversationalForms\Builder;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use JaOcero\ConversationalForms\Support\Views;
use JaOcero\ConversationalForms\Themes\Fonts;
use JaOcero\ConversationalForms\Themes\Theme;

/**
 * The builder's Design tab: a preset to start from, then colours, font,
 * corner radius and background image, with a live preview and contrast
 * warnings. Edits the form state's `theme`.
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
            Section::make(self::text('design'))
                ->description(self::text('design_description'))
                ->statePath('theme')
                ->columns(['default' => 1, 'lg' => 2])
                ->schema([
                    Grid::make(1)->schema([
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
                        Grid::make(2)
                            ->schema([
                                ColorPicker::make('accent')->label(self::text('accent'))->hex()->required()->live(onBlur: true),
                                ColorPicker::make('background')->label(self::text('background'))->hex()->required()->live(onBlur: true),
                                ColorPicker::make('text')->label(self::text('text'))->hex()->required()->live(onBlur: true),
                                ColorPicker::make('answer')->label(self::text('answer'))->hex()->placeholder(self::text('same_as_text'))->live(onBlur: true),
                                Select::make('font')->label(self::text('font'))->options(Fonts::options())->placeholder(self::text('system_font'))->live(),
                                ToggleButtons::make('radius')
                                    ->label(self::text('radius'))
                                    ->options(array_combine(array_keys(Theme::RADII), array_map(fn (string $radius): string => self::text("radii.{$radius}"), array_keys(Theme::RADII))))
                                    ->default('medium')
                                    ->inline()
                                    ->live(),
                                FileUpload::make('background_image')
                                    ->label(self::text('background_image'))
                                    ->image()
                                    ->disk(config('conversational-forms.images.disk', 'public'))
                                    ->directory(config('conversational-forms.images.directory'))
                                    ->visibility('public')
                                    ->live(),
                                Slider::make('overlay')
                                    ->label(self::text('overlay'))
                                    ->helperText(self::text('overlay_help'))
                                    ->range(0, 90)
                                    ->step(5)
                                    ->default(0)
                                    ->required(false)
                                    ->live(),
                            ])
                            ->visible(fn (Get $get): bool => ($get('preset') ?? self::FOLLOW_PANEL) !== self::FOLLOW_PANEL),
                    ]),
                    Html::make(fn (Get $get): HtmlString => new HtmlString(view(Views::name('builder.theme-preview'), [
                        'theme' => self::themeFrom([
                            'preset' => $get('preset'),
                            'accent' => $get('accent'),
                            'background' => $get('background'),
                            'text' => $get('text'),
                            'answer' => $get('answer'),
                            'font' => $get('font'),
                            'background_image' => $get('background_image'),
                            'overlay' => $get('overlay'),
                            'radius' => $get('radius'),
                        ]),
                    ])->render())),
                ]),
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
