# Conversational Forms for Filament

Typeform-style conversational forms for Filament. One question at a time, fully in your Laravel app.

> [!WARNING]
> This package is in early development (`0.x`). The API may change before `v1.0.0`. See the [roadmap](docs/PLAN.md).

- **One question per screen**, with smooth transitions, a progress bar, and welcome and end screens.
- **Keyboard first**: Enter to continue, letter and number shortcuts, auto-advance after a choice.
- **Logic**: jumps, and/or conditions, show/hide, multiple endings, redirects, and scores, re-checked on the server so skipped questions are never required and results can't be forged.
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
- [Structuring a conversation](#structuring-a-conversation)
- [Logic](#logic)
- [Saving answers](#saving-answers)
- [Public pages](#public-pages)
- [The builder](#the-builder)
- [Using other Filament fields](#using-other-filament-fields)
- [Displaying answers](#displaying-answers)
- [Keyboard shortcuts](#keyboard-shortcuts)
- [Theming](#theming)
- [Testing](#testing)
- [Accessibility](#accessibility)
- [Troubleshooting](#troubleshooting)

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- Filament 5.11+
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
| `endScreen()` / `endScreens()` | The screen shown after submitting, or several chosen by logic. A default "Thanks" screen is shown when none is set. See [Endings](#endings). |
| `variables()` | Variables and their starting values. See [Variables and scores](#variables-and-scores). |
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
| `media()` | An image shown with the question: beside it on wide screens, above the answer on small ones. `->media($url, alt: 'Our new dashboard')` |
| `logic()` | Rules deciding where to go after this question. See [Logic](#logic). |
| `validationAttribute()` | The name used in validation messages. Defaults to "answer", since question labels are full sentences. |

Questions are numbered automatically by their position in the conversation. Labels and descriptions can include answers to earlier questions, see [Answer piping](#answer-piping).

| Question | Answer | Keys |
| --- | --- | --- |
| [`ShortText`](#shorttext-email-website-and-phone) | text | |
| [`Email`](#shorttext-email-website-and-phone) | email address | |
| [`Website`](#shorttext-email-website-and-phone) | URL | |
| [`Phone`](#shorttext-email-website-and-phone) | phone number | |
| [`Number`](#number) | integer or float | |
| [`LongText`](#longtext) | multi-line text | Shift + Enter for a line break |
| [`MultipleChoice`](#multiplechoice) | value, or a list of values | A–Z |
| [`PictureChoice`](#picturechoice) | value, or a list of values | A–Z |
| [`Dropdown`](#dropdown) | value | type to filter, ↑ ↓, Enter |
| [`YesNo`](#yesno) | `true` / `false` | Y / N |
| [`Checkbox`](#checkbox) | `true` / `false` | Space |
| [`LegalConsent`](#legalconsent) | `true` / `false` | Y / N |
| [`OpinionScale`](#opinionscale-and-nps) | integer | 0–9 |
| [`Nps`](#opinionscale-and-nps) | integer from 0 to 10 | 0–9 |
| [`Rating`](#rating) | integer from 1 | 1–9, 0 for 10 |
| [`Matrix`](#matrix) | `['row' => 'answer']` | arrows within a row, Tab between rows |
| [`Ranking`](#ranking) | values in order | Space to pick up, ↑ ↓ to move |
| [`DateQuestion`](#datequestion-and-timequestion) | `YYYY-MM-DD` | digits, moves between boxes |
| [`TimeQuestion`](#datequestion-and-timequestion) | `HH:MM` | digits, moves between boxes |
| [`ContactInfo`](#contactinfo-and-address) | `['first_name' => …, 'email' => …]` | |
| [`Address`](#contactinfo-and-address) | `['line1' => …, 'city' => …]` | |
| [`FileUpload`](#fileupload) | stored file path(s) | |
| [`Signature`](#signature) | stored PNG path | |

### ShortText, Email, Website and Phone

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

Website::make('website')->label("What's your website?"); // validates the URL

Phone::make('phone')->label("What's your number?");
```

All four support `placeholder()`, `minLength()`, `maxLength()`, `length()`, `autocomplete()`, `inputMode()` and `extraInputAttributes()`.

`Phone` accepts common formats such as `+63 917 123 4567` or `(02) 8123-4567` and saves the number without spaces, dashes and brackets (`+639171234567`). Call `->normalize(false)` to save it as typed. For per-country validation, install [`propaganistas/laravel-phone`](https://github.com/Propaganistas/Laravel-Phone) and add its rule: `->rule('phone:PH')`.

### Number

A number, saved as an integer or a float.

```php
use JaOcero\ConversationalForms\Questions\Number;

Number::make('team_size')
    ->label('How many people are on your team?')
    ->integer()
    ->minValue(1)
    ->maxValue(10000);
```

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

### PictureChoice

Like `MultipleChoice`, with an image for each answer. It supports the same options, including `multiple()`.

```php
use JaOcero\ConversationalForms\Questions\PictureChoice;

PictureChoice::make('style')
    ->label('Which style do you prefer?')
    ->options(['modern' => 'Modern', 'classic' => 'Classic'])
    ->images([
        'modern' => asset('images/modern.jpg'),
        'classic' => asset('images/classic.jpg'),
    ])
    ->hiddenLabels(); // optional: show only the images (labels stay available to screen readers)
```

### Dropdown

A searchable list for questions with many answers. Type to filter, use ↑ and ↓ to move, and Enter to choose. While the list is open, Enter picks an answer instead of continuing.

```php
use JaOcero\ConversationalForms\Questions\Dropdown;

Dropdown::make('country')
    ->label('Where are you based?')
    ->options(Country::class); // an array, a closure or an enum
```

### YesNo

Two answers, picked with Y or N. Stores `true` or `false`.

```php
use JaOcero\ConversationalForms\Questions\YesNo;

YesNo::make('newsletter')
    ->label('Would you like product updates?')
    ->yesLabel('Yes, please')
    ->noLabel('No, thanks');
```

### Checkbox

A single tick box. Space ticks it and Enter continues. Use `accepted()` when it must be ticked.

```php
use JaOcero\ConversationalForms\Questions\Checkbox;

Checkbox::make('updates')
    ->label('Product updates')
    ->checkboxLabel('Send me occasional product updates');
```

### LegalConsent

Ask the respondent to accept terms. Saved as `true` (accepted) or `false` (declined). Pass an `HtmlString` to include links.

- `required()` means an answer must be chosen. Declining is a valid answer, so you can handle it with logic, for example by ending the conversation.
- `accepted()` means only accepting lets the respondent continue. They see "Please accept to continue." otherwise.

```php
use Illuminate\Support\HtmlString;
use JaOcero\ConversationalForms\Questions\LegalConsent;

LegalConsent::make('consent')
    ->label('Do you accept our terms?')
    ->terms(new HtmlString('Read our <a href="/privacy">privacy policy</a>.'))
    ->required()
    ->logic([Logic::when('consent', 'is', false)->endWith('declined')]);
```

### OpinionScale and Nps

A row of numbers, picked by typing the number. On a 0–10 scale, type `1` then `0` to pick 10. Stores an integer.

```php
use JaOcero\ConversationalForms\Questions\OpinionScale;

OpinionScale::make('recommend')
    ->label('How likely are you to recommend us?')
    ->range(0, 10)          // default; up to 11 steps
    ->startLabel('Not likely')
    ->endLabel('Very likely');
```

`Nps` is a 0–10 scale with Net Promoter Score labels. `Nps::getCategory($score)` returns `'detractor'` (0–6), `'passive'` (7–8) or `'promoter'` (9–10).

```php
use JaOcero\ConversationalForms\Questions\Nps;

Nps::make('nps')->label('How likely are you to recommend us to a friend?');
```

### Rating

Stars, hearts or thumbs, picked by typing the number. Stores an integer from 1.

```php
use JaOcero\ConversationalForms\Questions\Rating;

Rating::make('rating')
    ->label('How would you rate your experience?')
    ->steps(5)        // 3 to 10, default 5
    ->shape('heart'); // star (default), heart or thumb
```

### Matrix

Several statements rated on the same answers, as a grid on wide screens and stacked on small ones. Uses native radio buttons (or checkboxes with `multiple()`), so arrows move between answers in a row and Tab between rows. When required, every row must be answered.

```php
use JaOcero\ConversationalForms\Questions\Matrix;

Matrix::make('importance')
    ->label('How important are these features?')
    ->rows(['reports' => 'Reports', 'automation' => 'Automation'])
    ->answers(['low' => 'Nice to have', 'medium' => 'Important', 'high' => 'Essential'])
    ->required();
```

Saved as `['reports' => 'high', 'automation' => 'medium']`, or a list of answers per row with `multiple()`.

### Ranking

Put the options in order by dragging (mouse, pen or touch), with the move buttons, or with the keyboard: Space picks an item up, ↑ and ↓ move it, Space drops it and Escape cancels. Every move is announced to screen readers. Saved as the values in order; the options start in the given order.

```php
use JaOcero\ConversationalForms\Questions\Ranking;

Ranking::make('priorities')
    ->label('Put these in order of importance')
    ->options(['price' => 'Price', 'speed' => 'Speed', 'support' => 'Support']);
```

### DateQuestion and TimeQuestion

Dates are typed into day, month and year boxes; focus moves to the next box as each one fills up, and Backspace in an empty box moves back. Each box also opens a list to pick from (click it or press ↓): days, month names in the app's locale, and the years between `minDate` and `maxDate` (a century back to ten years ahead when they aren't set). Typing narrows the list, Enter or a click picks a value and opens the next box's list, and values that can't make a valid date are disabled (31 under February, months before `minDate`). The calendar button opens a month view under the boxes: the arrow keys move by day and week, Page Up / Page Down by month (with Shift, by year), Enter picks and Escape closes. It opens on the answer, or on the month and year already typed, and dates outside `minDate`/`maxDate` can't be picked. Its week starts on the app locale's first day (Monday for `en`, Sunday for `en_US`). Times follow the app locale's clock: 12-hour locales (`en`, `en_US`) add an AM / PM box (press A or P, or pick from its list; it comes first where the locale writes it first, like Korean), 24-hour ones (`en_GB`, `fr`) don't, and `->twelveHour()` / `->twelveHour(false)` overrides it. Hour lists follow the clock and minutes come in steps of five; any minute can still be typed. Dates that don't exist (31 February) are rejected. Saved as `YYYY-MM-DD` (and `HH:MM` for times, always on a 24-hour clock, so `9:30 PM` is saved as `21:30`), so date logic operators work on them. Tables and infolists show times on the question's clock.

```php
use JaOcero\ConversationalForms\Questions\DateQuestion;
use JaOcero\ConversationalForms\Questions\TimeQuestion;

DateQuestion::make('start_date')
    ->label('When would you like to start?')
    ->format('MDY')      // order of the boxes: DMY (default), MDY or YMD
    ->minDate(today())
    ->maxDate(today()->addYear())
    ->firstDayOfWeek(0)  // start the calendar's week on Sunday (0) to Saturday (6)
    ->calendar(false)    // hide the calendar button
    ->dropdowns(false);  // hide the lists under the boxes

TimeQuestion::make('call_time')
    ->label("What's the best time to call?")
    ->twelveHour();      // force AM / PM; false forces 24-hour; default follows the locale
```

### ContactInfo and Address

Several inputs on one screen, saved together as an array. Each part has the right autocomplete hint and is validated on its own.

```php
use JaOcero\ConversationalForms\Questions\Address;
use JaOcero\ConversationalForms\Questions\ContactInfo;

ContactInfo::make('contact')
    ->label('How can we reach you?')
    ->parts(['first_name', 'last_name', 'email'])  // default: first_name, last_name, email, phone, company
    ->requiredParts(['first_name', 'email'])       // the default
    ->required();

Address::make('office')
    ->label('Where is your office?')
    ->required();                                  // requires line1, city, postal_code and country
```

`Address` parts are `line1`, `line2`, `city`, `state`, `postal_code` and `country`. Parts are only required when the question is required.

### FileUpload

Filament's file upload with a conversational drop zone: choose files or drag them in, see their progress, and remove them. Everything Filament's `FileUpload` supports works the same way, including disks, directories, accepted file types, size limits and multiple files.

```php
use JaOcero\ConversationalForms\Questions\FileUpload;

FileUpload::make('cv')
    ->label('Upload your CV')
    ->acceptedFileTypes(['application/pdf'])
    ->maxSize(5120)
    ->disk('s3')
    ->directory('applications');
```

### Signature

Draw a signature with a mouse, pen or finger, or choose "Type your name instead", which is the keyboard and screen reader friendly way to sign. The browser sends a PNG; the server checks that it really is a PNG within the size limit and stores it. Saved as the stored file's path.

```php
use JaOcero\ConversationalForms\Questions\Signature;

Signature::make('signature')
    ->label('Please sign to confirm')
    ->disk('local')          // default: Filament's default disk
    ->directory('signatures')
    ->maxSize(1024)          // kilobytes
    ->typing(false)          // drawing only
    ->required();
```

### AudioAnswer and VideoAnswer

Answers recorded in the browser with the microphone, or the camera and microphone. People see one **Start recording** button, a timer, and a player to listen back; **Record again** replaces the recording. Recordings are uploaded like `FileUpload` files, so `disk()`, `directory()` and `visibility()` work the same way, and the answer is the stored file's path.

```php
use JaOcero\ConversationalForms\Questions\AudioAnswer;
use JaOcero\ConversationalForms\Questions\VideoAnswer;

AudioAnswer::make('pitch')
    ->label('Tell us about your idea')
    ->maxDuration(90)      // seconds; recording stops by itself (default 120)
    ->maxSize(4096)        // kilobytes (default 5 MB)
    ->disk('s3')
    ->required();

VideoAnswer::make('intro')
    ->label('Say hi on camera')
    ->maxDuration(45)      // default 60 seconds and 12 MB
    ->allowUpload(false);  // only offer a file upload when recording isn't possible
```

When the browser can't record (no permission, no microphone, a device in use, an old browser, or a page that isn't served over HTTPS), people are told why and can upload a file instead. Chrome and Firefox record WebM, Safari MP4.

**Security.** Recordings are uploads from the public, so they get the same checks as any upload: the size limit and file type are validated on the server (the duration only in the browser, since checking it on the server would need a tool such as FFmpeg); files go through Livewire's temporary uploads, which are throttled; public pages also limit each visitor's requests; and builder-made conversations store them on a private disk by default (`conversational-forms.uploads`). Livewire's default upload limit is 12 MB; raise `livewire.temporary_file_upload.rules` for longer videos.

## Structuring a conversation

### Statement

A screen without a question, for example an introduction to a new section. It isn't numbered, and logic can jump to it.

```php
use JaOcero\ConversationalForms\Components\Statement;

Statement::make('about_you')
    ->title('Great! Now a few questions about your team.')
    ->description('This takes about a minute.')
    ->buttonLabel('Continue');
```

### QuestionGroup

Several questions on one screen, validated together. Logic attached to a question in the group runs after the whole group is answered.

```php
use JaOcero\ConversationalForms\Components\QuestionGroup;

QuestionGroup::make('full_name', [
    ShortText::make('first_name')->label('First name')->required(),
    ShortText::make('last_name')->label('Last name'),
])->label("What's your name?");
```

### Hidden fields

A value that is never shown, for example a campaign source passed in the URL (`/apply?utm_source=newsletter`). It is saved with the other answers and can be used in logic and piping. Only parameters you declare are read, and values are cut to 255 characters (`->maxLength()`).

```php
use JaOcero\ConversationalForms\Questions\HiddenField;

HiddenField::make('utm_source')->fromQuery();
HiddenField::make('referrer')->fromQuery('ref'); // read ?ref=...
```

> [!WARNING]
> Like any form state, a hidden field's value can be changed by the respondent. Treat it as untrusted input.

### Answer piping

Use `{field}` in a label, description, statement or welcome / end screen to show an earlier answer:

```php
Email::make('email')->label('Nice to meet you, {first_name}! Where can we reach you?');

EndScreen::make()->title('Thanks, {first_name}! 🎉');
```

Choices show their label (`{plan}` → "Enterprise"), yes / no answers show "Yes" or "No", and multiple answers are joined with commas. Answers are inserted as plain text, so they can never inject HTML, and they update while the respondent types, without a request to the server.

## Logic

Logic decides what happens after a question is answered: which question comes next, whether the conversation ends early and with which end screen, and how variables such as a score change.

```php
use JaOcero\ConversationalForms\Logic\Logic;

MultipleChoice::make('plan')
    ->options([...])
    ->logic([
        Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
        Logic::when('plan', 'is', 'none')->end(),                 // finish now
        Logic::when('plan', 'is', 'student')->endWith('student'), // finish with a specific end screen
        Logic::always()->jumpTo('recommend'),                    // fallback
    ]);
```

Rules are evaluated **in order**. Every matching rule runs its calculations, and the first matching rule that jumps or ends decides where to go; the rules after it are skipped. Without such a rule, the conversation moves to the next question. Questions (and statements and groups) are referred to by their name.

### Conditions

```php
// All conditions must match (AND):
Logic::when('plan', 'is', 'team')->and('seats', '>=', 5)->jumpTo('team_size');

// At least one must match (OR):
Logic::when('plan', 'is', 'enterprise')->or('seats', '>', 100)->jumpTo('sales');

// Nested groups:
Logic::when(Logic::all([
    Logic::condition('country', 'is', 'ph'),
    Logic::any([
        Logic::condition('age', '<', 18),
        Logic::condition('student', 'is', true),
    ]),
]))->jumpTo('discount');
```

A single chain can't mix `and()` and `or()`; use `Logic::all()` and `Logic::any()` to group conditions instead.

| Answer | Operators |
| --- | --- |
| Any | `is` (`=`), `is_not` (`!=`), `is_empty`, `is_not_empty` |
| Text | `contains`, `not_contains`, `starts_with`, `ends_with` (case-insensitive) |
| Multiple answers | `includes_any`, `includes_all`, `excludes` (value: an array); `is` matches any chosen answer |
| Numbers, scales, ratings | `gt` (`>`), `gte` (`>=`), `lt` (`<`), `lte` (`<=`), `between` (value: `[min, max]`, inclusive) |
| Dates (`YYYY-MM-DD`) | `before`, `after`, `on`, `not_on`, `between` |

Numbers compare by value (`'7.50'` is `7.5`), `false` and `0` are answers rather than "empty", and an empty string counts as unanswered.

### Variables and scores

Variables are calculated by logic, for example a quiz score or a lead score. They start at 0 unless you give them a starting value:

```php
Conversation::make([...])
    ->variables(['score' => 0, 'lives' => 3]);

Logic::when('q1', 'is', 'b')->add('score', 10);
Logic::always()->subtract('lives', 1);   // also multiply(), divide() and set()

Logic::whenVariable('score', '>=', 70)->endWith('passed');
```

For quizzes, mark the correct answer and the points it is worth. For a multiple-answer question, every correct answer and no other must be chosen:

```php
MultipleChoice::make('capital')->options([...])->correct('manila')->points(10);
MultipleChoice::make('primes')->options([...])->multiple()->correct(['2', '3'])->points(10);
```

Show a variable with `{@score}` in an end screen, and read it when the conversation is submitted:

```php
EndScreen::make('passed')->title('You scored {@score} points, {first_name}!');

protected function handleConversationSubmission(array $data, ConversationResult $result): void
{
    $result->variable('score'); // also $result->variables and $result->ending
}
```

Variables, logic rules and correct answers never leave the server: they are not in the page and not in any response, so a respondent can't read the answers to a quiz or change their score. That is also why `{@variable}` is meant for end screens: it is filled in when the conversation is submitted.

### Endings

Give a conversation several end screens and choose one with logic. The first is shown when no rule chooses another:

```php
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\Redirect;

Conversation::make([...])->endScreens([
    EndScreen::make('default')->title('Thanks!'),
    EndScreen::make('not_eligible')->title('Sorry, you need to be 18 or older.'),
    Redirect::make('book_a_call')->url('https://cal.example.com/acme?email={email}'),
]);

Number::make('age')->logic([Logic::when('age', '<', 18)->endWith('not_eligible')]);
```

A `Redirect` ending sends the respondent to a URL after the conversation is submitted. `{field}` and `{@variable}` tokens in the URL are replaced with URL-encoded values.

### Showing and hiding questions

`visibleWhen()` shows a question, statement or group only when its conditions match:

```php
QuestionGroup::make('work', [
    MultipleChoice::make('employment')->options(['employed' => 'Employed', 'student' => 'Student']),
    ShortText::make('company')
        ->required()
        ->visibleWhen(Logic::condition('employment', 'is', 'employed')),
]);

Statement::make('team_tips')->visibleWhen(Logic::any([...]));
```

The question appears or disappears as soon as the earlier answer changes, without a request to the server. A hidden question is never required and its answer is not saved. A whole step (a single question, statement or group) whose conditions don't match is skipped. Visibility can only depend on answers, not on variables.

### Safety

- Jumps can only move forward, so a conversation can never loop. A rule that jumps backwards, or to a question or end screen that doesn't exist, throws an `InvalidLogicException` with a clear message as soon as the conversation is rendered.
- Questions that logic skips are not required, and their answers are not saved.
- The path, the ending and every variable are worked out again on the server when the conversation is submitted. A modified browser can't skip a required question, submit answers to questions that weren't on its path, choose its ending or change a score.
- Answers to questions that are no longer on the path (for example after the respondent goes back and changes an earlier answer) never influence later rules.

### Logic for other fields

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

## The builder

Conversations can also be made in a Filament panel, without code. Add the plugin to a panel and publish the migration:

```php
use JaOcero\ConversationalForms\ConversationalFormsPlugin;

$panel->plugin(
    ConversationalFormsPlugin::make()
        ->navigationGroup('Forms'), // optional
);
```

```bash
php artisan conversational-forms:install   # publishes the config and migration, and offers to migrate
php artisan storage:link                   # images uploaded in the builder are stored on the public disk
```

The panel gets a **Conversations** page. Creating one asks for a title and an address, then opens the builder: an outline of the conversation on the left and a live preview of the selected step beside it.

- **Outline**: the welcome screen, the steps (with a group's questions under it), the endings, **Design** and **Settings**. Add a question type, a question group or a statement after the selected step, duplicate or delete a step, and drag steps into order (or select one and press <kbd>Alt</kbd> + <kbd>↑</kbd> / <kbd>↓</kbd>). Steps that still need a key or a question are marked, and so are steps with conditions or logic.
- **Preview**: the selected step, drawn with the conversation's own styles and theme. Click any text in it (the question, its description, a choice, a button, the welcome and end screens) to edit it in place, and add or remove choices right there. Piped answers show as `{key}` chips, and the preview can be switched to a phone-sized frame.
- **Toolbar** above the preview: the selected step's type, with **Logic** and **Settings** buttons. Settings opens a panel with the rest of the step's options (required, ranges, formats, limits, an image, the key, and quiz points for a correct answer); clicking anywhere else closes it. Design and Settings in the outline open the same panel. A new step's key follows its text until it's saved; it's what logic and `{key}` piping refer to.
- **Logic**, switched to at the top of the outline, lists every step with its rules as sentences: *when* [question] [comparison] [value], *then* jump to a later step, end, or end with a chosen ending, and optionally add to a variable; steps can also be asked only when earlier answers match. The comparisons offered fit the question (a date offers *is before*, a multiple choice offers its own options), and the steps offered are only the ones after the current step.

The draft saves itself a few seconds after your last change (`builder.autosave_seconds` in the config, `null` to turn it off), and **Save** or <kbd>⌘</kbd> / <kbd>Ctrl</kbd> + <kbd>S</kbd> saves at once; the header says whether everything is saved, and leaving with unsaved changes asks first. When the draft can't be saved yet, saving by itself only marks what needs attention, without moving you away from what you're doing. Only adding, copying, deleting and reordering steps, uploads, and the logic pickers (whose choices come from other steps) talk to the server while you edit. Saving checks the draft the same way code is checked: missing values select the step that needs them, and duplicate keys, conditions on questions that don't exist and backward jumps are explained in a notification instead of being saved.

**Test run** saves the draft and opens its public page beside the editor, with logic, so you can answer it. **Publish** copies the draft into a new, numbered version: the public page always shows the latest published version, so you can keep editing without changing the live conversation, and answers can always be read against the exact questions they were given to. A published conversation can be **closed** (its page says so) and reopened.

The **Logic map** shows every step in order, with the jumps and endings chosen by logic as arcs beside them, and warns about steps that an earlier rule always skips and endings that are never used.

### The public page

Builder-made conversations are answered at `/f/{address}`, outside the panel and without a login, with the same full-screen layout as `ConversationPage`. Change the prefix or middleware in the config, or turn the routes off and register `ConversationalFormPage` yourself:

```php
// config/conversational-forms.php
'routes' => [
    'enabled' => true,
    'prefix' => 'f',
    'middleware' => ['web', 'throttle:60,1'],
],
```

### Responses

Answers to builder-made conversations are stored. A response is saved as *not finished* after the first answer and updated after each step, then marked completed on submit, so you can see where people stop. Previews are never stored.

Each conversation's **Responses** tab shows:

- the number of people who started and completed it, the completion rate and the average time to complete;
- a drop-off chart: how many people answered each step;
- the Net Promoter Score of each NPS question;
- a table of responses, with one column per question showing answers the way people read them (a choice's label, a date in the question's format), filters for status, ending, version and date, and a transcript of each response;
- export to CSV or Excel, of all filtered responses or a selection.

Responses that were never finished are deleted after 30 days by Laravel's model pruning. Schedule it, and change the number of days (or `null` to keep them) in the config:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
use JaOcero\ConversationalForms\Models\ConversationalFormResponse;

Schedule::command('model:prune', ['--model' => ConversationalFormResponse::class])->daily();
```

### Sharing and embedding

**Share** (once published) gives the link, and two ways to embed the conversation in another website: an iframe, or a script that inserts an iframe which grows with the conversation:

```html
<div data-conversational-form="feedback"></div>
<script src="https://your-app.com/f/embed.js" async></script>
```

Install `bacon/bacon-qr-code` to also get a QR code of the link.

### Spam protection

Public pages ignore submissions that fill in a hidden field people never see, or that arrive faster than a person could answer (3 seconds), and limit each visitor to 120 requests a minute. They still show the end screen, so bots learn nothing. A captcha can be added; Cloudflare Turnstile is built in:

```dotenv
CONVERSATIONAL_FORMS_CAPTCHA=turnstile
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
```

Other services can be used by implementing `JaOcero\ConversationalForms\Contracts\CaptchaProvider` and setting its class name as the provider.

### Events

When a conversation is completed, `ConversationalFormSubmitted` is dispatched with the form, the version that was answered (`null` for a preview), the answers, the `ConversationResult` and the stored response:

```php
use JaOcero\ConversationalForms\Events\ConversationalFormSubmitted;

Event::listen(function (ConversationalFormSubmitted $event): void {
    if ($event->isPreview()) {
        return;
    }

    Lead::create(['email' => $event->data['email'], 'score' => $event->result->variable('score')]);
});
```

### How conversations are stored

The builder saves conversations as plain JSON (a *definition*), which `ConversationDefinition` turns into the same `Conversation` that code would build, so a stored conversation and a coded one behave identically. You can use it directly, for example to keep a conversation in a file:

```php
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;

// Code → definition (the conversation must belong to a schema, for example in a test).
$definition = ConversationDefinition::fromConversation($conversation);

// Definition → conversation. validate() throws with a readable message if it can't be built.
$stored = ConversationDefinition::make($definition);
$stored->validate();
$conversation = $stored->toConversation();
```

```json
{
    "schema_version": 1,
    "steps": [
        { "type": "short_text", "key": "name", "label": "What's your name?", "required": true },
        {
            "type": "multiple_choice",
            "key": "plan",
            "label": "Which plan, {name}?",
            "settings": { "options": [{ "value": "starter", "label": "Starter" }, { "value": "enterprise", "label": "Enterprise" }] },
            "rules": [{ "if": { "all": [{ "field": "plan", "op": "is", "value": "enterprise" }] }, "then": [{ "end_with": "sales" }] }]
        }
    ],
    "endings": [{ "key": "default", "title": "Thanks!" }, { "key": "sales", "title": "Our team will call you." }]
}
```

### Your own question types

Register a question type so it can be stored in definitions and added in the builder. Its builder fields edit the keys your question reads in `definitionSettings()`:

```php
use Filament\Forms\Components\TextInput;
use JaOcero\ConversationalForms\Definitions\QuestionType;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Logic\Enums\AnswerType;

// In a service provider's boot() method:
app(QuestionTypeRegistry::class)->register(
    QuestionType::make('license_plate', LicensePlate::class)
        ->label('License plate')
        ->icon('heroicon-o-truck')
        ->answerType(AnswerType::Text)
        ->settingsSchema(fn (): array => [
            TextInput::make('country')->label('Country code'),
        ]),
);
```

```php
class LicensePlate extends Question
{
    protected ?string $country = null;

    public function getDefinitionSettings(): array
    {
        return ['country' => $this->country];
    }

    public function definitionSettings(array $settings): static
    {
        $this->country = $settings['country'] ?? null;

        return $this;
    }
}
```

To show it in the editor's live preview, add a Blade view with `previewView()`. It's rendered once and drawn by Alpine.js, so it reads the question from the `question` variable (`question.data.label`, `question.data.settings.country`) and must have a single root element; without one, the preview shows the question's text and suggests a test run:

```php
QuestionType::make('license_plate', LicensePlate::class)
    // ...
    ->previewView('license-plates::preview');
```

```blade
{{-- resources/views/preview.blade.php --}}
<input class="cf-input" x-bind:placeholder="question.data?.settings?.country ?? 'AB 123 CD'" />
```

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

## Displaying answers

Show stored answers the way their questions describe them, in tables and infolists:

```php
use JaOcero\ConversationalForms\Infolists\Components\AnswerEntry;
use JaOcero\ConversationalForms\Tables\Columns\AnswerColumn;
use JaOcero\ConversationalForms\Tables\Columns\NpsColumn;
use JaOcero\ConversationalForms\Tables\Columns\RatingColumn;

$plan = MultipleChoice::make('plan')->options($plans); // the question from your conversation

AnswerColumn::make('plan')->question($plan);   // "Enterprise" instead of "enterprise"
AnswerEntry::make('plan')->question($plan);    // the same, in an infolist
NpsColumn::make('nps');                        // red, amber or green badge by category
RatingColumn::make('rating')->steps(5);        // ★★★★☆
```

`AnswerColumn` and `AnswerEntry` are regular `TextColumn` and `TextEntry` components, so every other option (sorting, searching, badges, …) still works.

## Keyboard shortcuts

| Keys | Action |
| --- | --- |
| Enter | Continue (validates the current question) |
| Ctrl / Cmd + Enter | Continue from anywhere, including a long text answer |
| Shift + Enter | Line break in a long text answer |
| Alt + ↓ / Alt + ↑ | Next / previous question |
| A–Z | Pick a multiple choice or picture choice answer |
| Y / N | Answer a yes / no question |
| 0–9 | Pick a value on an opinion scale, NPS or rating (type `1` `0` for 10 on a 0–10 scale) |
| Space | Tick a checkbox |
| ↑ ↓, Enter, Esc | Move through, choose from, or close a dropdown's list |
| Space, then ↑ ↓, then Space | Pick up, move and drop a ranking item (Esc cancels) |
| digits | Fill in date and time boxes; focus moves on as each box fills up |
| ↑ ↓ ← → | Move between answers |
| Space | Toggle the focused answer |
| Tab / Shift + Tab | Move between controls on the current question |

## Theming

### Themes

A theme sets a conversation's colours, font, corner radius and background image, in light and dark mode alike; the public page switches to light or dark to match its background. Six themes are bundled (`paper`, `midnight`, `forest`, `sunset`, `mono` and `lavender`), and any of them can be adjusted:

```php
use JaOcero\ConversationalForms\Themes\Theme;

Conversation::make([...])->theme(Theme::preset('midnight'));

Conversation::make([...])->theme(
    Theme::make()
        ->accent('#e11d48')      // buttons, chosen answers and focus rings
        ->background('#fff7f1')
        ->text('#3b1f1a')        // questions
        ->answer('#9a3412')      // typed answers (defaults to the text colour)
        ->font('DM Sans')        // see Themes\Fonts::FAMILIES
        ->radius('large')        // none, small, medium, large or full
        ->backgroundImage('https://example.com/sky.jpg')
        ->overlay(60),           // how much the background colour covers the image
);
```

In the builder, **Design** has the same options and the preview follows them as they change. It warns when colours fall short of WCAG AA contrast (4.5:1 for text, 3:1 for buttons), and every bundled theme passes. A theme goes live with the next published version.

Fonts are loaded from [Bunny Fonts](https://fonts.bunny.net), a privacy-friendly host of the Google Fonts families. Set `conversational-forms.fonts.url` to `null` to never load fonts from another site; themes then use the system font.

### CSS variables

Conversations follow your Filament panel's colors and dark mode automatically. To change them, set any of these CSS variables on `.cf-conversation`, for example in your theme or with `extraAttributes()`:

| Variable | Default | Used for |
| --- | --- | --- |
| `--cf-accent` | `--primary-600` (`--primary-400` in dark mode) | Buttons, answers, focus rings |
| `--cf-accent-contrast` | white, or the primary palette's darkest shade when white is too light on it | Text on the accent color |
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
