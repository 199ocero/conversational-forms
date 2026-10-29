<?php

use Illuminate\View\ViewException;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\QuestionGroup;
use JaOcero\ConversationalForms\Components\Statement;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\Number;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Questions\YesNo;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\Redirect;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;

function quiz(): Conversation
{
    return Conversation::make([
        ShortText::make('name')->required(),
        MultipleChoice::make('capital')
            ->options(['manila' => 'Manila', 'cebu' => 'Cebu'])
            ->correct('manila')
            ->points(50),
        MultipleChoice::make('primes')
            ->options(['2' => '2', '3' => '3', '4' => '4', '9' => '9'])
            ->multiple()
            ->correct(['2', '3'])
            ->points(50),
        YesNo::make('ready')->logic([
            Logic::whenVariable('score', '>=', 100)->endWith('passed'),
            Logic::always()->endWith('failed'),
        ]),
    ])
        ->variables(['score' => 0])
        ->endScreens([
            EndScreen::make('passed')->title('You passed with {@score} points, {name}!'),
            EndScreen::make('failed')->title('You scored {@score}. Try again!'),
        ]);
}

describe('endings', function () {
    it('renders every end screen and chooses one with logic', function () {
        conversation(quiz())
            ->assertSeeHtml('data-cf-ending="passed"')
            ->assertSeeHtml('data-cf-ending="failed"')
            ->set('data.name', 'Jay')
            ->set('data.capital', 'manila')
            ->set('data.primes', ['2', '3'])
            ->set('data.ready', true)
            ->advanceConversation('ready')
            ->assertReturned(fn (array $response): bool => ($response['next'] === null) && ($response['ending'] === 'passed'))
            ->submitConversation()
            ->assertConversationCompleted('passed')
            ->assertDispatched('cf-conversation-completed', ending: 'passed');
    });

    it('uses the first end screen when no rule chooses one', function () {
        conversation(Conversation::make([ShortText::make('name')])->endScreens([
            EndScreen::make('thanks'),
            EndScreen::make('other'),
        ]))
            ->submitConversation()
            ->assertConversationCompleted('thanks');
    });

    it('ends early with a named ending', function () {
        conversation(Conversation::make([
            Number::make('age')->logic([Logic::when('age', '<', 18)->endWith('too_young')]),
            Email::make('email')->required(),
        ])->endScreens([EndScreen::make('default'), EndScreen::make('too_young')]))
            ->set('data.age', '15')
            ->advanceConversation('age')
            ->assertReturned(fn (array $response): bool => ($response['next'] === null) && ($response['ending'] === 'too_young'))
            ->submitConversation()
            ->assertConversationCompleted('too_young');

        expect(TestConversation::$submissions[0]['data'])->not->toHaveKey('email');
    });

    it('fails loudly when logic chooses an end screen that does not exist', function () {
        conversation(Conversation::make([ShortText::make('name')->logic([Logic::always()->endWith('vip')])]));
    })->throws(ViewException::class, 'ends with [vip], but no end screen with that name exists');

    it('redirects with answers and variables in the URL', function () {
        conversation(Conversation::make([
            Email::make('email'),
            YesNo::make('call')->logic([Logic::when('call', 'is', true)->add('priority', 2)->endWith('book')]),
        ])->endScreens([
            EndScreen::make('default'),
            Redirect::make('book')->url('https://cal.example.com/acme?email={email}&priority={@priority}'),
        ]))
            ->set('data.email', 'jay+test@example.com')
            ->set('data.call', true)
            ->submitConversation()
            ->assertRedirect('https://cal.example.com/acme?email=jay%2Btest%40example.com&priority=2');
    });
});

describe('variables and scoring', function () {
    it('calculates a score from correct answers on the server', function (string $capital, array $primes, int $score, string $ending) {
        $livewire = conversation(quiz())
            ->set('data.name', 'Jay')
            ->set('data.capital', $capital)
            ->set('data.primes', $primes)
            ->set('data.ready', true)
            ->submitConversation()
            ->assertConversationCompleted($ending);

        $text = preg_replace('/\s+/', ' ', strip_tags($livewire->html()));

        expect($text)->toContain($ending === 'passed' ? "You passed with {$score} points, Jay!" : "You scored {$score}. Try again!")
            ->and(TestConversation::$submissions[0]['result']->variable('score'))->toBe($score);
    })->with([
        'all correct' => ['manila', ['2', '3'], 100, 'passed'],
        'one wrong' => ['cebu', ['2', '3'], 50, 'failed'],
        'missing a correct answer' => ['manila', ['2'], 50, 'failed'],
        'an extra wrong answer' => ['manila', ['2', '3', '9'], 50, 'failed'],
        'all wrong' => ['cebu', ['4'], 0, 'failed'],
    ]);

    it('cannot be forged from the browser', function () {
        conversation(quiz())
            ->set('data.name', 'Jay')
            ->set('data.capital', 'cebu')
            ->set('data.score', 100)
            ->set('data.ready', true)
            ->submitConversation()
            ->assertConversationCompleted('failed');

        expect(TestConversation::$submissions[0]['result']->variables)->toBe(['score' => 0]);
    });

    it('never sends variables or correct answers to the browser', function () {
        conversation(quiz())
            ->assertDontSeeHtml('correct')
            ->assertDontSeeHtml('includes_all')
            ->set('data.name', 'Jay')
            ->set('data.capital', 'manila')
            ->advanceConversation('capital')
            ->assertReturned(fn (array $response): bool => array_keys($response) === ['next', 'ending', 'path', 'progress']);
    });

    it('knows whether an answer is correct', function () {
        $question = MultipleChoice::make('primes')->options(['2' => '2', '3' => '3', '4' => '4'])->multiple()->correct(['2', '3']);

        expect($question->isCorrect(['3', '2']))->toBeTrue()
            ->and($question->isCorrect(['2']))->toBeFalse()
            ->and($question->isCorrect(['2', '3', '4']))->toBeFalse();
    });
});

