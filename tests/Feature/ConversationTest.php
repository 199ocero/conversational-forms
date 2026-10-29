<?php

use Filament\Forms\Components\Select;
use Illuminate\View\ViewException;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\ConversationStep;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\OpinionScale;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;
use Livewire\Features\SupportTesting\Testable;

function onboarding(): Conversation
{
    return Conversation::make([
        ShortText::make('name')->label("What's your name?")->required(),
        MultipleChoice::make('plan')
            ->label('Which plan?')
            ->options(['starter' => 'Starter', 'enterprise' => 'Enterprise'])
            ->required()
            ->logic([
                Logic::when('plan', 'is', 'enterprise')->jumpTo('company_size'),
                Logic::always()->jumpTo('recommend'),
            ]),
        ShortText::make('company_size')->label('How big is your company?')->required(),
        OpinionScale::make('recommend')->label('How likely are you to recommend us?'),
    ]);
}

function getConversation(Testable $livewire): Conversation
{
    return $livewire->instance()->getConversation();
}

describe('structure', function () {
    it('wraps every child in a step named after its field', function () {
        $conversation = getConversation(conversation(onboarding()));

        expect($conversation->getSteps())->each->toBeInstanceOf(ConversationStep::class)
            ->and($conversation->getStepNames())->toBe(['name', 'plan', 'company_size', 'recommend']);
    });

    it('renders every step, including steps that are not on the path', function () {
        conversation(onboarding())
            ->assertSeeHtml('data-cf-step="name"')
            ->assertSeeHtml('data-cf-step="company_size"')
            ->assertSeeHtml('data-cf-step="recommend"')
            ->assertSeeHtml('wire:partial="schema-component::form.conversation.cf-step-name"');
    });

    it('numbers questions by their position', function () {
        conversation(onboarding())
            ->assertSeeHtml('<span class="cf-question-number" aria-hidden="true">')
            ->assertSeeInOrder(['data-cf-step="name"', '1', 'data-cf-step="plan"', '2']);
    });

    it('renders the welcome and end screens', function () {
        conversation(onboarding()
            ->welcomeScreen(WelcomeScreen::make()->title('Get started in a minute')->buttonLabel('Begin'))
            ->endScreen(EndScreen::make()->title('All done!')))
            ->assertSee(['Get started in a minute', 'Begin', 'All done!'])
            ->assertSeeHtml('data-cf-screen="welcome"');
    });

    it('has a default end screen', function () {
        conversation(onboarding())->assertSee('Thanks for completing this form!');
    });

    it('supports any Filament field as a step', function () {
        conversation(Conversation::make([
            Select::make('country')->options(['ph' => 'Philippines'])->required(),
            ShortText::make('city'),
        ]))
            ->assertSeeHtml('data-cf-step="country"')
            ->advanceConversation('country')
            ->assertHasErrors(['data.country' => 'required'])
            ->set('data.country', 'ph')
            ->advanceConversation('country')
            ->assertConversationAdvancedTo('city');
    });
});

