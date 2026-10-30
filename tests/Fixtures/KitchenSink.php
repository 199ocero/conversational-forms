<?php

namespace JaOcero\ConversationalForms\Tests\Fixtures;

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\QuestionGroup;
use JaOcero\ConversationalForms\Components\Statement;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions;
use JaOcero\ConversationalForms\Screens\EndScreen;
use JaOcero\ConversationalForms\Screens\Redirect;
use JaOcero\ConversationalForms\Screens\WelcomeScreen;

/**
 * Every question type, screen and kind of logic, configured in code.
 */
final class KitchenSink
{
    public static function conversation(): Conversation
    {
        return Conversation::make([
            Questions\HiddenField::make('utm_source')->fromQuery()->maxLength(100),
            Questions\ShortText::make('name')->label("What's your name?")->description('First name is fine.')->required()->placeholder('Jay')->maxLength(80)
                ->media('https://example.com/hello.png', 'A wave'),
            Questions\LongText::make('about')->label('Tell us about you')->rows(5)->submitOnEnter(false)->minLength(10),
            Questions\Email::make('email')->label('Email, {name}?')->required(),
            Questions\Website::make('website'),
            Questions\Phone::make('phone')->normalize(false),
            Questions\Number::make('seats')->minValue(1)->maxValue(500)->integer()->logic([
                Logic::when('seats', '>=', 100)->add('lead_score', 30)->jumpTo('plan'),
            ]),
            Questions\MultipleChoice::make('channels')->options(['email' => 'Email', 'chat' => 'Chat', '01' => 'Zero one'])
                ->multiple()->other()->randomize()->minSelections(1)->maxSelections(2),
            Questions\MultipleChoice::make('plan')->options(['starter' => 'Starter', 'enterprise' => 'Enterprise'])->required()
                ->correct('enterprise')->points(5, 'score')
                ->logic([
                    Logic::when('plan', 'is', 'enterprise')->jumpTo('company'),
                    Logic::always()->jumpTo('about_you'),
                ]),
            QuestionGroup::make('company', [
                Questions\Number::make('company_size')->required(),
                Questions\YesNo::make('has_it_team')->yesLabel('Yep')->noLabel('Nope'),
                Questions\ShortText::make('it_contact')->visibleWhen(Logic::condition('has_it_team', 'is', true)),
            ])->label('About your company')->description('A few details.'),
            Statement::make('about_you')->title('Now about you')->description('Three more questions.')->buttonLabel('Go on'),
            Questions\PictureChoice::make('view')->options(['table' => 'Table', 'board' => 'Board'])->images(['table' => 'https://example.com/t.png', 'board' => 'images/board.png'])->hiddenLabels(),
            Questions\Dropdown::make('country')->options(['ph' => 'Philippines', 'jp' => 'Japan']),
            Questions\LegalConsent::make('consent')->termsMarkdown('Read [the terms](https://example.com).')->accepted(),
            Questions\Checkbox::make('beta')->checkboxLabel('Join the beta')->accepted(),
            Questions\Rating::make('rating')->steps(10)->shape('heart'),
            Questions\OpinionScale::make('ease')->range(1, 7)->startLabel('Hard')->endLabel('Easy'),
            Questions\Nps::make('recommend')->startLabel('Never')->endLabel('Always')->logic([
                Logic::when('recommend', '<=', 6)->endWith('sorry'),
            ]),
            Questions\DateQuestion::make('start')->format('MDY')->minDate('2026-01-01')->maxDate('2027-12-31')->calendar(false)->firstDayOfWeek(0),
            Questions\TimeQuestion::make('call_time')->twelveHour()->dropdowns(false),
            Questions\ContactInfo::make('teammate')->parts(['first_name', 'email'])->requiredParts(['email']),
            Questions\Address::make('office')->parts(['line1', 'city', 'country'])->visibleWhen(Logic::condition('plan', 'is', 'enterprise')),
            Questions\Matrix::make('importance')->rows(['reports' => 'Reports', 'api' => 'API'])->answers(['low' => 'Low', 'high' => 'High'])->multiple(),
            Questions\Ranking::make('priorities')->options(['price' => 'Price', 'speed' => 'Speed']),
            Questions\FileUpload::make('logo')->multiple()->maxFiles(3)->maxSize(2048)->acceptedFileTypes(['image/*']),
            Questions\Signature::make('signature')->typing(false)->maxSize(512),
        ])
            ->variables(['lead_score' => 0, 'score' => 0])
            ->logic([
                'consent' => [Logic::whenVariable('lead_score', '>=', 30)->endWith('priority')],
            ])
            ->progressBar(false)
            ->autoAdvance(true, 250)
            ->welcomeScreen(WelcomeScreen::make()->title('Welcome')->description('Takes a minute.')->buttonLabel("Let's go"))
            ->endScreens([
                EndScreen::make('default')->title('Thanks, {name}!')->button('Back home', 'https://example.com'),
                EndScreen::make('priority')->title('We will call you'),
                Redirect::make('sorry')->url('https://example.com/feedback?email={email}'),
            ]);
    }
}