describe('visibility', function () {
    function employment(): Conversation
    {
        return Conversation::make([
            QuestionGroup::make('work', [
                MultipleChoice::make('employment')->options(['employed' => 'Employed', 'student' => 'Student']),
                ShortText::make('company')
                    ->required()
                    ->visibleWhen(Logic::condition('employment', 'is', 'employed')),
            ]),
            Email::make('email'),
        ]);
    }

    it('renders conditional questions and shows them in the browser', function () {
        conversation(employment())
            ->assertSeeHtml('data-cf-question="data.company"')
            ->assertSeeHtml('matchesConditions(JSON.parse(');
    });

    it('only requires conditional questions when they are shown', function () {
        conversation(employment())
            ->set('data.employment', 'student')
            ->advanceConversation('work')
            ->assertHasNoErrors()
            ->set('data.employment', 'employed')
            ->advanceConversation('work')
            ->assertHasErrors(['data.company' => 'required']);
    });

    it('does not save hidden answers', function () {
        conversation(employment())
            ->set('data.employment', 'student')
            ->set('data.company', 'Acme')
            ->submitConversation()
            ->assertConversationCompleted();

        expect(TestConversation::$submissions[0]['data'])->not->toHaveKey('company');
    });

    it('renders a conditional question that starts off the path, so it can appear later', function () {
        conversation(Conversation::make([
            MultipleChoice::make('plan')->options(['team' => 'Team', 'solo' => 'Solo']),
            Number::make('seats')->visibleWhen(Logic::condition('plan', 'is', 'team')),
            Email::make('email'),
        ]))
            ->assertConversationPath(['plan', 'email'])
            ->assertSeeHtml('data-cf-question="data.seats"')
            ->assertSeeHtml('wire:model="data.seats"');
    });

    it('skips whole steps whose conditions do not match', function () {
        conversation(Conversation::make([
            MultipleChoice::make('plan')->options(['team' => 'Team', 'solo' => 'Solo']),
            Number::make('seats')->visibleWhen(Logic::condition('plan', 'is', 'team')),
            Statement::make('team_tips')->title('Tips for teams')->visibleWhen(Logic::any([Logic::condition('plan', 'is', 'team')])),
            Email::make('email'),
        ]))
            ->set('data.plan', 'solo')
            ->assertConversationPath(['plan', 'email'])
            ->set('data.plan', 'team')
            ->assertConversationPath(['plan', 'seats', 'team_tips', 'email']);
    });

    it('cannot depend on variables', function () {
        conversation(Conversation::make([
            ShortText::make('name'),
            ShortText::make('bonus')->visibleWhen(Logic::condition('@score', 'gt', 10)),
        ]));
    })->throws(ViewException::class, 'The visibility conditions of [bonus] use a variable.');
});

describe('operators in conversations', function () {
    it('routes on numbers, text and multiple answers', function (string $field, mixed $answer, string $next) {
        conversation(Conversation::make([
            ShortText::make('q')->logic([
                Logic::when('q', 'contains', 'urgent')->jumpTo('urgent'),
                Logic::when('q', 'between', [100, 500])->jumpTo('mid'),
                Logic::when('q', 'gt', 500)->or('q', 'starts_with', 'vip')->jumpTo('high'),
            ]),
            ShortText::make('low'),
            ShortText::make('mid'),
            ShortText::make('high'),
            ShortText::make('urgent'),
        ]))
            ->set("data.{$field}", $answer)
            ->advanceConversation('q')
            ->assertConversationAdvancedTo($next);
    })->with([
        'contains' => ['q', 'This is URGENT please', 'urgent'],
        'between' => ['q', '250', 'mid'],
        'greater than' => ['q', '9000', 'high'],
        'or' => ['q', 'VIP customer', 'high'],
        'no rule' => ['q', '50', 'low'],
    ]);
});
