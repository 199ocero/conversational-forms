# Questions

[← Back to the README](../README.md)

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
| `endScreen()` / `endScreens()` | The screen shown after submitting, or several chosen by logic. A default "Thanks" screen is shown when none is set. See [Endings](logic.md#endings). |
| `variables()` | Variables and their starting values. See [Variables and scores](logic.md#variables-and-scores). |
| `progressBar()` | Show how far along the respondent is. It follows the actual path, so skipped questions don't count. |
| `navigationButtons()` | Previous and next arrow buttons. Keyboard shortcuts work either way. |
| `autoAdvance()` | Move to the next question as soon as a single-choice question is answered. |
| `fullScreen()` | Used by public pages. Embedded conversations only react to keys while focus is inside them. |
| `submitUsing()` | The Livewire method called once the last question is answered. |
| `logic()` | Logic for fields that aren't conversation questions, keyed by step name. See [Logic](logic.md). |

Every question also supports Filament's usual field methods: `label()`, `required()`, `rules()`, `default()`, `disabled()`, `visible()`, `dehydrated()`, `afterStateUpdated()`, and so on.

## Questions

All questions share these options:

| Method | Description |
| --- | --- |
| `label()` | The question. |
| `description()` | Supporting text under the question. |
| `media()` | An image shown with the question: beside it on wide screens, above the answer on small ones. `->media($url, alt: 'Our new dashboard')` |
| `logic()` | Rules deciding where to go after this question. See [Logic](logic.md). |
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
| [`AudioAnswer`](#audioanswer-and-videoanswer) | stored recording path | |
| [`VideoAnswer`](#audioanswer-and-videoanswer) | stored recording path | |

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
