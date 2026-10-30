<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use JaOcero\ConversationalForms\Builder\DefinitionState;
use JaOcero\ConversationalForms\ConversationalFormsServiceProvider;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Events\ConversationalFormSubmitted;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\ConversationalFormResource;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\CreateConversationalForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\EditConversationalForm;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ListConversationalForms;
use JaOcero\ConversationalForms\Filament\Resources\ConversationalForms\Pages\ViewLogicMap;
use JaOcero\ConversationalForms\Livewire\ConversationalFormPage;
use JaOcero\ConversationalForms\Models\ConversationalForm;
use JaOcero\ConversationalForms\Tests\Fixtures\Models\User;
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
            ->definition->toBe(['schema_version' => 1, 'steps' => []]);
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
            ->assertSee("What's your name?")
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

        expect($form->refresh()->definition['steps'][1]['rules'])->toBe([
            ['if' => ['all' => [['field' => 'subscribe', 'op' => 'is', 'value' => false]]], 'then' => [['end_with' => 'bye']]],
        ]);
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

        expect($form->refresh()->definition)->toBe($definition);
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

        Livewire::test(ConversationalFormPage::class, ['slug' => $form->slug])
            ->set('data.name', 'Jay')
            ->set('data.email', 'jay@example.com')
            ->submitConversation()
            ->assertConversationCompleted('default')
            ->assertSeeText('Thanks, Jay!');

        Event::assertDispatched(ConversationalFormSubmitted::class, fn (ConversationalFormSubmitted $event): bool => $event->form->is($form)
            && ($event->version?->is($form->publishedVersion) ?? false)
            && ($event->data === ['name' => 'Jay', 'email' => 'jay@example.com'])
            && (! $event->isPreview()));
    });

    it('validates answers on the server like any conversation', function () {
        $form = ConversationalForm::factory()->published()->create();

        Livewire::test(ConversationalFormPage::class, ['slug' => $form->slug])
            ->set('data.email', 'not-an-email')
            ->advanceConversation('name')
            ->assertHasErrors(['data.name' => 'required']);
    });
});
