<?php

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\LongText;
use JaOcero\ConversationalForms\Questions\ShortText;

describe('ShortText', function () {
    it('renders an accessible text input bound to its state', function () {
        conversation(Conversation::make([
            ShortText::make('name')->label("What's your name?")->description('First name is fine.')->required()->maxLength(80),
        ]))
            ->assertSeeHtml('class="cf-question-label"')
            ->assertSeeHtml('for="form.conversation.cf-step-name.name"')
            ->assertSeeHtml('id="form.conversation.cf-step-name.name"')
            ->assertSeeHtml('wire:model="data.name"')
            ->assertSeeHtml('maxlength="80"')
            ->assertSeeHtml('aria-required="true"')
            ->assertSeeHtml('aria-describedby="form.conversation.cf-step-name.name-description"')
            ->assertSee('First name is fine.')
            ->assertSee('Type your answer here…');
    });

    it('validates length', function () {
        conversation(Conversation::make([ShortText::make('name')->maxLength(3)]))
            ->set('data.name', 'Jay-Are')
            ->advanceConversation('name')
            ->assertHasErrors(['data.name' => 'max']);
    });
});

describe('Email', function () {
    it('renders an email input', function () {
        conversation(Conversation::make([Email::make('email')]))
            ->assertSeeHtml('type="email"')
            ->assertSeeHtml('autocomplete="email"')
            ->assertSeeHtml('inputmode="email"');
    });

    it('validates the address', function (string $email, bool $isValid) {
        $livewire = conversation(Conversation::make([Email::make('email'), ShortText::make('name')]))
            ->set('data.email', $email)
            ->advanceConversation('email');

        $isValid ? $livewire->assertHasNoErrors() : $livewire->assertHasErrors(['data.email' => 'email']);
    })->with([
        ['jay@example.com', true],
        ['not-an-email', false],
    ]);
});

describe('LongText', function () {
    it('submits on Enter and adds line breaks with Shift + Enter by default', function () {
        conversation(Conversation::make([LongText::make('notes')]))
            ->assertSeeHtml('<textarea')
            ->assertSeeHtml('x-on:keydown.enter=')
            ->assertSeeHtml('<kbd>Shift ⇧</kbd>');
    });

    it('can keep Enter for line breaks', function () {
        conversation(Conversation::make([LongText::make('notes')->submitOnEnter(false)->rows(6)]))
            ->assertDontSeeHtml('x-on:keydown.enter=')
            ->assertSeeHtml('rows="6"')
            ->assertSeeHtml('<kbd>Ctrl</kbd>');
    });
});
