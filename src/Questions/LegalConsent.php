<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Filament\Forms\Components\Concerns\CanBeAccepted;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ask the respondent to accept terms. Saved as `true` (accepted) or `false`
 * (declined). `required()` means an answer must be chosen, so declining can be
 * handled with logic; `accepted()` means only accepting lets them continue.
 */
class LegalConsent extends YesNo
{
    use CanBeAccepted;

    protected string $view = 'conversational-forms::questions.yes-no';

    protected string | Htmlable | Closure | null $terms = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validationMessages([
            'accepted' => __('conversational-forms::conversational-forms.questions.must_accept'),
        ]);
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
