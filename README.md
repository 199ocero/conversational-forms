# Conversational Forms for Filament

Typeform-style conversational forms for Filament. One question at a time, fully in your Laravel app.

> [!WARNING]
> This package is in early development (`0.x`). The API may change before `v1.0.0`. See the [roadmap](docs/PLAN.md).

- **One question per screen**, with smooth transitions, a progress bar, and welcome and end screens.
- **Keyboard first**: Enter to continue, letter and number shortcuts, auto-advance after a choice.
- **Logic jumps** that are re-checked on the server, so skipped questions are never required and answers cannot be forged.
- **Works with any Filament field.** The plugin's own question fields look the part, and every other field (built-in or third-party) still works inside a conversation.
- **Your data stays yours.** Answers go through Filament's normal validation and state handling, and are handed back to you to save however you like.
- **Public pages** that work with or without a Filament panel, no login required.
- Accessible, dark-mode aware and mobile friendly, with **no theme setup**.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [The conversation](#the-conversation)
- [Questions](#questions)
- [Logic](#logic)
- [Saving answers](#saving-answers)
- [Public pages](#public-pages)
- [Using other Filament fields](#using-other-filament-fields)
- [Keyboard shortcuts](#keyboard-shortcuts)
- [Theming](#theming)
- [Testing](#testing)
- [Accessibility](#accessibility)
- [Troubleshooting](#troubleshooting)

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- Filament 5
- Browsers: the last two versions of Chrome, Edge, Firefox and Safari (Safari 16.4+, Chrome 111+, Firefox 128+)

## Installation

```bash
composer require jaocero/conversational-forms
php artisan filament:assets
```

That's it. The plugin ships its own compiled stylesheet and scripts, and loads them only on pages that contain a conversation, so you don't need a custom theme or any Vite changes.

> [!NOTE]
> Run `php artisan filament:assets` again after every update of the package. Filament's installer adds it to your `post-autoload-dump` Composer script.

## Quick start

Add a `Conversation` to any Filament form, then let your Livewire component handle the submission:

```php
use Filament\Schemas\Schema;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\Concerns\InteractsWithConversation;
use JaOcero\ConversationalForms\Livewire\ConversationResult;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\OpinionScale;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;

class Onboarding extends Page // or any Livewire component that uses InteractsWithSchemas
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
                    ShortText::make('name')
                        ->label("What's your name?")
                        ->required(),

                    Email::make('email')
                        ->label('Where can we reach you?')
                        ->required(),

                    MultipleChoice::make('plan')
                        ->label('Which plan are you interested in?')
                        ->options([
                            'starter' => 'Starter',
                            'enterprise' => 'Enterprise',
                        ])
                        ->required()
                        ->logic([
                            Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
                            Logic::always()->jumpTo('recommend'),
                        ]),

                    ShortText::make('company_size')
                        ->label('How big is your team?'),

                    OpinionScale::make('recommend')
                        ->label('How likely are you to recommend us?'),
                ])
                    ->welcomeScreen(WelcomeScreen::make()->title('Get started in a minute'))
                    ->endScreen(EndScreen::make()->title('Thanks, we will be in touch!')),
            ]);
    }

    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        Lead::create($data);
    }
}
```

On a Filament `Page`, render the form with `EmbeddedSchema::make('form')` in the page's `content()` schema, as you would for any page form.

## The conversation

Every child of a `Conversation` becomes one screen. The conversation shows them one at a time, validates each screen before moving on, and works out which question comes next from the logic you define.

```php
Conversation::make([...])
    ->welcomeScreen(WelcomeScreen::make()->title('Hi!')->description('...')->buttonLabel('Start'))
    ->endScreen(EndScreen::make()->title('Thanks!')->description('...')->button('Back to our site', url('/')))
    ->progressBar()               // default: true
    ->navigationButtons()         // previous / next arrows, default: true
    ->autoAdvance(true, delay: 400) // move on after a single choice, default: true
    ->fullScreen()                // fill the viewport and listen to keys on the whole page
    ->submitUsing('create')       // Livewire method called after the last question, default: submitConversation
```

| Method | Description |
| --- | --- |
| `welcomeScreen()` | An optional first screen with a start button. |
| `endScreen()` | The screen shown after submitting. A default "Thanks" screen is shown when none is set. |
| `progressBar()` | Show how far along the respondent is. It follows the actual path, so skipped questions don't count. |
| `navigationButtons()` | Previous and next arrow buttons. Keyboard shortcuts work either way. |
| `autoAdvance()` | Move to the next question as soon as a single-choice question is answered. |
| `fullScreen()` | Used by public pages. Embedded conversations only react to keys while focus is inside them. |
| `submitUsing()` | The Livewire method called once the last question is answered. |
| `logic()` | Logic for fields that aren't conversation questions, keyed by step name. See [Logic](#logic). |

Every question also supports Filament's usual field methods: `label()`, `required()`, `rules()`, `default()`, `disabled()`, `visible()`, `dehydrated()`, `afterStateUpdated()`, and so on.

## Questions

All questions share these options:

| Method | Description |
| --- | --- |
| `label()` | The question. |
| `description()` | Supporting text under the question. |
| `logic()` | Rules deciding where to go after this question. See [Logic](#logic). |
| `validationAttribute()` | The name used in validation messages. Defaults to "answer", since question labels are full sentences. |

Questions are numbered automatically by their position in the conversation.

### ShortText and Email

A single-line answer. Press Enter to continue.

```php
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\ShortText;

ShortText::make('name')
    ->label("What's your name?")
    ->placeholder('Jane Doe')
    ->maxLength(80)
    ->autocomplete('name');

Email::make('email')->label('Where can we reach you?'); // validates the address
```

`ShortText` supports `placeholder()`, `minLength()`, `maxLength()`, `length()`, `autocomplete()`, `inputMode()` and `extraInputAttributes()`.

### LongText

A multi-line answer. Enter continues and Shift + Enter adds a line break.

```php
use JaOcero\ConversationalForms\Questions\LongText;

LongText::make('notes')
    ->label('Anything else?')
    ->rows(4)
    ->maxLength(2000)
    ->submitOnEnter(false); // Enter adds a line break; Ctrl / Cmd + Enter continues
```

### MultipleChoice

Answers labelled A, B, C… that can be picked by pressing the letter. Picking a single answer moves on automatically.

```php
use JaOcero\ConversationalForms\Questions\MultipleChoice;

MultipleChoice::make('plan')
    ->label('Which plan?')
    ->options([
        'starter' => 'Starter',
        'team' => 'Team',
        'enterprise' => 'Enterprise',
    ])
    ->other()       // adds an "Other" answer with a text input; the typed text is stored
    ->randomize();  // shuffles the answers, in a stable order for each respondent

MultipleChoice::make('channels')
    ->label('How did you hear about us?')
    ->options([...])
    ->multiple()
    ->minSelections(1)
    ->maxSelections(2);
```

`options()` accepts an array, a closure or an enum, like Filament's `Select`. Only the given options (or a typed "Other" answer) pass validation. A multiple choice question stores an array.

### YesNo

Two answers, picked with Y or N. Stores `true` or `false`.

```php
use JaOcero\ConversationalForms\Questions\YesNo;

YesNo::make('newsletter')
    ->label('Would you like product updates?')
    ->yesLabel('Yes, please')
    ->noLabel('No, thanks');
```

### OpinionScale

A row of numbers, picked by typing the number. On a 0–10 scale, type `1` then `0` to pick 10. Stores an integer.

```php
use JaOcero\ConversationalForms\Questions\OpinionScale;

OpinionScale::make('recommend')
    ->label('How likely are you to recommend us?')
    ->range(0, 10)          // default; up to 11 steps
    ->startLabel('Not likely')
    ->endLabel('Very likely');
```

## Logic

Logic decides which question comes next. Rules are attached to a question and evaluated in order after it is answered: the first rule whose conditions match wins. Without a matching rule, the conversation moves to the next question.

```php
use JaOcero\ConversationalForms\Logic\Logic;

MultipleChoice::make('plan')
    ->options([...])
    ->logic([
        Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
        Logic::when('plan', 'is', 'none')->end(),          // skip to the end
        Logic::always()->jumpTo('recommend'),             // fallback
    ]);

// Several conditions must all match:
Logic::when('plan', 'is', 'team')->and('seats', 'is_not', '1')->jumpTo('team_size');
```

Questions are referred to by their field name. The operators available in this version are `is` (also `=`) and `is_not` (also `!=`); more operators, and/or groups, scoring and multiple endings are on the [roadmap](docs/PLAN.md).

**Logic is safe by design:**

- Jumps can only move forward, so a conversation can never loop. A rule that jumps backwards, or to a question that doesn't exist, throws an `InvalidLogicException` with a clear message as soon as the conversation is rendered.
- Questions that logic skips are not required, and their answers are not saved.
- The path is worked out again on the server when the conversation is submitted. A modified browser cannot skip a required question or submit answers to questions that were not on its path.
- Answers to questions that are no longer on the path (for example after the respondent goes back and changes an earlier answer) never influence later rules.

For fields that aren't conversation questions (see [Using other Filament fields](#using-other-filament-fields)), pass logic to the conversation, keyed by the field name:

```php
Conversation::make([
    Select::make('country')->options([...]),
    ShortText::make('state'),
    Email::make('email'),
])->logic([
    'country' => [Logic::when('country', 'is', 'ph')->jumpTo('email')],
]);
```

## Saving answers

The plugin never stores anything. Answers go through Filament's normal validation and dehydration, and `InteractsWithConversation` hands them to your component once the conversation is complete:

```php
use JaOcero\ConversationalForms\Livewire\ConversationResult;

protected function handleConversationSubmission(array $data, ConversationResult $result): void
{
    // $data: the validated answers, without skipped questions.
    // $result->path: the questions the respondent went through, in order.
    // $result->ending: the end screen that was shown.

    Application::create($data);
}
```

If your schema isn't called `form`, override `getConversationSchemaName()`. To use your own method instead (for example `create` on a Filament create page), call `->submitUsing('create')` on the conversation.

## Public pages

`ConversationPage` serves a full-screen conversation at a public URL. It doesn't need a Filament panel or a logged-in user.

```php
namespace App\Livewire;

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\ConversationPage;
use JaOcero\ConversationalForms\Livewire\ConversationResult;

class JobApplication extends ConversationPage
{
    protected static ?string $title = 'Apply: Senior Laravel Developer';

    public function conversation(Conversation $conversation): Conversation
    {
        return $conversation->components([
            // ...
        ]);
    }

    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        Applicant::create($data);
    }
}
```

```php
// routes/web.php
use App\Livewire\JobApplication;

Route::livewire('/apply', JobApplication::class)->middleware('throttle:60,1');
```

The page uses a minimal layout with Filament's styles, follows the visitor's light or dark mode, and works without a Vite build. To use your own layout, set `protected static string $layout = 'layouts.app';` on the page; your layout must include `@filamentStyles` and `@filamentScripts`.

## Using other Filament fields

Any Filament field can be a question: built-in fields like `Select` and `DatePicker`, and third-party fields. They keep their own look inside the conversation, and Enter moves on (except while a dropdown or popup is open).

```php
Conversation::make([
    ShortText::make('name'),
    Select::make('country')->options($countries)->searchable(),
    DatePicker::make('start_date'),
]);
```

Field authors can opt in to auto-advance and keyboard hints by implementing `JaOcero\ConversationalForms\Contracts\ConversationAware` and dispatching a bubbling `cf-answered` DOM event once the answer is complete. To stop the conversation from reacting to keys inside a widget, add a `data-cf-keyboard-ignore` attribute to it.

When a step becomes visible, the conversation dispatches a `cf-step-shown` window event (with `{ conversation, step, element }`) and a `resize` event, so JavaScript-heavy fields that were initialised while hidden can measure themselves again.

## Keyboard shortcuts

| Keys | Action |
| --- | --- |
| Enter | Continue (validates the current question) |
| Ctrl / Cmd + Enter | Continue from anywhere, including a long text answer |
| Shift + Enter | Line break in a long text answer |
| Alt + ↓ / Alt + ↑ | Next / previous question |
| A–Z | Pick a multiple choice answer |
| Y / N | Answer a yes / no question |
| 0–9 | Pick a value on an opinion scale (type `1` `0` for 10) |
| ↑ ↓ ← → | Move between answers |
| Space | Toggle the focused answer |
| Tab / Shift + Tab | Move between controls on the current question |

## Theming

Conversations follow your Filament panel's colors and dark mode automatically. To change them, set any of these CSS variables on `.cf-conversation`, for example in your theme or with `extraAttributes()`:

| Variable | Default | Used for |
| --- | --- | --- |
| `--cf-accent` | `--primary-600` (`--primary-400` in dark mode) | Buttons, answers, focus rings |
| `--cf-accent-contrast` | white | Text on the accent color |
| `--cf-fg` / `--cf-muted` | `--gray-950` / `--gray-500` | Text |
| `--cf-bg` | transparent | Background |
| `--cf-answer-color` | `--cf-fg` | Text typed into answers (set it to `var(--cf-accent)` for accent-coloured answers) |
| `--cf-surface` | white (`--gray-900` in dark mode) | Background of the "Other" text field |
| `--cf-danger` | `--danger-600` | Validation messages |
| `--cf-font-family` | Filament's font | Font |
| `--cf-question-size` / `--cf-answer-size` | `1.875rem` / `1.5rem` on larger screens | Question and answer text size |
| `--cf-radius` | `0.5rem` | Corner radius |
| `--cf-content-width` | `42rem` | Width of full-screen conversations |
| `--cf-transition-duration` | `450ms` (0 with reduced motion) | Transitions |

```php
Conversation::make([...])->extraAttributes(['style' => '--cf-accent: #0f766e; --cf-radius: 1rem']);
```

Every element has a stable class you can target: `cf-conversation`, `cf-progress`, `cf-step`, `cf-question`, `cf-question-number`, `cf-question-label`, `cf-question-description`, `cf-question-error`, `cf-input`, `cf-textarea`, `cf-choices`, `cf-choice`, `cf-choice-key`, `cf-scale`, `cf-scale-option`, `cf-button`, `cf-hint`, `cf-navigation`, `cf-screen`, `cf-welcome-screen` and `cf-end-screen`. The active step has `cf-active` and a selected answer has `cf-selected`.

## Testing

The plugin adds helpers to Livewire's testing API:

```php
use Livewire\Livewire;

Livewire::test(Onboarding::class)
    ->set('data.name', 'Jay')
    ->advanceConversation('name')
    ->assertConversationAdvancedTo('email')
    ->set('data.email', 'jay@example.com')
    ->set('data.plan', 'starter')
    ->assertConversationPath(['name', 'email', 'plan', 'recommend'])
    ->submitConversation()
    ->assertConversationCompleted();
```

| Method | Description |
| --- | --- |
| `advanceConversation($step)` | Validate a question and move past it, like pressing Enter. |
| `assertConversationAdvancedTo($step)` | Assert where the last `advanceConversation()` went (`null` after the last question). |
| `assertConversationPath($steps)` | Assert the questions the respondent would go through, given the current answers. |
| `submitConversation()` | Submit the conversation. |
| `assertConversationCompleted($ending = null)` | Assert it was submitted without errors. |

## Accessibility

- Every question is properly labelled, and descriptions and errors are linked with `aria-describedby`. Choices use radio group and checkbox semantics with roving focus, and shortcuts are announced with `aria-keyshortcuts`.
- Inactive questions are `inert`, so they're skipped by keyboard and screen readers. Focus moves to the new question after every transition, and a live region announces the progress ("Question 3 of 8").
- Validation errors move focus to the invalid answer and are announced.
- Focus is always visible, and transitions are turned off for people who prefer reduced motion.

## Troubleshooting

**The conversation is unstyled or doesn't react.** Run `php artisan filament:assets` and hard-refresh the page.

**Enter does something unexpected inside a third-party field.** Add `data-cf-keyboard-ignore` to the field's element via `extraAttributes()`. Ctrl / Cmd + Enter always continues.

**A field that depends on another answer doesn't update while typing.** Questions skip the server render after each change, because every question is rendered once. If you use `->live()` with `afterStateUpdated()` to change other parts of the form, call `->skipRenderAfterStateUpdated(false)` on that question.

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
