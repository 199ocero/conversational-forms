<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use JaOcero\ConversationalForms\Questions\Concerns\HasChoices;

class YesNo extends Question
{
    use HasChoices;

    protected string $view = 'conversational-forms::questions.yes-no';

    protected string | Closure | null $yesLabel = null;

    protected string | Closure | null $noLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('boolean');
    }

    public function yesLabel(string | Closure | null $label): static
    {
        $this->yesLabel = $label;

        return $this;
    }

    public function noLabel(string | Closure | null $label): static
    {
        $this->noLabel = $label;

        return $this;
    }

    public function shouldAutoAdvance(): bool
    {
        return true;
    }

    /**
     * @return list<array{value: bool, label: string, key: string}>
     */
    public function getChoices(): array
    {
        return [
            ['value' => true, 'label' => $this->evaluate($this->yesLabel) ?? __('conversational-forms::conversational-forms.questions.yes'), 'key' => 'y'],
            ['value' => false, 'label' => $this->evaluate($this->noLabel) ?? __('conversational-forms::conversational-forms.questions.no'), 'key' => 'n'],
        ];
    }
}
