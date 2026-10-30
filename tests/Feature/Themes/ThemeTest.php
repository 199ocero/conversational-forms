<?php

use Filament\Facades\Filament;
use JaOcero\ConversationalForms\Builder\ThemeSchema;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\EditConversationalForm;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Tests\Fixtures\Models\User;
use JaOcero\ConversationalForms\Themes\Contrast;
use JaOcero\ConversationalForms\Themes\Fonts;
use JaOcero\ConversationalForms\Themes\Theme;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

describe('contrast', function () {
    it('measures contrast as WCAG does', function (string $first, string $second, float $ratio) {
        expect(Contrast::ratio($first, $second))->toBe($ratio);
    })->with([
        'black on white' => ['#000000', '#ffffff', 21.0],
        'the same colour' => ['#4f46e5', '#4f46e5', 1.0],
        'grey just under AA' => ['#777777', '#ffffff', 4.48],
        'short hex' => ['#fff', '#000', 21.0],
    ]);

    it('rejects colours that aren\'t hex', function () {
        Contrast::normalize('red');
    })->throws(InvalidArgumentException::class, 'is not a hex colour');
});

describe('Theme', function () {
    it('ships presets that are all readable', function (string $preset) {
        expect(Theme::preset($preset)->contrastIssues())->toBe([]);
    })->with(array_keys(Theme::PRESETS));

    it('knows dark themes from light ones, and picks readable button text', function () {
        expect(Theme::preset('midnight')->isDark())->toBeTrue()
            ->and(Theme::preset('paper')->isDark())->toBeFalse()
            ->and(Theme::preset('midnight')->accentContrast())->toBe('#0a0a0a')
            ->and(Theme::preset('forest')->accentContrast())->toBe('#ffffff');
    });

    it('explains which colours are hard to read', function () {
        $issues = Theme::make()->background('#ffffff')->text('#bbbbbb')->accent('#fde68a')->contrastIssues();

        expect($issues)->toHaveCount(3)
            ->and($issues[0])->toBe('Questions on the background: 1.9:1 (at least 4.5:1 is recommended).')
            ->and($issues[2])->toStartWith('Buttons and answers on the background');
    });

    it('sets the conversation\'s variables', function () {
        $variables = Theme::make()->accent('#E11D48')->background('#fff')->text('#111827')->font('Lora')->radius('large')->toCssVariables();

        expect($variables)->toMatchArray([
            '--cf-accent' => '#e11d48',
            '--cf-accent-contrast' => '#ffffff',
            '--cf-bg' => '#ffffff',
            '--cf-fg' => '#111827',
            '--cf-answer-color' => '#111827',
            '--cf-font-family' => "'Lora', ui-serif, Georgia, serif",
            '--cf-radius' => '1rem',
        ])->not->toHaveKey('--cf-bg-image');
    });

    it('covers a background image with the background colour', function () {
        $variables = Theme::make()->background('#0f172a')->backgroundImage('conversational-forms/images/sky.jpg')->overlay(60)->toCssVariables();

        expect($variables['--cf-bg-image'])->toBe('url("/storage/conversational-forms/images/sky.jpg")')
            ->and($variables['--cf-bg-overlay'])->toBe('rgb(15 23 42 / 0.6)');
    });

    it('loads fonts from Bunny Fonts, or not at all', function () {
        expect(Fonts::stylesheetUrl('IBM Plex Sans'))->toBe('https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700&display=swap')
            ->and(Fonts::stylesheetUrl(null))->toBeNull();

        config()->set('conversational-forms.fonts.url', null);

        expect(Fonts::stylesheetUrl('Inter'))->toBeNull();
    });

    it('round-trips through an array', function () {
        $theme = Theme::preset('sunset')->backgroundImage('https://example.com/a.jpg')->overlay(40);

        expect(Theme::fromArray($theme->toArray())->toArray())->toBe($theme->toArray());
    });

    it('rejects unknown fonts, radii and presets', function (Closure $make, string $message) {
        expect($make)->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'font' => [fn () => Theme::make()->font('Comic Sans'), 'Unknown font [Comic Sans]'],
        'radius' => [fn () => Theme::make()->radius('huge'), 'A radius must be one of'],
        'preset' => [fn () => Theme::preset('neon'), 'no theme preset called [neon]'],
    ]);
});

