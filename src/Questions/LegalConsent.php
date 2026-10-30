<?php

namespace JaOcero\ConversationalForms\Questions;

use Closure;
use Filament\Forms\Components\Concerns\CanBeAccepted;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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

    /**
     * The Markdown the terms were made from, kept for `toDefinition()`.
     */
    protected ?string $termsMarkdown = null;

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

    /**
     * Terms written in Markdown, for example with links. HTML in the Markdown
     * is shown as text, so terms from the builder can't inject markup.
     */
    public function termsMarkdown(string $markdown): static
    {
        $this->termsMarkdown = $markdown;
        $this->terms = new HtmlString(Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]));

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

    /**
     * @return array<string, mixed>
     */
    public function getDefinitionSettings(): array
    {
        return [
            ...parent::getDefinitionSettings(),
            'terms' => $this->termsMarkdown ?? static::definitionText($this->evaluate($this->terms)),
            'must_accept' => $this->hasDefinitionRule('accepted') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function definitionSettings(array $settings): static
    {
        parent::definitionSettings($settings);

        if (filled($settings['terms'] ?? null)) {
            $this->termsMarkdown((string) $settings['terms']);
        }

        if ($settings['must_accept'] ?? false) {
            $this->accepted();
        }

        return $this;
    }
}