describe('advancing', function () {
    it('validates only the current step', function () {
        conversation(onboarding())
            ->advanceConversation('name')
            ->assertHasErrors(['data.name' => 'required'])
            ->assertHasNoErrors(['data.plan', 'data.company_size'])
            ->assertReturned(null);
    });

    it('returns the next step and the path', function () {
        conversation(onboarding())
            ->set('data.name', 'Jay')
            ->advanceConversation('name')
            ->assertHasNoErrors()
            ->assertReturned([
                'next' => 'plan',
                'ending' => null,
                'path' => ['name', 'plan', 'recommend'],
                'progress' => 0.3333,
            ]);
    });

    it('follows logic jumps', function (string $plan, array $path, string $next) {
        conversation(onboarding())
            ->set('data.name', 'Jay')
            ->set('data.plan', $plan)
            ->advanceConversation('plan')
            ->assertConversationAdvancedTo($next)
            ->assertConversationPath($path);
    })->with([
        'enterprise' => ['enterprise', ['name', 'plan', 'company_size', 'recommend'], 'company_size'],
        'starter' => ['starter', ['name', 'plan', 'recommend'], 'recommend'],
    ]);

    it('takes logic for any field from the conversation', function () {
        conversation(Conversation::make([
            Select::make('country')->options(['ph' => 'Philippines', 'us' => 'United States']),
            ShortText::make('state'),
            ShortText::make('email'),
        ])->logic([
            'country' => [Logic::when('country', 'is', 'ph')->jumpTo('email')],
        ]))
            ->set('data.country', 'ph')
            ->advanceConversation('country')
            ->assertConversationAdvancedTo('email');
    });

    it('reports the ending after the last step', function () {
        conversation(onboarding())
            ->set('data.name', 'Jay')
            ->set('data.plan', 'starter')
            ->set('data.recommend', 9)
            ->advanceConversation('recommend')
            ->assertReturned(fn (array $response): bool => ($response['next'] === null)
                && ($response['ending'] === Conversation::DEFAULT_ENDING)
                && ((float) $response['progress'] === 1.0));
    });

    it('rejects a step that is not on the path', function () {
        conversation(onboarding())
            ->set('data.name', 'Jay')
            ->set('data.plan', 'starter')
            ->advanceConversation('company_size')
            ->assertReturned(null)
            ->assertHasNoErrors();
    });

    it('skips rendering after a valid answer, because every step is already rendered', function () {
        $livewire = conversation(onboarding())
            ->set('data.name', 'Jay')
            ->advanceConversation('name');

        expect($livewire->effects)->not->toHaveKey('html')
            ->and($livewire->effects['partials'] ?? [])->toBe([]);
    });

    it('re-renders only the current step to show its errors', function () {
        $livewire = conversation(onboarding())->advanceConversation('name');

        expect($livewire->effects)->not->toHaveKey('html')
            ->and(array_keys($livewire->effects['partials']))->toBe(['schema-component::form.conversation.cf-step-name'])
            ->and($livewire->effects['partials']['schema-component::form.conversation.cf-step-name'])->toContain('field is required.')
            ->toContain('aria-invalid="true"');
    });

    it('re-renders the step again to clear errors once it is valid', function () {
        $livewire = conversation(onboarding())
            ->advanceConversation('name')
            ->set('data.name', 'Jay')
            ->advanceConversation('name');

        expect(array_keys($livewire->effects['partials']))->toBe(['schema-component::form.conversation.cf-step-name']);
    });
});

describe('submitting', function () {
    it('hands the validated answers and the result to the component', function () {
        conversation(onboarding())
            ->set('data.name', 'Jay')
            ->set('data.plan', 'starter')
            ->set('data.recommend', 9)
            ->submitConversation()
            ->assertConversationCompleted(Conversation::DEFAULT_ENDING)
            ->assertDispatched('cf-conversation-completed', key: 'form.conversation', ending: 'default');

        [$submission] = TestConversation::$submissions;

        expect($submission['data'])->toBe(['name' => 'Jay', 'plan' => 'starter', 'recommend' => 9])
            ->and($submission['result']->path)->toBe(['name', 'plan', 'recommend'])
            ->and($submission['result']->ending)->toBe('default');
    });

    it('does not require or save answers to skipped questions', function () {
        conversation(onboarding())
            ->set('data.name', 'Jay')
            ->set('data.plan', 'starter')
            ->set('data.company_size', 'forged answer')
            ->submitConversation()
            ->assertConversationCompleted();

        expect(TestConversation::$submissions[0]['data'])->not->toHaveKey('company_size');
    });

    it('requires questions on the path even if the browser skipped them', function () {
        conversation(onboarding())
            ->set('data.name', 'Jay')
            ->set('data.plan', 'enterprise')
            ->submitConversation()
            ->assertHasErrors(['data.company_size' => 'required'])
            ->assertDispatched('cf-conversation-invalid', key: 'form.conversation', step: 'company_size')
            ->assertNotDispatched('cf-conversation-completed');

        expect(TestConversation::$submissions)->toBe([]);
    });
});

describe('logic validation', function () {
    it('fails loudly when logic jumps backwards', function () {
        conversation(Conversation::make([
            ShortText::make('name'),
            ShortText::make('email')->logic([Logic::always()->jumpTo('name')]),
        ]));
    })->throws(ViewException::class, 'A logic rule on [email] jumps to [name], which does not come after it.');

    it('fails loudly when logic targets a missing step', function () {
        conversation(Conversation::make([
            ShortText::make('name')->logic([Logic::always()->jumpTo('nickname')]),
            Email::make('email'),
        ]));
    })->throws(ViewException::class, 'no step with that name exists');
});

it('requires a name for a step without a field', function () {
    conversation(Conversation::make([ConversationStep::make([])]));
})->throws(ViewException::class, 'A conversation step without a field must be given a name.');
