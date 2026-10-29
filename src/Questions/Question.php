<?php

namespace JaOcero\ConversationalForms\Questions;

use Filament\Forms\Components\Field;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Js;
use JaOcero\ConversationalForms\Components\Conversation;
use JaOcero\ConversationalForms\Components\Step;
use JaOcero\ConversationalForms\Concerns\HasVisibilityConditions;
use JaOcero\ConversationalForms\Contracts\ConversationAware;
use JaOcero\ConversationalForms\Contracts\FormatsAnswer;
use JaOcero\ConversationalForms\Logic\ConditionGroup;
use JaOcero\ConversationalForms\Support\Answer;
use JaOcero\ConversationalForms\Support\KeyboardHint;
use JaOcero\ConversationalForms\Support\Piping;

/**
 * Base class for the plugin's own question fields. Questions render their own
 * markup, but state, validation and saving are handled by Filament as usual.
 */
abstract class Question extends Field implements ConversationAware, FormatsAnswer
{
    use Concerns\HasDescription;
    use Concerns\HasLogic;
    use Concerns\HasMedia;
    use HasVisibilityConditions;

    protected function setUp(): void
    {
        parent::setUp();

        // Answers are sent with the next step's request, and every step is already
        // rendered, so typing or choosing never needs a server render on its own.
        $this->skipRenderAfterStateUpdated();

        // Question labels are full sentences ("What's your name?"), which read
        // badly inside validation messages, so messages refer to "the answer".
        $this->validationAttribute(__('conversational-forms::conversational-forms.questions.validation_attribute'));

        $this->visible(fn (Question $component): bool => $component->passesVisibilityConditions());
        $this->visibleJs(fn (Question $component): ?string => $component->getVisibilityConditionsJs());
    }

    public function passesVisibilityConditions(): bool
    {
        $conditions = $this->getVisibilityConditions();

        if (! $conditions instanceof ConditionGroup) {
            return true;
        }

        $answers = $this->getConversation()?->getAnswers() ?? (array) $this->getContainer()->getRawState();

        return $conditions->matches($answers);
    }

    /**
     * The browser checks the same conditions (`matchesConditions()` on the
     * conversation), so the question appears or disappears as soon as an
     * earlier answer changes.
     */
    public function getVisibilityConditionsJs(): ?string
    {
        $conditions = $this->getVisibilityConditions();

        if (! $conditions instanceof ConditionGroup) {
            return null;
        }

        $statePath = $this->getConversation()?->getContainer()->getStatePath() ?? $this->getContainer()->getStatePath();

        return 'matchesConditions(' . Js::from($conditions->toArray()) . ', ' . Js::from($statePath) . ')';
    }

    /**
     * A question with visibility conditions is always rendered, and shown or
     * hidden in the browser, so it can appear without a server round trip.
     * Whether it is validated and saved still follows the server's decision.
     */
    public function toSchemaHtml(?bool $isVisible = null): string
    {
        return parent::toSchemaHtml(($this->getVisibilityConditions() instanceof ConditionGroup) ? true : $isVisible);
    }

    public function getConversationStep(): ?Step
    {
        $step = $this->getContainer()->getParentComponent();

        return ($step instanceof Step) ? $step : null;
    }

    public function getConversation(): ?Conversation
    {
        $step = $this->getConversationStep();

        if (! $step instanceof Step) {
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

        if ((! $step instanceof Step) || ($step->getQuestion() !== $this)) {
            return null;
        }

        return $this->getConversation()?->getStepNumber($step->getName());
    }

    public function shouldAutoAdvance(): bool
    {
        return false;
    }

    public function formatAnswer(mixed $answer): string
    {
        return Answer::format($answer);
    }

    /**
     * Labels for this question's possible answers, keyed by value. Used by
     * piping in the browser.
     *
     * @return array<string, string>|null
     */
    public function getAnswerLabels(): ?array
    {
        return null;
    }

    /**
     * The label with `{field}` tokens replaced by answers.
     */
    public function getPipedLabel(): string | Htmlable | null
    {
        return Piping::render($this->getLabel(), $this->getConversation());
    }

    public function getPipedDescription(): string | Htmlable | null
    {
        return Piping::render($this->getDescription(), $this->getConversation());
    }

    /**
     * @return array<KeyboardHint>
     */
    public function getKeyboardHints(): array
    {
        return [];
    }
}
