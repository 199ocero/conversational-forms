<?php

namespace JaOcero\ConversationalForms\Tests\Fixtures\Livewire;

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\ConversationPage;
use JaOcero\ConversationalForms\Livewire\ConversationResult;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions\LongText;
use JaOcero\ConversationalForms\Questions\MultipleChoice;
use JaOcero\ConversationalForms\Questions\OpinionScale;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Questions\YesNo;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;

/**
 * The onboarding conversation driven by the browser tests: a welcome screen,
 * a required question, a choice with a jump, a scale, yes / no, long text and
 * two endings.
 */
class BrowserConversationPage extends ConversationPage
{
    protected static ?string $title = 'Get started';

    /**
     * The browser tests' server runs in the test's own process, so submissions
     * can be checked from the test.
     *
     * @var list<array{data: array<string, mixed>, result: ConversationResult}>
     */
    public static array $submissions = [];

    public function conversation(Conversation $conversation): Conversation
    {
        return $conversation
            ->components([
                ShortText::make('name')->label("What's your name?")->required(),
                MultipleChoice::make('plan')
                    ->label('Which plan, {name}?')
                    ->options(['starter' => 'Starter', 'team' => 'Team', 'enterprise' => 'Enterprise'])
                    ->required()
                    ->logic([
                        Logic::when('plan', 'is', 'enterprise')->jumpTo('company'),
                        Logic::always()->jumpTo('recommend'),
                    ]),
                ShortText::make('company')->label('Which company?')->required(),
                OpinionScale::make('recommend')
                    ->label('How likely are you to recommend us?')
                    ->range(0, 10)
                    ->startLabel('Not likely')
                    ->endLabel('Very likely'),
                YesNo::make('newsletter')->label('Want product updates?'),
                LongText::make('notes')->label('Anything else?'),
            ])
            ->welcomeScreen(WelcomeScreen::make()->title('Get started in a minute')->buttonLabel('Start'))
            ->endScreen(EndScreen::make()->title('Thanks, {name}!'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleConversationSubmission(array $data, ConversationResult $result): void
    {
        static::$submissions[] = ['data' => $data, 'result' => $result];
    }
}
