<?php

namespace JaOcero\ConversationalForms\Questions;

use Filament\Forms\Components\Field;
use JaOcero\ConversationalForms\Contracts\ConversationQuestion;

/**
 * Base class for the plugin's own question fields. Questions render their own
 * markup, but state, validation and saving are handled by Filament as usual.
 */
abstract class Question extends Field implements ConversationQuestion
{
    use Concerns\IsConversationQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpConversationQuestion();
    }
}
