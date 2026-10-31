<?php

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use JaOcero\ConversationalForms\Builder\DefinitionState;
use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
use JaOcero\ConversationalForms\Definitions\QuestionType;
use JaOcero\ConversationalForms\Definitions\QuestionTypeRegistry;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Events\ConversationalFormSubmitted;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\CreateConversationalForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\EditConversationalForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ListConversationalForms;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ViewLogicMap;
use JaOcero\ConversationalForms\Livewire\ConversationalFormPage;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Tests\Fixtures\Models\User;
use JaOcero\ConversationalForms\Themes\Theme;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(User::query()->create(['name' => 'Jay', 'email' => 'jay@example.com', 'password' => 'secret']));

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * @param  array<string, mixed>  $definition
 * @return array<string, mixed>
 */
function builderState(array $definition): array
{
    return DefinitionState::fromDefinition($definition);
}

describe('the builder resource', function () {
    it('lists conversations with their status and versions', function () {
        $published = ConversationalForm::factory()->published()->create(['title' => 'Onboarding']);
        $draft = ConversationalForm::factory()->create(['title' => 'Exit survey']);

        Livewire::test(ListConversationalForms::class)
            ->assertCanSeeTableRecords([$published, $draft])
            ->assertSee('/f/' . $published->slug)
            ->assertSee('Published')
            ->assertSee('Draft');
    });

    it('creates a conversation and opens the builder', function () {
        Livewire::test(CreateConversationalForm::class)
            ->fillForm(['title' => 'Job application'])
            ->assertSchemaStateSet(['slug' => 'job-application'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ConversationalFormResource::getUrl('edit', ['record' => ConversationalForm::query()->sole()]));

        expect(ConversationalForm::query()->sole())
            ->status->toBe(FormStatus::Draft)
            ->definition->toEqual(['schema_version' => 1, 'steps' => []]);
    });

    it('requires unique addresses', function () {
        ConversationalForm::factory()->create(['slug' => 'taken']);

        Livewire::test(CreateConversationalForm::class)
            ->fillForm(['title' => 'Another', 'slug' => 'taken'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    });

    it('loads the draft into the builder and saves it back', function () {
        $form = ConversationalForm::factory()->create();

        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->assertSet('data.steps', fn (array $steps): bool => collect($steps)->pluck('data.label')->contains("What's your name?"))
            ->assertSchemaStateSet(['title' => $form->title]);

        $state = builderState([
            'steps' => [
                ['type' => 'short_text', 'key' => 'name', 'label' => 'Name?', 'required' => true],
                ['type' => 'yes_no', 'key' => 'subscribe', 'label' => 'Subscribe, {name}?', 'rules' => [
                    ['if' => ['all' => [['field' => 'subscribe', 'op' => 'is', 'value' => false]]], 'then' => [['end_with' => 'bye']]],
                ]],
            ],
            'endings' => [['key' => 'default', 'title' => 'Thanks!'], ['key' => 'bye', 'title' => 'Bye']],
        ]);

        $page->set('data.steps', $state['steps'])
            ->set('data.endings', $state['endings'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        expect(canonical($form->refresh()->definition['steps'][1]['rules']))->toBe(canonical([
            ['if' => ['all' => [['field' => 'subscribe', 'op' => 'is', 'value' => false]]], 'then' => [['end_with' => 'bye']]],
        ]));
    });

    it('refuses to save a draft that can\'t be built, and says why', function () {
        $form = ConversationalForm::factory()->create();
        $definition = $form->definition;

        $state = builderState([
            'steps' => [
                ['type' => 'short_text', 'key' => 'a', 'label' => 'A'],
                ['type' => 'short_text', 'key' => 'a', 'label' => 'Again'],
            ],
        ]);

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->set('data.steps', $state['steps'])
            ->call('save')
            ->assertNotified("This conversation can't be saved yet");

        expect(canonical($form->refresh()->definition))->toBe(canonical($definition));
    });

    it('publishes the draft as a new version', function () {
        $form = ConversationalForm::factory()->create();

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('Draft: not published yet.')
            ->callAction('publish')
            ->assertNotified('Version 1 is live');

        $form->refresh();

        expect($form->isPublished())->toBeTrue()
            ->and($form->publishedVersion->number)->toBe(1)
            ->and($form->hasUnpublishedChanges())->toBeFalse();

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('Version 1 is live.')
            ->assertActionHidden('publish');
    });

    it('closes and reopens a published conversation', function () {
        $form = ConversationalForm::factory()->published()->create();

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->callAction('close');

        expect($form->refresh()->status)->toBe(FormStatus::Closed);

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->callAction('reopen');

        expect($form->refresh()->status)->toBe(FormStatus::Published);
    });

    it('shows the logic map of the draft', function () {
        $form = ConversationalForm::factory()->withDefinition([
            'steps' => [
                ['type' => 'multiple_choice', 'key' => 'plan', 'label' => 'Plan?', 'rules' => [
                    ['if' => 'always', 'then' => [['jump_to' => 'size']]],
                ]],
                ['type' => 'short_text', 'key' => 'skipped', 'label' => 'Never asked'],
                ['type' => 'number', 'key' => 'size', 'label' => 'Size?'],
            ],
        ])->create();

        Livewire::test(ViewLogicMap::class, ['record' => $form->getRouteKey()])
            ->assertSee('Logic map: ' . $form->title)
            ->assertSee('Never asked')
            ->assertSee('The step "skipped" can never be reached');
    });
});

/**
 * The keys of the builder items at `$path` (`data.steps`, `data.endings`, …), in order.
 *
 * @return list<string>
 */
function itemKeys(Testable $page, string $path = 'data.steps'): array
{
    return array_keys(data_get($page->instance(), $path) ?? []);
}

/**
 * The options of the condition pickers rendered on the page, by group,
 * keyed by the picker's state path.
 *
 * @return array<string, array<string, list<string>>>
 */
function conditionFieldOptions(Testable $page): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>' . $page->html());

    $options = [];

    foreach ((new DOMXPath($document))->query('//select[substring(@id, string-length(@id) - 5) = ".field"]') as $select) {
        foreach ($select->getElementsByTagName('optgroup') as $group) {
            foreach ($group->getElementsByTagName('option') as $option) {
                $options[$select->getAttribute('id')][$group->getAttribute('label')][] = $option->getAttribute('value');
            }
        }
    }

    return $options;
}

function editorForm(): ConversationalForm
{
    return ConversationalForm::factory()->withDefinition([
        'steps' => [
            ['type' => 'short_text', 'key' => 'name', 'label' => 'Name?'],
            ['type' => 'email', 'key' => 'email', 'label' => 'Email?'],
            ['type' => 'group', 'key' => 'contact', 'label' => 'Contact', 'questions' => [
                ['type' => 'phone', 'key' => 'phone', 'label' => 'Phone?'],
            ]],
        ],
        'endings' => [['key' => 'default', 'title' => 'Thanks!']],
    ])->create();
}

describe('the editor', function () {
    it('fills the window without the panel\'s sidebar and topbar', function () {
        $form = editorForm();

        get(ConversationalFormResource::getUrl('edit', ['record' => $form]))
            ->assertOk()
            ->assertSee('cf-editor-body')
            ->assertDontSee('fi-main-sidebar')
            ->assertSee('data-cf-editor-items="steps"', escape: false)
            ->assertSee('data-cf-editor-items="endings"', escape: false)
            ->assertSee(ViewLogicMap::getUrl(['record' => $form]))
            ->assertSee('Test run');
    });

    it('shows one item\'s settings at a time, chosen in the browser', function () {
        $form = editorForm();
        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()]);
        [$name, , $group] = itemKeys($page);
        $question = itemKeys($page, "data.steps.{$group}.data.questions")[0];

        $page->assertSeeHtml('x-show="$store.cfEditor?.showsWithin(\'steps.' . $name . '\')"')
            ->assertSeeHtml('data-cf-editor-item="steps.' . $group . '.questions.' . $question . '"')
            // A group's own settings only show while the group itself is selected.
            ->assertSeeHtml('x-show="$store.cfEditor?.shows(\'steps.' . $group . '\')"')
            ->assertSeeHtml('x-show="$store.cfEditor?.shows(\'design\')"')
            ->assertSeeHtml('x-show="$store.cfEditor?.shows(\'settings\')"')
            // Settings open on demand in the content view; logic has a view of its own.
            ->assertSeeHtml("x-show=\"(\$store.cfEditor?.view ?? 'content') === 'content'\"")
            ->assertSeeHtml("x-show=\"(\$store.cfEditor?.view ?? 'content') === 'logic'\"");
    });

    it('lets keys follow new labels and shows dependent fields in the browser', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);

        $page->assertSeeHtml('window.conversationalFormsEditor?.followKey($el, $state, $old, $get, $set)')
            ->assertSeeHtml("\$get('type') === 'redirect'")
            ->assertSeeHtml("Object.keys(\$get('conditions') ?? {}).length > 1")
            // Texts are edited in the preview; their fields stay, hidden, for validation.
            ->assertSeeHtml('cf-editor-edited-in-preview')
            ->assertSeeHtml("\$get('auto_advance')");
    });

    it('adds a question after the selected step, with its defaults', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        [$name, $email] = itemKeys($page);

        $page->call('mountAction', 'addBetween', ['block' => 'multiple_choice', 'afterItem' => $name], ['schemaComponent' => 'form.steps']);

        $keys = itemKeys($page);
        $added = $keys[1];

        expect($keys)->toHaveCount(4)
            ->and($keys[0])->toBe($name)
            ->and($keys[2])->toBe($email)
            ->and(data_get($page->instance(), "data.steps.{$added}.type"))->toBe('multiple_choice')
            // Choices start with two empty options, as in the builder before.
            ->and(data_get($page->instance(), "data.steps.{$added}.data.settings.options"))->toHaveCount(2);
    });

    it('adds the first question of an empty conversation', function () {
        $form = ConversationalForm::factory()->create(['definition' => ['schema_version' => 1, 'steps' => []]]);
        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()]);

        $page->call('mountAction', 'add', ['block' => 'statement'], ['schemaComponent' => 'form.steps']);

        expect(collect(data_get($page->instance(), 'data.steps'))->pluck('type')->all())->toBe(['statement']);
    });

    it('accepts conditions on questions in groups, deeper than Livewire allows by default', function () {
        config(['livewire.payload.max_nesting_depth' => 10]);

        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        $group = itemKeys($page)[2];
        $question = itemKeys($page, "data.steps.{$group}.data.questions")[0];

        // data.steps.{uuid}.data.questions.{uuid}.data.visibility.conditions.{uuid}.field: 11 levels.
        $page->set("data.steps.{$group}.data.questions.{$question}.data.visibility.conditions.new.field", 'name')
            ->assertSet("data.steps.{$group}.data.questions.{$question}.data.visibility.conditions.new.field", 'name');

        expect(config('livewire.payload.max_nesting_depth'))->toBe(16);
    });

    it('keeps Livewire\'s nesting limit when it is off or already higher', function (?int $limit) {
        config(['livewire.payload.max_nesting_depth' => $limit]);

        Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);

        expect(config('livewire.payload.max_nesting_depth'))->toBe($limit);
    })->with([[null], [32]]);

    it('lets conditions check only questions answered by then', function () {
        $form = ConversationalForm::factory()->withDefinition([
            'steps' => [
                ['type' => 'short_text', 'key' => 'name', 'label' => 'Name?'],
                ['type' => 'multiple_choice', 'key' => 'plan', 'label' => 'Plan?', 'settings' => ['options' => [['value' => 'pro', 'label' => 'Pro']]],
                    'rules' => [['if' => ['all' => [['field' => 'plan', 'op' => 'is', 'value' => 'pro']]], 'then' => [['jump_to' => 'size']]]],
                    'visible_when' => ['all' => [['field' => 'name', 'op' => 'is_not_empty']]]],
                ['type' => 'group', 'key' => 'contact', 'label' => 'Contact', 'questions' => [
                    ['type' => 'email', 'key' => 'email', 'label' => 'Email?'],
                    ['type' => 'phone', 'key' => 'phone', 'label' => 'Phone?', 'visible_when' => ['all' => [['field' => 'email', 'op' => 'is_not_empty']]]],
                ]],
                ['type' => 'number', 'key' => 'size', 'label' => 'Size?'],
            ],
            'hidden_fields' => [['key' => 'source']],
        ])->create();

        $options = collect(conditionFieldOptions(Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])));
        $at = fn (string $part): array => $options->first(fn (array $values, string $id): bool => str_contains($id, $part)) ?? [];

        // After "Plan?" is answered: it first, then earlier answers; never later questions.
        expect($at('.logic.'))->toBe(['This question' => ['plan'], 'Earlier answers' => ['name'], 'Hidden fields' => ['source']])
            // Before "Plan?" is asked: only earlier answers.
            ->and($at('.visibility.'))->toBe(['Earlier answers' => ['name'], 'Hidden fields' => ['source']])
            // A question in a group: earlier steps and the group's earlier questions.
            ->and($options->first(fn (array $values, string $id): bool => str_contains($id, '.questions.') && str_contains($id, '.visibility.')))
            ->toBe(['Earlier answers' => ['name', 'plan', 'email'], 'Hidden fields' => ['source']]);
    });

    it('calls a group\'s own questions "this screen" in its rules', function () {
        $form = ConversationalForm::factory()->withDefinition([
            'steps' => [
                ['type' => 'short_text', 'key' => 'name', 'label' => 'Name?'],
                ['type' => 'group', 'key' => 'contact', 'label' => 'Contact', 'questions' => [
                    ['type' => 'email', 'key' => 'email', 'label' => 'Email?'],
                    ['type' => 'phone', 'key' => 'phone', 'label' => 'Phone?'],
                ], 'rules' => [['if' => ['all' => [['field' => 'phone', 'op' => 'is_empty']]], 'then' => [['end' => true]]]]],
            ],
        ])->create();
        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()]);
        $group = itemKeys($page)[1];

        $options = collect(conditionFieldOptions($page))->first(fn (array $groups, string $id): bool => str_contains($id, "steps.{$group}.data.logic."));

        expect($options)->toBe(['This screen' => ['email', 'phone'], 'Earlier answers' => ['name']]);
    });

    it('starts a new rule with the question it belongs to', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        $email = itemKeys($page)[1];

        $page->call('mountAction', 'add', [], ['schemaComponent' => "form.steps.{$email}.data.logic"]);

        $rule = Arr::first(data_get($page->instance(), "data.steps.{$email}.data.logic"));

        expect(Arr::first($rule['conditions'])['field'])->toBe('email');
    });

    it('adds questions to a group', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        $group = itemKeys($page)[2];

        $page->call('mountAction', 'add', ['block' => 'number'], ['schemaComponent' => "form.steps.{$group}.data.questions"]);

        expect(collect(data_get($page->instance(), "data.steps.{$group}.data.questions"))->pluck('type')->all())->toBe(['phone', 'number']);
    });

    it('saves a conversation with a choice added but not named yet, without that choice', function () {
        $form = ConversationalForm::factory()->withDefinition([
            'steps' => [['type' => 'multiple_choice', 'key' => 'plan', 'label' => 'Plan?', 'settings' => ['options' => [
                ['value' => 'starter', 'label' => 'Starter'],
            ]]]],
        ])->create();
        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()]);
        $plan = itemKeys($page)[0];

        // As the preview's "Add choice" does: a new, empty option in the state.
        $page->set("data.steps.{$plan}.data.settings.options.new-choice", ['label' => '', 'value' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(canonical($form->refresh()->definition['steps'][0]['settings']['options']))->toBe(canonical([['value' => 'starter', 'label' => 'Starter']]));
    });

    it('copies a step right after itself, with keys that are free', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        [$name, , $group] = itemKeys($page);

        $page->call('mountAction', 'clone', ['item' => $name], ['schemaComponent' => 'form.steps'])
            ->call('mountAction', 'clone', ['item' => $name], ['schemaComponent' => 'form.steps'])
            ->call('mountAction', 'clone', ['item' => $group], ['schemaComponent' => 'form.steps']);

        $steps = array_values(data_get($page->instance(), 'data.steps'));

        expect(array_column(array_column($steps, 'data'), 'key'))->toBe(['name', 'name_3', 'name_2', 'email', 'contact', 'contact_2'])
            ->and(collect($steps[5]['data']['questions'])->pluck('data.key')->all())->toBe(['phone_2'])
            ->and($steps[1]['data']['label'])->toBe('Name?');
    });

    it('deletes and reorders steps', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        [$name, $email, $group] = itemKeys($page);

        $page->call('mountAction', 'reorder', ['items' => [$group, $name, $email]], ['schemaComponent' => 'form.steps']);

        expect(itemKeys($page))->toBe([$group, $name, $email]);

        $page->call('mountAction', 'delete', ['item' => $name], ['schemaComponent' => 'form.steps'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(array_column(ConversationalForm::query()->sole()->definition['steps'], 'key'))->toBe(['contact', 'email']);
    });

    it('lists new endings and steps in the logic right away', function () {
        $form = ConversationalForm::factory()->withDefinition([
            'steps' => [
                ['type' => 'short_text', 'key' => 'name', 'label' => 'Name?', 'rules' => [['if' => 'always', 'then' => [['end_with' => 'default']]]]],
                ['type' => 'short_text', 'key' => 'city', 'label' => 'City?', 'rules' => [['if' => 'always', 'then' => [['jump_to' => 'email']]]]],
                ['type' => 'email', 'key' => 'email', 'label' => 'Email?'],
            ],
            'endings' => [['key' => 'default', 'title' => 'Thanks!']],
        ])->create();
        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()]);
        [$name, $city] = itemKeys($page);

        $page->call('mountAction', 'add', [], ['schemaComponent' => 'form.endings']);

        // A new ending gets a free key, and the whole form is drawn again, so the rules list it at once.
        expect(collect(data_get($page->instance(), 'data.endings'))->pluck('key')->all())->toBe(['default', 'ending_2']);

        expect($page->html())->toMatch('/<option\s+value="ending_2"/');

        // A new step, once it has a key, is offered to the earlier step's jump.
        $page->call('mountAction', 'addBetween', ['block' => 'short_text', 'afterItem' => $city], ['schemaComponent' => 'form.steps']);
        $added = itemKeys($page)[2];

        $page->set("data.steps.{$added}.data.key", 'company')
            ->call('$refresh');

        expect($page->html())->toMatch('/<option\s+value="company"/');
    });

    it('adds endings and keeps at least one', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        $default = itemKeys($page, 'data.endings')[0];

        $page->call('mountAction', 'add', [], ['schemaComponent' => 'form.endings']);

        expect(itemKeys($page, 'data.endings'))->toHaveCount(2);

        [, $added] = itemKeys($page, 'data.endings');

        $page->call('mountAction', 'delete', ['item' => $added], ['schemaComponent' => 'form.endings']);

        expect(itemKeys($page, 'data.endings'))->toBe([$default]);

        // The outline offers no delete for the last ending; saving without one fails anyway.
        $page->call('mountAction', 'delete', ['item' => $default], ['schemaComponent' => 'form.endings'])
            ->call('save')
            ->assertHasFormErrors(['endings' => 'min']);
    });

    it('gives the preview the addresses of stored and uploaded images', function () {
        Storage::fake('public');
        Storage::disk('public')->put('conversational-forms/team.jpg', 'image');

        $form = ConversationalForm::factory()->withDefinition([
            'steps' => [
                ['type' => 'short_text', 'key' => 'name', 'label' => 'Name?', 'media' => ['url' => 'conversational-forms/team.jpg', 'alt' => 'The team']],
                ['type' => 'picture_choice', 'key' => 'pet', 'label' => 'Pet?', 'settings' => ['options' => [
                    ['value' => 'cat', 'label' => 'Cat', 'image' => 'conversational-forms/cat.jpg'],
                ]]],
            ],
        ])->create();

        Storage::disk('public')->put('conversational-forms/cat.jpg', 'image');

        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->assertSet('imageUrls', [
                'conversational-forms/team.jpg' => Storage::disk('public')->url('conversational-forms/team.jpg'),
                'conversational-forms/cat.jpg' => Storage::disk('public')->url('conversational-forms/cat.jpg'),
            ]);

        $page->set('data.theme.preset', 'paper')
            ->set('data.theme.background_image', UploadedFile::fake()->image('beach.jpg'));

        // New uploads are keyed by the name Livewire gives them in the browser's state.
        $urls = $page->get('imageUrls');
        $upload = collect($urls)->keys()->first(fn (string $key): bool => str_starts_with($key, 'livewire-file:'));

        expect($urls)->toHaveCount(3)
            ->and($upload)->not->toBeNull()
            ->and($urls[$upload])->toContain('preview-file');
    });

    it('requires a quiz answer only when the question is scored', function () {
        $form = ConversationalForm::factory()->withDefinition([
            'steps' => [['type' => 'yes_no', 'key' => 'agree', 'label' => 'Agree?']],
        ])->create();
        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()]);
        $agree = itemKeys($page)[0];

        $page->call('save')->assertHasNoFormErrors();

        $page->set("data.steps.{$agree}.data.correct.enabled", true)
            ->call('save')
            ->assertHasFormErrors(["steps.{$agree}.data.correct.answer" => 'required']);
    });

    it('tells the editor which items have errors, and when the draft is saved', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        [, $email] = itemKeys($page);

        $page->set("data.steps.{$email}.data.label", '')
            ->call('save')
            ->assertDispatched('cf-editor-invalid', fn (string $event, array $parameters): bool => in_array("data.steps.{$email}.data.label", $parameters['paths'], strict: true))
            ->assertNotDispatched('cf-editor-saved');

        $page->set("data.steps.{$email}.data.label", 'Your email?')
            ->call('save')
            ->assertDispatched('cf-editor-saved');
    });

    it('saves by itself, quietly', function () {
        $form = editorForm();

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->set('data.title', 'Saved by itself')
            ->call('autosave')
            ->assertDispatched('cf-editor-saved')
            ->assertNotNotified();

        expect($form->refresh()->title)->toBe('Saved by itself');
    });

    it('reports problems quietly when saving by itself', function () {
        $form = editorForm();
        $definition = $form->definition;
        $page = Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()]);
        [$name, $email] = itemKeys($page);

        // A draft that can't be built: not saved, and said in the editor rather than in a notification.
        $page->set("data.steps.{$email}.data.key", 'name')
            ->call('autosave')
            ->assertDispatched('cf-editor-autosave-failed')
            ->assertNotDispatched('cf-editor-saved')
            ->assertNotNotified();

        // A missing value: the editor marks it without moving people away from what they're doing.
        $page->set("data.steps.{$email}.data.key", 'email')
            ->set("data.steps.{$name}.data.label", '')
            ->call('autosave')
            ->assertDispatched('cf-editor-invalid', fn (string $event, array $parameters): bool => $parameters['isAutosave'] === true);

        expect(canonical($form->refresh()->definition))->toBe(canonical($definition));
    });

    it('tells the browser how long to wait before saving by itself', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);

        expect($page->instance()->getEditorConfiguration()['autosaveSeconds'])->toBe(3);

        config(['conversational-forms.builder.autosave_seconds' => null]);

        expect($page->instance()->getEditorConfiguration()['autosaveSeconds'])->toBeNull();
    });

    it('saves with the header action', function () {
        $form = editorForm();

        Livewire::test(EditConversationalForm::class, ['record' => $form->getRouteKey()])
            ->set('data.title', 'Renamed')
            ->callAction('save')
            ->assertNotified();

        expect($form->refresh()->title)->toBe('Renamed');
    });

    it('describes every question type to the browser', function () {
        $page = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()]);
        $configuration = $page->instance()->getEditorConfiguration();

        expect($configuration['types'])->toHaveKeys(['short_text', 'matrix', 'video_answer', 'group', 'statement'])
            ->and(collect($configuration['types'])->every(fn (array $type): bool => $type['hasPreview'] && filled($type['icon'])))->toBeTrue()
            ->and(collect($configuration['composites']['address'])->pluck('key')->all())->toBe(['line1', 'line2', 'city', 'state', 'postal_code', 'country'])
            ->and(collect($configuration['composites']['address'])->where('isRequiredByDefault', true)->pluck('key')->all())->toBe(['line1', 'city', 'postal_code', 'country'])
            ->and($configuration['theme']['radii'])->toBe(Theme::RADII);
    });

    it('previews other packages\' question types once they give a preview view', function () {
        app(QuestionTypeRegistry::class)->register(
            QuestionType::make('payment', ShortText::class)->label('Payment'),
        );

        $configuration = Livewire::test(EditConversationalForm::class, ['record' => editorForm()->getRouteKey()])
            ->instance()
            ->getEditorConfiguration();

        expect($configuration['types']['payment'])->toMatchArray(['label' => 'Payment', 'hasPreview' => false]);
    });
});

