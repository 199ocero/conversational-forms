# Conversational Forms for Filament

<div class="filament-hidden">

![Header](https://raw.githubusercontent.com/199ocero/conversational-forms/main/art/images/jaocero-conversational-forms.jpeg)

</div>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jaocero/conversational-forms.svg?style=flat-square)](https://packagist.org/packages/jaocero/conversational-forms)
[![Total Downloads](https://img.shields.io/packagist/dt/jaocero/conversational-forms.svg?style=flat-square)](https://packagist.org/packages/jaocero/conversational-forms)

Typeform-style conversational forms for Filament. One question at a time, fully in your Laravel app.

> [!WARNING]
> This package is in early development (`0.x`). The API may change before `v1.0.0`. See the [roadmap](https://github.com/199ocero/conversational-forms/blob/main/docs/PLAN.md).

- **23 question types**, one per screen, with a progress bar and welcome and end screens.
- **Keyboard first**: Enter to continue, letter and number shortcuts, auto-advance after a choice.
- **Logic**: jumps, conditions, multiple endings and scores, checked again on the server.
- **Build in code or in a no-code builder** inside your Filament panel.
- **No theme setup**. Dark mode, mobile and accessibility are built in.

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- Filament 5.11+

## Installation

```bash
composer require jaocero/conversational-forms
php artisan filament:assets
```

That's it. No custom theme or Vite changes needed. Run `php artisan filament:assets` again after every update of the package.

## Quick start

Add a `Conversation` to any Filament form and handle the answers in your Livewire component:

```php
use Filament\Schemas\Schema;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\Concerns\InteractsWithConversation;
use JaOcero\ConversationalForms\Livewire\ConversationResult;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Screens\EndScreen;

class Onboarding extends Page
{
    use InteractsWithConversation;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Conversation::make([
                    ShortText::make('name')->label("What's your name?")->required(),
                    Email::make('email')->label('Nice to meet you, {name}! Your email?')->required(),
                    MultipleChoice::make('plan')
                        ->label('Which plan are you interested in?')
                        ->options(['starter' => 'Starter', 'team' => 'Team', 'enterprise' => 'Enterprise']),
                ])->endScreen(EndScreen::make()->title('Thanks, {name}!')),
            ]);
    }

    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        Lead::create($data); // the plugin never stores anything, you decide
    }
}
```

On a Filament `Page`, render it with `EmbeddedSchema::make('form')` in the page's `content()` schema.

## Question types

There are **23 question types**, and any other Filament field works inside a conversation too.

| Group | Questions |
| --- | --- |
| Text | `ShortText`, `LongText`, `Email`, `Website`, `Phone`, `Number` |
| Choice | `MultipleChoice`, `PictureChoice`, `Dropdown`, `YesNo`, `Checkbox`, `LegalConsent` |
| Scales | `OpinionScale`, `Nps`, `Rating`, `Matrix`, `Ranking` |
| Date and time | `DateQuestion`, `TimeQuestion` |
| Details | `ContactInfo`, `Address` |
| Files and media | `FileUpload`, `Signature`, `AudioAnswer`, `VideoAnswer` |

Use `Statement`, `QuestionGroup` and `HiddenField` to structure a conversation. See [all questions and options](https://github.com/199ocero/conversational-forms/blob/main/docs/questions.md).

## Logic

Decide where to go after each answer:

```php
use JaOcero\ConversationalForms\Logic\Logic;

MultipleChoice::make('plan')
    ->options([...])
    ->logic([
        Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
        Logic::when('plan', 'is', 'none')->end(),
    ]);
```

Conditions, scores, endings and show/hide are covered in the [logic guide](https://github.com/199ocero/conversational-forms/blob/main/docs/logic.md).

## Public pages

Serve a full-screen conversation at a public URL, no panel or login needed:

```php
class JobApplication extends ConversationPage
{
    public function conversation(Conversation $conversation): Conversation
    {
        return $conversation->components([/* ... */]);
    }
}

Route::livewire('/apply', JobApplication::class);
```

See [public pages](https://github.com/199ocero/conversational-forms/blob/main/docs/public-pages.md).

## No-code builder

Let your team build conversations in a Filament panel, with a live preview, logic, publishing and response analytics:

```php
$panel->plugin(ConversationalFormsPlugin::make());
```

```bash
php artisan conversational-forms:install
```

See [the builder](https://github.com/199ocero/conversational-forms/blob/main/docs/builder.md).

## Learn more

- [Questions](https://github.com/199ocero/conversational-forms/blob/main/docs/questions.md): every question type and conversation option
- [Logic](https://github.com/199ocero/conversational-forms/blob/main/docs/logic.md): jumps, conditions, scores, endings and saving answers
- [Public pages](https://github.com/199ocero/conversational-forms/blob/main/docs/public-pages.md)
- [The builder](https://github.com/199ocero/conversational-forms/blob/main/docs/builder.md): responses, sharing, spam protection and events
- [Theming](https://github.com/199ocero/conversational-forms/blob/main/docs/theming.md): bundled themes and CSS variables
- [Testing](https://github.com/199ocero/conversational-forms/blob/main/docs/testing.md)
- [Keyboard, accessibility and troubleshooting](https://github.com/199ocero/conversational-forms/blob/main/docs/keyboard-and-accessibility.md)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Jay-Are Ocero](https://github.com/199ocero)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
