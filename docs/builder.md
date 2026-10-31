# The builder

[← Back to the README](../README.md)

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
