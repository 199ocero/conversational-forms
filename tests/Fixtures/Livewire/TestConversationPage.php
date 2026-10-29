<?php

namespace JaOcero\ConversationalForms\Tests\Fixtures\Livewire;

use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Livewire\ConversationPage;
use JaOcero\ConversationalForms\Questions\Email;
use JaOcero\ConversationalForms\Questions\ShortText;
use JaOcero\ConversationalForms\Screens\EndScreen;

class TestConversationPage extends ConversationPage
{
    protected static ?string $title = 'Join the waitlist';

    public function conversation(Conversation $conversation): Conversation
    {
        return $conversation
            ->components([
                ShortText::make('name')->label("What's your name?")->required(),
                Email::make('email')->label('Where can we reach you?')->required(),
            ])
            ->endScreen(EndScreen::make()->title('You are on the list!'));
    }
}
