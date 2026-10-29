<?php

namespace JaOcero\ConversationalForms\Questions;

use Filament\Forms\Components\Field;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\ConversationStep;
use JaOcero\ConversationalForms\Contracts\ConversationAware;
use JaOcero\ConversationalForms\Support\KeyboardHint;

/**
 * Base class for the plugin's own question fields. Questions render their own
 * markup, but state, validation and saving are handled by Filament as usual.
 */
abstract class Question extends Field implements ConversationAware
{
    use Concerns\HasDescription;
    use Concerns\HasLogic;

    protected function setUp(): void
    {
        parent::setUp();

        // Answers are sent with the next step's request, and every step is already
        // rendered, so typing or choosing never needs a server render on its own.
        $this->skipRenderAfterStateUpdated();

        // Question labels are full sentences ("What's your name?"), which read
        // badly inside validation messages, so messages refer to "the answer".
        $this->validationAttribute(__('conversational-forms::conversational-forms.questions.validation_attribute'));
    }

    public function getConversationStep(): ?ConversationStep
    {
        $step = $this->getContainer()->getParentComponent();

        return ($step instanceof ConversationStep) ? $step : null;
    }

    public function getConversation(): ?Conversation
    {
        $step = $this->getConversationStep();

        if (! $step instanceof ConversationStep) {
            return null;
        }

        $conversation = $step->getContainer()->getParentComponent();

        return ($conversation instanceof Conversation) ? $conversation : null;
    }

    /**
     * The question's position in the conversation, shown before its label.
     */
    public function getQuestionNumber(): ?int
    {
        $step = $this->getConversationStep();

        if ((! $step instanceof ConversationStep) || ($step->getQuestion() !== $this)) {
            return null;
        }

        return $this->getConversation()?->getStepNumber($step->getName());
    }

    public function shouldAutoAdvance(): bool
    {
        return false;
    }

    /**
     * @return array<KeyboardHint>
     */
    public function getKeyboardHints(): array
    {
        return [];
    }
}
