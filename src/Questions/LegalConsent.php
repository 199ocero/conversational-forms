<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ask the respondent to accept terms. When required, only accepting lets
 * them continue. Saved as `true` (accepted) or `false`.
 */
class LegalConsent extends YesNo
{
    protected string $view = 'conversational-forms::questions.yes-no';

    protected string | Htmlable | Closure | null $terms = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('accepted', fn (LegalConsent $component): bool => $component->isRequired());
    }

    /**
     * The terms, shown in a scrollable box. Pass an `HtmlString` to include links.
     */
    public function terms(string | Htmlable | Closure | null $terms): static
    {
        $this->terms = $terms;

        return $this;
    }

    public function getTerms(): string | Htmlable | null
    {
        return $this->evaluate($this->terms);
    }

    /**
     * @return list<array{value: bool, label: string, key: string}>
     */
    public function getChoices(): array
    {
        return [
            ['value' => true, 'label' => $this->evaluate($this->yesLabel) ?? __('conversational-forms::conversational-forms.questions.accept'), 'key' => 'y'],
            ['value' => false, 'label' => $this->evaluate($this->noLabel) ?? __('conversational-forms::conversational-forms.questions.decline'), 'key' => 'n'],
        ];
    }
}
