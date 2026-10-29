<?php

use JaOcero\ConversationalForms\Tests\Fixtures\Livewire\TestConversationPage;
use Livewire\Livewire;

it('serves a conversation at a public URL without a panel or login', function () {
    $this->get('/waitlist')
        ->assertOk()
        ->assertSee('<title>Join the waitlist</title>', escape: false)
        ->assertSee('css/filament/filament/app.css', escape: false)
        ->assertSee('js/jaocero/conversational-forms/components/conversation.js', escape: false)
        ->assertSee('data-cf-full-screen="true"', escape: false)
        ->assertSee("What's your name?");
});

it('completes the conversation and shows the end screen', function () {
    Livewire::test(TestConversationPage::class)
        ->set('data.name', 'Jay')
        ->advanceConversation('name')
        ->assertConversationAdvancedTo('email')
        ->set('data.email', 'jay@example.com')
        ->advanceConversation('email')
        ->assertConversationAdvancedTo(null)
        ->submitConversation()
        ->assertConversationCompleted()
        ->assertSee('You are on the list!');
});