describe('the public page', function () {
    it('shows the published version, not the draft', function () {
        $form = ConversationalForm::factory()->published()->create();
        $form->update(['definition' => [...$form->definition, 'steps' => [['type' => 'short_text', 'key' => 'other', 'label' => 'Unpublished question']]]]);

        get("/f/{$form->slug}")
            ->assertOk()
            ->assertSee("What's your name?")
            ->assertDontSee('Unpublished question');
    });

    it('is not found until it is published', function () {
        $form = ConversationalForm::factory()->create();

        get("/f/{$form->slug}")->assertNotFound();
        get('/f/missing')->assertNotFound();
    });

    it('previews the draft with a signed link only', function () {
        $form = ConversationalForm::factory()->create();

        get(ConversationalFormsServiceProvider::getFormUrl($form, preview: true))
            ->assertOk()
            ->assertSee("What's your name?");

        get("/f/{$form->slug}?preview=1")->assertNotFound();
    });

    it('says when a conversation is closed', function () {
        $form = ConversationalForm::factory()->published()->create();
        $form->close();

        get("/f/{$form->slug}")
            ->assertOk()
            ->assertSee('This conversation is closed')
            ->assertDontSee("What's your name?");
    });

    it('announces submissions with the version that was answered', function () {
        Event::fake([ConversationalFormSubmitted::class]);

        $form = ConversationalForm::factory()->published()->create();

        $page = Livewire::test(ConversationalFormPage::class, ['slug' => $form->slug]);

        // Submitting faster than a person could answer is treated as spam.
        $this->travel(10)->seconds();

        $page->set('data.name', 'Jay')
            ->set('data.email', 'jay@example.com')
            ->submitConversation()
            ->assertConversationCompleted('default')
            ->assertSeeText('Thanks, Jay!');

        Event::assertDispatched(ConversationalFormSubmitted::class, fn (ConversationalFormSubmitted $event): bool => $event->form->is($form)
            && ($event->version?->is($form->publishedVersion) ?? false)
            && ($event->data === ['name' => 'Jay', 'email' => 'jay@example.com'])
            && (! $event->isPreview())
            && ($event->response?->isCompleted() ?? false));
    });

    it('validates answers on the server like any conversation', function () {
        $form = ConversationalForm::factory()->published()->create();

        Livewire::test(ConversationalFormPage::class, ['slug' => $form->slug])
            ->set('data.email', 'not-an-email')
            ->advanceConversation('name')
            ->assertHasErrors(['data.name' => 'required']);
    });
});