describe('themed conversations', function () {
    it('styles a conversation defined in code', function () {
        conversation(Conversation::make([ShortText::make('name')])->theme(Theme::preset('midnight')))
            ->assertSeeHtml('data-cf-theme="dark"')
            ->assertSeeHtml('--cf-bg: #0f172a')
            ->assertSeeHtml('href="https://fonts.bunny.net/css?family=inter:400,500,600,700&amp;display=swap"');
    });

    it('leaves unthemed conversations to the panel', function () {
        conversation(Conversation::make([ShortText::make('name')]))
            ->assertDontSeeHtml('data-cf-theme')
            ->assertDontSeeHtml('fonts.bunny.net');
    });

    it('shows the published theme on the public page, in its own light or dark mode', function () {
        $form = ConversationalForm::factory()->create(['theme' => Theme::preset('midnight')->toArray()]);
        $form->publish();

        get("/f/{$form->slug}")
            ->assertOk()
            ->assertSee('--default-theme-mode: dark', escape: false)
            ->assertSee("classList.toggle('dark', true)", escape: false)
            ->assertSee('background-color: #0f172a !important', escape: false);
    });

    it('keeps showing the published theme while the draft\'s changes', function () {
        $form = ConversationalForm::factory()->create(['theme' => Theme::preset('paper')->toArray()]);
        $form->publish();
        $form->update(['theme' => Theme::preset('midnight')->toArray()]);

        expect($form->hasUnpublishedChanges())->toBeTrue();

        get("/f/{$form->slug}")->assertSee('--cf-bg: #fbfaf7', escape: false);
    });
});

describe('the Design tab', function () {
    beforeEach(function () {
        actingAs(User::query()->create(['name' => 'Jay', 'email' => 'jay@example.com', 'password' => 'secret']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    });

    it('starts from a preset and saves the theme', function () {
        $form = ConversationalForm::factory()->create();

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->assertSchemaStateSet(['theme.preset' => 'panel'])
            ->set('data.theme.preset', 'forest')
            ->assertSchemaStateSet(['theme.accent' => '#2f6f3e', 'theme.font' => 'Lora'])
            ->set('data.theme.accent', '#14532d')
            ->call('save')
            ->assertHasNoFormErrors();

        expect($form->refresh()->theme)->toMatchArray(['preset' => 'forest', 'accent' => '#14532d', 'background' => '#f1f5ef', 'font' => 'Lora']);
    });

    it('follows the panel again without a theme', function () {
        $form = ConversationalForm::factory()->create(['theme' => Theme::preset('mono')->toArray()]);

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->assertSchemaStateSet(['theme.background' => '#ffffff'])
            ->set('data.theme.preset', 'panel')
            ->call('save');

        expect($form->refresh()->theme)->toBeNull();
    });

    it('converts between stored themes and the tab', function () {
        expect(ThemeSchema::toState(null))->toBe(['preset' => 'panel', 'overlay' => 0, 'radius' => 'medium'])
            ->and(ThemeSchema::fromState(['preset' => 'panel', 'accent' => '#000000']))->toBeNull()
            ->and(ThemeSchema::fromState([...Theme::preset('lavender')->toArray(), 'preset' => 'lavender']))->toMatchArray(['preset' => 'lavender', 'radius' => 'full'])
            // Half-typed colours don't break the preview.
            ->and(ThemeSchema::fromState(['preset' => 'paper', 'accent' => '#12', 'background' => '#fff', 'text' => '#000']))->toBeNull();
    });
});
