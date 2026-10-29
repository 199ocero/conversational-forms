<?php

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\OpinionScale;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Questions\YesNo;
use Livewire\Features\SupportTesting\Testable;

$plans = ['starter' => 'Starter', 'team' => 'Team', 'enterprise' => 'Enterprise'];

function firstQuestion(Testable $livewire): mixed
{
    return $livewire->instance()->getConversation()->getSteps()[0]->getQuestion();
}

describe('MultipleChoice', function () use ($plans) {
    it('renders a radio group with letter shortcuts', function () use ($plans) {
        conversation(Conversation::make([MultipleChoice::make('plan')->options($plans)]))
            ->assertSeeHtml('role="radiogroup"')
            ->assertSeeHtml('aria-labelledby="form.conversation.cf-step-plan.plan-label"')
            ->assertSeeHtml('aria-keyshortcuts="A"')
            ->assertSeeHtml('aria-keyshortcuts="C"')
            ->assertSeeInOrder(['Starter', 'Team', 'Enterprise']);
    });

    it('only accepts its options', function () use ($plans) {
        conversation(Conversation::make([MultipleChoice::make('plan')->options($plans), ShortText::make('next')]))
            ->set('data.plan', 'platinum')
            ->advanceConversation('plan')
            ->assertHasErrors(['data.plan' => 'in'])
            ->set('data.plan', 'team')
            ->advanceConversation('plan')
            ->assertHasNoErrors();
    });

    it('accepts a typed answer when other is allowed', function () use ($plans) {
        conversation(Conversation::make([MultipleChoice::make('plan')->options($plans)->other(), ShortText::make('next')]))
            ->assertSeeHtml('aria-keyshortcuts="D"')
            ->assertSee('Other')
            ->set('data.plan', 'Something custom')
            ->advanceConversation('plan')
            ->assertHasNoErrors();
    });

    it('validates multiple selections', function () use ($plans) {
        conversation(Conversation::make([
            MultipleChoice::make('plans')->options($plans)->multiple()->minSelections(1)->maxSelections(2)->required(),
            ShortText::make('next'),
        ]))
            ->assertSeeHtml('role="group"')
            ->assertSeeHtml('role="checkbox"')
            ->assertSee('Choose between 1 and 2')
            ->set('data.plans', ['starter', 'team', 'enterprise'])
            ->advanceConversation('plans')
            ->assertHasErrors(['data.plans' => 'max'])
            ->set('data.plans', ['starter', 'platinum'])
            ->advanceConversation('plans')
            ->assertHasErrors(['data.plans.1' => 'in'])
            ->set('data.plans', ['starter', 'team'])
            ->advanceConversation('plans')
            ->assertHasNoErrors();
    });

    it('defaults multiple selections to an empty list', function () use ($plans) {
        conversation(Conversation::make([MultipleChoice::make('plans')->options($plans)->multiple()]))
            ->assertSet('data.plans', []);
    });

    it('auto-advances single choices only', function () use ($plans) {
        expect(MultipleChoice::make('plan')->options($plans)->shouldAutoAdvance())->toBeTrue()
            ->and(MultipleChoice::make('plan')->options($plans)->multiple()->shouldAutoAdvance())->toBeFalse();
    });

    it('shuffles choices in a stable order for each respondent', function () {
        $options = array_combine(range('a', 'l'), range('A', 'L'));
        $livewire = conversation(Conversation::make([MultipleChoice::make('letter')->options($options)->randomize()]));
        $question = firstQuestion($livewire);

        $order = array_column($question->getChoices(), 'value');

        expect($order)->not->toBe(array_keys($options))
            ->and($order)->toEqualCanonicalizing(array_keys($options))
            ->and(array_column($question->getChoices(), 'value'))->toBe($order)
            ->and(array_column($question->getChoices(), 'key'))->toBe(range('a', 'l'));
    });
});

describe('YesNo', function () {
    it('renders yes and no with Y and N shortcuts', function () {
        conversation(Conversation::make([YesNo::make('newsletter')->yesLabel('Sure')]))
            ->assertSeeHtml('aria-keyshortcuts="Y"')
            ->assertSeeHtml('aria-keyshortcuts="N"')
            ->assertSeeInOrder(['Sure', 'No']);
    });

    it('stores a boolean', function (mixed $value, bool $isValid) {
        $livewire = conversation(Conversation::make([YesNo::make('newsletter')->required(), ShortText::make('next')]))
            ->set('data.newsletter', $value)
            ->advanceConversation('newsletter');

        $isValid ? $livewire->assertHasNoErrors() : $livewire->assertHasErrors(['data.newsletter']);
    })->with([
        'yes' => [true, true],
        'no' => [false, true],
        'not answered' => [null, false],
        'not a boolean' => ['maybe', false],
    ]);
});

describe('OpinionScale', function () {
    it('renders every value with its labels', function () {
        conversation(Conversation::make([OpinionScale::make('recommend')->range(1, 5)->startLabel('Not likely')->endLabel('Very likely')]))
            ->assertSeeHtml('role="radiogroup"')
            ->assertSeeHtml('--cf-scale-steps: 5')
            ->assertSeeHtml('aria-label="5 / 5"')
            ->assertSee(['1: Not likely', '5: Very likely']);
    });

    it('only accepts whole numbers within its range', function (mixed $value, bool $isValid) {
        $livewire = conversation(Conversation::make([OpinionScale::make('recommend'), ShortText::make('next')]))
            ->set('data.recommend', $value)
            ->advanceConversation('recommend');

        $isValid ? $livewire->assertHasNoErrors() : $livewire->assertHasErrors(['data.recommend']);
    })->with([
        [0, true],
        [10, true],
        [11, false],
        [4.5, false],
    ]);

    it('rejects a range it cannot render', function (int $start, int $end) {
        OpinionScale::make('recommend')->range($start, $end)->getChoices();
    })->with([
        'reversed' => [5, 1],
        'too long' => [0, 11],
        'negative' => [-1, 5],
    ])->throws(InvalidArgumentException::class);
});
