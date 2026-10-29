<?php

namespace JaOcero\ConversationalForms\Concerns;

use Closure;
use JaOcero\ConversationalForms\Contracts\FormatsAnswer;
use JaOcero\ConversationalForms\Support\Answer;

/**
 * Shared by the answer columns and entries: format a stored answer the way the
 * question it came from would describe it.
 */
trait FormatsAnswers
{
    protected FormatsAnswer | Closure | null $question = null;

    /**
     * The question the answer belongs to, for example the same `MultipleChoice`
     * used in the conversation, so stored values are shown as their labels.
     */
    public function question(FormatsAnswer | Closure | null $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getQuestion(): ?FormatsAnswer
    {
        return $this->evaluate($this->question);
    }

    public function formatAnswer(mixed $answer): string
    {
        return $this->getQuestion()?->formatAnswer($answer) ?? Answer::format($answer);
    }
}
