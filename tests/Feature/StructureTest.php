<?php

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\QuestionGroup;
use JaOcero\ConversationalForms\Components\Statement;
use JaOcero\ConversationalForms\Logic\Logic;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\HiddenField;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversation;
use Livewire\Livewire;

describe('Statement', function () {
    it('is a step without a question that does not take a number', function () {
        $livewire = conversation(Conversation::make([
            Statement::make('intro')->title('A few questions about you')->buttonLabel("Let's go"),
            ShortText::make('name'),
        ]))
            ->assertSee(['A few questions about you', "Let's go"])
            ->advanceConversation('intro')
            ->assertConversationAdvancedTo('name');

        expect($livewire->instance()->getConversation()->getStepNumber('name'))->toBe(1)
            ->and($livewire->instance()->getConversation()->getStepNumber('intro'))->toBeNull();
    });

    it('can be the target of a jump', function () {
        conversation(Conversation::make([
            ShortText::make('name')->logic([Logic::always()->jumpTo('outro')]),
            ShortText::make('skipped'),
            Statement::make('outro')->title('Almost done'),
        ]))
            ->set('data.name', 'Jay')
            ->advanceConversation('name')
            ->assertConversationAdvancedTo('outro');
    });
});

describe('QuestionGroup', function () {
    it('shows several questions on one screen and validates them together', function () {
        conversation(Conversation::make([
            QuestionGroup::make('full_name', [
                ShortText::make('first_name')->label('First name')->required(),
                ShortText::make('last_name')->label('Last name')->required(),
            ])->label("What's your name?"),
            Email::make('email'),
        ]))
            ->assertSee(["What's your name?", 'First name', 'Last name'])
            ->set('data.first_name', 'Jay')
            ->advanceConversation('full_name')
            ->assertHasErrors(['data.last_name' => 'required'])
            ->assertHasNoErrors(['data.first_name'])
            ->set('data.last_name', 'Ocero')
            ->advanceConversation('full_name')
            ->assertConversationAdvancedTo('email');
    });

    it('applies logic from its questions after the whole group', function () {
        conversation(Conversation::make([
            QuestionGroup::make('company', [
                ShortText::make('company_name'),
                ShortText::make('company_size')->logic([Logic::when('company_size', 'is', '1')->jumpTo('email')]),
            ]),
            ShortText::make('team'),
            Email::make('email'),
        ]))
            ->set('data.company_size', '1')
            ->advanceConversation('company')
            ->assertConversationAdvancedTo('email');
    });
});

describe('HiddenField', function () {
    it('is filled from the URL, never shown as a step, and saved with the answers', function () {
        Livewire::withQueryParams(['utm_source' => 'newsletter', 'ignored' => 'x']);

        $livewire = conversation(Conversation::make([
            ShortText::make('name'),
            HiddenField::make('utm_source')->fromQuery(),
            HiddenField::make('ref')->fromQuery('ignored'),
            HiddenField::make('not_in_query')->fromQuery(),
        ]))
            ->assertSet('data.utm_source', 'newsletter')
            ->assertSet('data.ref', 'x')
            ->assertDontSeeHtml('data-cf-step="utm_source"')
            ->set('data.name', 'Jay')
            ->submitConversation()
            ->assertConversationCompleted();

        expect($livewire->instance()->getConversation()->getStepNames())->toBe(['name'])
            ->and(TestConversation::$submissions[0]['data'])->toMatchArray(['utm_source' => 'newsletter', 'ref' => 'x', 'not_in_query' => null]);
    });

    it('can drive logic', function () {
        Livewire::withQueryParams(['source' => 'partner']);

        conversation(Conversation::make([
            HiddenField::make('source')->fromQuery(),
            ShortText::make('name')->logic([Logic::when('source', 'is', 'partner')->jumpTo('partner_code')]),
            ShortText::make('referral'),
            ShortText::make('partner_code'),
        ]))
            ->set('data.name', 'Jay')
            ->advanceConversation('name')
            ->assertConversationAdvancedTo('partner_code');
    });

    it('limits the length of values from the URL', function () {
        Livewire::withQueryParams(['utm_source' => str_repeat('a', 300)]);

        conversation(Conversation::make([ShortText::make('name'), HiddenField::make('utm_source')->fromQuery()]))
            ->assertSet('data.utm_source', str_repeat('a', 255));
    });
});
